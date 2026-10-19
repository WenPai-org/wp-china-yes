<?php
/**
 * Ed25519 helper for Admin unit tests. TEST ONLY key material.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use WenPai\ChinaYes\Apps\ManifestVerifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sign a decoded JSON object with the TEST ONLY Ed25519 key.
 */
final class SignedPayload {

	/**
	 * Default kid shared with ManifestVerifier.
	 *
	 * @since 4.0.0
	 */
	public const KID = 'wpcy-apps-2026';

	/**
	 * Encode $document with a valid detached signature.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $document Payload without signature.
	 * @param string               $kid      Key id.
	 */
	public static function encode( array $document, string $kid = self::KID ): string {
		$signed = self::sign( $document, $kid );
		$json   = wp_json_encode( $signed );
		return is_string( $json ) ? $json : '';
	}

	/**
	 * Return $document plus kid and signature.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $document Payload without signature.
	 * @param string               $kid      Key id.
	 * @return array<string, mixed>
	 */
	public static function sign( array $document, string $kid = self::KID ): array {
		unset( $document['signature'] );
		$document['kid'] = $kid;

		$secret  = self::secret();
		$message = ManifestVerifier::canonicalize( $document );
		$raw     = sodium_crypto_sign_detached( $message, $secret );

		$document['signature'] = sodium_bin2base64( $raw, SODIUM_BASE64_VARIANT_ORIGINAL );
		return $document;
	}

	/**
	 * Tamper the signature bytes of an already-signed document.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $document Signed payload.
	 * @return array<string, mixed>
	 */
	public static function corrupt( array $document ): array {
		$sig                   = isset( $document['signature'] ) && is_string( $document['signature'] )
			? $document['signature']
			: '';
		$document['signature'] = '' === $sig ? 'AAAA' : strrev( $sig );
		return $document;
	}

	/**
	 * TEST ONLY secret key bytes.
	 */
	private static function secret(): string {
		$path  = dirname( __DIR__, 2 ) . '/fixtures/keys/wpcy-test-ed25519.key';
		$lines = file( $path, FILE_IGNORE_NEW_LINES );
		if ( ! is_array( $lines ) ) {
			return '';
		}

		$b64 = '';
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$b64 = $line;
			break;
		}

		try {
			return sodium_base642bin( $b64, SODIUM_BASE64_VARIANT_ORIGINAL );
		} catch ( \SodiumException $e ) {
			unset( $e );
			return '';
		}
	}
}
