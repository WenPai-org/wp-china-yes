<?php
/**
 * Ed25519 helper for Admin unit tests. Signs with the throwaway key from TestKeys.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use WenPai\ChinaYes\Apps\ManifestVerifier;
use WenPai\ChinaYes\Tests\Unit\Support\TestKeys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sign a decoded JSON object with the per-process TEST ONLY Ed25519 key.
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
	 * Verifier that trusts the throwaway key for every known kid.
	 *
	 * @since 4.0.0
	 */
	public static function verifier(): ManifestVerifier {
		return new ManifestVerifier( TestKeys::public_key(), TestKeys::keys_by_kid() );
	}

	/**
	 * Throwaway secret key bytes, generated at test runtime.
	 */
	private static function secret(): string {
		return TestKeys::secret();
	}
}
