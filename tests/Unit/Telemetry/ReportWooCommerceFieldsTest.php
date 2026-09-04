<?php
/**
 * WooCommerce block of the compatibility report.
 *
 * Isolated so class WooCommerce does not leak into ReportFieldsTest.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Telemetry;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Telemetry\Report;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-telemetry-stubs.php';
require_once __DIR__ . '/woocommerce-fakes.php';
require_once __DIR__ . '/woocommerce-wc.php';

/**
 * Port of tests/test-telemetry-woocommerce.php PASS sentences.
 */
class ReportWooCommerceFieldsTest extends TestCase {

	/**
	 * WooCommerce fakes and option bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		TelemetryStore::reset();

		TelemetryStore::$wp_version                                = '4.9.26';
		TelemetryStore::$site_url                                  = 'https://shop.example';
		TelemetryStore::$plugins                                   = array(
			'woocommerce/woocommerce.php' => array(
				'Version' => WC_VERSION,
				'Name'    => 'WooCommerce',
				'Author'  => 'Automattic',
			),
		);
		TelemetryStore::$user_counts                               = array(
			'total_users' => 120,
			'avail_roles' => array(
				'administrator' => 1,
				'customer'      => 119,
			),
		);
		OptionStore::$options['active_plugins']                    = array( 'woocommerce/woocommerce.php' );
		OptionStore::$options['woocommerce_allow_tracking']        = 'yes';
		OptionStore::$options['woocommerce_cart_page_id']          = 7;
		OptionStore::$options['woocommerce_checkout_page_id']      = 8;
		OptionStore::$options['woocommerce_calc_taxes']            = 'yes';
		OptionStore::$options['woocommerce_enable_coupons']        = 'yes';
		OptionStore::$options['woocommerce_enable_guest_checkout'] = 'no';
		$_SERVER['SERVER_SOFTWARE']                                = 'nginx/1.26';
		$GLOBALS['wpdb'] = new \Wpcy_Telemetry_Fake_Wpdb(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- unit stub.
	}

	/**
	 * Aggregates only; country without state; tracking flag; WP 4.9 has_block guard.
	 */
	public function test_woocommerce_block_carries_aggregates_only() {
		$report = ( new Report( new Repository() ) )->collect();

		$this->assertArrayHasKey( 'woocommerce', $report );
		$this->assertIsArray( $report['woocommerce'] );
		$wc = $report['woocommerce'];

		$this->assertSame( WC_VERSION, $wc['wc_version'] );
		$this->assertSame( 'CNY', $wc['currency'] );
		$this->assertSame( 'CN', $wc['base_location'] );
		$this->assertTrue( $wc['allow_tracking'] );
		$this->assertSame( 15, $wc['products_total'] );
		$this->assertSame(
			array(
				'simple'   => 12,
				'variable' => 3,
			),
			$wc['products_by_type']
		);
		$this->assertSame( 42, $wc['orders_total'] );
		$this->assertSame(
			array(
				'wc-completed'  => 40,
				'wc-processing' => 2,
			),
			$wc['orders_by_status']
		);
		$this->assertSame(
			array(
				array(
					'id'      => 'alipay',
					'enabled' => true,
				),
				array(
					'id'      => 'cod',
					'enabled' => false,
				),
			),
			$wc['gateways']
		);
		$this->assertFalse( $wc['block_cart'] );
		$this->assertSame( array(), $wc['template_overrides'] );
		$this->assertSame( 3, $wc['shipping_zones_count'] );
		$this->assertSame(
			array(
				'administrator' => 1,
				'customer'      => 119,
			),
			$wc['user_roles']
		);
		$this->assertSame( 1, TelemetryStore::$count_users_calls );
		$this->assertSame( 120, $report['platform']['users_count'] );

		$forbidden_keys   = array( 'admin_email', 'customer_email', 'billing_email', 'order_items', 'line_items', 'license_key', 'api_key', 'password', 'user_email', 'store_id', 'blog_id', 'state', 'postcode', 'city' );
		$forbidden_values = array( 'GD' );
		$this->walk_forbidden( $report, $forbidden_keys, $forbidden_values );
	}

	/**
	 * Walk keys and string values. Same as tests/test-telemetry-woocommerce.php.
	 *
	 * @param mixed    $node             Tree.
	 * @param string[] $forbidden_keys   Keys.
	 * @param string[] $forbidden_values Values.
	 */
	private function walk_forbidden( $node, array $forbidden_keys, array $forbidden_values ): void {
		if ( is_string( $node ) ) {
			$this->assertFalse(
				in_array( $node, $forbidden_values, true ),
				'forbidden value present in report: ' . $node
			);
			return;
		}
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $key => $value ) {
			if ( is_string( $key ) ) {
				$this->assertFalse(
					in_array( $key, $forbidden_keys, true ),
					'forbidden key present in report: ' . $key
				);
			}
			$this->walk_forbidden( $value, $forbidden_keys, $forbidden_values );
		}
	}
}
