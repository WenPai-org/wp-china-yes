<?php
/**
 * ProviderService connect gates, persist rules, and product matching.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Providers\ProviderService;
use WenPai\ChinaYes\Providers\Store;
use WenPai\ChinaYes\Providers\WcAmClient;
use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WenPai\ChinaYes\Tests\Unit\Services\SiteBinding\BindingStore;
use WP_Error;

require_once __DIR__ . '/wp-providers-stubs.php';

/**
 * Connect / products contract from providers.md §3.
 */
class ProviderServiceTest extends TestCase {

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
		$GLOBALS['wpcy_test_plugins'] = array();
	}

	/**
	 * Unbound connect is 403 wpcy_provider_binding_required.
	 */
	public function test_connect_unbound_is_403() {
		$service = $this->service( 'unbound' );
		$result  = $service->connect( 'weixiaoduo-mall', 'a@example.com', 'key' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_provider_binding_required', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->assertArrayNotHasKey( ( new Store() )->license_option( 'weixiaoduo-mall' ), OptionStore::$options );
	}

	/**
	 * Coming soon is 400 wpcy_provider_coming_soon.
	 */
	public function test_connect_coming_soon_is_400() {
		$service = $this->service( 'bound' );
		$result  = $service->connect( 'wenpai-marketplace', 'a@example.com', 'key' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_provider_coming_soon', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * Successful product_list stores ciphertext, connected, and the product cache.
	 */
	public function test_connect_success_persists() {
		BindingStore::$responses = array(
			array(
				'code' => 200,
				'body' => '{"success":true,"data":{"product_list":[{"product_id":12,"slug":"demo-plugin","title":"Demo"}]}}',
			),
		);
		$service                 = $this->service( 'bound' );
		$result                  = $service->connect( 'weixiaoduo-mall', 'alice@example.com', 'WXD-SECRET-KEY' );
		$this->assertIsArray( $result );
		$this->assertSame( 'connected', $result['connection'] );
		$this->assertSame( 'a***@example.com', $result['email_masked'] );
		$this->assertSame( 1, $result['product_count'] );
		$this->assertArrayNotHasKey( 'license_key', $result );
		$this->assertArrayNotHasKey( 'instance', $result );
		$this->assertSame( 'WXD-SECRET-KEY', ( new Store() )->license_key( 'weixiaoduo-mall' ) );
		$encoded = wp_json_encode( $result );
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( 'alice@example.com', $encoded );
		$this->assertStringNotContainsString( 'WXD-SECRET-KEY', $encoded );
		$this->assertCount( 1, BindingStore::$requests );
		$this->assertStringContainsString( 'wc_am_action=product_list', BindingStore::$requests[0]['url'] );
		$this->assertStringNotContainsString( 'wc_am_action=activate', BindingStore::$requests[0]['url'] );
		$this->assertStringNotContainsString( 'wc_am_action=status', BindingStore::$requests[0]['url'] );
	}

	/**
	 * Bad email is 400 wpcy_invalid_schema and does not store the key.
	 */
	public function test_connect_bad_email_is_invalid_schema() {
		$service = $this->service( 'bound' );
		$result  = $service->connect( 'weixiaoduo-mall', 'not-an-email', 'key' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_invalid_schema', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$this->assertArrayNotHasKey( ( new Store() )->license_option( 'weixiaoduo-mall' ), OptionStore::$options );
		$this->assertSame( array(), BindingStore::$requests );
	}

	/**
	 * Empty license key is 400 wpcy_invalid_schema.
	 */
	public function test_connect_empty_key_is_invalid_schema() {
		$service = $this->service( 'bound' );
		$result  = $service->connect( 'weixiaoduo-mall', 'a@example.com', '   ' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_invalid_schema', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$this->assertArrayNotHasKey( ( new Store() )->license_option( 'weixiaoduo-mall' ), OptionStore::$options );
	}

	/**
	 * Invalid product_list does not store the key.
	 */
	public function test_connect_invalid_does_not_persist() {
		BindingStore::$responses = array(
			array(
				'code' => 200,
				'body' => '{"success":false}',
			),
		);
		$service                 = $this->service( 'bound' );
		$result                  = $service->connect( 'weixiaoduo-mall', 'a@example.com', 'bad-key' );
		$this->assertIsArray( $result );
		$this->assertSame( 'invalid', $result['connection'] );
		$this->assertArrayNotHasKey( ( new Store() )->license_option( 'weixiaoduo-mall' ), OptionStore::$options );
		$this->assertArrayNotHasKey( ( new Store() )->instance_option( 'weixiaoduo-mall' ), OptionStore::$options );
	}

	/**
	 * Unreachable product_list does not store the key and is 503.
	 */
	public function test_connect_unreachable_does_not_persist() {
		BindingStore::$responses = array(
			new WP_Error( 'http_request_failed', 'timeout' ),
		);
		$service                 = $this->service( 'bound' );
		$result                  = $service->connect( 'weixiaoduo-mall', 'a@example.com', 'key' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_provider_unreachable', $result->get_error_code() );
		$this->assertSame( 503, $result->get_error_data()['status'] );
		$this->assertArrayNotHasKey( ( new Store() )->license_option( 'weixiaoduo-mall' ), OptionStore::$options );
	}

	/**
	 * Products() exact-matches get_plugins() directories.
	 */
	public function test_products_exact_directory_match() {
		BindingStore::$responses      = array(
			array(
				'code' => 200,
				'body' => '{"success":true,"data":{"product_list":[{"product_id":1,"slug":"demo-plugin","title":"Demo"},{"product_id":2,"slug":"other-plugin","title":"Other"}]}}',
			),
		);
		$GLOBALS['wpcy_test_plugins'] = array(
			'demo-plugin/demo-plugin.php' => array(
				'Name'    => 'Demo',
				'Version' => '1.0.0',
			),
		);
		$service                      = $this->service( 'bound' );
		$service->connect( 'weixiaoduo-mall', 'a@example.com', 'key' );
		$list = $service->products( 'weixiaoduo-mall' );
		$this->assertIsArray( $list );
		$this->assertCount( 2, $list['products'] );
		$this->assertTrue( $list['products'][0]['installed'] );
		$this->assertTrue( $list['products'][0]['update_managed'] );
		$this->assertFalse( $list['products'][1]['installed'] );
		$this->assertFalse( $list['products'][1]['update_managed'] );
	}

	/**
	 * Test() maps product_list ok / invalid / unreachable.
	 */
	public function test_test_three_states() {
		BindingStore::$responses = array(
			array(
				'code' => 200,
				'body' => '{"success":true,"data":{"product_list":[]}}',
			),
		);
		$service                 = $this->service( 'bound' );
		$service->connect( 'weixiaoduo-mall', 'a@example.com', 'key' );

		BindingStore::$responses = array(
			array(
				'code' => 200,
				'body' => '{"success":true,"data":{"product_list":[{"product_id":9,"slug":"demo","title":"Demo"}]}}',
			),
		);
		$ok                      = $service->test( 'weixiaoduo-mall' );
		$this->assertIsArray( $ok );
		$this->assertSame( 'connected', $ok['connection'] );
		$this->assertSame( 1, $ok['product_count'] );
		$this->assertStringContainsString( 'wc_am_action=product_list', BindingStore::$requests[1]['url'] );

		BindingStore::$responses = array(
			array(
				'code' => 200,
				'body' => '{"success":false}',
			),
		);
		$bad                     = $service->test( 'weixiaoduo-mall' );
		$this->assertIsArray( $bad );
		$this->assertSame( 'invalid', $bad['connection'] );

		BindingStore::$responses = array(
			new WP_Error( 'http_request_failed', 'timeout' ),
		);
		$down                    = $service->test( 'weixiaoduo-mall' );
		$this->assertInstanceOf( WP_Error::class, $down );
		$this->assertSame( 'wpcy_provider_unreachable', $down->get_error_code() );
		$this->assertSame( 503, $down->get_error_data()['status'] );
		$this->assertSame( 'unreachable', ( new Store() )->item( 'weixiaoduo-mall' )['connection'] );
	}

	/**
	 * After a timeout, a second products() still returns the cached list.
	 */
	public function test_products_timeout_then_second_call_keeps_cache() {
		BindingStore::$responses = array(
			array(
				'code' => 200,
				'body' => '{"success":true,"data":{"product_list":[{"product_id":1,"slug":"demo-plugin","title":"Demo"}]}}',
			),
		);
		$service                 = $this->service( 'bound' );
		$service->connect( 'weixiaoduo-mall', 'a@example.com', 'key' );
		$this->age_products_cache( 'weixiaoduo-mall', 1000 );

		BindingStore::$responses = array(
			new WP_Error( 'http_request_failed', 'timeout' ),
		);
		$first                   = $service->products( 'weixiaoduo-mall' );
		$this->assertCount( 1, $first['products'] );
		$this->assertSame( '1', $first['products'][0]['product_id'] );
		$this->assertSame( 'unreachable', ( new Store() )->item( 'weixiaoduo-mall' )['connection'] );

		BindingStore::$responses = array(
			new WP_Error( 'http_request_failed', 'timeout' ),
		);
		$second                  = $service->products( 'weixiaoduo-mall' );
		$this->assertCount( 1, $second['products'] );
		$this->assertSame( 'Demo', $second['products'][0]['title'] );
		$this->assertSame( 'unreachable', ( new Store() )->item( 'weixiaoduo-mall' )['connection'] );
	}

	/**
	 * Invalid still returns purchased items from cache (read-only).
	 */
	public function test_products_invalid_still_returns_purchased() {
		BindingStore::$responses = array(
			array(
				'code' => 200,
				'body' => '{"success":true,"data":{"product_list":[{"product_id":7,"slug":"paid-plugin","title":"Paid"}]}}',
			),
		);
		$service                 = $this->service( 'bound' );
		$service->connect( 'weixiaoduo-mall', 'a@example.com', 'key' );
		$this->age_products_cache( 'weixiaoduo-mall', 1000 );

		BindingStore::$responses = array(
			array(
				'code' => 200,
				'body' => '{"success":false}',
			),
		);
		$list                    = $service->products( 'weixiaoduo-mall' );
		$this->assertCount( 1, $list['products'] );
		$this->assertSame( '7', $list['products'][0]['product_id'] );
		$this->assertSame( 'invalid', ( new Store() )->item( 'weixiaoduo-mall' )['connection'] );

		$key                           = ( new Store() )->products_transient( 'weixiaoduo-mall' );
		$raw                           = RestStore::$transients[ $key ];
		$raw['fetched_at']             = gmdate( 'Y-m-d\TH:i:s\Z' );
		RestStore::$transients[ $key ] = $raw;

		$again = $service->products( 'weixiaoduo-mall' );
		$this->assertCount( 1, $again['products'] );
		$this->assertSame( 'Paid', $again['products'][0]['title'] );
		$this->assertSame( 'invalid', ( new Store() )->item( 'weixiaoduo-mall' )['connection'] );
	}

	/**
	 * Bound service with queued HTTP.
	 *
	 * @param string $status Binding status.
	 */
	private function service( string $status ): ProviderService {
		OptionStore::$options[ Schema::SITE_IDENTITY ] = array(
			'schema_version' => 1,
			'site_uuid'      => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
			'binding'        => array(
				'status'       => $status,
				'site_hash'    => 'bound' === $status ? 'hash' : null,
				'credential'   => null,
				'bound_at'     => 'bound' === $status ? '2026-09-06T00:00:00Z' : null,
				'challenge_id' => null,
			),
		);
		$repo    = new Repository();
		$binding = new SiteBindingModule( $repo );
		$factory = static function ( string $api_url ) {
			return new WcAmClient(
				$api_url,
				static function ( string $url, array $args ) {
					return wp_remote_post( $url, $args );
				}
			);
		};
		$plugins = static function () {
			return get_plugins();
		};

		return new ProviderService( new Store(), $binding, $factory, $plugins, null, $repo );
	}

	/**
	 * Push fetched_at back so the cache is no longer in the 15-minute window.
	 *
	 * @param string $id  Provider id.
	 * @param int    $age Seconds in the past.
	 */
	private function age_products_cache( string $id, int $age ): void {
		$key = ( new Store() )->products_transient( $id );
		$raw = RestStore::$transients[ $key ] ?? null;
		$this->assertIsArray( $raw );
		$raw['fetched_at']             = gmdate( 'Y-m-d\TH:i:s\Z', time() - $age );
		RestStore::$transients[ $key ] = $raw;
	}
}
