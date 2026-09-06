<?php
/**
 * GET /residency/ruleset and GET /residency/log.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Privacy\DataResidency\DataResidencyModule;
use WenPai\ChinaYes\Rest\Permissions;
use WenPai\ChinaYes\Rest\ResidencyController;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_Error;
use WP_REST_Request;

require_once __DIR__ . '/wp-rest-stubs.php';

/**
 * Empty log, pagination bounds, permission deny, and route index.
 */
class ResidencyControllerTest extends TestCase {

	/**
	 * Reset bags.
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
	 * No B-tier records: items is an empty list. No body field.
	 */
	public function test_empty_log_is_empty_list() {
		$controller = new ResidencyController( new DataResidencyModule() );
		$response   = $controller->get_log( new WP_REST_Request() );
		$data       = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $data['items'] );
		$this->assertArrayNotHasKey( 'body', $data );
	}

	/**
	 * Ruleset payload has version, issued_at, tiers; no signature, no body.
	 */
	public function test_ruleset_has_version_tiers_without_signature() {
		$controller = new ResidencyController( new DataResidencyModule() );
		$response   = $controller->get_ruleset( new WP_REST_Request() );
		$data       = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'ruleset_version', $data );
		$this->assertArrayHasKey( 'issued_at', $data );
		$this->assertArrayHasKey( 'tiers', $data );
		$this->assertIsInt( $data['ruleset_version'] );
		$this->assertIsArray( $data['tiers'] );
		$this->assertArrayNotHasKey( 'signature', $data );
		$this->assertArrayNotHasKey( 'body', $data );
	}

	/**
	 * Page/per_page: default 20, cap 100, page past the end is empty.
	 */
	public function test_log_pagination_bounds() {
		$this->seed_log( 25 );
		$controller = new ResidencyController( new DataResidencyModule() );

		$default = $controller->get_log( new WP_REST_Request() )->get_data();
		$this->assertCount( 20, $default['items'] );
		$this->assertSame( array( 'host', 'data_class', 'count', 'last_seen' ), array_keys( $default['items'][0] ) );
		$this->assertArrayNotHasKey( 'body', $default['items'][0] );

		$page_two         = new WP_REST_Request();
		$page_two->params = array(
			'page'     => 2,
			'per_page' => 20,
		);
		$second           = $controller->get_log( $page_two )->get_data();
		$this->assertCount( 5, $second['items'] );

		$past         = new WP_REST_Request();
		$past->params = array(
			'page'     => 3,
			'per_page' => 20,
		);
		$this->assertSame( array(), $controller->get_log( $past )->get_data()['items'] );

		$capped         = new WP_REST_Request();
		$capped->params = array( 'per_page' => 101 );
		$this->assertCount( 25, $controller->get_log( $capped )->get_data()['items'] );

		$zero         = new WP_REST_Request();
		$zero->params = array( 'per_page' => 0 );
		$this->assertCount( 20, $controller->get_log( $zero )->get_data()['items'] );
	}

	/**
	 * Missing manage_options is wpcy_forbidden on both residency routes.
	 */
	public function test_permission_denied_without_manage_options() {
		$result = Permissions::manage_options_read( new WP_REST_Request() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_forbidden', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );

		$module = new RestModule( new Repository() );
		$module->register_routes();

		$callbacks = array();
		foreach ( RestStore::$routes as $row ) {
			if ( in_array( $row['route'], array( '/residency/ruleset', '/residency/log' ), true ) ) {
				$callbacks[ $row['route'] ] = $row['args']['permission_callback'];
			}
		}
		$this->assertCount( 2, $callbacks );
		foreach ( $callbacks as $route => $callback ) {
			$denied = call_user_func( $callback, new WP_REST_Request() );
			$this->assertInstanceOf( WP_Error::class, $denied, $route );
			$this->assertSame( 'wpcy_forbidden', $denied->get_error_code(), $route );
		}
	}

	/**
	 * REST index includes both residency routes.
	 */
	public function test_rest_index_includes_residency_routes() {
		$module = new RestModule( new Repository() );
		$module->register_routes();

		$routes = array();
		foreach ( RestStore::$routes as $row ) {
			$routes[] = $row['route'];
		}
		$this->assertContains( '/residency/ruleset', $routes );
		$this->assertContains( '/residency/log', $routes );
	}

	/**
	 * Seed B-tier log rows keyed by host.
	 *
	 * @param int $count Row count.
	 */
	private function seed_log( int $count ): void {
		$log = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$host         = sprintf( 'host-%02d.example', $i );
			$log[ $host ] = array(
				'host'       => $host,
				'data_class' => 'comments',
				'count'      => $i,
				'last_seen'  => sprintf( '2026-09-06T00:%02d:00Z', $i ),
				'body'       => 'must-not-leak',
			);
		}
		OptionStore::$options[ DataResidencyModule::LOG_OPTION ] = $log;
	}
}
