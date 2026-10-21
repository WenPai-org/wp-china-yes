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
	 * TEST ONLY Ed25519 public key (Base64). Public half is tests/fixtures/keys/wpcy-test-ed25519.pub;
	 * the private half is not in this repository.
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
		'wpcy-apps-2026'    => self::TEST_PUBLIC_KEY,
		'wpcy-ruleset-2026' => self::TEST_PUBLIC_KEY,
	);

	/**
	 * Base64 public key used when the payload has no kid.
	 *
	 * @var string
	 */
	private string $public_key;

	/**
	 * Base64 public keys by kid for this instance.
	 *
	 * @var array<string, string>
	 */
	private array $keys_by_kid;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param string|null                $public_key  Base64 public key. Null uses the TEST ONLY key.
	 * @param array<string, string>|null $keys_by_kid Kid to Base64 public key map. Null uses the built-in map.
	 */
	public function __construct( $public_key = null, ?array $keys_by_kid = null ) {
		$this->public_key  = is_string( $public_key ) && '' !== $public_key
			? $public_key
			: self::TEST_PUBLIC_KEY;
		$this->keys_by_kid = is_array( $keys_by_kid ) ? $keys_by_kid : self::KEYS_BY_KID;
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
		if ( '' === $message
			|| SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $pk )
			|| SODIUM_CRYPTO_SIGN_BYTES !== strlen( $sig ) ) {
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
	 * @return string Canonical bytes, or '' when $value cannot be encoded.
	 */
	public static function canonicalize( $value ): string {
		$sorted = self::sort_keys( $value );
		$flags  = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		if ( defined( 'JSON_THROW_ON_ERROR' ) ) {
			$flags |= JSON_THROW_ON_ERROR;
		}
		try {
			$encoded = json_encode( $sorted, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- canonical bytes must not go through wp_json_encode filters.
		} catch ( \JsonException $e ) {
			// Not encodable as canonical JSON (INF/NaN such as a decoded 1e999,
			// malformed UTF-8, nesting deeper than 512). Such a payload cannot
			// carry a valid signature; '' makes verification fail closed.
			unset( $e );
			return '';
		}
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

		if ( ! isset( $this->keys_by_kid[ $payload['kid'] ] ) ) {
			return '';
		}

		return $this->keys_by_kid[ $payload['kid'] ];
	}

	/**
	 * Short fingerprint of the public keys this instance trusts.
	 *
	 * Covers the key for payloads without a kid and the whole kid map, so it
	 * changes whenever any trusted key changes. Caches of verified documents
	 * include it in their key: content verified under other keys is never read.
	 *
	 * @since 4.0.0
	 *
	 * @return string 16 lowercase hex characters.
	 */
	public function trust_fingerprint(): string {
		$keys = $this->keys_by_kid;
		ksort( $keys, SORT_STRING );
		$material = "wpcy-trust-v1\n*=" . $this->public_key . "\n";
		foreach ( $keys as $kid => $key ) {
			$material .= $kid . '=' . $key . "\n";
		}
		return substr( hash( 'sha256', $material ), 0, 16 );
	}
}
