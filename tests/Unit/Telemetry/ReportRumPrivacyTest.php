<?php
/**
 * Tests for W8 telemetry browser probe privacy toggle and RUM metadata forwarding.
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Telemetry;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Rest\ClientProbeController;
use WenPai\ChinaYes\Telemetry\Report;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-telemetry-stubs.php';

/**
 * Verifies W8 telemetry Report respects diagnostics.client_probe_enabled and forwards RUM metadata.
 */
class ReportRumPrivacyTest extends TestCase {

	/**
	 * Reset stores.
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		TelemetryStore::reset();
	}

	/**
	 * Report includes sanitized RUM fields when enabled and omits browser probes when disabled.
	 */
	public function test_report_honors_client_probe_enabled_toggle(): void {
		OptionStore::$options[ ClientProbeController::OPTION ] = array(
			'checked_at' => '2026-11-26T08:05:00Z',
			'probes'     => array(
				array(
					'target'            => 'googlefonts.admincdn.com',
					'result'            => 'ok',
					'latency_ms'        => 38,
					'province'          => 'Guangdong',
					'isp'               => 'CU',
					'switched_fallback' => false,
					'tao'               => true,
				),
			),
		);

		$repo = new Repository();
		$repo->set( 'diagnostics.client_probe_enabled', true );
		$enabled_report = ( new Report( $repo ) )->collect();
		$this->assertArrayHasKey( 'probes', $enabled_report );
		$this->assertArrayHasKey( 'browser', $enabled_report['probes'] );
		$this->assertSame( 'Guangdong', $enabled_report['probes']['browser'][0]['province'] );
		$this->assertSame( 'CU', $enabled_report['probes']['browser'][0]['isp'] );
		$this->assertFalse( $enabled_report['probes']['browser'][0]['switched_fallback'] );
		$this->assertTrue( $enabled_report['probes']['browser'][0]['tao'] );

		$repo->set( 'diagnostics.client_probe_enabled', false );
		$disabled_report = ( new Report( $repo ) )->collect();
		$this->assertFalse( isset( $disabled_report['probes']['browser'] ) );
	}

	/**
	 * Report preserves valid UTF-8 when truncating multi-byte Chinese province and isp strings.
	 */
	public function test_report_utf8_multibyte_province_and_isp_encode_cleanly(): void {
		OptionStore::$options[ ClientProbeController::OPTION ] = array(
			'checked_at' => '2026-11-26T08:05:00Z',
			'probes'     => array(
				array(
					'target'     => 'googlefonts.admincdn.com',
					'result'     => 'ok',
					'latency_ms' => 31,
					'province'   => '内蒙古自治区呼和浩特市新城区',
					'isp'        => str_repeat( '中国联通宽带接入运营商', 4 ),
				),
			),
		);

		$repo   = new Repository();
		$report = ( new Report( $repo ) )->collect();
		$row    = $report['probes']['browser'][0];
		$this->assertSame( '内蒙古自治区呼和浩特市新城区', $row['province'] );
		$this->assertSame( 32, mb_strlen( $row['isp'], 'UTF-8' ) );
		$this->assertTrue( mb_check_encoding( $row['province'], 'UTF-8' ) );
		$this->assertTrue( mb_check_encoding( $row['isp'], 'UTF-8' ) );
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $report ) : json_encode( $report ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap may not load WordPress.
		$this->assertIsString( $encoded );
		$decoded = json_decode( $encoded, true );
		$this->assertIsArray( $decoded );
		$this->assertSame( '内蒙古自治区呼和浩特市新城区', $decoded['probes']['browser'][0]['province'] );
	}
}
