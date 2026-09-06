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
	 * Successful activate stores ciphertext and connected.
	 */
	public function test_connect_success_persists() {
		BindingStore::$responses = array(
			array(
				'code' => 200,
				'body' => '{"success":true}',
			),
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
		$this->assertArrayNotHasKey( 'license_key', $result );
		$this->assertArrayNotHasKey( 'instance', $result );
		$this->assertSame( 'WXD-SECRET-KEY', ( new Store() )->license_key( 'weixiaoduo-mall' ) );
		$encoded = wp_json_encode( $result );
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( 'alice@example.com', $encoded );
		$this->assertStringNotContainsString( 'WXD-SECRET-KEY', $encoded );
	}

	/**
	 * Invalid activate does not store the key.
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
	 * Unreachable activate does not store the key and is 503.
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
				'body' => '{"success":true}',
			),
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
}
