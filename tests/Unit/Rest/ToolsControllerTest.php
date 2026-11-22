<?php
/**
 * Local Quick Tools REST controller and MaintenanceModule unit tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\MaintenanceModule;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Rest\ToolsController;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_REST_Request;

require_once __DIR__ . '/wp-rest-stubs.php';

/**
 * Covers /tools/clear-transients, /tools/maintenance, /tools/snapshot, and MaintenanceModule safety bypasses.
 */
class ToolsControllerTest extends TestCase {

	/**
	 * Reset option and REST stores.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		OptionStore::reset();
		RestError::reset();
	}

	/**
	 * RestModule exposes all three local M0 toolbox routes.
	 */
	public function test_rest_index_registers_tools_routes(): void {
		$module = new RestModule( new Repository() );
		$module->register_routes();

		$routes = array();
		foreach ( RestStore::$routes as $row ) {
			$routes[] = $row['route'];
		}
		$this->assertContains( '/tools/clear-transients', $routes );
		$this->assertContains( '/tools/maintenance', $routes );
		$this->assertContains( '/tools/snapshot', $routes );
		$this->assertContains( '/tools/audit', $routes );
		$this->assertContains( '/tools/audit/fix', $routes );
	}

	/**
	 * Expired transient cleanup runs bounded DELETE and returns deleted count.
	 */
	public function test_clear_transients_deletes_expired_rows_and_excludes_wpcy_prefix(): void {
		$prev_wpdb = $GLOBALS['wpdb'] ?? null;
		$queries   = array();
		$fake_wpdb = new class( $queries ) {
			/**
			 * Options table name.
			 *
			 * @var string
			 */
			public string $options = 'wp_options';

			/**
			 * Captured queries.
			 *
			 * @var array<int, string>
			 */
			public array $queries;

			/**
			 * Constructor.
			 *
			 * @param array<int, string> $queries Query log reference.
			 */
			public function __construct( array &$queries ) {
				$this->queries = &$queries;
			}

			/**
			 * Escape LIKE wildcards.
			 *
			 * @param string $text Raw string.
			 * @return string
			 */
			public function esc_like( string $text ): string {
				return addcslashes( $text, '_%\\' );
			}

			/**
			 * Format SQL query with args.
			 *
			 * @param string $query SQL template.
			 * @param mixed  ...$args Args.
			 * @return string
			 */
			public function prepare( string $query, ...$args ): string {
				return $query . ' | ' . implode( ',', array_map( 'strval', $args ) );
			}

			/**
			 * Execute query and return 6 affected rows (= 3 transient pairs).
			 *
			 * @param string $sql SQL string.
			 * @return int
			 */
			public function query( string $sql ): int {
				$this->queries[] = $sql;
				return 6;
			}
		};

		try {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- isolated wpdb mock for unit test.
			$GLOBALS['wpdb'] = $fake_wpdb;
			$controller      = new ToolsController( new Repository(), new Checker() );
			$response        = $controller->clear_transients( new WP_REST_Request( 'POST' ) );
			$data            = $response->get_data();

			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( 3, $data['deleted'] );
			$this->assertCount( 1, $fake_wpdb->queries );
			$this->assertStringContainsString( '\\_transient\\_timeout\\_wpcy\\_', $fake_wpdb->queries[0] );
		} finally {
			if ( null === $prev_wpdb ) {
				unset( $GLOBALS['wpdb'] );
			} else {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring test state.
				$GLOBALS['wpdb'] = $prev_wpdb;
			}
		}
	}

	/**
	 * Maintenance mode toggles cleanly and never blocks admins or recovery mode.
	 */
	public function test_maintenance_toggle_and_safety_bypasses(): void {
		$repo       = new Repository();
		$controller = new ToolsController( $repo, new Checker() );
		$module     = new MaintenanceModule( $repo );

		$this->assertFalse( MaintenanceModule::is_enabled() );
		$this->assertFalse( $module->should_block_request() );

		$req       = new WP_REST_Request();
		$req->json = array( 'enabled' => true );
		$on_res    = $controller->set_maintenance( $req );
		$this->assertSame( 200, $on_res->get_status() );
		$this->assertTrue( $on_res->get_data()['enabled'] );
		$this->assertTrue( MaintenanceModule::is_enabled() );

		// Unauthenticated visitor is blocked with 503.
		RestStore::$caps['manage_options'] = false;
		$this->assertTrue( $module->should_block_request() );

		// Logged-in administrator is never locked out.
		RestStore::$caps['manage_options'] = true;
		$this->assertFalse( $module->should_block_request() );

		// Recovery mode also bypasses maintenance lock for visitors.
		RestStore::$caps['manage_options'] = false;
		$repo->set( 'recovery_mode', true );
		$this->assertFalse( $module->should_block_request() );

		$off_req       = new WP_REST_Request();
		$off_req->json = array( 'enabled' => false );
		$off_res       = $controller->set_maintenance( $off_req );
		$this->assertFalse( $off_res->get_data()['enabled'] );
		$this->assertFalse( MaintenanceModule::is_enabled() );
	}

	/**
	 * Snapshot endpoint returns privacy-sanitized runtime summary and companion/conflict lists.
	 */
	public function test_get_snapshot_returns_sanitized_summary(): void {
		OptionStore::$options['active_plugins'] = array(
			'wpavatar/wpavatar.php',
			'useso-take-over-google/useso-take-over-google.php',
		);

		$controller = new ToolsController( new Repository(), new Checker() );
		$response   = $controller->get_snapshot( new WP_REST_Request( 'GET' ) );
		$data       = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( PHP_VERSION, $data['php_version'] );
		$this->assertSame( 2, $data['active_plugins'] );
		$this->assertContains( 'wpavatar', $data['companions'] );
		$this->assertContains( 'useso-take-over-google', $data['conflicts'] );
		$this->assertStringContainsString( '=== WenPai Leaf Environment Snapshot ===', $data['text'] );
		$this->assertStringNotContainsString( 'password', strtolower( $data['text'] ) );
	}

	/**
	 * Audit and one-click fix endpoints improve score from degraded domestic settings.
	 */
	public function test_audit_and_one_click_fix_improve_score(): void {
		$repo = new Repository();
		$repo->set( 'connectivity.wordpress_org', 'off' );
		$repo->set( 'connectivity.public_assets.scope', 'off' );
		$repo->set( 'connectivity.avatar', 'off' );
		$repo->set( 'connectivity.heartbeat', 'off' );
		$repo->set( 'connectivity.dashboard_feeds', 'allow' );

		$controller = new ToolsController( $repo, new Checker() );
		$before_res = $controller->get_audit( new WP_REST_Request() );
		$this->assertSame( 200, $before_res->get_status() );
		$before_data = $before_res->get_data();
		$this->assertSame( 25, $before_data['score'] );
		$this->assertSame( 4, $before_data['fixable_count'] );

		$fix_res  = $controller->fix_audit( new WP_REST_Request() );
		$fix_data = $fix_res->get_data();
		$this->assertSame( 200, $fix_res->get_status() );
		$this->assertSame( 25, $fix_data['before_score'] );
		$this->assertGreaterThan( 90, $fix_data['after_score'] );
		$this->assertSame( 5, $fix_data['fixed_count'] );
		$this->assertSame( 0, $fix_data['audit']['fixable_count'] );
	}
}
