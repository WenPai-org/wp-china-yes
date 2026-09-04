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
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Rest\AnnouncementsController;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
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
			}
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
		$repo->set( 'announcements.dismissed', $ids );

		$module = new AnnouncementsModule( $repo );
		$module->dismiss( 'new-id' );

		$stored = $module->dismissed_ids();
		$this->assertCount( 100, $stored );
		$this->assertNotContains( 'old-0', $stored );
		$this->assertContains( 'new-id', $stored );
		$this->assertSame( 'new-id', $stored[99] );
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
		$payload = wp_json_encode(
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
			}
		);
		$module->refresh();
		$items = $module->payload()['items'];
		$this->assertCount( 1, $items );
		$this->assertSame( 'ok-https', $items[0]['id'] );
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
		return new AnnouncementsModule( new Repository(), $this->fixture() );
	}
}
