<?php
/**
 * Tests for W7 ManifestController and W8 ClientProbeController privacy/RUM metadata.
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Connectivity\PublicAssets\Manifest;
use WenPai\ChinaYes\Rest\ClientProbeController;
use WenPai\ChinaYes\Rest\ManifestController;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_REST_Request;

require_once __DIR__ . '/wp-rest-stubs.php';

/**
 * Verifies /manifest REST routes and W8 province/isp/switched_fallback/tao/privacy toggle.
 */
class ManifestAndProbeRumTest extends TestCase {

	/**
	 * Reset REST and Option stores.
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		OptionStore::reset();
	}

	/**
	 * ManifestController returns status snapshot and 502 on unreachable upstream without clobbering LKG.
	 */
	public function test_manifest_controller_get_and_refresh(): void {
		$state    = array(
			'version'       => 2026112601,
			'issued_at'     => '2026-11-26T08:00:00Z',
			'key_id'        => 'admincdn-test-2026-11',
			'active_source' => 'primary',
			'stored_at'     => time(),
			'manifest'      => array(
				'killSwitch' => false,
				'classes'    => array(),
			),
		);
		$manifest = new Manifest(
			new Repository(),
			null,
			array(),
			static function () {
				return array(
					'status' => 503,
					'body'   => '',
					'etag'   => '',
				);
			},
			static function () use ( &$state ) {
				return $state;
			},
			static function ( array $s ) use ( &$state ) {
				$state = $s;
			}
		);

		$ctrl = new ManifestController( $manifest );
		$get  = $ctrl->get_item();
		$this->assertSame( 200, $get->get_status() );
		$this->assertSame( 2026112601, $get->get_data()['version'] );

		$ref = $ctrl->refresh();
		$this->assertSame( 502, $ref->get_status() );
		$this->assertSame( 2026112601, $ref->get_data()['manifest']['version'] );
	}

	/**
	 * ClientProbeController persists optional province/isp/switched_fallback/tao and honors client_probe_enabled=false.
	 */
	public function test_client_probe_rum_metadata_and_privacy_toggle(): void {
		$repo = new Repository();
		$ctrl = new ClientProbeController( $repo );

		$req       = new WP_REST_Request();
		$req->json = array(
			'probes' => array(
				array(
					'url'               => 'https://googlefonts.admincdn.com/css?family=Roboto',
					'result'            => 'ok',
					'latency_ms'        => 45,
					'province'          => 'Zhejiang',
					'isp'               => 'CT',
					'switched_fallback' => true,
					'tao'               => true,
				),
			),
		);

		$res = $ctrl->update_item( $req );
		$this->assertSame( 200, $res->get_status() );
		$saved = $res->get_data()['probes'][0];
		$this->assertSame( 'Zhejiang', $saved['province'] );
		$this->assertSame( 'CT', $saved['isp'] );
		$this->assertTrue( $saved['switched_fallback'] );
		$this->assertTrue( $saved['tao'] );

		$get = $ctrl->get_item( new WP_REST_Request() )->get_data()['probes'][0];
		$this->assertSame( 'Zhejiang', $get['province'] );
		$this->assertTrue( $get['tao'] );

		// Turning diagnostics.client_probe_enabled off suppresses GET and ignores POST.
		$repo->set( 'diagnostics.client_probe_enabled', false );
		$off_get = $ctrl->get_item( new WP_REST_Request() );
		$this->assertSame( array(), $off_get->get_data()['probes'] );

		OptionStore::reset();
		$repo->set( 'diagnostics.client_probe_enabled', false );
		$ctrl->update_item( $req );
		$this->assertArrayNotHasKey( ClientProbeController::OPTION, OptionStore::$options );
	}

	/**
	 * Multi-byte Chinese province and isp truncate cleanly without invalid UTF-8 sequences.
	 */
	public function test_client_probe_utf8_multibyte_truncation_preserves_valid_json(): void {
		$repo = new Repository();
		$ctrl = new ClientProbeController( $repo );

		$long_isp  = str_repeat( '中国电信骨干网络宽带运营商测试数据', 3 );
		$req       = new WP_REST_Request();
		$req->json = array(
			'probes' => array(
				array(
					'url'        => 'https://googlefonts.admincdn.com/css?family=Roboto',
					'result'     => 'ok',
					'latency_ms' => 24,
					'province'   => '内蒙古自治区呼和浩特市新城区',
					'isp'        => $long_isp,
				),
			),
		);

		$res = $ctrl->update_item( $req );
		$this->assertSame( 200, $res->get_status() );
		$saved = $res->get_data()['probes'][0];
		$this->assertSame( '内蒙古自治区呼和浩特市新城区', $saved['province'] );
		$this->assertSame( 32, mb_strlen( $saved['isp'], 'UTF-8' ) );
		$this->assertTrue( mb_check_encoding( $saved['province'], 'UTF-8' ) );
		$this->assertTrue( mb_check_encoding( $saved['isp'], 'UTF-8' ) );
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $res->get_data() ) : json_encode( $res->get_data() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap may not load WordPress.
		$this->assertIsString( $encoded );
		$decoded = json_decode( $encoded, true );
		$this->assertIsArray( $decoded );
		$this->assertSame( '内蒙古自治区呼和浩特市新城区', $decoded['probes'][0]['province'] );
	}
}
