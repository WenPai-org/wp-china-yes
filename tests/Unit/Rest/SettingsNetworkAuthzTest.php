<?php
/**
 * PUT /settings: network-scope roots and allow_site_override locks.
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
use WenPai\ChinaYes\Rest\DocumentWriter;
use WenPai\ChinaYes\Rest\NetworkSettingsController;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\SettingsController;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/wp-rest-stubs.php';
require_once __DIR__ . '/wp-rest-action-stub.php';

/**
 * Single-site and multisite groups for the subsite write rules.
 */
class SettingsNetworkAuthzTest extends TestCase {

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

	/*
	 * Single site.
	 */

	/**
	 * Single site: network-scope roots are ordinary site keys and save.
	 */
	public function test_single_site_network_roots_are_writable() {
		$response = $this->put( $this->network_changes() );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$stored = get_option( Schema::SETTINGS );
		$this->assertSame( 7, $stored['data_residency']['ruleset_version'] );
		$this->assertSame( array( 'demo' ), $stored['apps']['disabled'] );
		$this->assertFalse( $stored['diagnostics']['scheduled_checks'] );
	}

	/**
	 * Single site: no lock exists, the admin segment saves.
	 */
	public function test_single_site_admin_segment_is_writable() {
		$response = $this->put( array( 'admin' => array( 'hide_promo' => false ) ) );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertFalse( get_option( Schema::SETTINGS )['admin']['hide_promo'] );
	}

	/**
	 * Single site: a full document with one edit saves.
	 */
	public function test_single_site_full_document_put_succeeds() {
		$doc                              = $this->full_document();
		$doc['connectivity']['heartbeat'] = 'on';

		$response = $this->put( $doc );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 'on', get_option( Schema::SETTINGS )['connectivity']['heartbeat'] );
	}

	/*
	 * Multisite.
	 */

	/**
	 * Subsite: changed network roots are 403 with data.fields; nothing saved.
	 */
	public function test_subsite_changed_network_roots_are_forbidden() {
		$this->multisite( true );
		$network_before = OptionStore::$site_options;

		$result = $this->put( $this->network_changes() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_settings_network_scope', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertSame( 403, $data['status'] );
		$this->assertSame( array( 'data_residency', 'apps', 'diagnostics' ), $data['fields'] );
		$this->assertSame( $network_before, OptionStore::$site_options );
		$this->assertArrayNotHasKey( Schema::SITE_OVERRIDES, OptionStore::$options );
	}

	/**
	 * Subsite: ordinary key mixed with a network root saves nothing.
	 */
	public function test_subsite_mixed_network_and_ordinary_saves_nothing() {
		$this->multisite( true );

		$result = $this->put(
			array(
				'connectivity'   => array( 'heartbeat' => 'on' ),
				'data_residency' => array( 'ruleset_version' => 7 ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( array( 'data_residency' ), $result->get_error_data()['fields'] );
		$this->assertArrayNotHasKey( Schema::SITE_OVERRIDES, OptionStore::$options );
		$this->assertSame( 'off', ( new Repository() )->get( 'connectivity.heartbeat' ) );
	}

	/**
	 * Subsite, locked: a changed locked root is 409 with data.fields.
	 */
	public function test_locked_subsite_changed_locked_root_is_conflict() {
		$this->multisite( false );

		$result = $this->put( array( 'connectivity' => array( 'heartbeat' => 'on' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_settings_locked', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertSame( array( 'connectivity' ), $result->get_error_data()['fields'] );
		$this->assertArrayNotHasKey( Schema::SITE_OVERRIDES, OptionStore::$options );
	}

	/**
	 * Subsite, locked: the admin segment is refused and not stored.
	 */
	public function test_locked_subsite_admin_segment_is_refused() {
		$this->multisite( false );

		$result = $this->put( array( 'admin' => array( 'hide_promo' => false ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_settings_locked', $result->get_error_code() );
		$this->assertSame( array( 'admin' ), $result->get_error_data()['fields'] );
		$this->assertArrayNotHasKey( Schema::SITE_OVERRIDES, OptionStore::$options );
	}

	/**
	 * Subsite, locked: ordinary key mixed with a locked root saves nothing.
	 */
	public function test_locked_subsite_mixed_locked_and_ordinary_saves_nothing() {
		$this->multisite( false );

		$result = $this->put(
			array(
				'recovery_mode' => true,
				'admin'         => array( 'hide_promo' => false ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( array( 'admin' ), $result->get_error_data()['fields'] );
		$this->assertArrayNotHasKey( Schema::SITE_OVERRIDES, OptionStore::$options );
		$this->assertFalse( ( new Repository() )->get( 'recovery_mode' ) );
	}

	/**
	 * Subsite, locked: full document with unchanged locked / network roots succeeds.
	 */
	public function test_locked_subsite_full_document_with_unchanged_roots_succeeds() {
		$this->multisite( false );
		$doc                  = $this->full_document();
		$doc['recovery_mode'] = true;

		$response = $this->put( $doc );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$overrides = get_option( Schema::SITE_OVERRIDES );
		$this->assertTrue( $overrides['recovery_mode'] );
		foreach ( array( 'admin', 'connectivity', 'modules', 'profile', 'data_residency', 'diagnostics' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $overrides );
		}
	}

	/**
	 * Subsite, unlocked: full document with unchanged network roots succeeds.
	 */
	public function test_unlocked_subsite_full_document_edits_ordinary_key() {
		$this->multisite( true );
		$doc                              = $this->full_document();
		$doc['connectivity']['heartbeat'] = 'on';

		$response = $this->put( $doc );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 'on', get_option( Schema::SITE_OVERRIDES )['connectivity']['heartbeat'] );
		$this->assertArrayNotHasKey( 'data_residency', get_option( Schema::SITE_OVERRIDES ) );
	}

	/**
	 * Subsite, unlocked: the admin segment still saves as an override.
	 */
	public function test_unlocked_subsite_admin_segment_is_writable() {
		$this->multisite( true );

		$response = $this->put( array( 'admin' => array( 'hide_promo' => false ) ) );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertFalse( get_option( Schema::SITE_OVERRIDES )['admin']['hide_promo'] );
	}

	/**
	 * Subsite, locked: a changed network root and a changed locked root in one
	 * PUT is 403 (network scope wins) and data.fields lists only network roots.
	 */
	public function test_locked_subsite_network_and_locked_change_is_403_network_fields_only() {
		$this->multisite( false );
		$network_before = OptionStore::$site_options;

		$result = $this->put(
			array(
				'data_residency' => array( 'ruleset_version' => 7 ),
				'connectivity'   => array( 'heartbeat' => 'on' ),
				'admin'          => array( 'hide_promo' => false ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_settings_network_scope', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->assertSame( array( 'data_residency' ), $result->get_error_data()['fields'] );
		$this->assertSame( $network_before, OptionStore::$site_options );
		$this->assertArrayNotHasKey( Schema::SITE_OVERRIDES, OptionStore::$options );
	}

	/**
	 * Subsite, locked: a refused profile change records no profile_set event and
	 * saves nothing (no overrides, no profile_confirmed_at, network untouched).
	 */
	public function test_locked_subsite_refused_profile_records_no_event_and_saves_nothing() {
		$this->multisite( false );
		$options_before = OptionStore::$options;
		$network_before = OptionStore::$site_options;

		$result = $this->put( array( 'profile' => 'crossborder' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_settings_locked', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertSame( array( 'profile' ), $result->get_error_data()['fields'] );
		$this->assertSame( array(), RestStore::$actions );
		$this->assertSame( $options_before, OptionStore::$options );
		$this->assertSame( $network_before, OptionStore::$site_options );
		$this->assertSame( 'domestic', ( new Repository() )->get( 'profile' ) );

		// Control: the same PUT on an unlocked subsite does record profile_set.
		$this->multisite( true );
		$this->assertInstanceOf( WP_REST_Response::class, $this->put( array( 'profile' => 'crossborder' ) ) );
		$this->assertCount( 1, RestStore::$actions );
		$this->assertSame( 'wpcy_events_record', RestStore::$actions[0]['tag'] );
		$this->assertSame( 'profile_set', RestStore::$actions[0]['args'][0] );
	}

	/**
	 * Subsite: modules.site_blocklist echoed unchanged is accepted (not 400) and
	 * never stored in the site overrides.
	 */
	public function test_subsite_unchanged_site_blocklist_echo_is_accepted_and_not_stored() {
		$this->multisite( true );
		$network_before = OptionStore::$site_options;
		$doc            = $this->full_document();
		$this->assertArrayHasKey( 'site_blocklist', $doc['modules'] );

		$response = $this->put(
			array(
				'modules'      => array( 'site_blocklist' => $doc['modules']['site_blocklist'] ),
				'connectivity' => array( 'heartbeat' => 'on' ),
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$overrides = get_option( Schema::SITE_OVERRIDES );
		$this->assertIsArray( $overrides );
		$this->assertSame( 'on', $overrides['connectivity']['heartbeat'] );
		$this->assertFalse( isset( $overrides['modules']['site_blocklist'] ) );
		$this->assertSame( $network_before, OptionStore::$site_options );
	}

	/**
	 * Regression: PUT /network-settings (registered route, manage_network_options
	 * + nonce) still changes network-scope roots after the subsite checks.
	 */
	public function test_network_settings_route_still_writes_network_roots() {
		$this->multisite( false );
		RestStore::$caps['manage_network_options'] = true;
		RestStore::$nonces['n']                    = true;

		$route = $this->route( '/network-settings', 'PUT' );
		$this->assertSame( array( NetworkSettingsController::class, 'update_item' ), array( get_class( $route['callback'][0] ), $route['callback'][1] ) );

		$request                        = new WP_REST_Request();
		$request->headers['x-wp-nonce'] = 'n';
		$request->json                  = array(
			'data_residency' => array( 'ruleset_version' => 7 ),
			'apps'           => array( 'disabled' => array( 'demo' ) ),
			'diagnostics'    => array( 'scheduled_checks' => false ),
		);
		$this->assertTrue( call_user_func( $route['permission_callback'], $request ) );

		$response = call_user_func( $route['callback'], $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$network = OptionStore::$site_options[ Schema::NETWORK_SETTINGS ];
		$this->assertSame( 7, $network['data_residency']['ruleset_version'] );
		$this->assertSame( array( 'demo' ), $network['apps']['disabled'] );
		$this->assertFalse( $network['diagnostics']['scheduled_checks'] );
		$this->assertFalse( $network['allow_site_override'] );
		$this->assertArrayNotHasKey( Schema::SITE_OVERRIDES, OptionStore::$options );
	}

	/**
	 * Single site: a client still echoing the removed announcements root is not
	 * rejected; the root is dropped and never stored.
	 */
	public function test_single_site_legacy_announcements_root_is_dropped() {
		$response = $this->put(
			array(
				'announcements' => array( 'dismissed' => array( 'a1' ) ),
				'connectivity'  => array( 'heartbeat' => 'on' ),
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$stored = get_option( Schema::SETTINGS );
		$this->assertSame( 'on', $stored['connectivity']['heartbeat'] );
		$this->assertArrayNotHasKey( 'announcements', $stored );
		$this->assertArrayNotHasKey( 'announcements', $response->get_data() );
	}

	/**
	 * Subsite: announcements is no longer a network-scope root, so sending it is
	 * neither 403 nor stored in site overrides or the network document.
	 */
	public function test_subsite_legacy_announcements_root_is_dropped_not_forbidden() {
		$this->multisite( true );
		$network_before = OptionStore::$site_options;

		$response = $this->put(
			array(
				'announcements' => array( 'dismissed' => array( 'a1' ) ),
				'connectivity'  => array( 'heartbeat' => 'on' ),
			)
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$overrides = get_option( Schema::SITE_OVERRIDES );
		$this->assertIsArray( $overrides );
		$this->assertSame( 'on', $overrides['connectivity']['heartbeat'] );
		$this->assertArrayNotHasKey( 'announcements', $overrides );
		$this->assertSame( $network_before, OptionStore::$site_options );
	}

	/**
	 * PUT /network-settings: the removed root in the body is dropped, other
	 * network roots still save, and a stored legacy root is cleared.
	 */
	public function test_network_settings_legacy_announcements_root_is_dropped() {
		$this->multisite( true );
		OptionStore::$site_options[ Schema::NETWORK_SETTINGS ]['announcements'] = array( 'dismissed' => array( 'old' ) );
		RestStore::$caps['manage_network_options']                              = true;
		RestStore::$nonces['n'] = true;

		$route                          = $this->route( '/network-settings', 'PUT' );
		$request                        = new WP_REST_Request();
		$request->headers['x-wp-nonce'] = 'n';
		$request->json                  = array(
			'announcements'  => array( 'dismissed' => array( 'a1' ) ),
			'data_residency' => array( 'ruleset_version' => 7 ),
		);
		$this->assertTrue( call_user_func( $route['permission_callback'], $request ) );

		$response = call_user_func( $route['callback'], $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$network = OptionStore::$site_options[ Schema::NETWORK_SETTINGS ];
		$this->assertSame( 7, $network['data_residency']['ruleset_version'] );
		$this->assertArrayNotHasKey( 'announcements', $network );
	}

	/**
	 * One registered wpcy/v1 route handler by path and method.
	 *
	 * @param string $path   Route path.
	 * @param string $method HTTP method.
	 * @return array<string, mixed>
	 */
	private function route( string $path, string $method ): array {
		( new RestModule( new Repository() ) )->register_routes();
		foreach ( RestStore::$routes as $row ) {
			if ( $path !== $row['route'] ) {
				continue;
			}
			foreach ( $row['args'] as $handler ) {
				if ( is_array( $handler ) && isset( $handler['methods'] ) && $method === $handler['methods'] ) {
					return $handler;
				}
			}
		}
		$this->fail( 'route not registered: ' . $method . ' ' . $path );
	}

	/**
	 * Changed values for all three network-scope roots.
	 *
	 * @return array<string, mixed>
	 */
	private function network_changes(): array {
		return array(
			'data_residency' => array( 'ruleset_version' => 7 ),
			'apps'           => array( 'disabled' => array( 'demo' ) ),
			'diagnostics'    => array( 'scheduled_checks' => false ),
		);
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
	 * GET /settings body, as a client would echo it back.
	 *
	 * @return array<string, mixed>
	 */
	private function full_document(): array {
		$data = $this->controller()->get_item( new WP_REST_Request() )->get_data();
		$this->assertIsArray( $data );
		return $data;
	}

	/**
	 * PUT /settings with a JSON body.
	 *
	 * @param array<string, mixed> $body Body.
	 * @return WP_REST_Response|WP_Error
	 */
	private function put( array $body ) {
		$request       = new WP_REST_Request();
		$request->json = $body;
		return $this->controller()->update_item( $request );
	}

	/**
	 * Controller under test.
	 *
	 * @return SettingsController
	 */
	private function controller(): SettingsController {
		return new SettingsController( new DocumentWriter( new Repository() ) );
	}
}
