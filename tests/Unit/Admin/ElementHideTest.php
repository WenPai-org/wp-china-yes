<?php
/**
 * Element hide: signed rules, 72h stale cache, red-line selectors, master switch.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\ElementHide\ElementHideModule;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Acceptance: signed element_hide rules hide promo selectors; core notices never.
 */
class ElementHideTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AdminStore::reset();
		OptionStore::reset();
	}

	/**
	 * Empty source disables production fetch.
	 */
	public function test_empty_source_has_no_rules() {
		$module = new ElementHideModule( new Repository() );
		$this->assertSame( '', $module->source() );
		$this->assertSame( array(), $module->active_rules() );
	}

	/**
	 * Valid signature caches the document.
	 */
	public function test_valid_signature_caches_rules() {
		$payload = SignedPayload::encode( $this->document() );
		$module  = $this->module_from_payload( $payload );
		$cached  = $module->refresh();

		$this->assertIsArray( $cached );
		$this->assertSame( 1, $cached['version'] );
		$ids = array();
		foreach ( $module->active_rules() as $rule ) {
			$ids[] = $rule['id'];
		}
		$this->assertContains( 'woo-promo-notice', $ids );
	}

	/**
	 * Invalid signature keeps the previous valid document.
	 */
	public function test_invalid_signature_keeps_previous() {
		$valid   = SignedPayload::encode( $this->document() );
		$bad_doc = SignedPayload::corrupt( json_decode( $valid, true ) );
		$bad     = wp_json_encode( $bad_doc );
		$calls   = 0;
		$module  = new ElementHideModule(
			new Repository(),
			'mock://element-hide',
			static function () use ( &$calls, $valid, $bad ) {
				++$calls;
				return 1 === $calls ? $valid : $bad;
			}
		);

		$first = $module->refresh();
		$this->assertIsArray( $first );
		$second = $module->refresh();
		$this->assertSame( $first['issued_at'], $second['issued_at'] );
		$this->assertSame( $first['rules'], $second['rules'] );
	}

	/**
	 * Missing signature keeps the previous valid document.
	 */
	public function test_missing_signature_keeps_previous() {
		$valid  = SignedPayload::encode( $this->document() );
		$plain  = wp_json_encode( $this->document( '2026-09-12T00:00:00Z' ) );
		$calls  = 0;
		$module = new ElementHideModule(
			new Repository(),
			'mock://element-hide',
			static function () use ( &$calls, $valid, $plain ) {
				++$calls;
				return 1 === $calls ? $valid : $plain;
			}
		);

		$first  = $module->refresh();
		$second = $module->refresh();
		$this->assertSame( $first['issued_at'], $second['issued_at'] );
	}

	/**
	 * Fetch failure within 72h keeps the last valid document.
	 */
	public function test_failed_refresh_within_72h_keeps_cache() {
		$valid  = SignedPayload::encode( $this->document() );
		$calls  = 0;
		$module = new ElementHideModule(
			new Repository(),
			'mock://element-hide',
			static function () use ( &$calls, $valid ) {
				++$calls;
				return 1 === $calls ? $valid : '';
			}
		);

		$first  = $module->refresh();
		$second = $module->refresh();
		$this->assertSame( $first['issued_at'], $second['issued_at'] );
		$this->assertNotEmpty( $module->active_rules() );
	}

	/**
	 * Stale cache older than 72h is cleared on failed refresh.
	 */
	public function test_stale_cache_over_72h_is_cleared() {
		$module = new ElementHideModule(
			new Repository(),
			'mock://element-hide',
			static function () {
				return '';
			}
		);

		$stale               = $this->document();
		$stale['fetched_at'] = gmdate( 'Y-m-d\\TH:i:s\\Z', time() - ( 73 * HOUR_IN_SECONDS ) );
		AdminStore::$transients[ ElementHideModule::TRANSIENT_KEY ] = $stale;

		$this->assertNull( $module->refresh() );
		$this->assertNull( $module->cached_document() );
		$this->assertSame( array(), $module->active_rules() );
	}

	/**
	 * Core / update-nag / Site Health selectors are dropped even when signed.
	 */
	public function test_red_line_selectors_are_dropped() {
		$payload = SignedPayload::encode(
			array(
				'version'   => 1,
				'issued_at' => '2026-09-11T00:00:00Z',
				'rules'     => array(
					array(
						'id'       => 'hide-update-nag',
						'selector' => '.update-nag',
						'label'    => 'core nag',
					),
					array(
						'id'       => 'hide-updated',
						'selector' => '.updated',
						'label'    => 'updated',
					),
					array(
						'id'       => 'hide-notice',
						'selector' => '.notice',
						'label'    => 'generic notice',
					),
					array(
						'id'            => 'woo-promo-notice',
						'target_plugin' => 'woocommerce',
						'selector'      => '.woocommerce-message',
						'label'         => 'Woo promo',
					),
				),
			)
		);
		$module  = $this->module_from_payload( $payload );
		$module->refresh();

		$ids = array();
		foreach ( $module->active_rules() as $rule ) {
			$ids[] = $rule['id'];
		}
		$this->assertNotContains( 'hide-update-nag', $ids );
		$this->assertNotContains( 'hide-updated', $ids );
		$this->assertNotContains( 'hide-notice', $ids );
		$this->assertContains( 'woo-promo-notice', $ids );

		ob_start();
		$module->print_hide_styles();
		$css = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'update-nag', $css );
		$this->assertStringNotContainsString( '.updated', $css );
		$this->assertStringContainsString( 'woocommerce-message', $css );
		$this->assertStringContainsString( 'display:none!important', $css );
	}

	/**
	 * Master switch off prints nothing.
	 */
	public function test_switch_off_prints_nothing() {
		$repo = new Repository();
		$repo->set( 'admin.hide_promo', false );
		$module = new ElementHideModule(
			$repo,
			'mock://element-hide',
			static function () {
				return SignedPayload::encode(
					array(
						'version'   => 1,
						'issued_at' => '2026-09-11T00:00:00Z',
						'rules'     => array(
							array(
								'id'       => 'woo-promo-notice',
								'selector' => '.woocommerce-message',
								'label'    => 'Woo promo',
							),
						),
					)
				);
			}
		);
		$module->refresh();

		ob_start();
		$module->print_hide_styles();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * Frontend requests print nothing even when the switch is on.
	 */
	public function test_frontend_prints_nothing() {
		AdminStore::$is_admin = false;
		$module               = $this->module_from_payload( SignedPayload::encode( $this->document() ) );
		$module->refresh();

		ob_start();
		$module->print_hide_styles();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * REST status route is registered on rest_api_init.
	 */
	public function test_register_exposes_element_hide_route() {
		$module = new ElementHideModule( new Repository() );
		$module->register();
		$this->assertArrayHasKey( 'rest_api_init', AdminStore::$hooks );
		$this->assertArrayHasKey( 'admin_head', AdminStore::$hooks );

		$module->register_routes();
		$routes = array();
		foreach ( AdminStore::$routes as $row ) {
			$routes[] = $row['route'];
		}
		$this->assertContains( '/element-hide', $routes );
	}

	/**
	 * Default hide_promo is on.
	 */
	public function test_hide_promo_defaults_on() {
		$repo = new Repository();
		$this->assertTrue( (bool) $repo->get( 'admin.hide_promo', false ) );
		$mod = new ElementHideModule( $repo );
		$env = new Environment( Environment::ADMIN );
		$this->assertTrue( $mod->enabled( $repo, $env ) );
	}

	/**
	 * Printing styles records a monthly hit.
	 */
	public function test_print_records_monthly_hits() {
		$module = $this->module_from_payload( SignedPayload::encode( $this->document() ) );
		$module->refresh();

		ob_start();
		$module->print_hide_styles();
		ob_end_clean();

		$diag = $module->diagnostics();
		$this->assertSame( 1, $diag['hits'] );
		$this->assertSame( 1, $diag['version'] );
	}

	/**
	 * Strictly greater version replaces the cached document.
	 */
	public function test_greater_version_is_adopted() {
		$calls  = 0;
		$first  = SignedPayload::encode( $this->document( '2026-09-11T00:00:00Z', 1 ) );
		$second = SignedPayload::encode( $this->document( '2026-09-12T00:00:00Z', 2 ) );
		$module = new ElementHideModule(
			new Repository(),
			'mock://element-hide',
			static function () use ( &$calls, $first, $second ) {
				++$calls;
				return 1 === $calls ? $first : $second;
			}
		);

		$cached = $module->refresh();
		$this->assertSame( 1, $cached['version'] );
		$cached = $module->refresh();
		$this->assertSame( 2, $cached['version'] );
		$this->assertSame( '2026-09-12T00:00:00Z', $cached['issued_at'] );
		$this->assertFalse( $module->diagnostics()['stale_version'] );
	}

	/**
	 * Equal version is discarded; previous cache stays; stale_version is set.
	 */
	public function test_equal_version_is_discarded() {
		$calls  = 0;
		$first  = SignedPayload::encode( $this->document( '2026-09-11T00:00:00Z', 1 ) );
		$second = SignedPayload::encode( $this->document( '2026-09-12T00:00:00Z', 1 ) );
		$module = new ElementHideModule(
			new Repository(),
			'mock://element-hide',
			static function () use ( &$calls, $first, $second ) {
				++$calls;
				return 1 === $calls ? $first : $second;
			}
		);

		$first_cached  = $module->refresh();
		$second_cached = $module->refresh();
		$this->assertSame( $first_cached['issued_at'], $second_cached['issued_at'] );
		$this->assertSame( 1, $second_cached['version'] );
		$this->assertTrue( $module->diagnostics()['stale_version'] );
	}

	/**
	 * Smaller version is discarded; previous cache stays.
	 */
	public function test_smaller_version_is_discarded() {
		$calls  = 0;
		$first  = SignedPayload::encode( $this->document( '2026-09-11T00:00:00Z', 3 ) );
		$second = SignedPayload::encode( $this->document( '2026-09-12T00:00:00Z', 2 ) );
		$module = new ElementHideModule(
			new Repository(),
			'mock://element-hide',
			static function () use ( &$calls, $first, $second ) {
				++$calls;
				return 1 === $calls ? $first : $second;
			}
		);

		$first_cached  = $module->refresh();
		$second_cached = $module->refresh();
		$this->assertSame( 3, $second_cached['version'] );
		$this->assertSame( $first_cached['issued_at'], $second_cached['issued_at'] );
		$this->assertTrue( $module->diagnostics()['stale_version'] );
	}

	/**
	 * Schema version major >1 is rejected after a valid signature.
	 */
	public function test_schema_version_major_greater_than_one_is_rejected() {
		$ok                    = SignedPayload::encode( $this->document( '2026-09-11T00:00:00Z', 1 ) );
		$bad                   = $this->document( '2026-09-12T00:00:00Z', 2 );
		$bad['schema_version'] = '2.0';
		$bad                   = SignedPayload::encode( $bad );
		$calls                 = 0;
		$module                = new ElementHideModule(
			new Repository(),
			'mock://element-hide',
			static function () use ( &$calls, $ok, $bad ) {
				++$calls;
				return 1 === $calls ? $ok : $bad;
			}
		);

		$first  = $module->refresh();
		$second = $module->refresh();
		$this->assertSame( 1, $second['version'] );
		$this->assertSame( $first['issued_at'], $second['issued_at'] );
		$this->assertFalse( $module->diagnostics()['stale_version'] );
	}

	/**
	 * Schema version 1.x is adopted.
	 */
	public function test_schema_version_one_is_adopted() {
		$doc                   = $this->document( '2026-09-12T00:00:00Z', 2 );
		$doc['schema_version'] = '1.0';
		$module                = $this->module_from_payload( SignedPayload::encode( $doc ) );
		$cached                = $module->refresh();
		$this->assertSame( 2, $cached['version'] );
	}

	/**
	 * Protocol other than adblock-v1 is rejected after a valid signature.
	 */
	public function test_foreign_protocol_is_rejected() {
		$ok              = SignedPayload::encode( $this->document( '2026-09-11T00:00:00Z', 1 ) );
		$bad             = $this->document( '2026-09-12T00:00:00Z', 2 );
		$bad['protocol'] = 'adblock-v2';
		$bad             = SignedPayload::encode( $bad );
		$calls           = 0;
		$module          = new ElementHideModule(
			new Repository(),
			'mock://element-hide',
			static function () use ( &$calls, $ok, $bad ) {
				++$calls;
				return 1 === $calls ? $ok : $bad;
			}
		);

		$first  = $module->refresh();
		$second = $module->refresh();
		$this->assertSame( 1, $second['version'] );
		$this->assertSame( $first['issued_at'], $second['issued_at'] );
	}

	/**
	 * Protocol adblock-v1 is adopted.
	 */
	public function test_protocol_adblock_v1_is_adopted() {
		$doc             = $this->document( '2026-09-12T00:00:00Z', 2 );
		$doc['protocol'] = 'adblock-v1';
		$module          = $this->module_from_payload( SignedPayload::encode( $doc ) );
		$cached          = $module->refresh();
		$this->assertSame( 2, $cached['version'] );
	}

	/**
	 * Missing ttl defaults to 86400; an explicit ttl is stored.
	 */
	public function test_ttl_defaults_and_override() {
		$module = $this->module_from_payload( SignedPayload::encode( $this->document() ) );
		$cached = $module->refresh();
		$this->assertSame( 86400, $cached['ttl'] );

		$custom        = $this->document( '2026-09-12T00:00:00Z', 2 );
		$custom['ttl'] = 3600;
		$module        = $this->module_from_payload( SignedPayload::encode( $custom ) );
		$cached        = $module->refresh();
		$this->assertSame( 3600, $cached['ttl'] );
	}

	/**
	 * Admin retry within one hour does not pull again.
	 */
	public function test_admin_retry_throttled_within_one_hour() {
		$calls   = 0;
		$payload = SignedPayload::encode( $this->document() );
		$module  = new ElementHideModule(
			new Repository(),
			'mock://element-hide',
			static function () use ( &$calls, $payload ) {
				++$calls;
				return $payload;
			}
		);

		$first = $module->retry();
		$this->assertIsArray( $first );
		$this->assertSame( 1, $calls );
		$second = $module->retry();
		$this->assertSame( $first['issued_at'], $second['issued_at'] );
		$this->assertSame( 1, $calls );
	}

	/**
	 * Reserved production URL is documented; default source stays empty.
	 */
	public function test_production_url_reserved_default_source_empty() {
		$module = new ElementHideModule( new Repository() );
		$this->assertSame( '', $module->source() );
		$this->assertSame( 'https://wpcy.com/rulesets/element-hide.json', ElementHideModule::PRODUCTION_URL );
		$this->assertFalse( strpos( ElementHideModule::PRODUCTION_URL, '?' ) );
	}

	/**
	 * HTTP pull uses 10s timeout, sslverify, no query, no cookies.
	 */
	public function test_http_fetch_is_anonymous_and_verifies_tls() {
		$module = new ElementHideModule(
			new Repository(),
			'https://wpcy.com/rulesets/element-hide.json'
		);
		$module->refresh();

		$this->assertNotEmpty( AdminStore::$http );
		$req = AdminStore::$http[0];
		$this->assertSame( 'https://wpcy.com/rulesets/element-hide.json', $req['url'] );
		$this->assertFalse( strpos( $req['url'], '?' ) );
		$this->assertSame( 10, $req['args']['timeout'] );
		$this->assertTrue( $req['args']['sslverify'] );
		$this->assertSame( array(), $req['args']['cookies'] );
		$this->assertSame( array(), $req['args']['headers'] );
	}

	/**
	 * A source URL with query string is not requested.
	 */
	public function test_source_url_with_query_is_not_fetched() {
		$module = new ElementHideModule(
			new Repository(),
			'https://wpcy.com/rulesets/element-hide.json?site=1'
		);
		$module->refresh();
		$this->assertSame( array(), AdminStore::$http );
	}

	/**
	 * Sample fixture document.
	 *
	 * @param string $issued  Issued-at stamp.
	 * @param int    $version Document version.
	 * @return array<string, mixed>
	 */
	private function document( string $issued = '2026-09-11T00:00:00Z', int $version = 1 ): array {
		return array(
			'version'   => $version,
			'issued_at' => $issued,
			'rules'     => array(
				array(
					'id'            => 'woo-promo-notice',
					'target_plugin' => 'woocommerce',
					'selector'      => '.woocommerce-message',
					'label'         => 'Woo promo',
				),
			),
		);
	}

	/**
	 * Module pointed at a JSON payload fetcher.
	 *
	 * @param string $payload Signed JSON.
	 */
	private function module_from_payload( string $payload ): ElementHideModule {
		return new ElementHideModule(
			new Repository(),
			'mock://element-hide',
			static function () use ( $payload ) {
				return $payload;
			}
		);
	}
}
