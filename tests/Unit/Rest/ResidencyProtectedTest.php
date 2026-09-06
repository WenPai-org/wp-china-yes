<?php
/**
 * GET /residency/protected and POST /residency/test shapes.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Diagnostics\OutboundLayers;
use WenPai\ChinaYes\Privacy\DataResidency\DataResidencyModule;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WenPai\ChinaYes\Privacy\SiteBlocklist\Repository as BlocklistRepository;
use WenPai\ChinaYes\Rest\ResidencyController;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_Error;
use WP_REST_Request;

require_once __DIR__ . '/wp-rest-stubs.php';

/**
 * Three-layer GET and per-layer POST test.
 */
class ResidencyProtectedTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		OptionStore::reset();
		RestError::reset();
	}

	/**
	 * GET /residency/protected has l0 / l1 / l2 / noise_block.
	 */
	public function test_get_protected_three_layers() {
		$controller = $this->controller();
		$response   = $controller->get_protected( new WP_REST_Request() );
		$data       = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'l0', $data );
		$this->assertArrayHasKey( 'l1', $data );
		$this->assertArrayHasKey( 'l2', $data );
		$this->assertArrayHasKey( 'noise_block', $data );
		$this->assertContains( $data['l0']['source'], array( 'builtin', 'builtin+signed' ) );
		$this->assertNotEmpty( $data['l0']['hosts'] );
		$this->assertArrayHasKey( 'ruleset_version', $data['l1'] );
		$this->assertArrayHasKey( 'tiers', $data['l1'] );
		$this->assertTrue( $data['l2']['enabled'] );
		$this->assertSame( array(), $data['l2']['hosts'] );
		$this->assertTrue( $data['noise_block']['enabled'] );
		$this->assertSame( array(), $data['noise_block']['hosts'] );
		$this->assertArrayNotHasKey( 'signature', $data );
	}

	/**
	 * POST /residency/test L0 allow.
	 */
	public function test_residency_test_l0_allow() {
		$data = $this->controller()->test_url( $this->url_request( 'https://api.wenpai.net/v1' ) );
		$this->assertNotInstanceOf( WP_Error::class, $data );
		$body = $data->get_data();
		$this->assertSame( 'l0', $body['layer'] );
		$this->assertSame( 'allow', $body['action'] );
		$this->assertSame( 'api.wenpai.net', $body['host'] );
	}

	/**
	 * POST /residency/test L1 reroute.
	 */
	public function test_residency_test_l1_reroute() {
		$data = $this->controller()->test_url( $this->url_request( 'https://tracking.woocommerce.com/v1' ) );
		$body = $data->get_data();
		$this->assertSame( 'l1', $body['layer'] );
		$this->assertSame( 'reroute', $body['action'] );
		$this->assertSame( 'A', $body['detail']['tier'] );
	}

	/**
	 * Ingest not ready does not report reroute; action is the actual allow and detail keeps enabled_when.
	 */
	public function test_residency_test_ingest_not_ready_falls_through() {
		$ruleset    = new Ruleset( null, null, false );
		$config     = new Repository();
		$module     = new DataResidencyModule( $ruleset, false, $config );
		$layers     = new OutboundLayers( $config, $ruleset, new BlocklistRepository( $config, $ruleset ), $module );
		$controller = new ResidencyController( $module, $layers );
		$data       = $controller->test_url( $this->url_request( 'https://tracking.woocommerce.com/v1' ) );
		$body       = $data->get_data();
		$this->assertSame( 'l1', $body['layer'] );
		$this->assertSame( 'allow', $body['action'] );
		$this->assertSame( 'ingest_ready', $body['detail']['enabled_when'] );
		$this->assertSame( 'A', $body['detail']['tier'] );
	}

	/**
	 * POST /residency/test L2 block.
	 */
	public function test_residency_test_l2_block() {
		OptionStore::$options[ Schema::SETTINGS ] = array(
			'schema_version' => 2,
			'modules'        => array(
				'site_blocklist' => array(
					'enabled' => true,
					'hosts'   => array(
						array(
							'host'  => 'tracker.example.com',
							'match' => 'exact',
							'note'  => '',
						),
					),
				),
			),
		);
		$data                                     = $this->controller()->test_url( $this->url_request( 'https://tracker.example.com/v1' ) );
		$body                                     = $data->get_data();
		$this->assertSame( 'l2', $body['layer'] );
		$this->assertSame( 'block', $body['action'] );
	}

	/**
	 * POST /residency/test noise_block block.
	 */
	public function test_residency_test_noise_block() {
		$payload = array(
			'issued_at'       => '2026-09-06T00:00:00Z',
			'ruleset_version' => 1,
			'tiers'           => array(
				'C' => array(
					array(
						'action' => 'ignore',
						'host'   => '*',
					),
				),
			),
			'protected_hosts' => array(),
			'noise_block'     => array(
				array(
					'host'  => 'heartbeat.license.example',
					'match' => 'exact',
				),
			),
		);
		$tmp     = tempnam( sys_get_temp_dir(), 'wpcy-nb-' );
		$this->assertNotFalse( $tmp );
		file_put_contents( $tmp, json_encode( $payload ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- temp unsigned JSON.

		try {
			$ruleset    = new Ruleset( $tmp, null, false );
			$config     = new Repository();
			$module     = new DataResidencyModule( $ruleset, false, $config );
			$layers     = new OutboundLayers( $config, $ruleset, new BlocklistRepository( $config, $ruleset ), $module );
			$controller = new ResidencyController( $module, $layers );
			$data       = $controller->test_url( $this->url_request( 'https://heartbeat.license.example/ping' ) );
			$body       = $data->get_data();
			$this->assertSame( 'noise_block', $body['layer'] );
			$this->assertSame( 'block', $body['action'] );
		} finally {
			unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temp ruleset.
		}
	}

	/**
	 * Illegal URL is 400 wpcy_invalid_schema.
	 */
	public function test_residency_test_invalid_url_is_400() {
		$result = $this->controller()->test_url( $this->url_request( 'not-a-url' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_invalid_schema', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * REST index includes the two new residency routes.
	 */
	public function test_rest_index_includes_protected_and_test() {
		$module = new RestModule( new Repository() );
		$module->register_routes();
		$routes = array();
		foreach ( RestStore::$routes as $row ) {
			$routes[] = $row['route'];
		}
		$this->assertContains( '/residency/protected', $routes );
		$this->assertContains( '/residency/test', $routes );
	}

	/**
	 * Controller wired with unsigned ruleset.
	 */
	private function controller(): ResidencyController {
		$ruleset = new Ruleset( null, null, false );
		$config  = new Repository();
		$module  = new DataResidencyModule( $ruleset, true, $config );
		$layers  = new OutboundLayers( $config, $ruleset, new BlocklistRepository( $config, $ruleset ), $module );
		return new ResidencyController( $module, $layers );
	}

	/**
	 * POST body with url.
	 *
	 * @param string $url URL.
	 */
	private function url_request( string $url ): WP_REST_Request {
		$request       = new WP_REST_Request();
		$request->json = array( 'url' => $url );
		return $request;
	}
}
