<?php
/**
 * Service-catalog fetch + cache state machine.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Services\Catalog;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Rest\CatalogController;
use WenPai\ChinaYes\Services\Catalog\CatalogClient;
use WenPai\ChinaYes\Services\Catalog\CatalogModule;
use WenPai\ChinaYes\Services\SiteBinding\ChallengeClient;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WenPai\ChinaYes\Tests\Unit\Services\Entitlements\EntitlementsStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/../Entitlements/wp-entitlements-stubs.php';

/**
 * Catalog client/module: bound fetch, cache hit, unreachable, production block.
 */
class CatalogStateTest extends TestCase {

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
		EntitlementsStore::$api = 'https://mock.wpcy.test/v1';
	}

	/**
	 * Bound site: catalog items come back normalized with version.
	 */
	public function test_bound_fetch_returns_catalog_items() {
		$module = $this->bound_module( $this->catalog_payload() );
		$snap   = $module->snapshot();

		$this->assertSame( '2026-11-17', $snap['catalog_version'] );
		$this->assertFalse( $snap['stale'] );
		$this->assertNotEmpty( $snap['items'] );
		$this->assertSame( 'windfonts', $snap['items'][0]['id'] );
		$this->assertSame( 'included', $snap['items'][0]['access'] );
		$this->assertSame( 'motusnap', $snap['items'][1]['id'] );
		$this->assertSame( 'active', $snap['items'][1]['access'] );
		$this->assertSame( 100, $snap['items'][1]['quota']['limit'] );
	}

	/**
	 * Malformed body (no items list) is rejected as unavailable.
	 */
	public function test_missing_items_list_is_unavailable() {
		$module = $this->bound_module( array( 'catalog_version' => 'x' ) );
		$snap   = $module->snapshot();

		$this->assertSame( array(), $snap['items'] );
		$this->assertSame( '', $snap['catalog_version'] );
	}

	/**
	 * Unbound site: no HTTP, empty catalog.
	 */
	public function test_unbound_site_does_not_fetch() {
		$repo   = new Repository( new Logger( 'debug' ) );
		$client = new CatalogClient( null, array( EntitlementsStore::class, 'http_get' ), EntitlementsStore::$api );
		$module = new CatalogModule( $repo, null, $client, array( EntitlementsStore::class, 'get_transient' ), array( EntitlementsStore::class, 'set_transient' ) );
		$snap   = $module->snapshot();

		$this->assertSame( array(), $snap['items'] );
		$this->assertSame( array(), EntitlementsStore::$requests );
	}

	/**
	 * Unreachable with no cache: empty catalog, REST 200, never 5xx.
	 */
	public function test_unreachable_without_cache_is_empty_200() {
		$module                         = $this->bound_module( null );
		EntitlementsStore::$responses[] = new WP_Error( 'http_request_failed', 'mock unreachable' );

		$snap = $module->snapshot();
		$this->assertSame( array(), $snap['items'] );

		$response = ( new CatalogController( $module ) )->get_item( new WP_REST_Request() );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'catalog_version', $response->get_data() );
		$this->assertArrayHasKey( 'items', $response->get_data() );
	}

	/**
	 * Unreachable within 72h: stale catalog is served with stale=true.
	 */
	public function test_unreachable_within_72h_serves_stale() {
		$module = $this->bound_module( $this->catalog_payload() );
		$this->assertNotEmpty( $module->snapshot()['items'] );

		EntitlementsStore::delete_transient( CatalogModule::TRANSIENT_FRESH );
		EntitlementsStore::$responses[] = new WP_Error( 'http_request_failed', 'mock unreachable' );

		$snap = $module->snapshot();
		$this->assertTrue( $snap['stale'] );
		$this->assertSame( '2026-11-17', $snap['catalog_version'] );
		$this->assertNotEmpty( $snap['items'] );
	}

	/**
	 * Production host is never contacted; mock URL is /v1/sites/{hash}/catalog.
	 */
	public function test_production_host_blocked_and_mock_url() {
		EntitlementsStore::$api = ChallengeClient::DEFAULT_API;
		$module                 = $this->bound_module( null );
		$module->snapshot();
		$this->assertSame( array(), EntitlementsStore::$requests );

		EntitlementsStore::$api = 'https://mock.wpcy.test/v1';
		$module                 = $this->bound_module( $this->catalog_payload() );
		$module->snapshot();
		$this->assertNotEmpty( EntitlementsStore::$requests );
		$url = EntitlementsStore::$requests[0]['url'];
		$this->assertStringContainsString( '/v1/sites/site_hash_test_01/catalog', $url );
		$this->assertStringNotContainsString( '/v1/v1/', $url );
		$this->assertStringNotContainsString( ChallengeClient::PRODUCTION_HOST, $url );
	}

	/**
	 * Credential header is forwarded when present, never logged.
	 */
	public function test_credential_header_forwarded() {
		$repo                 = new Repository( new Logger( 'debug' ) );
		$ident                = $repo->get_identity();
		$binding              = $ident['binding'];
		$binding['status']    = 'bound';
		$binding['site_hash'] = 'site_hash_test_01';
		$ident['binding']     = $binding;
		$repo->set_identity( $ident );
		$repo->set_credential( 'cred_test_secret' );

		$this->queue_json( $this->catalog_payload() );
		$client = new CatalogClient( null, array( EntitlementsStore::class, 'http_get' ), EntitlementsStore::$api );
		$module = new CatalogModule( $repo, null, $client, array( EntitlementsStore::class, 'get_transient' ), array( EntitlementsStore::class, 'set_transient' ) );
		$module->snapshot();

		$this->assertNotEmpty( EntitlementsStore::$requests );
		$headers = EntitlementsStore::$requests[0]['args']['headers'];
		$this->assertSame( 'cred_test_secret', $headers[ CatalogClient::TEST_CREDENTIAL_HEADER ] );
		$this->assertStringNotContainsString( 'cred_test_secret', EntitlementsStore::$log_sink );
	}

	/**
	 * Bound module with optional queued fixture.
	 *
	 * @param array<string, mixed>|null $payload Fixture body, or null for no HTTP.
	 * @return CatalogModule
	 */
	private function bound_module( $payload ): CatalogModule {
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

		if ( is_array( $payload ) ) {
			$this->queue_json( $payload );
		}

		$client = new CatalogClient( $logger, array( EntitlementsStore::class, 'http_get' ), EntitlementsStore::$api );
		return new CatalogModule( $repo, $logger, $client, array( EntitlementsStore::class, 'get_transient' ), array( EntitlementsStore::class, 'set_transient' ) );
	}

	/**
	 * Catalog fixture body matching license-server wpcy_catalog.go shape.
	 *
	 * @return array<string, mixed>
	 */
	private function catalog_payload(): array {
		return array(
			'catalog_version' => '2026-11-17',
			'items'           => array(
				array(
					'id'             => 'windfonts',
					'name'           => '文派字库',
					'provider'       => 'windfonts',
					'provider_label' => '文派',
					'desc'           => '开源中文字体托管',
					'vendor'         => '',
					'service'        => 'windfonts',
					'entitlement'    => 'included',
					'install'        => 'install',
					'action'         => 'settings',
					'scenes'         => array( 'speed' ),
					'quota'          => null,
				),
				array(
					'id'             => 'motusnap',
					'name'           => '墨图截图',
					'provider'       => 'motusnap',
					'provider_label' => '墨图云集',
					'desc'           => '网页截图服务',
					'vendor'         => 'MotuCloud',
					'service'        => 'motusnap',
					'entitlement'    => 'active',
					'install'        => 'buy',
					'action'         => 'buy',
					'scenes'         => array(),
					'quota'          => array(
						'limit'     => 100,
						'used'      => 3,
						'period'    => 'monthly',
						'resets_at' => '2026-12-01T00:00:00Z',
					),
				),
			),
		);
	}

	/**
	 * Queue a 200 JSON body as the next HTTP response.
	 *
	 * @param array<string, mixed> $payload Body.
	 */
	private function queue_json( array $payload ): void {
		EntitlementsStore::$responses[] = array(
			'code' => 200,
			'body' => (string) wp_json_encode( $payload ),
		);
	}
}
