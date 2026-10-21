<?php
/**
 * Public key that signed the committed fixtures, for test mode only.
 *
 * The signed fixtures under tests/fixtures (apps, mock-app, element-hide,
 * notice-rules, announcements) and the shipped ruleset baseline carry
 * precomputed signatures made with the TEST ONLY key whose public half is
 * tests/fixtures/keys/wpcy-test-ed25519.pub. Production code does not trust
 * that key: src/ has no copy of it and no switch that loads it. A test opts in
 * by passing these values to the verifier or ruleset constructor.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Support;

use WenPai\ChinaYes\Apps\ManifestVerifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TEST ONLY fixture public key, read from tests/fixtures/keys.
 */
final class LegacyFixtureKey {

	/**
	 * Path of the TEST ONLY public key file.
	 *
	 * @since 4.0.0
	 */
	public static function path(): string {
		return dirname( __DIR__, 2 ) . '/fixtures/keys/wpcy-test-ed25519.pub';
	}

	/**
	 * Base64 public key (first non-comment line of the key file).
	 *
	 * @since 4.0.0
	 */
	public static function public_key(): string {
		$lines = file( self::path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( false === $lines ) {
			return '';
		}
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line && '#' !== $line[0] ) {
				return $line;
			}
		}
		return '';
	}

	/**
	 * Kid map pointing every known kid at the fixture public key.
	 *
	 * @since 4.0.0
	 * @return array<string, string>
	 */
	public static function keys_by_kid(): array {
		return array_fill_keys( TestKeys::KIDS, self::public_key() );
	}

	/**
	 * Verifier in test mode: trusts the fixture key for every kid and for payloads without a kid.
	 *
	 * @since 4.0.0
	 */
	public static function verifier(): ManifestVerifier {
		return new ManifestVerifier( self::public_key(), self::keys_by_kid() );
	}
}
