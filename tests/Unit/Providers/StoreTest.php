<?php
/**
 * Provider Store: seal/open, fail-closed decrypt, email mask, disconnect.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Providers\Store;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;

require_once __DIR__ . '/wp-providers-stubs.php';

/**
 * Storage contract from providers.md §2.
 */
class StoreTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		RestStore::reset();
	}

	/**
	 * Sealed license opens to the same plaintext.
	 */
	public function test_license_roundtrip() {
		$store = new Store();
		$this->assertTrue( $store->put_license_key( 'weixiaoduo-mall', 'WXD-SECRET-KEY' ) );
		$option = $store->license_option( 'weixiaoduo-mall' );
		$this->assertArrayHasKey( $option, OptionStore::$options );
		$cipher = OptionStore::$options[ $option ];
		$this->assertIsString( $cipher );
		$this->assertNotSame( 'WXD-SECRET-KEY', $cipher );
		$this->assertSame( 'WXD-SECRET-KEY', $store->license_key( 'weixiaoduo-mall' ) );
	}

	/**
	 * Decrypt failure is disconnected; plaintext is not returned.
	 */
	public function test_decrypt_failure_is_disconnected() {
		$store                                 = new Store();
		$option                                = $store->license_option( 'weixiaoduo-mall' );
		OptionStore::$options[ $option ]       = 'not-valid-ciphertext';
		OptionStore::$options[ Store::OPTION ] = array(
			'schema_version' => 1,
			'items'          => array(
				'weixiaoduo-mall' => array(
					'connection' => 'connected',
				),
			),
		);

		$this->assertNull( $store->license_key( 'weixiaoduo-mall' ) );
		$this->assertSame( 'disconnected', $store->item( 'weixiaoduo-mall' )['connection'] );
	}

	/**
	 * Email is stored as mask + sha256, never plaintext.
	 */
	public function test_email_is_masked_and_hashed() {
		$store  = new Store();
		$fields = $store->email_fields( 'Alice@Example.COM' );
		$this->assertSame( 'a***@example.com', $fields['email_masked'] );
		$this->assertSame( hash( 'sha256', 'alice@example.com' ), $fields['email_hash'] );
		$store->put_item( 'weixiaoduo-mall', $fields );
		$encoded = wp_json_encode( $store->document() );
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( 'alice@example.com', $encoded );
		$this->assertStringNotContainsString( 'Alice@Example.COM', $encoded );
	}

	/**
	 * Disconnect deletes license, instance, and products transient.
	 */
	public function test_disconnect_deletes_three_keys() {
		$store = new Store();
		$store->put_license_key( 'weixiaoduo-mall', 'WXD-SECRET-KEY' );
		$store->put_instance( 'weixiaoduo-mall', '11111111-2222-4333-8444-555555555555' );
		$store->put_products(
			'weixiaoduo-mall',
			array(
				array(
					'product_id' => '1',
					'title'      => 'Demo',
					'slug'       => 'demo',
				),
			)
		);
		$store->put_item(
			'weixiaoduo-mall',
			array(
				'connection'   => 'connected',
				'email_masked' => 'a***@example.com',
				'email_hash'   => hash( 'sha256', 'a@example.com' ),
			)
		);

		$store->disconnect( 'weixiaoduo-mall' );

		$this->assertArrayNotHasKey( $store->license_option( 'weixiaoduo-mall' ), OptionStore::$options );
		$this->assertArrayNotHasKey( $store->instance_option( 'weixiaoduo-mall' ), OptionStore::$options );
		$this->assertArrayNotHasKey( $store->products_transient( 'weixiaoduo-mall' ), RestStore::$transients );
		$item = $store->item( 'weixiaoduo-mall' );
		$this->assertSame( 'disconnected', $item['connection'] );
		$this->assertNull( $item['email_masked'] );
		$this->assertNull( $item['email_hash'] );
	}

	/**
	 * Empty plaintext is refused and never written.
	 */
	public function test_empty_license_is_not_stored() {
		$store = new Store();
		$this->assertFalse( $store->put_license_key( 'weixiaoduo-mall', '' ) );
		$this->assertArrayNotHasKey( $store->license_option( 'weixiaoduo-mall' ), OptionStore::$options );
	}

	/**
	 * Instance is sealed; option value is not the UUID.
	 */
	public function test_instance_is_sealed() {
		$store  = new Store();
		$uuid   = '11111111-2222-4333-8444-555555555555';
		$option = $store->instance_option( 'weixiaoduo-mall' );
		$store->put_instance( 'weixiaoduo-mall', $uuid );
		$this->assertArrayHasKey( $option, OptionStore::$options );
		$cipher = OptionStore::$options[ $option ];
		$this->assertIsString( $cipher );
		$this->assertNotSame( $uuid, $cipher );
		$this->assertSame( $uuid, $store->read_instance( 'weixiaoduo-mall' ) );
	}

	/**
	 * Leftover plaintext instance is migrated to ciphertext on read.
	 */
	public function test_plaintext_instance_is_migrated() {
		$store                           = new Store();
		$uuid                            = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
		$option                          = $store->instance_option( 'weixiaoduo-mall' );
		OptionStore::$options[ $option ] = $uuid;
		$this->assertSame( $uuid, $store->read_instance( 'weixiaoduo-mall' ) );
		$cipher = OptionStore::$options[ $option ];
		$this->assertIsString( $cipher );
		$this->assertNotSame( $uuid, $cipher );
		$this->assertSame( $uuid, $store->read_instance( 'weixiaoduo-mall' ) );
	}
}
