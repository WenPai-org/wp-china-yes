<?php
/**
 * License-server service-catalog GET client (mock-safe).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Services\Catalog;

use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Services\SiteBinding\ChallengeClient;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET {API}/v1/sites/{site_hash}/catalog.
 *
 * Catalog fields are copied from the server body. This class does not
 * invent items, prices, or entitlement states. Production host is
 * blocked unless an explicit test flag is on (ChallengeClient).
 */
final class CatalogClient {

	/**
	 * Production Bearer credential header (frozen M1 contract).
	 *
	 * @since 4.0.0
	 */
	public const AUTH_HEADER = 'Authorization';

	/**
	 * Legacy staging credential header kept for backwards compatibility.
	 *
	 * @since 4.0.0
	 */
	public const TEST_CREDENTIAL_HEADER = 'X-WPCY-Test-Credential';

	/**
	 * Kernel logger. Null skips log lines.
	 *
	 * PHP 7.4 has no union property types.
	 *
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * HTTP GET callable. Defaults to wp_remote_get.
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable
	 */
	private $http;

	/**
	 * Optional API base override (tests / mock). Null uses ChallengeClient::api_base().
	 *
	 * PHP 7.4 has no union property types.
	 *
	 * @var string|null
	 */
	private $api;

	/**
	 * Constructor. Does not register hooks and does not send HTTP.
	 *
	 * @since 4.0.0
	 *
	 * @param Logger|null   $logger Failure sink. Context must not contain secrets.
	 * @param callable|null $http   `fn(string $url, array $args): array|WP_Error`.
	 * @param string|null   $api    API base override. Null uses ChallengeClient.
	 */
	public function __construct( $logger = null, $http = null, $api = null ) {
		$this->logger = $logger instanceof Logger ? $logger : null;
		$this->http   = null !== $http ? $http : 'wp_remote_get';
		$this->api    = is_string( $api ) ? $api : null;
	}

	/**
	 * Fetch the service catalog for a bound site.
	 *
	 * @since 4.0.0
	 *
	 * @param string $site_hash  Bound site hash from site binding.
	 * @param string $credential Plaintext credential for the staging header.
	 * @return array{catalog_version: string, items: list<array<string, mixed>>}|WP_Error
	 */
	public function fetch( string $site_hash, string $credential = '' ) {
		if ( ! $this->outbound_allowed() ) {
			return self::unavailable();
		}

		$hash = self::sanitize_hash( $site_hash );
		if ( '' === $hash ) {
			return self::unavailable();
		}

		$url = $this->api_root() . '/sites/' . rawurlencode( $hash ) . '/catalog';
		$raw = $this->get( $url, $credential );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$list = isset( $raw['items'] ) && is_array( $raw['items'] ) ? $raw['items'] : null;
		if ( ! is_array( $list ) ) {
			$this->log_failure( 'Catalog response was missing the items list.', $url, '', 0 );
			return self::unavailable();
		}

		$items = array();
		foreach ( $list as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$normalized = self::normalize_item( $row );
			if ( is_array( $normalized ) ) {
				$items[] = $normalized;
			}
		}

		return array(
			'catalog_version' => isset( $raw['catalog_version'] ) && is_string( $raw['catalog_version'] ) ? $raw['catalog_version'] : '',
			'items'           => $items,
		);
	}

	/**
	 * Resolved API base: constructor override, else ChallengeClient.
	 *
	 * @since 4.0.0
	 */
	public function api_base(): string {
		if ( is_string( $this->api ) ) {
			$base = $this->api;
			return function_exists( 'untrailingslashit' ) ? untrailingslashit( $base ) : rtrim( $base, '/' );
		}

		return ChallengeClient::api_base();
	}

	/**
	 * `{base}/v1` with a single `/v1` suffix.
	 *
	 * @since 4.0.0
	 */
	public function api_root(): string {
		$base = $this->api_base();
		if ( '' === $base ) {
			return '';
		}
		if ( preg_match( '#/v1$#', $base ) ) {
			return $base;
		}

		return $base . '/v1';
	}

	/**
	 * Whether this process may GET the configured API.
	 *
	 * Empty base or non-HTTPS → no. Any HTTPS URL (production or mock) → yes.
	 *
	 * @since 4.0.0
	 */
	public function outbound_allowed(): bool {
		$base = $this->api_base();
		if ( '' === $base ) {
			return false;
		}
		if ( 0 !== strpos( $base, 'https://' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Allow only URL-safe site hashes in path segments.
	 *
	 * @since 4.0.0
	 *
	 * @param string $hash Raw hash.
	 */
	public static function sanitize_hash( string $hash ): string {
		if ( ! preg_match( '/^[A-Za-z0-9._-]{1,128}$/', $hash ) ) {
			return '';
		}

		return $hash;
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
			'wpcy_catalog_unavailable',
			__( '暂时无法读取服务目录，请稍后重试。', 'wp-china-yes' ),
			array(
				'status' => 503,
			)
		);
	}

	/**
	 * GET JSON. Never logs the credential.
	 *
	 * @param string $url        Absolute URL.
	 * @param string $credential Plaintext credential for the mock header.
	 * @return array<string, mixed>|WP_Error
	 */
	private function get( string $url, string $credential ) {
		$request_id = self::new_id();
		$headers    = array(
			'Accept'       => 'application/json',
			'X-Request-Id' => $request_id,
		);
		if ( '' !== $credential ) {
			$headers[ self::AUTH_HEADER ] = 'Bearer ' . $credential;
		}

		$args = array(
			'timeout'   => 10,
			'sslverify' => true,
			'headers'   => $headers,
		);

		$response = call_user_func( $this->http, $url, $args );
		if ( is_wp_error( $response ) ) {
			$this->log_failure( 'Catalog HTTP error.', $url, $request_id, 0 );
			return self::unavailable();
		}

		$code = self::response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$this->log_failure( 'Catalog HTTP status was not 2xx.', $url, $request_id, $code );
			return self::unavailable();
		}

		$decoded = json_decode( self::response_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			$this->log_failure( 'Catalog response was not JSON.', $url, $request_id, $code );
			return self::unavailable();
		}

		return $decoded;
	}

	/**
	 * Keep the catalog item shape. Unknown values pass through as empty strings.
	 *
	 * @param array<string, mixed> $row Raw item.
	 * @return array<string, mixed>|null
	 */
	private static function normalize_item( array $row ) {
		$id = isset( $row['id'] ) && is_string( $row['id'] ) ? $row['id'] : '';
		if ( '' === $id ) {
			return null;
		}

		$quota_in = isset( $row['quota'] ) && is_array( $row['quota'] ) ? $row['quota'] : null;
		$quota    = null;
		if ( is_array( $quota_in ) ) {
			$quota = array(
				'limit'     => array_key_exists( 'limit', $quota_in ) ? $quota_in['limit'] : null,
				'used'      => array_key_exists( 'used', $quota_in ) ? $quota_in['used'] : null,
				'period'    => array_key_exists( 'period', $quota_in ) ? $quota_in['period'] : null,
				'resets_at' => array_key_exists( 'resets_at', $quota_in ) ? $quota_in['resets_at'] : null,
			);
		}

		$pick   = static function ( $key ) use ( $row ) {
			return isset( $row[ $key ] ) && is_string( $row[ $key ] ) ? $row[ $key ] : '';
		};
		$scenes = isset( $row['scenes'] ) && is_array( $row['scenes'] ) ? array_values( array_filter( $row['scenes'], 'is_string' ) ) : array();

		return array(
			'id'             => $id,
			'name'           => $pick( 'name' ),
			'provider'       => $pick( 'provider' ),
			'provider_label' => $pick( 'provider_label' ),
			'desc'           => $pick( 'desc' ),
			'vendor'         => $pick( 'vendor' ),
			'service'        => $pick( 'service' ),
			'entitlement'    => $pick( 'entitlement' ),
			'install'        => $pick( 'install' ),
			'action'         => $pick( 'action' ),
			'scenes'         => $scenes,
			'quota'          => $quota,
		);
	}

	/**
	 * Warning line without secrets, email, or query strings.
	 *
	 * @param string $message    English log line.
	 * @param string $url        Request URL (host/path only are kept).
	 * @param string $request_id Request id.
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
	 * HTTP status from a wp_remote_* response.
	 *
	 * @param mixed $response Response.
	 */
	private static function response_code( $response ): int {
		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			return (int) wp_remote_retrieve_response_code( $response );
		}
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}

		return 0;
	}

	/**
	 * Body from a wp_remote_* response.
	 *
	 * @param mixed $response Response.
	 */
	private static function response_body( $response ): string {
		if ( function_exists( 'wp_remote_retrieve_body' ) ) {
			return (string) wp_remote_retrieve_body( $response );
		}
		if ( is_array( $response ) && isset( $response['body'] ) ) {
			return (string) $response['body'];
		}

		return '';
	}

	/**
	 * UUID for X-Request-Id.
	 */
	private static function new_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return bin2hex( random_bytes( 16 ) );
	}
}
