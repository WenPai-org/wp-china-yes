<?php
/**
 * Compatibility report fields match 3.9.3 tests/test-telemetry.php.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Telemetry;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Telemetry\Report;
use WenPai\ChinaYes\Telemetry\TelemetryModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-telemetry-stubs.php';

/**
 * Port of tests/test-telemetry.php PASS sentences.
 */
class ReportFieldsTest extends TestCase {

	/**
	 * Reset bags and server software.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		TelemetryStore::reset();
		OptionStore::$options['active_plugins'] = array( 'demo/demo.php' );
		$_SERVER['SERVER_SOFTWARE']             = 'nginx/1.26';
	}

	/**
	 * Cron is scheduled even when a leftover 3.x telemetry option is false.
	 */
	public function test_report_is_always_scheduled_regardless_of_legacy_option() {
		OptionStore::$options['wpcy_settings'] = array(
			'telemetry'          => false,
			'telemetry_site_url' => false,
			'bridge'             => false,
		);

		$module = new TelemetryModule( new Repository() );
		$module->register();

		$this->assertNotEmpty( TelemetryStore::$scheduled[ TelemetryModule::CRON_HOOK ] );
		$this->assertFalse( TelemetryStore::$cleared );
		$this->assertArrayHasKey( TelemetryModule::CRON_HOOK, TelemetryStore::$actions );
	}

	/**
	 * 2.1 field set, site_url always sent, no WooCommerce key, no business content.
	 */
	public function test_collect_carries_2_1_fields_and_excludes_business_content() {
		$report = ( new Report( new Repository() ) )->collect();

		foreach ( array( 'site_uuid', 'site_url', 'wp_version', 'php_version', 'mysql_version', 'active_theme', 'locale', 'server_software', 'wpcy_version', 'telemetry_version', 'plugins', 'platform', 'themes', 'translations' ) as $required ) {
			$this->assertArrayHasKey( $required, $report, 'report missing field: ' . $required );
		}

		$this->assertSame( 'https://site.example', $report['site_url'] );
		$this->assertSame( PHP_VERSION, $report['php_version'] );
		$this->assertSame( 'nginx/1.26', $report['server_software'] );
		$this->assertSame( Report::TELEMETRY_VERSION, $report['telemetry_version'] );
		$this->assertSame( '2.1', $report['telemetry_version'] );
		$this->assertSame( 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', $report['site_uuid'] );
		$this->assertSame( 'demo', $report['plugins'][0]['slug'] );
		$this->assertSame( 'Demo Co', $report['plugins'][0]['author'] );
		$this->assertArrayHasKey( 'php_extensions', $report['platform'] );
		$this->assertSame( 3, $report['platform']['users_count'] );
		$this->assertArrayNotHasKey( 'woocommerce', $report );

		$forbidden = array( 'admin_email', 'customer_email', 'billing_email', 'order_items', 'line_items', 'license_key', 'api_key', 'password', 'user_email' );
		$this->assert_no_forbidden_keys( $report, $forbidden );
	}

	/**
	 * Recurse and fail on forbidden keys. Same walk as tests/test-telemetry.php.
	 *
	 * @param mixed    $node      Tree.
	 * @param string[] $forbidden Forbidden keys.
	 */
	private function assert_no_forbidden_keys( $node, array $forbidden ): void {
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $key => $value ) {
			if ( is_string( $key ) ) {
				$this->assertFalse(
					in_array( $key, $forbidden, true ),
					'forbidden field present in report: ' . $key
				);
			}
			$this->assert_no_forbidden_keys( $value, $forbidden );
		}
	}
}
