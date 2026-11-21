<?php
/**
 * Announcements: dismiss persists; empty cache returns empty list without error.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\Announcements\AnnouncementsModule;
use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Rest\AnnouncementsController;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Support\LegacyFixtureKey;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Acceptance: dismiss then GET omits the id; no cache GET is {generated_at:null,items:[]}.
 */
class AnnouncementsTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AdminStore::reset();
		OptionStore::reset();
		RestError::reset();
	}

	/**
	 * No cache and empty source: GET is generated_at null, items [].
	 */
	public function test_no_cache_get_is_empty_without_error() {
		$module     = new AnnouncementsModule( new Repository() );
		$controller = new AnnouncementsController( $module );
		$response   = $controller->get_items( new WP_REST_Request() );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'generated_at' => null,
				'items'        => array(),
			),
			$response->get_data()
		);
		$this->assertSame( '', $module->source() );
	}

	/**
	 * Failed fetch with no prior cache still returns empty, not an error.
	 */
	public function test_offline_fetch_does_not_error() {
		$module = new AnnouncementsModule(
			new Repository(),
			'mock://down',
			static function () {
				return '';
			}
		);

		$payload = $module->payload();
		$this->assertNull( $payload['generated_at'] );
		$this->assertSame( array(), $payload['items'] );
	}

	/**
	 * Failed fetch keeps the previous cache.
	 */
	public function test_failed_refresh_keeps_old_cache() {
		$calls  = 0;
		$module = new AnnouncementsModule(
			new Repository(),
			'mock://ann',
			static function () use ( &$calls ) {
				++$calls;
				if ( 1 === $calls ) {
					return file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/announcements/sample.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
				}
				return '';
			},
			LegacyFixtureKey::verifier()
		);

		$first = $module->refresh();
		$this->assertIsArray( $first );
		$this->assertSame( '2026-09-03T12:00:00Z', $first['generated_at'] );

		$second = $module->refresh();
		$this->assertSame( $first['generated_at'], $second['generated_at'] );
		$this->assertCount( 6, $second['items'] );
	}

	/**
	 * Visible list is at most 5, newest first.
	 */
	public function test_visible_list_is_five_newest() {
		$module = $this->module_from_fixture();
		$module->refresh();
		$payload = $module->payload();

		$this->assertSame( '2026-09-03T12:00:00Z', $payload['generated_at'] );
		$this->assertCount( 5, $payload['items'] );
		$ids = array();
		foreach ( $payload['items'] as $item ) {
			$ids[] = $item['id'];
		}
		$this->assertSame(
			array( 'wptea-12345', 'one-2001', 'wptea-12340', 'wptea-12300', 'one-1990' ),
			$ids
		);
		$this->assertNotContains( 'wptea-12200', $ids );
	}

	/**
	 * Dismiss then GET omits that id; the sixth item slides in.
	 */
	public function test_dismiss_then_get_omits_id() {
		$module     = $this->module_from_fixture();
		$controller = new AnnouncementsController( $module );
		$module->refresh();

		$request         = new WP_REST_Request();
		$request->params = array( 'id' => 'wptea-12345' );
		$dismissed       = $controller->dismiss( $request );

		$this->assertSame( 200, $dismissed->get_status() );
		$ids = array();
		foreach ( $dismissed->get_data()['items'] as $item ) {
			$ids[] = $item['id'];
		}
		$this->assertNotContains( 'wptea-12345', $ids );
		$this->assertContains( 'wptea-12200', $ids );
		$this->assertContains( 'wptea-12345', $module->dismissed_ids() );

		$again     = $controller->get_items( new WP_REST_Request() );
		$again_ids = array();
		foreach ( $again->get_data()['items'] as $item ) {
			$again_ids[] = $item['id'];
		}
		$this->assertNotContains( 'wptea-12345', $again_ids );
	}

	/**
	 * Unknown id dismiss still 200 and does not empty the list.
	 */
	public function test_unknown_id_dismiss_is_200() {
		$module     = $this->module_from_fixture();
		$controller = new AnnouncementsController( $module );
		$module->refresh();

		$request         = new WP_REST_Request();
		$request->params = array( 'id' => 'not-a-real-id' );
		$response        = $controller->dismiss( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 5, $response->get_data()['items'] );
		$this->assertContains( 'not-a-real-id', $module->dismissed_ids() );
	}

	/**
	 * Dismissed list drops the oldest when over 100.
	 */
	public function test_dismissed_cap_drops_oldest() {
		$repo = new Repository();
		$ids  = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$ids[] = 'old-' . $i;
		}
		update_option( AnnouncementsModule::DISMISSED_OPTION, $ids );

		$module = new AnnouncementsModule( $repo );
		$module->dismiss( 'new-id' );

		$stored = $module->dismissed_ids();
		$this->assertCount( 100, $stored );
		$this->assertNotContains( 'old-0', $stored );
		$this->assertContains( 'new-id', $stored );
		$this->assertSame( 'new-id', $stored[99] );
	}

	/**
	 * Dismiss writes the per-site option only, never the settings document.
	 */
	public function test_dismiss_writes_site_option_not_settings() {
		$module = new AnnouncementsModule( new Repository() );
		$module->dismiss( 'wptea-12345' );

		$this->assertSame( 'wpcy_announcements_dismissed', AnnouncementsModule::DISMISSED_OPTION );
		$this->assertSame( array( 'wptea-12345' ), get_option( AnnouncementsModule::DISMISSED_OPTION ) );
		$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );
		$this->assertSame( array(), OptionStore::$site_options );
	}

	/**
	 * Reads come from the per-site option; a legacy announcements root still
	 * stored in the settings document is ignored.
	 */
	public function test_dismissed_ids_read_site_option() {
		$repo = new Repository();
		update_option( Schema::SETTINGS, array( 'announcements' => array( 'dismissed' => array( 'from-settings' ) ) ) );
		update_option( AnnouncementsModule::DISMISSED_OPTION, array( 'from-option', '', 7 ) );

		$this->assertSame( array( 'from-option' ), ( new AnnouncementsModule( $repo ) )->dismissed_ids() );
	}

	/**
	 * Multisite: a subsite dismiss leaves the network document untouched and
	 * is visible on that site only.
	 */
	public function test_multisite_dismiss_is_per_site_and_skips_network_settings() {
		OptionStore::$multisite                                = true;
		OptionStore::$site_options[ Schema::NETWORK_SETTINGS ] = Defaults::network_settings();
		$network_before                                        = OptionStore::$site_options;
		AdminStore::$sites                                     = array( 1, 2 );

		switch_to_blog( 2 );
		$module = $this->module_from_fixture();
		$module->refresh();
		$module->dismiss( 'wptea-12345' );
		$this->assertSame( array( 'wptea-12345' ), $module->dismissed_ids() );
		$this->assertNotContains( 'wptea-12345', $this->visible_ids( $module ) );
		restore_current_blog();

		$this->assertSame( $network_before, OptionStore::$site_options );
		$this->assertSame( array( 'wptea-12345' ), AdminStore::options_of( 2 )[ AnnouncementsModule::DISMISSED_OPTION ] );
		$this->assertArrayNotHasKey( AnnouncementsModule::DISMISSED_OPTION, AdminStore::options_of( 1 ) );
		$this->assertSame( array(), $module->dismissed_ids() );
		$this->assertContains( 'wptea-12345', $this->visible_ids( $module ) );
	}

	/**
	 * Ids in the visible payload.
	 *
	 * @param AnnouncementsModule $module Module.
	 * @return list<string>
	 */
	private function visible_ids( AnnouncementsModule $module ): array {
		$ids = array();
		foreach ( $module->payload()['items'] as $item ) {
			$ids[] = $item['id'];
		}
		return $ids;
	}

	/**
	 * HTTP source that is not wpcy.com is not fetched.
	 */
	public function test_non_wpcy_https_source_is_not_fetched() {
		$module  = new AnnouncementsModule( new Repository(), 'https://one.weixiaoduo.com/feed' );
		$payload = $module->payload();
		$this->assertNull( $payload['generated_at'] );
		$this->assertSame( array(), $payload['items'] );
	}

	/**
	 * HTTP source that is not https is dropped.
	 */
	public function test_http_item_url_is_dropped() {
		$payload = SignedPayload::encode(
			array(
				'generated_at' => '2026-09-03T12:00:00Z',
				'items'        => array(
					array(
						'id'           => 'bad-http',
						'source'       => 'wptea',
						'title'        => 'HTTP',
						'url'          => 'http://wptea.com/insecure',
						'summary'      => 'no',
						'published_at' => '2026-09-03T08:00:00Z',
					),
					array(
						'id'           => 'ok-https',
						'source'       => 'wptea',
						'title'        => 'HTTPS',
						'url'          => 'https://wptea.com/ok',
						'summary'      => 'yes',
						'published_at' => '2026-09-03T08:00:00Z',
					),
				),
			)
		);

		$module = new AnnouncementsModule(
			new Repository(),
			'mock://ann',
			static function () use ( $payload ) {
				return $payload;
			},
			SignedPayload::verifier()
		);
		$module->refresh();
		$items = $module->payload()['items'];
		$this->assertCount( 1, $items );
		$this->assertSame( 'ok-https', $items[0]['id'] );
	}

	/**
	 * Valid signature replaces the cache.
	 */
	public function test_valid_signature_replaces_cache() {
		$signed = SignedPayload::encode( $this->unsigned_document() );
		$module = new AnnouncementsModule(
			new Repository(),
			'mock://ann',
			static function () use ( $signed ) {
				return $signed;
			},
			SignedPayload::verifier()
		);

		$cached = $module->refresh();
		$this->assertIsArray( $cached );
		$this->assertSame( '2026-09-11T12:00:00Z', $cached['generated_at'] );
		$this->assertSame( 'ok-https', $cached['items'][0]['id'] );
	}

	/**
	 * Invalid signature keeps the previous valid document.
	 */
	public function test_invalid_signature_keeps_previous() {
		$valid   = SignedPayload::encode( $this->unsigned_document() );
		$bad_doc = SignedPayload::corrupt( json_decode( $valid, true ) );
		$bad     = wp_json_encode( $bad_doc );
		$calls   = 0;
		$module  = new AnnouncementsModule(
			new Repository(),
			'mock://ann',
			static function () use ( &$calls, $valid, $bad ) {
				++$calls;
				return 1 === $calls ? $valid : $bad;
			},
			SignedPayload::verifier()
		);

		$first  = $module->refresh();
		$second = $module->refresh();
		$this->assertSame( $first['generated_at'], $second['generated_at'] );
		$this->assertSame( 'ok-https', $second['items'][0]['id'] );
	}

	/**
	 * Missing signature keeps the previous valid document.
	 */
	public function test_missing_signature_keeps_previous() {
		$valid  = SignedPayload::encode( $this->unsigned_document() );
		$plain  = wp_json_encode( $this->unsigned_document( '2026-09-12T00:00:00Z' ) );
		$calls  = 0;
		$module = new AnnouncementsModule(
			new Repository(),
			'mock://ann',
			static function () use ( &$calls, $valid, $plain ) {
				++$calls;
				return 1 === $calls ? $valid : $plain;
			},
			SignedPayload::verifier()
		);

		$first  = $module->refresh();
		$second = $module->refresh();
		$this->assertSame( '2026-09-11T12:00:00Z', $second['generated_at'] );
		$this->assertSame( $first['generated_at'], $second['generated_at'] );
	}

	/**
	 * Disabling `admin.news_enabled` suppresses announcements payload and skips remote fetch.
	 */
	public function test_news_disabled_returns_empty_payload_and_skips_fetch() {
		$repo                              = new Repository();
		$settings                          = Defaults::settings();
		$settings['admin']['news_enabled'] = false;
		$repo->save_option( Schema::SETTINGS, $settings );

		$called = false;
		$module = new AnnouncementsModule(
			$repo,
			'mock://ann',
			static function () use ( &$called ) {
				$called = true;
				return '';
			}
		);

		$this->assertFalse( $module->is_enabled() );
		$this->assertSame(
			array(
				'generated_at' => null,
				'items'        => array(),
			),
			$module->payload()
		);
		$this->assertFalse( $called );
	}

	/**
	 * Entitled white-label custom HTTPS news_feed_url overrides source with an isolated cache key.
	 */
	public function test_entitled_whitelabel_custom_news_feed_overrides_source_and_isolates_cache() {
		set_transient(
			\WenPai\ChinaYes\Services\Entitlements\EntitlementsModule::TRANSIENT_FRESH,
			array(
				array(
					'service' => 'oem',
					'status'  => 'active',
				),
			),
			3600
		);

		$repo                                       = new Repository();
		$settings                                   = Defaults::settings();
		$settings['admin']['whitelabel']['enabled'] = true;
		$settings['admin']['whitelabel']['news_feed_url'] = 'https://agency.example.com/wp-json/agency/v1/news';
		$repo->save_option( Schema::SETTINGS, $settings );

		$fetched_url = '';
		$module      = new AnnouncementsModule(
			$repo,
			'https://wpcy.com/wp-json/wpcy/v1/announcements',
			static function ( string $target ) use ( &$fetched_url ) {
				$fetched_url = $target;
				return (string) wp_json_encode(
					array(
						'generated_at' => '2026-11-20T10:00:00Z',
						'items'        => array(
							array(
								'id'           => 'agency-1',
								'title'        => '<b>系统维护通知</b>',
								'url'          => 'https://agency.example.com/notice/1',
								'summary'      => '今晚 23:00 例行升级。',
								'published_at' => '2026-11-20T09:00:00Z',
							),
							array(
								'id'           => 'insecure-drop',
								'title'        => 'HTTP 链接应被丢弃',
								'url'          => 'http://agency.example.com/insecure',
								'summary'      => '丢弃',
								'published_at' => '2026-11-20T09:30:00Z',
							),
						),
					)
				);
			}
		);

		$this->assertSame( 'https://agency.example.com/wp-json/agency/v1/news', $module->source() );
		$this->assertStringStartsWith( 'wpcy_announcements_wl_', $module->cache_key() );

		$payload = $module->payload();
		$this->assertSame( 'https://agency.example.com/wp-json/agency/v1/news', $fetched_url );
		$this->assertSame( '2026-11-20T10:00:00Z', $payload['generated_at'] );
		$this->assertCount( 1, $payload['items'] );
		$this->assertSame( 'agency-1', $payload['items'][0]['id'] );
		$this->assertSame( '系统维护通知', $payload['items'][0]['title'] );
		$this->assertSame( 'whitelabel', $payload['items'][0]['source'] );
	}

	/**
	 * Unentitled sites ignore `whitelabel.news_feed_url` even if written in database options.
	 */
	public function test_unentitled_whitelabel_news_feed_url_is_ignored() {
		$repo                                       = new Repository();
		$settings                                   = Defaults::settings();
		$settings['admin']['whitelabel']['enabled'] = true;
		$settings['admin']['whitelabel']['news_feed_url'] = 'https://agency.example.com/wp-json/agency/v1/news';
		$repo->save_option( Schema::SETTINGS, $settings );

		$module = new AnnouncementsModule( $repo, 'https://wpcy.com/wp-json/wpcy/v1/announcements' );
		$this->assertSame( 'https://wpcy.com/wp-json/wpcy/v1/announcements', $module->source() );
		$this->assertStringNotContainsString( '_wl_', $module->cache_key() );
	}

	/**
	 * Unsigned sample used by existing tests.
	 *
	 * @param string $generated Generated-at stamp.
	 * @return array<string, mixed>
	 */
	private function unsigned_document( string $generated = '2026-09-11T12:00:00Z' ): array {
		return array(
			'generated_at' => $generated,
			'items'        => array(
				array(
					'id'           => 'ok-https',
					'source'       => 'wptea',
					'title'        => 'HTTPS',
					'url'          => 'https://wptea.com/ok',
					'summary'      => 'yes',
					'published_at' => '2026-09-03T08:00:00Z',
				),
			),
		);
	}

	/**
	 * Fixture path.
	 */
	private function fixture(): string {
		return dirname( __DIR__, 2 ) . '/fixtures/announcements/sample.json';
	}

	/**
	 * Module pointed at the sample fixture.
	 */
	private function module_from_fixture(): AnnouncementsModule {
		return new AnnouncementsModule( new Repository(), $this->fixture(), null, LegacyFixtureKey::verifier() );
	}
}
