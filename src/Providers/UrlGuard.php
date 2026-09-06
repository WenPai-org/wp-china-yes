<?php
/**
 * Outbound URL gate: HTTPS only, no private or reserved hosts.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rejects http:// and intranet literals. Hostnames are not DNS-resolved here
 * so unit tests never contact a network; IP literals are checked with
 * FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE.
 */
final class UrlGuard {

	/**
	 * Whether $url may be used as an outbound WC AM target.
	 *
	 * @since 4.0.0
	 *
	 * @param string $url Absolute URL.
	 */
	public static function allows( string $url ): bool {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $parts ) ) {
			return false;
		}
		if ( 'https' !== ( $parts['scheme'] ?? '' ) ) {
			return false;
		}

		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( '' === $host ) {
			return false;
		}
		if ( 'localhost' === $host || self::ends_with( $host, '.localhost' ) || self::ends_with( $host, '.local' ) ) {
			return false;
		}

		$ip = trim( $host, '[]' );
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$public = filter_var(
				$ip,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			);
			return false !== $public;
		}

		return true;
	}

	/**
	 * PHP 8.0-safe suffix check (str_ends_with exists on 8.0).
	 *
	 * @param string $value  Haystack.
	 * @param string $suffix Needle.
	 */
	private static function ends_with( string $value, string $suffix ): bool {
		return substr( $value, -strlen( $suffix ) ) === $suffix;
	}
}
