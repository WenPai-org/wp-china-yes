<?php
/**
 * MotuCloud native client: list / search timeout and core fallback.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\IconPhotos;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\IconPhotos\Client;
use WenPai\ChinaYes\Connectivity\IconPhotos\Origins;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WP_Error;

require_once dirname( __DIR__ ) . '/wp-error-stub.php';

/**
 * Native list/search; empty or failing native_api_base uses Openverse.
 */
class ClientTest extends TestCase {

	/**
	 * HTTP GET count.
	 *
	 * @var int
	 */
	private $calls = 0;

	/**
	 * URLs requested, in order.
	 *
	 * @var list<string>
	 */
	private $urls = array();

	/**
	 * Host => canned response.
	 *
	 * @var array<string, mixed>
	 */
	private $by_host = array();

	/**
	 * Last timeout used by HTTP GET.
	 *
	 * @var int
	 */
	private $timeout = 0;

	/**
	 * Reset bags.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->calls   = 0;
		$this->urls    = array();
		$this->by_host = array();
	}

	/**
	 * Empty native base skips MotuCloud and hits the core catalog.
	 */
	public function test_list_empty_base_uses_core(): void {
		$this->by_host['api.openverse.org'] = array(
			'code' => 200,
			'body' => '{"results":[]}',
		);
		$client                             = $this->client( '' );

		$out = $client->list();

		$this->assertIsArray( $out );
		$this->assertTrue( $client->last_used_core() );
		$this->assertStringContainsString( Origins::CORE_SEARCH_ORIGIN, $client->last_url() );
		$this->assertCount( 1, $this->urls );
	}

	/**
	 * Native 200 is used; core is not contacted.
	 */
	public function test_search_native_success(): void {
		$this->by_host['motu.example'] = array(
			'code' => 200,
			'body' => '{"hits":[1]}',
		);
		$client                        = $this->client( 'https://motu.example/api' );

		$out = $client->search( 'cat', array( 'page' => 1 ) );

		$this->assertSame( array( 'hits' => array( 1 ) ), $out );
		$this->assertFalse( $client->last_used_core() );
		$this->assertStringContainsString( 'https://motu.example/api/v1/search', $this->urls[0] );
		$this->assertStringContainsString( 'q=cat', $this->urls[0] );
		$this->assertCount( 1, $this->urls );
		$this->assertSame( Origins::NATIVE_TIMEOUT, $this->last_timeout() );
	}

	/**
	 * Native transport error falls back to Openverse.
	 */
	public function test_list_native_error_falls_back_to_core(): void {
		$this->by_host['motu.example']      = new WP_Error( 'http_request_failed', 'timeout' );
		$this->by_host['api.openverse.org'] = array(
			'code' => 200,
			'body' => '{"results":[2]}',
		);
		$client                             = $this->client( 'https://motu.example/api' );

		$out = $client->list( array( 'page' => 2 ) );

		$this->assertSame( array( 'results' => array( 2 ) ), $out );
		$this->assertTrue( $client->last_used_core() );
		$this->assertCount( 2, $this->urls );
		$this->assertStringContainsString( '/v1/list', $this->urls[0] );
		$this->assertStringContainsString( Origins::CORE_IMAGES_PATH, $this->urls[1] );
	}

	/**
	 * Native 5xx also falls back.
	 */
	public function test_search_native_http_error_falls_back(): void {
		$this->by_host['motu.example']      = array(
			'code' => 503,
			'body' => '',
		);
		$this->by_host['api.openverse.org'] = array(
			'code' => 200,
			'body' => '{"results":[]}',
		);
		$client                             = $this->client( 'https://motu.example/api' );

		$out = $client->search( 'dog' );

		$this->assertIsArray( $out );
		$this->assertTrue( $client->last_used_core() );
	}

	/**
	 * Both sides failing returns WP_Error.
	 */
	public function test_both_failing_is_error(): void {
		$this->by_host['*'] = new WP_Error( 'http_request_failed', 'down' );
		$client             = $this->client( 'https://motu.example/api' );

		$out = $client->list();

		$this->assertInstanceOf( WP_Error::class, $out );
	}

	/**
	 * HTTP GET used by Client. Public so it is a valid callable.
	 *
	 * @param string               $url  Request URL.
	 * @param array<string, mixed> $args Request args.
	 * @return mixed
	 */
	public function http_get( $url, $args ) {
		$this->urls[]  = $url;
		$this->timeout = isset( $args['timeout'] ) ? (int) $args['timeout'] : 0;
		++$this->calls;

		foreach ( $this->by_host as $needle => $value ) {
			if ( '*' !== $needle && false !== strpos( $url, $needle ) ) {
				return $value;
			}
		}

		return $this->by_host['*'] ?? new WP_Error( 'http_request_failed', 'miss' );
	}

	/**
	 * Last timeout recorded.
	 */
	private function last_timeout(): int {
		return $this->timeout;
	}

	/**
	 * Client under test.
	 *
	 * @param string $native Native API base.
	 */
	private function client( string $native ): Client {
		$config = new MapConfig(
			array(
				'connectivity.icon_photos' => array(
					'enabled'         => 'on',
					'mirrored_base'   => 'https://motu.example/m',
					'native_api_base' => $native,
				),
			)
		);

		return new Client( $config, array( $this, 'http_get' ) );
	}
}
