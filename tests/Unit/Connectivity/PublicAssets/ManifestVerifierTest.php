<?php
/**
 * M1 Golden Fixtures and Ed25519 manifest verification tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\PublicAssets;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\PublicAssets\Manifest;
use WenPai\ChinaYes\Connectivity\PublicAssets\ManifestVerifier;

require_once dirname( __DIR__ ) . '/wp-hook-stubs.php';

/**
 * Verifies canonical JSON, positive/negative M1 fixtures, keyring bounds, and sub-second TTL.
 */
class ManifestVerifierTest extends TestCase {

	private const EVAL_NOW = '2026-11-26T08:05:00Z';

	/**
	 * Load fixture directory root.
	 */
	private function fixture_dir(): string {
		return dirname( __DIR__, 3 ) . '/fixtures/manifest';
	}

	/**
	 * Load keyring decoded in object mode.
	 *
	 * @return \stdClass
	 */
	private function load_keyring(): \stdClass {
		$raw = file_get_contents( $this->fixture_dir() . '/keyring.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture.
		$this->assertNotFalse( $raw );
		$doc = json_decode( (string) $raw, false );
		$this->assertInstanceOf( \stdClass::class, $doc );
		return $doc;
	}

	/**
	 * All canonical-vectors.json cases match byte-for-byte canonical JSON.
	 */
	public function test_canonical_vectors_match_m1_spec(): void {
		$raw = file_get_contents( $this->fixture_dir() . '/canonical-vectors.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture.
		$this->assertNotFalse( $raw );
		$vectors = json_decode( (string) $raw, false );
		$this->assertIsObject( $vectors );
		$vars = get_object_vars( $vectors );
		$this->assertNotEmpty( $vars['vectors'] );

		foreach ( $vars['vectors'] as $vec ) {
			$vec_vars = get_object_vars( $vec );
			$input    = clone $vec_vars['input'];
			unset( $input->sig );
			$actual = ManifestVerifier::canonical_json( $input );
			$this->assertSame( $vec_vars['expectedCanonical'], $actual, 'Canonical vector mismatch for ' . $vec_vars['name'] );
			if ( isset( $vec_vars['expectedSha256'] ) ) {
				$this->assertSame( $vec_vars['expectedSha256'], hash( 'sha256', $actual ), 'SHA256 mismatch for ' . $vec_vars['name'] );
			}
		}
	}

	/**
	 * Valid active and retiring signed fixtures verify against keyring.json and Manifest::verify().
	 */
	public function test_valid_and_retiring_key_fixtures_verify(): void {
		$keyring_obj = $this->load_keyring();
		$keyring_arr = json_decode( (string) file_get_contents( $this->fixture_dir() . '/keyring.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture.

		foreach ( array( 'mirror-manifest.valid.json', 'mirror-manifest.retiring-key.valid.json' ) as $file ) {
			$doc = json_decode( (string) file_get_contents( $this->fixture_dir() . '/' . $file ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture.
			$res = ManifestVerifier::verify_detailed( $doc, $keyring_obj, self::EVAL_NOW, true );
			$this->assertTrue( $res['ok'], $file . ': ' . $res['code'] . ' - ' . $res['detail'] );
			$this->assertSame( 'ok', $res['code'] );

			// Also verify T4-S05 static signature call without eval_now ($keyring['keys'] assoc array).
			$this->assertTrue( Manifest::verify( $doc, $keyring_arr['keys'] ) );
		}
	}

	/**
	 * All 9 negative M1 manifest fixtures fail with the expected deterministic code.
	 */
	public function test_all_nine_negative_fixtures_fail_with_expected_code(): void {
		$keyring = $this->load_keyring();
		$cases   = array(
			'mirror-manifest.tampered.json'             => 'invalid_signature',
			'mirror-manifest.unknown-key.json'          => 'unknown_key',
			'mirror-manifest.revoked-key.json'          => 'revoked_key',
			'mirror-manifest.expired.json'              => 'expired',
			'mirror-manifest.malformed-sig-prefix.json' => 'malformed_sig',
			'mirror-manifest.malformed-sig-b64.json'    => 'malformed_sig',
			'mirror-manifest.malformed-sig-length.json' => 'malformed_sig',
			'mirror-manifest.bitflipped-sig.json'       => 'invalid_signature',
			'mirror-manifest.missing-keyid.json'        => 'missing_keyid',
		);

		foreach ( $cases as $file => $expected_code ) {
			$doc = json_decode( (string) file_get_contents( $this->fixture_dir() . '/negative/' . $file ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture.
			$res = ManifestVerifier::verify_detailed( $doc, $keyring, self::EVAL_NOW, true );
			$this->assertFalse( $res['ok'], $file . ' unexpectedly succeeded' );
			$this->assertSame( $expected_code, $res['code'], $file . ' returned wrong error code' );
		}
	}

	/**
	 * Reject empty keyId, key outside validity window, invalid calendar timestamps, and test sub-second TTL boundaries.
	 */
	public function test_calendar_bounds_key_window_and_subsecond_ttl_boundaries(): void {
		$keyring  = $this->load_keyring();
		$manifest = json_decode( (string) file_get_contents( $this->fixture_dir() . '/mirror-manifest.valid.json' ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture.

		$empty_kid        = clone $manifest;
		$empty_kid->keyId = ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- JSON fixture schema field.
		$this->assertSame( 'missing_keyid', ManifestVerifier::verify_detailed( $empty_kid, $keyring, self::EVAL_NOW, true )['code'] );

		$future_eval = ManifestVerifier::verify_detailed( $manifest, $keyring, '2028-01-01T00:00:00Z', true );
		$this->assertFalse( $future_eval['ok'] );
		$this->assertSame( 'key_outside_window', $future_eval['code'] );

		foreach ( array(
			'2026-02-30T00:00:00Z',
			'2026-13-01T00:00:00Z',
			'2026-11-26T25:00:00Z',
			'2026-11-26T08:05:00.Z',
			'2025-02-29T00:00:00Z',
			'2026-04-31T00:00:00Z',
			'2026-11-26T08:05:60Z',
		) as $bad_ts ) {
			$this->assertNull( ManifestVerifier::parse_utc_timestamp( $bad_ts ), 'Expected null for ' . $bad_ts );
		}

		$this->assertSame( 0, ManifestVerifier::parse_utc_timestamp( '2024-02-29T00:00:00Z' )[1] );
		$this->assertSame( 100000000, ManifestVerifier::parse_utc_timestamp( '2026-11-26T08:00:00.100Z' )[1] );
		$this->assertSame( 123456789, ManifestVerifier::parse_utc_timestamp( '2026-11-26T08:00:00.1234567899Z' )[1] );

		// wpstatic schema redline: reject non-wpstatic primary or non-empty clientFallbacks.
		$bad_wpstatic = json_decode( (string) file_get_contents( $this->fixture_dir() . '/mirror-manifest.valid.json' ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture.
		$this->assertIsObject( $bad_wpstatic );
		$bad_wpstatic->classes->wpstatic->primary = 'https://evil-3p.example/{path}';
		$bad_wp_res                               = ManifestVerifier::verify_detailed( $bad_wpstatic, $keyring, self::EVAL_NOW, true );
		$this->assertFalse( $bad_wp_res['ok'] );
		$this->assertSame( 'invalid_schema', $bad_wp_res['code'] );

		$bad_wpstatic_fb = json_decode( (string) file_get_contents( $this->fixture_dir() . '/mirror-manifest.valid.json' ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture.
		$this->assertIsObject( $bad_wpstatic_fb );
		$bad_wpstatic_fb->classes->wpstatic->clientFallbacks = array( 'https://s.w.org/{path}' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- JSON fixture schema field.
		$bad_fb_res = ManifestVerifier::verify_detailed( $bad_wpstatic_fb, $keyring, self::EVAL_NOW, true );
		$this->assertFalse( $bad_fb_res['ok'] );
		$this->assertSame( 'invalid_schema', $bad_fb_res['code'] );

		// Re-sign sub-second manifest using test seed from keyring.json.
		$key_vars = get_object_vars( get_object_vars( $keyring )['keys'] );
		$active   = get_object_vars( $key_vars['admincdn-test-2026-11'] );
		$seed     = sodium_base642bin( (string) $active['DO_NOT_USE_IN_PROD_testPrivateKeyB64'], SODIUM_BASE64_VARIANT_ORIGINAL );
		$kp       = sodium_crypto_sign_seed_keypair( $seed );
		$sk       = sodium_crypto_sign_secretkey( $kp );

		$subsec           = clone $manifest;
		$subsec->issuedAt = '2026-11-26T08:00:00.100Z'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- JSON fixture schema field.
		$subsec->ttl      = 300;
		unset( $subsec->sig );
		$subsec->sig = ManifestVerifier::SIG_PREFIX . sodium_bin2base64(
			sodium_crypto_sign_detached( ManifestVerifier::canonical_json( $subsec ), $sk ),
			SODIUM_BASE64_VARIANT_ORIGINAL
		);

		$this->assertTrue( ManifestVerifier::verify( $subsec, $keyring, '2026-11-26T08:05:00.050Z', true ) );
		$this->assertTrue( ManifestVerifier::verify( $subsec, $keyring, '2026-11-26T08:05:00.100Z', true ) );
		$expired_res = ManifestVerifier::verify_detailed( $subsec, $keyring, '2026-11-26T08:05:00.500Z', true );
		$this->assertFalse( $expired_res['ok'] );
		$this->assertSame( 'expired', $expired_res['code'] );
	}
}
