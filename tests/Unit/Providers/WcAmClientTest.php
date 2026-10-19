<?php
/**
 * WcAmClient stub HTTP: ok / invalid / unreachable / HTTP / intranet.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Providers\WcAmClient;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WenPai\ChinaYes\Tests\Unit\Services\SiteBinding\BindingStore;
use WP_Error;

require_once __DIR__ . '/wp-providers-stubs.php';

/**
 * Transport mapping from providers.md §4.
 */
class WcAmClientTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		RestStore::reset();
		BindingStore::reset();
	}

	/**
	 * 2xx success:true → ok.
	 */
	public function test_success_true_is_ok() {
		$client = $this->client(
			array(
				array(
					'code' => 200,
					'body' => '{"success":true,"data":{"activated":true}}',
				),
			)
		);
		$result = $client->activate( 'key', 'inst' );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'ok', $result['kind'] );
		$this->assertTrue( $result['data']['success'] );
	}

	/**
	 * 2xx success:false → invalid.
	 */
	public function test_success_false_is_invalid() {
		$client = $this->client(
			array(
				array(
					'code' => 200,
					'body' => '{"success":false,"error":"invalid"}',
				),
			)
		);
		$result = $client->status( 'key', 'inst' );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'invalid', $result['kind'] );
	}

	/**
	 * Timeout / WP_Error → unreachable.
	 */
	public function test_timeout_is_unreachable() {
		$client = $this->client(
			array(
				new WP_Error( 'http_request_failed', 'cURL error 28: timeout' ),
			)
		);
		$result = $client->status( 'key', 'inst' );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'unreachable', $result['kind'] );
	}

	/**
	 * Non-2xx → unreachable.
	 */
	public function test_non_2xx_is_unreachable() {
		$client = $this->client(
			array(
				array(
					'code' => 500,
					'body' => '{"success":true}',
				),
			)
		);
		$result = $client->status( 'key', 'inst' );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'unreachable', $result['kind'] );
	}

	/**
	 * HTTP target is rejected.
	 */
	public function test_http_target_is_rejected() {
		$client = new WcAmClient( 'http://mall.weixiaoduo.com' );
		$result = $client->status( 'key', 'inst' );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'unreachable', $result['kind'] );
		$this->assertSame( array(), BindingStore::$requests );
	}

	/**
	 * Intranet host is rejected.
	 */
	public function test_intranet_host_is_rejected() {
		$client = new WcAmClient( 'https://127.0.0.1' );
		$result = $client->status( 'key', 'inst' );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'unreachable', $result['kind'] );
		$this->assertSame( array(), BindingStore::$requests );
	}

	/**
	 * Private IPv4 is rejected.
	 */
	public function test_private_ipv4_is_rejected() {
		$client = new WcAmClient( 'https://10.0.0.5' );
		$result = $client->status( 'key', 'inst' );
		$this->assertSame( 'unreachable', $result['kind'] );
		$this->assertSame( array(), BindingStore::$requests );
	}

	/**
	 * Mall probe: key travels in GET query. Result data must not echo it.
	 */
	public function test_key_travels_in_query_result_omits_it() {
		$client = $this->client(
			array(
				array(
					'code' => 200,
					'body' => '{"success":true}',
				),
			)
		);
		$result = $client->activate( 'WXD-SECRET-KEY', 'inst' );
		$this->assertCount( 1, BindingStore::$requests );
		$url  = BindingStore::$requests[0]['url'];
		$args = BindingStore::$requests[0]['args'];
		$this->assertSame( 'GET', $args['method'] );
		$this->assertStringContainsString( 'wc_am_action=activate', $url );
		$this->assertStringContainsString( 'api_key=', $url );
		$encoded = wp_json_encode( $result );
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( 'WXD-SECRET-KEY', $encoded );
	}

	/**
	 * Build a client that drains BindingStore::$responses via the stub HTTP.
	 *
	 * @param array<int, mixed> $responses Queue.
	 */
	private function client( array $responses ): WcAmClient {
		BindingStore::$responses = $responses;
		return new WcAmClient(
			'https://mall.weixiaoduo.com',
			static function ( string $url, array $args ) {
				return wp_remote_post( $url, $args );
			}
		);
	}
}
