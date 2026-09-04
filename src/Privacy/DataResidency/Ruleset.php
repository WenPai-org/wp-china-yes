<?php
/**
 * Signed data-residency host table (baseline + verified increments).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Privacy\DataResidency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the built-in ruleset JSON, verifies Ed25519, and matches hosts.
 *
 * TEST ONLY public key (M0). One test key, two kid values
 * (wpcy-ruleset-2026, wpcy-apps-2026). Production keys are not in this repository.
 */
final class Ruleset {

	/**
	 * TEST ONLY Ed25519 public key (Base64). Not a production key.
	 *
	 * One test key for kid values wpcy-ruleset-2026 and wpcy-apps-2026.
	 * Production keys are generated on feicode-prod (linuxjoy 定稿 §7.5b-3).
	 *
	 * @since 4.0.0
	 */
	public const TEST_PUBLIC_KEY = 'qwZGapsafpxFs2zFK4RZ+HHDEuZeP9ImXvi6T+YkMpQ=';

	/**
	 * TEST ONLY public keys by kid. Same key for both kids until production keys exist.
	 *
	 * @since 4.0.0
	 * @var array<string, string>
	 */
	private const KEYS_BY_KID = array(
		'wpcy-ruleset-2026' => self::TEST_PUBLIC_KEY,
		'wpcy-apps-2026'    => self::TEST_PUBLIC_KEY,
	);

	/**
	 * Path prefixes on api.wordpress.org that belong to tier A (M0 whitelist).
	 *
	 * Path-filter expression is 待定（M0）; until then only these three.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const API_WORDPRESS_ORG_PATH_PREFIXES = array(
		'/core/version-check',
		'/plugins/update-check',
		'/themes/update-check',
	);

	/**
	 * Absolute path of the loaded JSON file.
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * Base64 Ed25519 public key used to verify the payload.
	 *
	 * @var string
	 */
	private string $public_key;

	/**
	 * Decoded document without the signature field.
	 *
	 * @var array<string, mixed>
	 */
	private array $document = array();

	/**
	 * Whether the last load verified.
	 *
	 * @var bool
	 */
	private bool $verified = false;

	/**
	 * Whether load() should verify Ed25519. Tests may skip when the private key is absent.
	 *
	 * @var bool
	 */
	private bool $require_signature;

	/**
	 * Create a ruleset from a JSON file.
	 *
	 * @since 4.0.0
	 *
	 * @param string|null $path       JSON path. Null uses the shipped baseline.
	 * @param string|null $public_key Base64 public key. Null uses the test key.
	 * @param bool        $verify     When false, load JSON without Ed25519 (test-only).
	 */
	public function __construct( $path = null, $public_key = null, bool $verify = true ) {
		$this->path              = is_string( $path ) && '' !== $path
			? $path
			: dirname( __DIR__ ) . '/rulesets/baseline.json';
		$this->public_key        = is_string( $public_key ) && '' !== $public_key
			? $public_key
			: self::TEST_PUBLIC_KEY;
		$this->require_signature = $verify;
		$this->load();
	}

	/**
	 * Whether the loaded document passed Ed25519 verification.
	 *
	 * @since 4.0.0
	 */
	public function verified(): bool {
		return $this->verified;
	}

	/**
	 * Ruleset version from the loaded document, or 0 when empty.
	 *
	 * @since 4.0.0
	 */
	public function version(): int {
		return isset( $this->document['ruleset_version'] ) ? (int) $this->document['ruleset_version'] : 0;
	}

	/**
	 * First matching rule for $url, A then B then C, document order.
	 *
	 * @since 4.0.0
	 *
	 * @param string $url Request URL.
	 * @return array<string, mixed>|null Rule array, or null when none match.
	 */
	public function match( string $url ) {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return $this->first_wildcard();
		}

		$host = strtolower( (string) $parts['host'] );
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';

		foreach ( array( 'A', 'B', 'C' ) as $tier ) {
			$rules = isset( $this->document['tiers'][ $tier ] ) && is_array( $this->document['tiers'][ $tier ] )
				? $this->document['tiers'][ $tier ]
				: array();
			foreach ( $rules as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}
				if ( ! $this->host_matches( $host, $rule ) ) {
					continue;
				}
				if ( 'api.wordpress.org' === $host && ! $this->api_wordpress_org_path_allowed( $path ) ) {
					continue;
				}
				return $rule;
			}
		}

		return $this->first_wildcard();
	}

	/**
	 * Canonical JSON: UTF-8, dictionary-sorted keys, no extra whitespace.
	 *
	 * List arrays keep document order. Used as the Ed25519 message.
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
	 * Read, verify, and keep the document. Failed verify leaves an empty table.
	 */
	private function load(): void {
		if ( ! is_readable( $this->path ) ) {
			return;
		}

		$raw = file_get_contents( $this->path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local shipped ruleset, not a remote URL.
		if ( ! is_string( $raw ) || '' === $raw ) {
			return;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return;
		}

		$signature = isset( $decoded['signature'] ) && is_string( $decoded['signature'] ) ? $decoded['signature'] : '';
		unset( $decoded['signature'] );

		if ( ! $this->require_signature ) {
			$this->document = $decoded;
			$this->verified = false;
			return;
		}

		if ( '' === $signature || ! $this->verify( $decoded, $signature ) ) {
			return;
		}

		$this->document = $decoded;
		$this->verified = true;
	}

	/**
	 * Verify Ed25519 over the canonical payload.
	 *
	 * When the payload has a non-empty `kid`, the matching public key is used.
	 * Unknown kid fails closed. Missing kid uses the constructor public key.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $payload   Document without signature.
	 * @param string               $signature Base64 signature.
	 * @return bool
	 */
	private function verify( array $payload, string $signature ): bool {
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
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
	 * Public key for this payload: kid map when `kid` is present, else constructor key.
	 *
	 * @since 4.0.0
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

	/**
	 * Whether $host matches one rule.
	 *
	 * @param string               $host Request host (lowercase).
	 * @param array<string, mixed> $rule Rule.
	 */
	private function host_matches( string $host, array $rule ): bool {
		$needle = isset( $rule['host'] ) && is_string( $rule['host'] ) ? strtolower( $rule['host'] ) : '';
		if ( '' === $needle ) {
			return false;
		}
		if ( '*' === $needle ) {
			return true;
		}

		$match = isset( $rule['match'] ) && is_string( $rule['match'] ) ? $rule['match'] : 'exact';
		if ( 'suffix' === $match ) {
			return $host === $needle || substr( $host, -strlen( '.' . $needle ) ) === '.' . $needle;
		}

		return $host === $needle;
	}

	/**
	 * A-tier api.wordpress.org is only version-check / update-check.
	 *
	 * @param string $path URL path.
	 */
	private function api_wordpress_org_path_allowed( string $path ): bool {
		foreach ( self::API_WORDPRESS_ORG_PATH_PREFIXES as $prefix ) {
			if ( 0 === strpos( $path, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * C-tier catch-all when present.
	 *
	 * @return array<string, mixed>|null
	 */
	private function first_wildcard() {
		$rules = isset( $this->document['tiers']['C'] ) && is_array( $this->document['tiers']['C'] )
			? $this->document['tiers']['C']
			: array();
		foreach ( $rules as $rule ) {
			if ( is_array( $rule ) && isset( $rule['host'] ) && '*' === $rule['host'] ) {
				return $rule;
			}
		}
		return null;
	}
}
