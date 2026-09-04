<?php
/**
 * License-server site-connection challenge and confirm client (mock-safe).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Services\SiteBinding;

use WenPai\ChinaYes\Core\Logger;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST {API}/v1/site-connections and {API}/v1/site-connections/{id}/confirm.
 *
 * WPCY_SERVICES_API may already include `/v1` (grok-context §7.5b-2) or not
 * (entitlements.md `{API}/v1/...`). api_root() keeps a single `/v1`.
 * The production host is never contacted unless an explicit test flag is on.
 */
final class ChallengeClient {

	/**
	 * Production license-server origin from grok-context §7.5b-2.
	 *
	 * Not used as an outbound default. M3 points WPCY_SERVICES_API here.
	 *
	 * @since 4.0.0
	 */
	public const DEFAULT_API = 'https://license.wenpai.net/v1';

	/**
	 * Production host. Outbound is blocked unless testing.
	 *
	 * @since 4.0.0
	 */
	public const PRODUCTION_HOST = 'license.wenpai.net';

	/**
	 * Filter that overrides the services API base URL (tests / mock).
	 *
	 * @since 4.0.0
	 */
	public const FILTER_API = 'wpcy_services_api';

	/**
	 * Kernel logger. Null skips log lines.
	 *
	 * PHP 7.4 has no union property types.
	 *
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * HTTP POST callable. Defaults to wp_remote_post.
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable
	 */
	private $http;

	/**
	 * Constructor. Does not register hooks and does not send HTTP.
	 *
	 * @since 4.0.0
	 *
	 * @param Logger|null   $logger Failure sink. Context must not contain secrets.
	 * @param callable|null $http   `fn(string $url, array $args): array|WP_Error`.
	 */
	public function __construct( $logger = null, $http = null ) {
		$this->logger = $logger instanceof Logger ? $logger : null;
		$this->http   = null !== $http ? $http : 'wp_remote_post';
	}

	/**
	 * Resolved API base: constant, then filter. Empty means outbound is off.
	 *
	 * @since 4.0.0
	 */
	public static function api_base(): string {
		$base = '';
		if ( defined( 'WPCY_SERVICES_API' ) && is_string( WPCY_SERVICES_API ) && '' !== WPCY_SERVICES_API ) {
			$base = WPCY_SERVICES_API;
		}
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( self::FILTER_API, $base );
			if ( is_string( $filtered ) ) {
				$base = $filtered;
			}
		}

		return function_exists( 'untrailingslashit' ) ? untrailingslashit( $base ) : rtrim( $base, '/' );
	}

	/**
	 * `{base}/v1` with a single `/v1` suffix.
	 *
	 * @since 4.0.0
	 */
	public static function api_root(): string {
		$base = self::api_base();
		if ( '' === $base ) {
			return '';
		}
		if ( preg_match( '#/v1$#', $base ) ) {
			return $base;
		}

		return $base . '/v1';
	}

	/**
	 * Whether this process may POST to the configured API.
	 *
	 * Empty base → no. Production host → only when WPCY_TESTING / WP_TESTS_DOMAIN.
	 * Any other HTTPS URL (mock) → yes.
	 *
	 * @since 4.0.0
	 */
	public static function outbound_allowed(): bool {
		$base = self::api_base();
		if ( '' === $base ) {
			return false;
		}
		if ( 0 !== strpos( $base, 'https://' ) ) {
			return false;
		}
		if ( self::is_production_host( $base ) && ! self::is_test_environment() ) {
			return false;
		}

		return true;
	}

	/**
	 * Start a site-connection challenge.
	 *
	 * @since 4.0.0
	 *
	 * @param array{site_url: string, site_uuid: string, plugin_version: string} $body Request body.
	 * @return array{challenge_id: string, challenge_token: string, expires_at: string}|WP_Error
	 */
	public function start( array $body ) {
		if ( ! self::outbound_allowed() ) {
			return self::unavailable();
		}

		$url = self::api_root() . '/site-connections';
		$raw = $this->post( $url, $body );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$id    = isset( $raw['challenge_id'] ) && is_string( $raw['challenge_id'] ) ? $raw['challenge_id'] : '';
		$token = isset( $raw['challenge_token'] ) && is_string( $raw['challenge_token'] ) ? $raw['challenge_token'] : '';
		$exp   = isset( $raw['expires_at'] ) && is_string( $raw['expires_at'] ) ? $raw['expires_at'] : '';
		if ( '' === $id || '' === $token || '' === $exp ) {
			return $this->failed();
		}

		return array(
			'challenge_id'    => $id,
			'challenge_token' => $token,
			'expires_at'      => $exp,
		);
	}

	/**
	 * Confirm a pending challenge and receive site_hash + credential.
	 *
	 * @since 4.0.0
	 *
	 * @param string $challenge_id Challenge id from start().
	 * @return array{site_hash: string, credential: string}|WP_Error
	 */
	public function confirm( string $challenge_id ) {
		if ( ! self::outbound_allowed() ) {
			return self::unavailable();
		}

		$id = self::sanitize_id( $challenge_id );
		if ( '' === $id ) {
			return $this->failed();
		}

		$url = self::api_root() . '/site-connections/' . rawurlencode( $id ) . '/confirm';
		$raw = $this->post( $url, array() );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$hash = isset( $raw['site_hash'] ) && is_string( $raw['site_hash'] ) ? $raw['site_hash'] : '';
		$cred = isset( $raw['credential'] ) && is_string( $raw['credential'] ) ? $raw['credential'] : '';
		if ( '' === $hash || '' === $cred ) {
			return $this->failed();
		}

		return array(
			'site_hash'  => $hash,
			'credential' => $cred,
		);
	}

	/**
	 * Allow only URL-safe challenge ids in path segments.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Raw id.
	 */
	public static function sanitize_id( string $id ): string {
		if ( ! preg_match( '/^[A-Za-z0-9._-]{1,128}$/', $id ) ) {
			return '';
		}

		return $id;
	}

	/**
	 * Stable plugin-side error when the license server is off or refused.
	 *
	 * @since 4.0.0
	 *
	 * @return WP_Error
	 */
	public static function unavailable(): WP_Error {
		return new WP_Error(
			'wpcy_binding_unavailable',
			__( 'Site binding is not available.', 'wp-china-yes' ),
			array(
				'status' => 503,
			)
		);
	}

	/**
	 * POST JSON with Idempotency-Key. Never logs the body.
	 *
	 * @param string               $url  Absolute URL.
	 * @param array<string, mixed> $body JSON body.
	 * @return array<string, mixed>|WP_Error
	 */
	private function post( string $url, array $body ) {
		$request_id = self::new_id();
		$payload    = self::json_body( $body );
		$args       = array(
			'timeout'   => 10,
			'sslverify' => true,
			'headers'   => array(
				'Content-Type'    => 'application/json',
				'Idempotency-Key' => $request_id,
				'X-Request-Id'    => $request_id,
			),
			'body'      => $payload,
		);

		$response = call_user_func( $this->http, $url, $args );
		if ( is_wp_error( $response ) ) {
			$this->log_failure( 'Site binding HTTP error.', $url, $request_id, 0 );
			return $this->failed();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$this->log_failure( 'Site binding HTTP status was not 2xx.', $url, $request_id, $code );
			return $this->failed();
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			$this->log_failure( 'Site binding response was not JSON.', $url, $request_id, $code );
			return $this->failed();
		}

		return $decoded;
	}

	/**
	 * JSON body. Never includes secrets from callers that did not pass them.
	 *
	 * @param array<string, mixed> $body Request body.
	 */
	private static function json_body( array $body ): string {
		if ( function_exists( 'wp_json_encode' ) ) {
			$payload = wp_json_encode( $body );
			return is_string( $payload ) ? $payload : '{}';
		}

		$payload = json_encode( $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap has no WordPress.
		return is_string( $payload ) ? $payload : '{}';
	}

	/**
	 * Warning line without secrets, email, or query strings.
	 *
	 * @param string $message    English log line.
	 * @param string $url        Request URL (host/path only are kept).
	 * @param string $request_id Idempotency / request id.
	 * @param int    $status     HTTP status or 0.
	 */
	private function log_failure( string $message, string $url, string $request_id, int $status ): void {
		if ( ! $this->logger instanceof Logger ) {
			return;
		}

		$host = '';
		$path = '';
		if ( '' !== $url ) {
			$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
			if ( is_array( $parts ) ) {
				$host = isset( $parts['host'] ) ? (string) $parts['host'] : '';
				$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
			}
		}

		$this->logger->log(
			'warning',
			$message,
			array(
				'host'       => $host,
				'path'       => $path,
				'request_id' => $request_id,
				'status'     => $status,
			)
		);
	}

	/**
	 * Generic start/confirm failure. User message has no secret.
	 *
	 * @return WP_Error
	 */
	private function failed(): WP_Error {
		return new WP_Error(
			'wpcy_binding_start_failed',
			__( 'Site binding request failed.', 'wp-china-yes' ),
			array(
				'status' => 502,
			)
		);
	}

	/**
	 * Production license-server host check.
	 *
	 * @param string $base API base URL.
	 */
	private static function is_production_host( string $base ): bool {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $base ) : parse_url( $base ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}

		return 0 === strcasecmp( (string) $parts['host'], self::PRODUCTION_HOST );
	}

	/**
	 * Explicit test flags only. PHPUnit itself does not open production.
	 *
	 * @since 4.0.0
	 */
	public static function is_test_environment(): bool {
		if ( defined( 'WPCY_TESTING' ) && WPCY_TESTING ) {
			return true;
		}
		if ( defined( 'WP_TESTS_DOMAIN' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * UUID for Idempotency-Key / X-Request-Id.
	 */
	private static function new_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return bin2hex( random_bytes( 16 ) );
	}
}
