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
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\SettingsController;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/wp-rest-stubs.php';

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
		$this->assertSame( array( 'a1' ), $stored['announcements']['dismissed'] );
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
		$this->assertSame( array( 'data_residency', 'announcements', 'apps', 'diagnostics' ), $data['fields'] );
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
	 * Changed values for all four network-scope roots.
	 *
	 * @return array<string, mixed>
	 */
	private function network_changes(): array {
		return array(
			'data_residency' => array( 'ruleset_version' => 7 ),
			'announcements'  => array( 'dismissed' => array( 'a1' ) ),
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
