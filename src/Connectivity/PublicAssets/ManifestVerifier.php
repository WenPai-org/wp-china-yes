<?php
/**
 * Ed25519 signature and schema verifier for admincdn.mirror-manifest/v1.
 *
 * Strictly aligned with admincdn M1 golden verifier (canonical UTF-8 JSON,
 * Gregorian UTC calendar bounds, sub-second TTL comparisons, and keyring window checks).
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\PublicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies signed admincdn mirror manifests fail-closed.
 */
final class ManifestVerifier {

	/**
	 * Supported manifest schema identifier.
	 */
	public const SCHEMA = 'admincdn.mirror-manifest/v1';

	/**
	 * Detached Ed25519 signature prefix.
	 */
	public const SIG_PREFIX = 'ed25519:';

	/**
	 * Maximum allowed clock skew in seconds for future issuedAt timestamps.
	 */
	public const MAX_FUTURE_SKEW_SECONDS = 300;

	/**
	 * Allowed class health states.
	 *
	 * @var list<string>
	 */
	public const HEALTH_STATES = array( 'ok', 'degraded', 'down' );

	/**
	 * Verify a decoded manifest and return a structured result.
	 *
	 * @since 4.1.0
	 *
	 * @param mixed       $payload     Decoded JSON object (stdClass recommended) or map.
	 * @param mixed       $keyring     Keyring object/map or `keys` map (`keyId => entry`).
	 * @param string|null $eval_now    Optional UTC RFC3339 evaluation timestamp.
	 * @param bool        $enforce_ttl Whether to enforce issuedAt + ttl expiry against eval_now/current UTC.
	 * @return array{ok: bool, code: string, detail: string}
	 */
	public static function verify_detailed( $payload, $keyring, ?string $eval_now = null, bool $enforce_ttl = false ): array {
		if ( ! is_object( $payload ) && ( ! is_array( $payload ) || self::is_list( $payload ) ) ) {
			return self::fail( 'invalid_payload', 'Manifest must be a JSON object.' );
		}

		$obj        = is_object( $payload ) ? clone $payload : self::array_to_object( $payload );
		$vars       = get_object_vars( $obj );
		$schema_err = self::validate_schema( $obj );
		if ( null !== $schema_err ) {
			return $schema_err;
		}

		$key_id = isset( $vars['keyId'] ) && is_string( $vars['keyId'] ) ? $vars['keyId'] : '';
		if ( '' === $key_id ) {
			return self::fail( 'missing_keyid', 'Missing or empty keyId.' );
		}

		$sig_raw = isset( $vars['sig'] ) && is_string( $vars['sig'] ) ? $vars['sig'] : '';
		$sig_bin = self::decode_signature( $sig_raw );
		if ( null === $sig_bin ) {
			return self::fail( 'malformed_sig', 'Signature must match ed25519:<88-char-base64>.' );
		}

		$keys = self::normalize_keyring( $keyring );
		if ( ! isset( $keys[ $key_id ] ) ) {
			return self::fail( 'unknown_key', 'Unknown keyId: ' . $key_id );
		}

		$key_entry = $keys[ $key_id ];
		$status    = isset( $key_entry['status'] ) && is_string( $key_entry['status'] ) ? $key_entry['status'] : '';
		if ( 'revoked' === $status ) {
			return self::fail( 'revoked_key', 'Key is revoked: ' . $key_id );
		}
		if ( 'active' !== $status && 'retiring' !== $status ) {
			return self::fail( 'invalid_key_status', 'Unsupported key status: ' . $status );
		}

		$issued_at = isset( $vars['issuedAt'] ) && is_string( $vars['issuedAt'] ) ? $vars['issuedAt'] : '';
		$issued_ts = self::parse_utc_timestamp( $issued_at );
		if ( null === $issued_ts ) {
			return self::fail( 'invalid_timestamp', 'Invalid issuedAt UTC timestamp.' );
		}

		if ( null !== $eval_now ) {
			$now_ts = self::parse_utc_timestamp( $eval_now );
			if ( null === $now_ts ) {
				return self::fail( 'invalid_eval_now', 'Invalid evaluation UTC timestamp.' );
			}
		} elseif ( $enforce_ttl ) {
			$now_ts = array( time(), 0 );
		} else {
			$now_ts = $issued_ts;
		}

		if ( ! self::key_in_window( $key_entry, $now_ts ) ) {
			return self::fail( 'key_outside_window', 'Signing key is outside validity window.' );
		}

		$pub_b64 = isset( $key_entry['publicKeyB64'] ) && is_string( $key_entry['publicKeyB64'] ) ? $key_entry['publicKeyB64'] : '';
		$pub_bin = self::decode_base64_exact( $pub_b64, defined( 'SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES' ) ? SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES : 32 );
		if ( null === $pub_bin ) {
			return self::fail( 'malformed_public_key', 'Invalid Ed25519 public key encoding.' );
		}

		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return self::fail( 'missing_sodium', 'Sodium extension is required for Ed25519 verification.' );
		}

		$unsigned = clone $obj;
		unset( $unsigned->sig );
		$canonical = self::canonical_json( $unsigned );
		if ( '' === $canonical ) {
			return self::fail( 'canonicalization_failed', 'Failed to encode canonical JSON.' );
		}

		if ( ! sodium_crypto_sign_verify_detached( $sig_bin, $canonical, $pub_bin ) ) {
			return self::fail( 'invalid_signature', 'Ed25519 detached signature mismatch.' );
		}

		if ( $enforce_ttl ) {
			$max_future = self::add_seconds_ts( $now_ts, self::MAX_FUTURE_SKEW_SECONDS );
			if ( self::compare_ts( $issued_ts, $max_future ) > 0 ) {
				return self::fail( 'future_issued_at', 'Manifest issuedAt exceeds clock skew limit.' );
			}

			$ttl        = isset( $obj->ttl ) && is_int( $obj->ttl ) ? $obj->ttl : 0;
			$expires_ts = self::add_seconds_ts( $issued_ts, $ttl );
			if ( self::compare_ts( $now_ts, $expires_ts ) > 0 ) {
				return self::fail( 'expired', 'Manifest TTL has elapsed.' );
			}
		}

		return array(
			'ok'     => true,
			'code'   => 'ok',
			'detail' => '',
		);
	}

	/**
	 * Verify a decoded manifest as a boolean.
	 *
	 * @since 4.1.0
	 *
	 * @param mixed       $payload     Decoded JSON object or map.
	 * @param mixed       $keyring     Keyring object/map or `keys` map.
	 * @param string|null $eval_now    Optional UTC RFC3339 evaluation timestamp.
	 * @param bool        $enforce_ttl Whether to enforce TTL expiry.
	 */
	public static function verify( $payload, $keyring, ?string $eval_now = null, bool $enforce_ttl = false ): bool {
		$res = self::verify_detailed( $payload, $keyring, $eval_now, $enforce_ttl );
		return $res['ok'];
	}

	/**
	 * Encode canonical UTF-8 JSON with sorted object keys and preserved array order.
	 *
	 * @since 4.1.0
	 *
	 * @param mixed $value Value to canonicalize.
	 */
	public static function canonical_json( $value ): string {
		$sorted = self::sort_for_canonical( $value );
		$flags  = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		if ( defined( 'JSON_THROW_ON_ERROR' ) ) {
			$flags |= JSON_THROW_ON_ERROR;
		}
		try {
			$encoded = json_encode( $sorted, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- canonical bytes must not pass through wp_json_encode filters.
		} catch ( \Throwable $e ) {
			unset( $e );
			return '';
		}
		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * Parse strict RFC3339 UTC timestamp into [epoch_seconds, frac9].
	 *
	 * Rejects non-UTC offsets, invalid calendar dates (e.g. Feb 30, non-leap Feb 29),
	 * leap seconds (:60), and trailing dots without fractional digits.
	 *
	 * @since 4.1.0
	 *
	 * @param string $ts UTC RFC3339 timestamp.
	 * @return array{0: int, 1: int}|null
	 */
	public static function parse_utc_timestamp( string $ts ): ?array {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d+))?Z\z/', $ts, $m ) ) {
			return null;
		}

		$year  = (int) $m[1];
		$month = (int) $m[2];
		$day   = (int) $m[3];
		$hour  = (int) $m[4];
		$min   = (int) $m[5];
		$sec   = (int) $m[6];
		$frac  = $m[7] ?? '';

		if ( ! checkdate( $month, $day, $year ) ) {
			return null;
		}
		if ( $hour > 23 || $min > 59 || $sec > 59 ) {
			return null;
		}

		$epoch = gmmktime( $hour, $min, $sec, $month, $day, $year );
		if ( false === $epoch ) {
			return null;
		}

		$frac9 = '' === $frac ? 0 : (int) substr( str_pad( $frac, 9, '0', STR_PAD_RIGHT ), 0, 9 );
		return array( $epoch, $frac9 );
	}

	/**
	 * Compare two [epoch_seconds, frac9] tuples.
	 *
	 * @param array{0: int, 1: int} $a Left tuple.
	 * @param array{0: int, 1: int} $b Right tuple.
	 */
	public static function compare_ts( array $a, array $b ): int {
		if ( $a[0] !== $b[0] ) {
			return $a[0] < $b[0] ? -1 : 1;
		}
		if ( $a[1] !== $b[1] ) {
			return $a[1] < $b[1] ? -1 : 1;
		}
		return 0;
	}

	/**
	 * Add integer seconds to a [epoch_seconds, frac9] tuple.
	 *
	 * @param array{0: int, 1: int} $ts      Timestamp tuple.
	 * @param int                   $seconds Seconds offset.
	 * @return array{0: int, 1: int}
	 */
	public static function add_seconds_ts( array $ts, int $seconds ): array {
		return array( $ts[0] + $seconds, $ts[1] );
	}

	/**
	 * Validate structural requirements of admincdn.mirror-manifest/v1.
	 *
	 * @param \stdClass $obj Manifest object.
	 * @return array{ok: bool, code: string, detail: string}|null
	 */
	private static function validate_schema( \stdClass $obj ): ?array {
		$vars = get_object_vars( $obj );
		if ( ! isset( $vars['schema'] ) || self::SCHEMA !== $vars['schema'] ) {
			return self::fail( 'invalid_schema', 'Schema must equal ' . self::SCHEMA );
		}
		if ( ! isset( $vars['version'] ) || ! is_int( $vars['version'] ) || $vars['version'] < 1 ) {
			return self::fail( 'invalid_schema', 'Manifest version must be a positive integer.' );
		}
		if ( ! isset( $vars['ttl'] ) || ! is_int( $vars['ttl'] ) || $vars['ttl'] < 1 ) {
			return self::fail( 'invalid_schema', 'Manifest ttl must be a positive integer.' );
		}
		if ( ! isset( $vars['killSwitch'] ) || ! is_bool( $vars['killSwitch'] ) ) {
			return self::fail( 'invalid_schema', 'Manifest killSwitch must be boolean.' );
		}
		if ( ! isset( $vars['classes'] ) || ! is_object( $vars['classes'] ) ) {
			return self::fail( 'invalid_schema', 'Manifest classes must be an object.' );
		}

		foreach ( get_object_vars( $vars['classes'] ) as $cls_name => $cls_val ) {
			if ( ! is_object( $cls_val ) ) {
				return self::fail( 'invalid_schema', 'Class entry must be an object: ' . $cls_name );
			}
			$cls_vars = get_object_vars( $cls_val );
			if ( ! isset( $cls_vars['state'] ) || ! in_array( $cls_vars['state'], self::HEALTH_STATES, true ) ) {
				return self::fail( 'invalid_schema', 'Invalid state on class: ' . $cls_name );
			}
			if ( ! isset( $cls_vars['primary'] ) || ! is_string( $cls_vars['primary'] ) || 1 !== preg_match( '/^https?:\/\/\S+$/', $cls_vars['primary'] ) ) {
				return self::fail( 'invalid_schema', 'Invalid primary URL on class: ' . $cls_name );
			}
			if ( ! isset( $cls_vars['clientFallbacks'] ) || ! is_array( $cls_vars['clientFallbacks'] ) ) {
				return self::fail( 'invalid_schema', 'clientFallbacks must be an array on class: ' . $cls_name );
			}
			foreach ( $cls_vars['clientFallbacks'] as $fb ) {
				if ( ! is_string( $fb ) || 1 !== preg_match( '/^https?:\/\/\S+$/', $fb ) ) {
					return self::fail( 'invalid_schema', 'Invalid clientFallback URL on class: ' . $cls_name );
				}
			}
			if ( 'wpstatic' === (string) $cls_name ) {
				$wpstatic_host = function_exists( 'wp_parse_url' )
					? wp_parse_url( $cls_vars['primary'], PHP_URL_HOST )
					: parse_url( $cls_vars['primary'], PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap may not load WordPress.
				if ( ! is_string( $wpstatic_host ) || 'wpstatic.admincdn.com' !== strtolower( $wpstatic_host ) ) {
					return self::fail( 'invalid_schema', 'wpstatic primary must use wpstatic.admincdn.com.' );
				}
				if ( array() !== $cls_vars['clientFallbacks'] ) {
					return self::fail( 'invalid_schema', 'wpstatic clientFallbacks must be empty.' );
				}
			}
		}

		return null;
	}

	/**
	 * Normalize keyring input into keyId => associative array.
	 *
	 * @param mixed $keyring Raw keyring wrapper or keys map.
	 * @return array<string, array<string, mixed>>
	 */
	private static function normalize_keyring( $keyring ): array {
		if ( is_object( $keyring ) ) {
			$vars = get_object_vars( $keyring );
			if ( isset( $vars['keys'] ) && ( is_object( $vars['keys'] ) || is_array( $vars['keys'] ) ) ) {
				$keyring = $vars['keys'];
			} else {
				$keyring = $vars;
			}
		} elseif ( is_array( $keyring ) && isset( $keyring['keys'] ) && ( is_array( $keyring['keys'] ) || is_object( $keyring['keys'] ) ) ) {
			$keyring = $keyring['keys'];
		}

		if ( is_object( $keyring ) ) {
			$keyring = get_object_vars( $keyring );
		}
		if ( ! is_array( $keyring ) ) {
			return array();
		}

		$out = array();
		foreach ( $keyring as $k => $entry ) {
			if ( is_object( $entry ) ) {
				$entry = get_object_vars( $entry );
			}
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$id = isset( $entry['keyId'] ) && is_string( $entry['keyId'] ) ? $entry['keyId'] : (string) $k;
			if ( '' !== $id ) {
				$out[ $id ] = $entry;
			}
		}

		return $out;
	}

	/**
	 * Check whether key notBefore/notAfter bounds cover evaluation timestamp.
	 *
	 * @param array<string, mixed>  $key_entry Normalized keyring entry.
	 * @param array{0: int, 1: int} $now_ts    Evaluation timestamp tuple.
	 */
	private static function key_in_window( array $key_entry, array $now_ts ): bool {
		if ( isset( $key_entry['notBefore'] ) && is_string( $key_entry['notBefore'] ) && '' !== $key_entry['notBefore'] ) {
			$nb = self::parse_utc_timestamp( $key_entry['notBefore'] );
			if ( null === $nb || self::compare_ts( $now_ts, $nb ) < 0 ) {
				return false;
			}
		}
		if ( isset( $key_entry['notAfter'] ) && is_string( $key_entry['notAfter'] ) && '' !== $key_entry['notAfter'] ) {
			$na = self::parse_utc_timestamp( $key_entry['notAfter'] );
			if ( null === $na || self::compare_ts( $now_ts, $na ) > 0 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Decode ed25519:<base64> signature strictly.
	 *
	 * @param string $sig Raw signature string.
	 */
	private static function decode_signature( string $sig ): ?string {
		if ( 1 !== preg_match( '/^ed25519:([A-Za-z0-9+\/]{86}==)$/', $sig, $m ) ) {
			return null;
		}
		$sig_len = defined( 'SODIUM_CRYPTO_SIGN_BYTES' ) ? SODIUM_CRYPTO_SIGN_BYTES : 64;
		return self::decode_base64_exact( $m[1], $sig_len );
	}

	/**
	 * Strictly decode Base64 with roundtrip check and exact byte length.
	 *
	 * @param string $b64            Base64 string.
	 * @param int    $expected_bytes Expected decoded length.
	 */
	private static function decode_base64_exact( string $b64, int $expected_bytes ): ?string {
		if ( '' === $b64 || ! function_exists( 'sodium_base642bin' ) ) {
			return null;
		}
		try {
			$bin = sodium_base642bin( $b64, SODIUM_BASE64_VARIANT_ORIGINAL );
		} catch ( \Throwable $e ) {
			unset( $e );
			return null;
		}
		if ( strlen( $bin ) !== $expected_bytes ) {
			return null;
		}
		if ( sodium_bin2base64( $bin, SODIUM_BASE64_VARIANT_ORIGINAL ) !== $b64 ) {
			return null;
		}
		return $bin;
	}

	/**
	 * Recursively sort object properties for canonical JSON while preserving list order.
	 *
	 * @param mixed $value Node.
	 * @return mixed
	 */
	private static function sort_for_canonical( $value ) {
		if ( is_object( $value ) ) {
			$vars = get_object_vars( $value );
			ksort( $vars, SORT_STRING );
			$sorted = new \stdClass();
			foreach ( $vars as $k => $v ) {
				$sorted->$k = self::sort_for_canonical( $v );
			}
			return $sorted;
		}
		if ( is_array( $value ) ) {
			if ( self::is_list( $value ) ) {
				$out = array();
				foreach ( $value as $item ) {
					$out[] = self::sort_for_canonical( $item );
				}
				return $out;
			}
			ksort( $value, SORT_STRING );
			$sorted = new \stdClass();
			foreach ( $value as $k => $v ) {
				$key          = (string) $k;
				$sorted->$key = self::sort_for_canonical( $v );
			}
			return $sorted;
		}
		return $value;
	}

	/**
	 * Convert associative arrays recursively to stdClass while preserving lists.
	 *
	 * @param array<mixed> $arr Input array.
	 */
	private static function array_to_object( array $arr ): \stdClass {
		$obj = new \stdClass();
		foreach ( $arr as $k => $v ) {
			$key = (string) $k;
			if ( is_array( $v ) && ! self::is_list( $v ) ) {
				$obj->$key = self::array_to_object( $v );
			} elseif ( is_array( $v ) ) {
				$obj->$key = array_map(
					static function ( $item ) {
						return is_array( $item ) && ! self::is_list( $item ) ? self::array_to_object( $item ) : $item;
					},
					$v
				);
			} else {
				$obj->$key = $v;
			}
		}
		return $obj;
	}

	/**
	 * PHP 8.0-compatible array_is_list() check.
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
	 * Build failure tuple.
	 *
	 * @param string $code   Error code.
	 * @param string $detail Human-readable detail.
	 * @return array{ok: bool, code: string, detail: string}
	 */
	private static function fail( string $code, string $detail ): array {
		return array(
			'ok'     => false,
			'code'   => $code,
			'detail' => $detail,
		);
	}
}
