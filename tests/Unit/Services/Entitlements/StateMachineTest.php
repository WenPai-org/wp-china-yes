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
use WenPai\ChinaYes\Core\Plugin;
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
	 * Empty/non-HTTPS API is blocked; production DEFAULT_API and mock GET use /v1/sites/{hash}/entitlements.
	 */
	public function test_empty_blocked_and_production_default_url_is_correct() {
		EntitlementsStore::$api = '';
		$module                 = $this->bound_module( null );
		$this->assertSame( array(), $module->items() );
		$this->assertSame( array(), EntitlementsStore::$requests );

		EntitlementsStore::$api = 'http://insecure.wpcy.test/v1';
		$module                 = $this->bound_module( null );
		$this->assertSame( array(), $module->items() );
		$this->assertSame( array(), EntitlementsStore::$requests );

		EntitlementsStore::$api = ChallengeClient::DEFAULT_API;
		$module                 = $this->bound_module( 'entitlements-active.json' );
		$module->items();
		$this->assertNotEmpty( EntitlementsStore::$requests );
		$url = EntitlementsStore::$requests[0]['url'];
		$this->assertSame( 'https://license.wenpai.net/v1/sites/site_hash_test_01/entitlements', $url );
		$this->assertStringNotContainsString( '/v1/v1/', $url );
	}

	/**
	 * Entitlements client sends Authorization: Bearer <credential> and never logs it.
	 */
	public function test_bearer_credential_header_forwarded_and_never_logged() {
		$logger               = new Logger(
			'debug',
			static function ( $level, $message, $context ) {
				EntitlementsStore::$log_sink .= $level . ' ' . $message . ' ' . wp_json_encode( $context ) . "\n";
			}
		);
		$repo                 = new Repository( $logger );
		$ident                = $repo->get_identity();
		$binding              = $ident['binding'];
		$binding['status']    = 'bound';
		$binding['site_hash'] = 'site_hash_test_01';
		$ident['binding']     = $binding;
		$repo->set_identity( $ident );
		$repo->set_credential( 'cred_ent_secret' );

		EntitlementsStore::$responses[] = new WP_Error( 'http_request_failed', 'forced failure to exercise log sink' );
		$client                         = new Client( $logger, array( EntitlementsStore::class, 'http_get' ), EntitlementsStore::$api );
		$module                         = new EntitlementsModule( $repo, $logger, $client, array( EntitlementsStore::class, 'get_transient' ), array( EntitlementsStore::class, 'set_transient' ) );
		$module->items();

		$this->assertNotEmpty( EntitlementsStore::$requests );
		$headers = EntitlementsStore::$requests[0]['args']['headers'];
		$this->assertSame( 'Bearer cred_ent_secret', $headers[ Client::AUTH_HEADER ] );
		$this->assertArrayNotHasKey( Client::TEST_CREDENTIAL_HEADER, $headers );
		$this->assertStringNotContainsString( 'cred_ent_secret', EntitlementsStore::$log_sink );
	}

	/**
	 * Restricted services are denied when the cached row is exhausted.
	 */
	public function test_entitlement_allows_false_when_exhausted() {
		$module = $this->bound_module( 'entitlements-exhausted.json' );
		$module->items();

		$this->assertFalse( $module->filter_allows( true, 'motusnap' ) );
		$this->assertFalse( $module->filter_allows( true, 'windfonts' ) );
		$this->assertTrue( $module->filter_allows( true, 'wordpress_org' ) );
	}

	/**
	 * Plugin::create() + exhausted cache: wp_head has no Windfonts stylesheet.
	 */
	public function test_plugin_create_exhausted_wp_head_has_no_windfonts_link() {
		$plugin = Plugin::create();
		$config = $plugin->container()->get( 'config' );
		$config->set( 'modules.windfonts', true );
		$config->set(
			'integrations.windfonts.fonts',
			array(
				array(
					'family'   => 'wenfeng-hcszt',
					'subset'   => 'full',
					'selector' => 'body',
					'enable'   => true,
				),
			)
		);

		$payload = $this->load_fixture( 'entitlements-exhausted.json' );
		RestStore::$transients[ EntitlementsModule::TRANSIENT_FRESH ] = array(
			'fetched_at'   => '2026-09-05T00:00:00Z',
			'entitlements' => $payload['entitlements'],
		);

		$modules = $plugin->registry()->modules();
		$ents    = $modules['services.entitlements'];
		$wind    = $modules['modules.windfonts'];
		$this->assertInstanceOf( EntitlementsModule::class, $ents );
		$ents->register();

		$this->assertFalse( apply_filters( 'wpcy_entitlement_allows', true, 'windfonts' ) );

		ob_start();
		$wind->print_stylesheets();
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'app.windfonts.com/api/css', $html );

		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'app.windfonts.com/api/css', $head );
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
	 * Suite-wide entitlement filters read fresh/stale cache without issuing remote HTTP calls.
	 */
	public function test_suite_entitlement_filters_are_render_safe_and_honor_stale_cache(): void {
		$module = $this->bound_module( null );
		$this->assertSame( array(), $module->filter_suite_entitlements() );
		$this->assertFalse( $module->filter_has_entitlement( false, 'oem' ) );
		$this->assertSame( 0, count( EntitlementsStore::$requests ) );

		EntitlementsStore::set_transient(
			EntitlementsModule::TRANSIENT_STALE,
			array(
				'fetched_at'   => '2026-11-21T00:00:00Z',
				'entitlements' => array(
					array(
						'service' => 'oem',
						'status'  => 'active',
					),
					array(
						'service' => 'wpmind-pro',
						'status'  => 'expired',
					),
				),
			),
			EntitlementsModule::TTL_STALE
		);

		$this->assertCount( 2, $module->filter_suite_entitlements() );
		$this->assertTrue( $module->filter_has_entitlement( false, 'oem' ) );
		$this->assertFalse( $module->filter_has_entitlement( false, 'wpmind-pro' ) );
		$this->assertSame( 0, count( EntitlementsStore::$requests ) );
	}

	/**
	 * Top-level `mind` credits payload from license-server is cached and exposed via `wenpai_site_entitlements['mind']`.
	 */
	public function test_mind_credits_envelope_persists_and_exposes_to_suite_filter(): void {
		$payload         = $this->load_fixture( 'entitlements-active.json' );
		$payload['mind'] = array(
			'credits_balance'     => 498,
			'credits_limit_month' => 500,
			'credits_used_month'  => 2,
		);

		$module = $this->bound_module( null );
		$this->queue_json( $payload );

		$snap = $module->snapshot();
		$this->assertArrayHasKey( 'mind', $snap );
		$this->assertSame( 498, $snap['mind']['credits_balance'] );
		$this->assertSame( 500, $snap['mind']['credits_limit_month'] );
		$this->assertSame( 2, $snap['mind']['credits_used_month'] );

		$suite = $module->filter_suite_entitlements();
		$this->assertArrayHasKey( 'mind', $suite );
		$this->assertSame( 498, $suite['mind']['credits_balance'] );
		$this->assertTrue( $module->filter_has_entitlement( false, 'mind' ) );

		// Survives fresh cache expiry via 72h stale fallback without network.
		EntitlementsStore::delete_transient( EntitlementsModule::TRANSIENT_FRESH );
		$stale_suite = $module->filter_suite_entitlements();
		$this->assertSame( 498, $stale_suite['mind']['credits_balance'] );
		$this->assertCount( 1, EntitlementsStore::$requests );
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
