<?php
/**
 * D4 profile suggestion. Geo via api.wenpai.net; never stores IP.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suggests domestic or crossborder. Never suggests mixed. Never writes profile.
 */
final class ProfileSuggest {

	/**
	 * Geo endpoint. Path is a stand-in until wenpai-net publishes the contract.
	 *
	 * @since 4.0.0
	 */
	public const GEO_URL = 'https://api.wenpai.net/v1/geo';

	/**
	 * Timezones treated as mainland-admin.
	 *
	 * @var list<string>
	 */
	private const INLAND_TIMEZONES = array(
		'Asia/Shanghai',
		'Asia/Chongqing',
		'Asia/Urumqi',
		'PRC',
	);

	/**
	 * Optional HTTP client: fn(string $url): mixed. Return decoded array or null.
	 *
	 * @var callable|null
	 */
	private $http;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param callable|null $http Optional geo client for tests.
	 */
	public function __construct( $http = null ) {
		$this->http = is_callable( $http ) ? $http : null;
	}

	/**
	 * D4 suggestion. Geo failure → suggestion null, server_country null.
	 *
	 * @since 4.0.0
	 *
	 * @param string|null $locale   Browser locale hint (not stored).
	 * @param string|null $timezone IANA timezone hint (not stored).
	 * @return array{suggestion: string|null, signals: array{server_country: string|null, admin_locale_hint: string|null, admin_tz_hint: string|null}}
	 */
	public function suggest( $locale = null, $timezone = null ): array {
		$locale_hint = $this->normalize_locale( $locale );
		$tz_hint     = $this->normalize_timezone( $timezone );
		$country     = $this->fetch_country();

		$suggestion = null;
		if ( 'CN' === $country ) {
			$suggestion = 'domestic';
		} elseif ( is_string( $country ) && '' !== $country && $this->admin_inland( $locale_hint, $tz_hint ) ) {
			$suggestion = 'crossborder';
		}

		return array(
			'suggestion' => $suggestion,
			'signals'    => array(
				'server_country'    => $country,
				'admin_locale_hint' => $locale_hint,
				'admin_tz_hint'     => $tz_hint,
			),
		);
	}

	/**
	 * Whether the admin hints count as mainland.
	 *
	 * @param string|null $locale   Normalized locale.
	 * @param string|null $timezone Normalized timezone.
	 */
	public function admin_inland( $locale, $timezone ): bool {
		if ( is_string( $locale ) && 1 === preg_match( '/^zh[-_]CN/i', $locale ) ) {
			return true;
		}

		return is_string( $timezone ) && in_array( $timezone, self::INLAND_TIMEZONES, true );
	}

	/**
	 * First Accept-Language tag, or null.
	 *
	 * @since 4.0.0
	 *
	 * @param string $header Raw Accept-Language.
	 * @return string|null
	 */
	public static function locale_from_accept_language( string $header ) {
		$header = trim( $header );
		if ( '' === $header ) {
			return null;
		}
		$parts = explode( ',', $header );
		$first = trim( (string) $parts[0] );
		$tag   = explode( ';', $first )[0];
		$tag   = trim( $tag );

		return '' === $tag ? null : $tag;
	}

	/**
	 * Geo country code, or null on failure. Never returns an IP.
	 *
	 * @return string|null
	 */
	private function fetch_country() {
		$body = $this->request_geo();
		if ( ! is_array( $body ) ) {
			return null;
		}
		$country = isset( $body['country'] ) && is_string( $body['country'] ) ? strtoupper( trim( $body['country'] ) ) : '';
		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $country ) ) {
			return null;
		}

		return $country;
	}

	/**
	 * Call the injectable client or wp_remote_get.
	 *
	 * @return mixed
	 */
	private function request_geo() {
		if ( is_callable( $this->http ) ) {
			return call_user_func( $this->http, self::GEO_URL );
		}

		if ( ! function_exists( 'wp_remote_get' ) ) {
			return null;
		}

		$response = wp_remote_get(
			self::GEO_URL,
			array(
				'timeout'   => 5,
				'sslverify' => true,
			)
		);
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return null;
		}
		$code = function_exists( 'wp_remote_retrieve_response_code' )
			? (int) wp_remote_retrieve_response_code( $response )
			: 0;
		if ( 200 !== $code ) {
			return null;
		}
		$raw = function_exists( 'wp_remote_retrieve_body' )
			? (string) wp_remote_retrieve_body( $response )
			: '';
		if ( '' === $raw ) {
			return null;
		}
		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Trim locale; empty becomes null.
	 *
	 * @param mixed $locale Raw locale.
	 * @return string|null
	 */
	private function normalize_locale( $locale ) {
		if ( ! is_string( $locale ) ) {
			return null;
		}
		$locale = trim( $locale );
		if ( '' === $locale || strlen( $locale ) > 64 ) {
			return null;
		}

		return $locale;
	}

	/**
	 * Trim timezone; empty becomes null.
	 *
	 * @param mixed $timezone Raw timezone.
	 * @return string|null
	 */
	private function normalize_timezone( $timezone ) {
		if ( ! is_string( $timezone ) ) {
			return null;
		}
		$timezone = trim( $timezone );
		if ( '' === $timezone || strlen( $timezone ) > 64 ) {
			return null;
		}

		return $timezone;
	}
}
