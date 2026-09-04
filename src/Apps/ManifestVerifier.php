<?php
/**
 * Ed25519 detached verification for app manifests and the apps index.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Apps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical JSON (spec §1.3) plus sodium_crypto_sign_verify_detached.
 *
 * TEST ONLY public key until M3. Production private keys are not in this repository.
 */
final class ManifestVerifier {

	/**
	 * TEST ONLY Ed25519 public key (Base64). Same material as tests/fixtures/keys/.
	 *
	 * @since 4.0.0
	 */
	public const TEST_PUBLIC_KEY = 'qwZGapsafpxFs2zFK4RZ+HHDEuZeP9ImXvi6T+YkMpQ=';

	/**
	 * Allowed origin host suffixes. HTTPS required.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const HOST_SUFFIXES = array(
		'wpcy.com',
		'wenpai.net',
	);

	/**
	 * TEST ONLY keys by kid. Same key until production keys exist.
	 *
	 * @since 4.0.0
	 * @var array<string, string>
	 */
	private const KEYS_BY_KID = array(
		'wpcy-apps-2026' => self::TEST_PUBLIC_KEY,
	);

	/**
	 * Base64 public key used when the payload has no kid.
	 *
	 * @var string
	 */
	private string $public_key;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param string|null $public_key Base64 public key. Null uses the TEST ONLY key.
	 */
	public function __construct( $public_key = null ) {
		$this->public_key = is_string( $public_key ) && '' !== $public_key
			? $public_key
			: self::TEST_PUBLIC_KEY;
	}

	/**
	 * Verify a decoded JSON object that carries a Base64 `signature` field.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $document Decoded JSON.
	 */
	public function verify( $document ): bool {
		if ( ! is_array( $document ) || self::is_list( $document ) ) {
			return false;
		}

		$signature = isset( $document['signature'] ) && is_string( $document['signature'] )
			? $document['signature']
			: '';
		unset( $document['signature'] );

		return $this->verify_payload( $document, $signature );
	}

	/**
	 * Verify Ed25519 over canonical JSON without the signature field.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $payload   Document without signature.
	 * @param string               $signature Base64 detached signature.
	 */
	public function verify_payload( array $payload, string $signature ): bool {
		if ( '' === $signature || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return false;
		}

		$public_key = $this->resolve_public_key( $payload );
		if ( '' === $public_key ) {
			return false;
		}

		try {
			$pk  = sodium_base642bin( $public_key, SODIUM_BASE64_VARIANT_ORIGINAL );
			$sig = sodium_base642bin( $signature, SODIUM_BASE64_VARIANT_ORIGINAL );
		} catch ( \SodiumException $e ) {
			unset( $e );
			return false;
		}

		$message = self::canonicalize( $payload );
		if ( '' === $message || SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $pk ) ) {
			return false;
		}

		return sodium_crypto_sign_verify_detached( $sig, $message, $pk );
	}

	/**
	 * Canonical JSON: UTF-8, dictionary-sorted keys, no extra whitespace.
	 *
	 * Same rules as scripts/sign-ruleset.php / spec §1.3. List arrays keep order.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $value Decoded JSON value.
	 */
	public static function canonicalize( $value ): string {
		$sorted = self::sort_keys( $value );
		$flags  = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		if ( defined( 'JSON_THROW_ON_ERROR' ) ) {
			$flags |= JSON_THROW_ON_ERROR;
		}
		$encoded = json_encode( $sorted, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- canonical bytes must not go through wp_json_encode filters.
		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * Whether $url is HTTPS with a host suffix in the allowlist.
	 *
	 * @since 4.0.0
	 *
	 * @param string $url Absolute URL.
	 */
	public static function origin_allowed( string $url ): bool {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		if ( 'https' !== $scheme || '' === $host ) {
			return false;
		}

		foreach ( self::HOST_SUFFIXES as $root ) {
			if ( $host === $root || substr( $host, -strlen( '.' . $root ) ) === '.' . $root ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively sort object keys; leave JSON arrays in order.
	 *
	 * @param mixed $value Node.
	 * @return mixed
	 */
	private static function sort_keys( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( self::is_list( $value ) ) {
			$out = array();
			foreach ( $value as $item ) {
				$out[] = self::sort_keys( $item );
			}
			return $out;
		}

		ksort( $value, SORT_STRING );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::sort_keys( $item );
		}
		return $value;
	}

	/**
	 * PHP 7.4 stand-in for array_is_list().
	 *
	 * @param array<mixed> $value Array.
	 */
	private static function is_list( array $value ): bool {
		if ( array() === $value ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Public key for this payload: kid map when `kid` is present, else constructor key.
	 *
	 * @param array<string, mixed> $payload Document without signature.
	 * @return string Base64 public key, or empty when kid is unknown.
	 */
	private function resolve_public_key( array $payload ): string {
		if ( ! isset( $payload['kid'] ) || ! is_string( $payload['kid'] ) || '' === $payload['kid'] ) {
			return $this->public_key;
		}

		if ( ! isset( self::KEYS_BY_KID[ $payload['kid'] ] ) ) {
			return '';
		}

		return self::KEYS_BY_KID[ $payload['kid'] ];
	}
}
