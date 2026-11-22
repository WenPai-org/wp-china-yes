<?php
/**
 * Unit tests for the `wenpai_sign_cloud_request` derived bearer token filter.
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
use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;

require_once __DIR__ . '/wp-binding-stubs.php';

/**
 * Verifies short-lived HMAC-SHA256 cloud signing without exposing raw site credentials.
 */
class CloudRequestSigningTest extends TestCase {

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
	 * Reset in-memory stores and load mock-license fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		OptionStore::reset();
		BindingStore::reset();

		$this->start_fixture   = $this->load_fixture( 'challenge-start.json' );
		$this->confirm_fixture = $this->load_fixture( 'challenge-confirm.json' );
		BindingStore::$api     = 'https://mock.wpcy.test/v1';
	}

	/**
	 * Registering SiteBindingModule wires `wenpai_sign_cloud_request` and derives a verifiable short-lived token.
	 */
	public function test_derives_verifiable_short_lived_token_for_allowed_scopes(): void {
		$module = $this->bind_site();
		$module->register();

		$this->assertArrayHasKey( 'wenpai_site_binding', RestStore::$hooks );
		$this->assertArrayHasKey( 'wenpai_sign_cloud_request', RestStore::$hooks );

		$raw_credential = $this->confirm_fixture['credential'];
		$target         = 'https://mind-api.wenpai.net/v1';

		foreach ( array( 'mind:gateway', 'wpmind:gateway' ) as $scope ) {
			$before = time();
			$token  = apply_filters(
				'wenpai_sign_cloud_request',
				'',
				array(
					'scope'  => $scope,
					'target' => $target,
				)
			);
			$after  = time();

			$this->assertIsString( $token );
			$this->assertNotSame( '', $token );
			$this->assertNotSame( $raw_credential, $token );
			$this->assertStringNotContainsString( $raw_credential, $token );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- asserting raw credential is not base64-embedded.
			$this->assertStringNotContainsString( base64_encode( $raw_credential ), $token );

			$parts = explode( '.', $token );
			$this->assertCount( 3, $parts );
			$this->assertSame( 'v1', $parts[0] );

			$payload_json = self::base64url_decode( $parts[1] );
			$this->assertIsString( $payload_json );
			$payload = json_decode( $payload_json, true );
			$this->assertIsArray( $payload );

			$this->assertSame( 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', $payload['site_uuid'] );
			$this->assertSame( $this->confirm_fixture['site_hash'], $payload['site_hash'] );
			$this->assertSame( $scope, $payload['scope'] );
			$this->assertSame( $target, $payload['target'] );
			$this->assertIsInt( $payload['iat'] );
			$this->assertIsInt( $payload['exp'] );
			$this->assertGreaterThanOrEqual( $before, $payload['iat'] );
			$this->assertLessThanOrEqual( $after, $payload['iat'] );
			$this->assertLessThanOrEqual( $payload['iat'] + SiteBindingModule::CLOUD_SIGN_TTL, $payload['exp'] );
			$this->assertSame( $payload['iat'] + 300, $payload['exp'] );
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', (string) $payload['nonce'] );

			$actual_sig   = self::base64url_decode( $parts[2] );
			$secret       = hash_hmac( 'sha256', SiteBindingModule::CLOUD_SIGN_KDF_PREFIX . $scope, $raw_credential, true );
			$expected_sig = hash_hmac( 'sha256', 'v1.' . $parts[1], $secret, true );
			$this->assertIsString( $actual_sig );
			$this->assertTrue( hash_equals( $expected_sig, $actual_sig ) );

			$wrong_secret = hash_hmac( 'sha256', SiteBindingModule::CLOUD_SIGN_KDF_PREFIX . $scope, 'wrong-credential', true );
			$wrong_sig    = hash_hmac( 'sha256', 'v1.' . $parts[1], $wrong_secret, true );
			$this->assertFalse( hash_equals( $wrong_sig, $actual_sig ) );
		}

		// Consecutive calls generate distinct nonces and tokens.
		$first  = $module->filter_sign_cloud_request(
			'',
			array(
				'scope'  => 'mind:gateway',
				'target' => $target,
			)
		);
		$second = $module->filter_sign_cloud_request(
			'',
			array(
				'scope'  => 'mind:gateway',
				'target' => $target,
			)
		);
		$this->assertNotSame( $first, $second );
		$this->assertStringNotContainsString( $raw_credential, BindingStore::$log_sink );
	}

	/**
	 * Unbound, pending, revoked, or corrupted credentials return the input token unchanged.
	 */
	public function test_rejects_unbound_pending_revoked_and_corrupted_binding(): void {
		$context = array(
			'scope'  => 'mind:gateway',
			'target' => 'https://mind-api.wenpai.net/v1',
		);

		$unbound = $this->module();
		$this->assertSame( '', $unbound->filter_sign_cloud_request( '', $context ) );

		// Pending state must not sign.
		$this->queue_json( $this->start_fixture );
		$unbound->start();
		$this->assertSame( 'pending', $unbound->snapshot()['status'] );
		$this->assertSame( '', $unbound->filter_sign_cloud_request( '', $context ) );

		// Confirm to bound, then corrupt the sealed credential ciphertext.
		$this->queue_json( $this->confirm_fixture );
		$unbound->confirm();
		$identity                          = get_option( Schema::SITE_IDENTITY );
		$identity['binding']['credential'] = 'not-valid-sodium-ciphertext';
		update_option( Schema::SITE_IDENTITY, $identity );
		$this->assertSame( '', $unbound->filter_sign_cloud_request( '', $context ) );

		// Revoked state must not sign.
		$unbound->revoke();
		$this->assertSame( '', $unbound->filter_sign_cloud_request( '', $context ) );
	}

	/**
	 * Unknown scopes, non-HTTPS URLs, and untrusted hosts are rejected; allowlist filter opens custom HTTPS hosts only.
	 */
	public function test_rejects_invalid_scope_and_untrusted_targets_and_honors_host_filter(): void {
		$module = $this->bind_site();

		// Invalid scopes or malformed context.
		$this->assertSame(
			'',
			$module->filter_sign_cloud_request(
				'',
				array(
					'scope'  => 'admin:all',
					'target' => 'https://mind-api.wenpai.net/v1',
				)
			)
		);
		$this->assertSame( '', $module->filter_sign_cloud_request( '', array( 'target' => 'https://mind-api.wenpai.net/v1' ) ) );
		$this->assertSame( '', $module->filter_sign_cloud_request( '', 'invalid-context' ) );

		// Non-HTTPS, untrusted hosts, embedded credentials, and empty targets.
		$bad_targets = array(
			'',
			'not-a-url',
			'http://mind-api.wenpai.net/v1',
			'https://evil.example.com/v1',
			'https://mind-api.wenpai.net.evil.com/v1',
			'https://user:pass@mind-api.wenpai.net/v1',
		);
		foreach ( $bad_targets as $bad_target ) {
			$this->assertSame(
				'',
				$module->filter_sign_cloud_request(
					'',
					array(
						'scope'  => 'mind:gateway',
						'target' => $bad_target,
					)
				),
				'Expected target to be rejected: ' . $bad_target
			);
		}

		// Custom host allowed via `wpcy_cloud_sign_allowed_hosts` filter (HTTPS only).
		add_filter(
			'wpcy_cloud_sign_allowed_hosts',
			static function ( $hosts ) {
				$hosts[] = 'staging-mind.wenpai.test';
				return $hosts;
			}
		);

		$custom_https = $module->filter_sign_cloud_request(
			'',
			array(
				'scope'  => 'mind:gateway',
				'target' => 'https://staging-mind.wenpai.test/v1',
			)
		);
		$this->assertStringStartsWith( 'v1.', (string) $custom_https );

		$custom_http = $module->filter_sign_cloud_request(
			'',
			array(
				'scope'  => 'mind:gateway',
				'target' => 'http://staging-mind.wenpai.test/v1',
			)
		);
		$this->assertSame( '', $custom_http );
	}

	/**
	 * Existing non-empty token strings pass through untouched.
	 */
	public function test_non_empty_incoming_token_passes_through_untouched(): void {
		$module = $this->bind_site();
		$result = $module->filter_sign_cloud_request(
			'existing-caller-token',
			array(
				'scope'  => 'mind:gateway',
				'target' => 'https://mind-api.wenpai.net/v1',
			)
		);
		$this->assertSame( 'existing-caller-token', $result );
	}

	/**
	 * Create a bound SiteBindingModule instance.
	 *
	 * @return SiteBindingModule
	 */
	private function bind_site(): SiteBindingModule {
		$module = $this->module();
		$this->queue_json( $this->start_fixture );
		$this->queue_json( $this->confirm_fixture );
		$started = $module->start();
		$this->assertIsArray( $started );
		$this->assertSame( 'bound', $started['status'] );
		return $module;
	}

	/**
	 * Build module under test with a capturing logger.
	 *
	 * @return SiteBindingModule
	 */
	private function module(): SiteBindingModule {
		$logger = new Logger(
			'debug',
			static function ( $level, $message, $context ) {
				BindingStore::$log_sink .= $level . ' ' . $message . ' ' . wp_json_encode( $context ) . "\n";
			}
		);
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

	/**
	 * Decode unpadded URL-safe Base64.
	 *
	 * @param string $encoded Segment.
	 * @return string|false
	 */
	private static function base64url_decode( string $encoded ) {
		$padded = strtr( $encoded, '-_', '+/' );
		$mod    = strlen( $padded ) % 4;
		if ( $mod > 0 ) {
			$padded .= str_repeat( '=', 4 - $mod );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- inverse of URL-safe base64 token encoding in unit test.
		return base64_decode( $padded, true );
	}
}
