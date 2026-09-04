<?php
/**
 * Site-binding state machine: start → pending → confirm → bound → revoke.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Services\SiteBinding;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Rest\BindingController;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Services\SiteBinding\ChallengeClient;
use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/wp-binding-stubs.php';

/**
 * Acceptance: pending 200 with token; GET /binding has no credential; log sink has no plaintext.
 */
class ChallengeFlowTest extends TestCase {

	/**
	 * Challenge fixture.
	 *
	 * @var array{challenge_id: string, challenge_token: string, expires_at: string}
	 */
	private $start_fixture;

	/**
	 * Confirm fixture.
	 *
	 * @var array{site_hash: string, credential: string}
	 */
	private $confirm_fixture;

	/**
	 * Reset bags and load fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		OptionStore::reset();
		BindingStore::reset();
		RestError::reset();

		$this->start_fixture   = $this->load_fixture( 'challenge-start.json' );
		$this->confirm_fixture = $this->load_fixture( 'challenge-confirm.json' );

		BindingStore::$api = 'https://mock.wpcy.test/v1';
	}

	/**
	 * Pending public endpoint is 200 and contains the token.
	 */
	public function test_pending_public_challenge_returns_token() {
		$module = $this->module();
		$this->queue_json( $this->start_fixture );
		$started = $module->start();
		$this->assertIsArray( $started );
		$this->assertSame( 'pending', $started['status'] );
		$this->assertSame( $this->start_fixture['challenge_id'], $started['challenge_id'] );
		$this->assertArrayNotHasKey( 'challenge_token', $started );
		$this->assertArrayNotHasKey( 'credential', $started );

		$pending = ( new BindingController( null, $module ) )->get_item( new WP_REST_Request() );
		$this->assertSame( 'pending', $pending->get_data()['status'] );
		$this->assertArrayNotHasKey( 'credential', $pending->get_data() );
		$this->assertArrayNotHasKey( 'challenge_token', $pending->get_data() );

		$controller            = new BindingController( null, $module );
		$request               = new WP_REST_Request();
		$request->params['id'] = $this->start_fixture['challenge_id'];
		$response              = $controller->challenge( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $this->start_fixture['challenge_token'], $data['challenge_token'] );
	}

	/**
	 * GET /binding never includes credential or challenge_token, before or after bind.
	 */
	public function test_get_binding_json_has_no_credential() {
		$module = $this->module();
		$empty  = ( new BindingController( null, $module ) )->get_item( new WP_REST_Request() );
		$this->assertSame( 200, $empty->get_status() );
		$this->assertSame(
			array(
				'status'    => 'unbound',
				'site_hash' => null,
				'bound_at'  => null,
			),
			$empty->get_data()
		);
		$this->assertArrayNotHasKey( 'credential', $empty->get_data() );
		$this->assertArrayNotHasKey( 'challenge_token', $empty->get_data() );

		$this->queue_json( $this->start_fixture );
		$module->start();
		$this->queue_json( $this->confirm_fixture );
		$bound = $module->confirm();
		$this->assertIsArray( $bound );
		$this->assertSame( 'bound', $bound['status'] );
		$this->assertSame( $this->confirm_fixture['site_hash'], $bound['site_hash'] );
		$this->assertNotNull( $bound['bound_at'] );
		$this->assertArrayNotHasKey( 'credential', $bound );
		$this->assertArrayNotHasKey( 'challenge_token', $bound );

		$stored = get_option( Schema::SITE_IDENTITY );
		$this->assertIsArray( $stored );
		$cipher = $stored['binding']['credential'];
		$this->assertIsString( $cipher );
		$this->assertNotSame( $this->confirm_fixture['credential'], $cipher );
		$this->assertNull( $stored['binding']['challenge_id'] );

		$response = ( new BindingController( null, $module ) )->get_item( new WP_REST_Request() );
		$data     = $response->get_data();
		$this->assertSame( 'bound', $data['status'] );
		$this->assertArrayNotHasKey( 'credential', $data );
		$this->assertArrayNotHasKey( 'challenge_token', $data );
		$encoded = json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit assertion payload.
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( $this->confirm_fixture['credential'], $encoded );
		$this->assertStringNotContainsString( $this->start_fixture['challenge_token'], $encoded );
	}

	/**
	 * Logger sink never contains the fixture credential or challenge token.
	 */
	public function test_log_sink_does_not_leak_fixture_secrets() {
		$logger = new Logger(
			'debug',
			static function ( $level, $message, $context ) {
				BindingStore::$log_sink .= $level . ' ' . $message . ' ' . wp_json_encode( $context ) . "\n";
			}
		);
		$module = $this->module( $logger );

		$this->queue_json( $this->start_fixture );
		$module->start();
		$this->queue_json( $this->confirm_fixture );
		$module->confirm();

		BindingStore::$responses[] = new WP_Error( 'http_request_failed', 'timeout ' . $this->confirm_fixture['credential'] );
		$client                    = new ChallengeClient( $logger );
		$client->start(
			array(
				'site_url'       => 'https://example.test',
				'site_uuid'      => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
				'plugin_version' => '4.0.0',
			)
		);

		$sink = BindingStore::$log_sink;
		$this->assertStringNotContainsString( $this->confirm_fixture['credential'], $sink );
		$this->assertStringNotContainsString( $this->start_fixture['challenge_token'], $sink );
		$this->assertStringNotContainsString( 'challenge_token', $sink );
		$this->assertStringNotContainsString( 'credential', $sink );
	}

	/**
	 * Confirm writes Idempotency-Key; revoke clears the sealed credential.
	 */
	public function test_confirm_sends_idempotency_key_and_revoke_clears_credential() {
		$module = $this->module();
		$this->queue_json( $this->start_fixture );
		$module->start();
		$this->assertNotEmpty( BindingStore::$requests );
		$start_headers = BindingStore::$requests[0]['args']['headers'];
		$this->assertArrayHasKey( 'Idempotency-Key', $start_headers );
		$this->assertNotSame( '', $start_headers['Idempotency-Key'] );
		$this->assertStringContainsString( '/v1/site-connections', BindingStore::$requests[0]['url'] );
		$this->assertStringNotContainsString( '/v1/v1/', BindingStore::$requests[0]['url'] );

		$this->queue_json( $this->confirm_fixture );
		$module->confirm();
		$confirm_url = BindingStore::$requests[1]['url'];
		$this->assertStringContainsString( '/v1/site-connections/' . $this->start_fixture['challenge_id'] . '/confirm', $confirm_url );
		$this->assertArrayHasKey( 'Idempotency-Key', BindingStore::$requests[1]['args']['headers'] );

		$revoked = $module->revoke();
		$this->assertSame( 'revoked', $revoked['status'] );
		$stored = get_option( Schema::SITE_IDENTITY );
		$this->assertNull( $stored['binding']['credential'] );
		$this->assertNull( $stored['binding']['challenge_id'] );
	}

	/**
	 * Empty API and production host without a test flag do not auto-start.
	 */
	public function test_no_api_and_production_host_do_not_outbound() {
		BindingStore::$api = '';
		$module            = $this->module();
		$result            = $module->start();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_binding_unavailable', $result->get_error_code() );
		$this->assertSame( array(), BindingStore::$requests );

		$module->register();
		$this->assertSame( array(), BindingStore::$requests );

		BindingStore::$api = ChallengeClient::DEFAULT_API;
		$this->assertFalse( ChallengeClient::outbound_allowed() );
		$this->assertInstanceOf( WP_Error::class, $this->module()->start() );
		$this->assertSame( array(), BindingStore::$requests );
	}

	/**
	 * Site UUID is generated once and reused on the challenge body.
	 */
	public function test_site_uuid_is_stable_across_start() {
		$module = $this->module();
		$first  = ( new Repository() )->get_identity()['site_uuid'];
		$this->queue_json( $this->start_fixture );
		$module->start();
		$second = ( new Repository() )->get_identity()['site_uuid'];
		$this->assertSame( $first, $second );
		$this->assertSame( 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', $first );

		$body = json_decode( BindingStore::$requests[0]['args']['body'], true );
		$this->assertSame( $first, $body['site_uuid'] );
		$this->assertSame( BindingStore::$site_url, $body['site_url'] );
		$this->assertArrayHasKey( 'plugin_version', $body );
	}

	/**
	 * Module under test with a capturing logger.
	 *
	 * @param Logger|null $logger Logger.
	 * @return SiteBindingModule
	 */
	private function module( $logger = null ): SiteBindingModule {
		if ( ! $logger instanceof Logger ) {
			$logger = new Logger(
				'debug',
				static function ( $level, $message, $context ) {
					BindingStore::$log_sink .= $level . ' ' . $message . ' ' . wp_json_encode( $context ) . "\n";
				}
			);
		}
		return new SiteBindingModule( new Repository( $logger ), $logger );
	}

	/**
	 * Queue a 200 JSON body as the next HTTP response.
	 *
	 * @param array<string, mixed> $payload Body.
	 */
	private function queue_json( array $payload ): void {
		BindingStore::$responses[] = array(
			'code' => 200,
			'body' => wp_json_encode( $payload ),
		);
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
