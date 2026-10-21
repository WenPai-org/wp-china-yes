<?php
/**
 * Apps fixtures re-signed with the throwaway runtime key.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Apps;

use WenPai\ChinaYes\Apps\ManifestVerifier;
use WenPai\ChinaYes\Tests\Unit\Support\TestKeys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signs with TestKeys so tests do not depend on any built-in key.
 */
final class SignedIndexFixture {

	/**
	 * Add a TestKeys signature.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $doc Payload without signature.
	 * @return array<string, mixed>
	 */
	public static function sign( array $doc ): array {
		unset( $doc['signature'] );
		$raw              = sodium_crypto_sign_detached( ManifestVerifier::canonicalize( $doc ), TestKeys::secret() );
		$doc['signature'] = sodium_bin2base64( $raw, SODIUM_BASE64_VARIANT_ORIGINAL );
		return $doc;
	}

	/**
	 * Tests/fixtures/apps/index.valid.json with the index and every manifest re-signed.
	 *
	 * @since 4.0.0
	 */
	public static function index_json(): string {
		$raw   = file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/apps/index.valid.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$index = json_decode( (string) $raw, true );
		if ( ! is_array( $index ) ) {
			return '';
		}
		foreach ( $index['apps'] as $i => $manifest ) {
			$index['apps'][ $i ] = self::sign( $manifest );
		}
		return (string) json_encode( self::sign( $index ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test payload.
	}

	/**
	 * Index_json() with the index signature replaced by a valid-length wrong one.
	 *
	 * @since 4.0.0
	 */
	public static function badly_signed_index_json(): string {
		$index              = json_decode( self::index_json(), true );
		$index['signature'] = sodium_bin2base64( str_repeat( "\x01", SODIUM_CRYPTO_SIGN_BYTES ), SODIUM_BASE64_VARIANT_ORIGINAL );
		return (string) json_encode( $index, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test payload.
	}

	/**
	 * Verifier trusting the throwaway key (kid map and payloads without kid).
	 *
	 * @since 4.0.0
	 */
	public static function verifier(): ManifestVerifier {
		return new ManifestVerifier( TestKeys::public_key(), TestKeys::keys_by_kid() );
	}

	/**
	 * Verifier trusting a different, freshly generated key.
	 *
	 * @since 4.0.0
	 */
	public static function other_verifier(): ManifestVerifier {
		$pk = sodium_bin2base64( sodium_crypto_sign_publickey( sodium_crypto_sign_keypair() ), SODIUM_BASE64_VARIANT_ORIGINAL );
		return new ManifestVerifier( $pk, array_fill_keys( TestKeys::KIDS, $pk ) );
	}
}
