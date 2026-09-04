<?php
/**
 * Entitlements state machine: active / exhausted / expired / unreachable.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Services\Entitlements;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Rest\EntitlementsController;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Services\Entitlements\Client;
use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;
use WenPai\ChinaYes\Services\SiteBinding\ChallengeClient;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/wp-entitlements-stubs.php';

/**
 * Four-state degrade: shouldUseUpstream('motusnap') matches entitlements.md §3.
 */
class StateMachineTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		OptionStore::reset();
		EntitlementsStore::reset();
		RestError::reset();
		EntitlementsStore::$api = 'https://mock.wpcy.test/v1';
	}

	/**
	 * Active: normal. Do not fall back to the original upstream.
	 */
	public function test_active_does_not_use_upstream() {
		$module  = $this->bound_module( 'entitlements-active.json' );
		$degrade = $module->degrade();

		$this->assertSame( 'active', $degrade->statusFor( 'motusnap' ) );
		$this->assertFalse( $degrade->shouldUseUpstream( 'motusnap' ) );

		$row = $module->items()[0];
		$this->assertSame( 'wpcy-leaf-motusnap-100', $row['id'] );
		$this->assertSame( 'motusnap', $row['service'] );
		$this->assertArrayHasKey( 'limit', $row['quota'] );
		$this->assertArrayHasKey( 'used', $row['quota'] );
		$this->assertSame( $this->fixture_quota( 'entitlements-active.json' ), $row['quota'] );
	}

	/**
	 * Exhausted: related modules fall back to the original upstream / tools read-only.
	 */
	public function test_exhausted_uses_upstream() {
		$module  = $this->bound_module( 'entitlements-exhausted.json' );
		$degrade = $module->degrade();

		$this->assertSame( 'exhausted', $degrade->statusFor( 'motusnap' ) );
		$this->assertTrue( $degrade->shouldUseUpstream( 'motusnap' ) );
	}

	/**
	 * Expired: same as exhausted plus 获取 URL https://wpcy.com/go/{service}.
	 */
	public function test_expired_uses_upstream_and_exposes_acquire_url() {
		$module  = $this->bound_module( 'entitlements-expired.json' );
		$degrade = $module->degrade();

		$this->assertSame( 'expired', $degrade->statusFor( 'motusnap' ) );
		$this->assertTrue( $degrade->shouldUseUpstream( 'motusnap' ) );
		$this->assertSame( 'https://wpcy.com/go/motusnap', $degrade->acquire_url( 'motusnap' ) );
	}

	/**
	 * Unreachable with no cache: baseline. Empty list, HTTP 200, use upstream.
	 */
	public function test_unreachable_without_cache_is_baseline() {
		$module = $this->bound_module( null );
		$this->queue_unreachable();

		$degrade = $module->degrade();
		$this->assertSame( '', $degrade->statusFor( 'motusnap' ) );
		$this->assertTrue( $degrade->shouldUseUpstream( 'motusnap' ) );
		$this->assertSame( array(), $module->items() );

		$response = ( new EntitlementsController( $module ) )->get_item( new WP_REST_Request() );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'entitlements' => array() ), $response->get_data() );
	}

	/**
	 * Unreachable within 72h: last cache is kept (active stays active).
	 */
	public function test_unreachable_within_72h_keeps_last_cache() {
		$module = $this->bound_module( 'entitlements-active.json' );
		$this->assertFalse( $module->degrade()->shouldUseUpstream( 'motusnap' ) );

		EntitlementsStore::delete_transient( EntitlementsModule::TRANSIENT_FRESH );
		$this->queue_unreachable();

		$this->assertSame( 'active', $module->degrade()->statusFor( 'motusnap' ) );
		$this->assertFalse( $module->degrade()->shouldUseUpstream( 'motusnap' ) );
		$this->assertNotSame( array(), $module->items() );
	}

	/**
	 * Unreachable after 72h: stale gone, baseline (use upstream).
	 */
	public function test_unreachable_after_72h_is_baseline() {
		$module = $this->bound_module( 'entitlements-active.json' );
		$this->assertFalse( $module->degrade()->shouldUseUpstream( 'motusnap' ) );
		EntitlementsStore::delete_transient( EntitlementsModule::TRANSIENT_FRESH );
		EntitlementsStore::delete_transient( EntitlementsModule::TRANSIENT_STALE );
		$this->queue_unreachable();

		$this->assertSame( '', $module->degrade()->statusFor( 'motusnap' ) );
		$this->assertTrue( $module->degrade()->shouldUseUpstream( 'motusnap' ) );
	}

	/**
	 * GET /entitlements returns the cached copy as 200, never 5xx.
	 */
	public function test_rest_returns_cached_copy_not_5xx() {
		$module   = $this->bound_module( 'entitlements-active.json' );
		$response = ( new EntitlementsController( $module ) )->get_item( new WP_REST_Request() );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'entitlements', $data );
		$this->assertSame( 'active', $data['entitlements'][0]['status'] );
		$this->assertSame( 'motusnap', $data['entitlements'][0]['service'] );
	}

	/**
	 * Production license-server is not contacted. Mock GET uses /v1/sites/{hash}/entitlements.
	 */
	public function test_production_host_is_not_contacted_and_mock_url_is_correct() {
		EntitlementsStore::$api = ChallengeClient::DEFAULT_API;
		$module                 = $this->bound_module( null );
		$this->assertSame( array(), $module->items() );
		$this->assertSame( array(), EntitlementsStore::$requests );

		EntitlementsStore::$api = 'https://mock.wpcy.test/v1';
		$module                 = $this->bound_module( 'entitlements-active.json' );
		$module->items();
		$this->assertNotEmpty( EntitlementsStore::$requests );
		$url = EntitlementsStore::$requests[0]['url'];
		$this->assertStringContainsString( '/v1/sites/site_hash_test_01/entitlements', $url );
		$this->assertStringNotContainsString( '/v1/v1/', $url );
		$this->assertStringNotContainsString( ChallengeClient::PRODUCTION_HOST, $url );
	}

	/**
	 * Client PHP sources do not hardcode a quota of 100.
	 */
	public function test_php_sources_do_not_hardcode_quota_one_hundred() {
		$root  = dirname( __DIR__, 4 );
		$dir   = $root . '/src/Services/Entitlements';
		$files = glob( $dir . '/*.php' );
		$this->assertNotFalse( $files );
		$files[] = $root . '/src/Rest/EntitlementsController.php';
		foreach ( $files as $path ) {
			$source = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
			$this->assertIsString( $source );
			$this->assertSame(
				0,
				preg_match( '/\b100\b/', $source ),
				$path . ' must not hardcode quota 100'
			);
		}
	}

	/**
	 * Bound module that optionally queues a fixture as the next HTTP 200.
	 *
	 * HTTP, API base, and transients are injected so --filter StateMachine
	 * still works when other suites' global stubs loaded first.
	 *
	 * @param string|null $fixture Fixture file name, or null to skip HTTP.
	 * @return EntitlementsModule
	 */
	private function bound_module( $fixture ): EntitlementsModule {
		$logger               = new Logger(
			'debug',
			static function ( $level, $message, $context ) {
				EntitlementsStore::$log_sink .= $level . ' ' . $message . ' ' . wp_json_encode( $context ) . "\n";
			}
		);
		$repo                 = new Repository( $logger );
		$identity             = $repo->get_identity();
		$binding              = $identity['binding'];
		$binding['status']    = 'bound';
		$binding['site_hash'] = 'site_hash_test_01';
		$identity['binding']  = $binding;
		$repo->set_identity( $identity );

		if ( is_string( $fixture ) ) {
			$this->queue_json( $this->load_fixture( $fixture ) );
		}

		$client = new Client(
			$logger,
			array( EntitlementsStore::class, 'http_get' ),
			EntitlementsStore::$api
		);

		return new EntitlementsModule(
			$repo,
			$logger,
			$client,
			array( EntitlementsStore::class, 'get_transient' ),
			array( EntitlementsStore::class, 'set_transient' )
		);
	}

	/**
	 * Queue a transport failure. The unreachable fixture is not a success body.
	 */
	private function queue_unreachable(): void {
		$this->load_fixture( 'entitlements-unreachable.json' );
		EntitlementsStore::$responses[] = new WP_Error( 'http_request_failed', 'mock unreachable' );
	}

	/**
	 * Queue a 200 JSON body as the next HTTP response.
	 *
	 * @param array<string, mixed> $payload Body.
	 */
	private function queue_json( array $payload ): void {
		EntitlementsStore::$responses[] = array(
			'code' => 200,
			'body' => wp_json_encode( $payload ),
		);
	}

	/**
	 * Quota object from a fixture, compared as-is (client does not compute).
	 *
	 * @param string $name File name.
	 * @return array<string, mixed>
	 */
	private function fixture_quota( string $name ): array {
		$decoded = $this->load_fixture( $name );
		return $decoded['entitlements'][0]['quota'];
	}

	/**
	 * Load a mock-license fixture.
	 *
	 * @param string $name File name.
	 * @return array<string, mixed>
	 */
	private function load_fixture( string $name ): array {
		$path = dirname( __DIR__, 3 ) . '/fixtures/mock-license/' . $name;
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$this->assertIsString( $raw );
		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}
}
