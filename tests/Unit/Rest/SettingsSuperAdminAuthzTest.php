<?php
/**
 * PUT /settings as a super admin and on the multisite main site.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/wp-rest-stubs.php';
require_once __DIR__ . '/wp-rest-action-stub.php';
require_once __DIR__ . '/wp-network-admin-stubs.php';

/**
 * Network-scope and lock rules have no capability or main-site bypass.
 *
 * A super admin (grant_super_admin) is modelled by granting both
 * manage_options and manage_network_options to the current user.
 */
class SettingsSuperAdminAuthzTest extends TestCase {

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
		$GLOBALS['wpcy_test_main_site'] = true;
	}

	/**
	 * Clear the main-site flag.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpcy_test_main_site'] );
		parent::tearDown();
	}

	/**
	 * Super admin on a subsite: changed network roots are still 403.
	 */
	public function test_super_admin_on_subsite_network_roots_are_forbidden() {
		$this->multisite( true );
		$this->super_admin();
		$GLOBALS['wpcy_test_main_site'] = false;
		$network_before                 = OptionStore::$site_options;

		$result = $this->put(
			array(
				'data_residency' => array( 'ruleset_version' => 7 ),
				'apps'           => array( 'disabled' => array( 'demo' ) ),
				'diagnostics'    => array( 'scheduled_checks' => false ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_settings_network_scope', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->assertSame( array( 'data_residency', 'apps', 'diagnostics' ), $result->get_error_data()['fields'] );
		$this->assertSame( $network_before, OptionStore::$site_options );
		$this->assertArrayNotHasKey( Schema::SITE_OVERRIDES, OptionStore::$options );
	}

	/**
	 * Super admin on a locked subsite: changed locked roots are still 409.
	 */
	public function test_super_admin_on_locked_subsite_locked_roots_are_conflict() {
		$this->multisite( false );
		$this->super_admin();
		$GLOBALS['wpcy_test_main_site'] = false;
		$network_before                 = OptionStore::$site_options;

		$result = $this->put(
			array(
				'connectivity' => array( 'heartbeat' => 'on' ),
				'admin'        => array( 'hide_promo' => false ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_settings_locked', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertSame( array( 'connectivity', 'admin' ), $result->get_error_data()['fields'] );
		$this->assertSame( $network_before, OptionStore::$site_options );
		$this->assertArrayNotHasKey( Schema::SITE_OVERRIDES, OptionStore::$options );
	}

	/**
	 * Main site, site screen (not network admin), locked: changed locked root is 409.
	 */
	public function test_main_site_locked_root_is_conflict() {
		$this->multisite( false );
		RestStore::$caps['manage_options'] = true;
		RestStore::$network_admin          = false;
		$this->assertTrue( is_main_site() );

		$result = $this->put( array( 'connectivity' => array( 'heartbeat' => 'on' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_settings_locked', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertSame( array( 'connectivity' ), $result->get_error_data()['fields'] );
		$this->assertArrayNotHasKey( Schema::SITE_OVERRIDES, OptionStore::$options );
		$this->assertSame( 'off', ( new Repository() )->get( 'connectivity.heartbeat' ) );
	}

	/**
	 * Main site as a super admin, locked: still 409, nothing saved.
	 */
	public function test_super_admin_on_main_site_locked_root_is_conflict() {
		$this->multisite( false );
		$this->super_admin();
		$network_before = OptionStore::$site_options;

		$result = $this->put( array( 'profile' => 'crossborder' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_settings_locked', $result->get_error_code() );
		$this->assertSame( array( 'profile' ), $result->get_error_data()['fields'] );
		$this->assertSame( $network_before, OptionStore::$site_options );
		$this->assertArrayNotHasKey( Schema::SITE_OVERRIDES, OptionStore::$options );
	}

	/**
	 * Main site as a super admin: network roots are still 403 on PUT /settings.
	 */
	public function test_super_admin_on_main_site_network_roots_are_forbidden() {
		$this->multisite( true );
		$this->super_admin();

		$result = $this->put( array( 'data_residency' => array( 'ruleset_version' => 7 ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_settings_network_scope', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->assertSame( array( 'data_residency' ), $result->get_error_data()['fields'] );
	}

	/**
	 * Grant the super-admin capabilities.
	 *
	 * @return void
	 */
	private function super_admin(): void {
		RestStore::$caps['manage_options']         = true;
		RestStore::$caps['manage_network_options'] = true;
	}

	/**
	 * Switch to multisite with a stored network document.
	 *
	 * @param bool $allow allow_site_override.
	 * @return void
	 */
	private function multisite( bool $allow ): void {
		OptionStore::$multisite         = true;
		$network                        = Defaults::network_settings();
		$network['allow_site_override'] = $allow;
		OptionStore::$site_options[ Schema::NETWORK_SETTINGS ] = $network;
	}

	/**
	 * PUT /settings through the registered route (permission + callback).
	 *
	 * @param array<string, mixed> $body Body.
	 * @return WP_REST_Response|WP_Error
	 */
	private function put( array $body ) {
		RestStore::$nonces['n'] = true;
		( new RestModule( new Repository() ) )->register_routes();
		$handler = null;
		foreach ( RestStore::$routes as $row ) {
			if ( '/settings' !== $row['route'] ) {
				continue;
			}
			foreach ( $row['args'] as $candidate ) {
				if ( is_array( $candidate ) && isset( $candidate['methods'] ) && 'PUT' === $candidate['methods'] ) {
					$handler = $candidate;
				}
			}
		}
		$this->assertIsArray( $handler );

		$request                        = new WP_REST_Request();
		$request->headers['x-wp-nonce'] = 'n';
		$request->json                  = $body;
		$this->assertTrue( call_user_func( $handler['permission_callback'], $request ) );

		return call_user_func( $handler['callback'], $request );
	}
}
