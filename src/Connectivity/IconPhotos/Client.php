<?php
/**
 * MotuCloud native API skeleton: list / search with timeout and core fallback.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\IconPhotos;

use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Providers\UrlGuard;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET {native_api_base}/v1/list and /v1/search. Failure returns the core Openverse source.
 */
final class Client {

	/**
	 * Config read model.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * HTTP GET: function( string $url, array $args ): mixed
	 *
	 * @var callable
	 */
	private $http_get;

	/**
	 * Last URL actually requested (tests).
	 *
	 * @var string
	 */
	private string $last_url = '';

	/**
	 * Whether the last successful body came from the core source.
	 *
	 * @var bool
	 */
	private bool $last_used_core = false;

	/**
	 * Constructor. Does not send HTTP.
	 *
	 * @since 4.0.0
	 *
	 * @param Config        $config   Config read model.
	 * @param callable|null $http_get Defaults to wp_remote_get().
	 */
	public function __construct( Config $config, $http_get = null ) {
		$this->config   = $config;
		$this->http_get = null !== $http_get ? $http_get : 'wp_remote_get';
	}

	/**
	 * List items from MotuCloud; fall back to the core Openverse catalog.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $args Query args (page, per_page, …).
	 * @return array<string, mixed>|WP_Error
	 */
	public function list( array $args = array() ) {
		$native = $this->request_native( Origins::NATIVE_LIST_PATH, $args );
		if ( ! $this->is_error( $native ) && is_array( $native ) ) {
			$this->last_used_core = false;
			return $native;
		}

		$core = $this->request_core( $args );
		if ( ! $this->is_error( $core ) && is_array( $core ) ) {
			$this->last_used_core = true;
			return $core;
		}

		return $this->as_error( $native, $core );
	}

	/**
	 * Search MotuCloud; fall back to core Openverse search.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $query Search string.
	 * @param array<string, mixed> $args  Extra query args.
	 * @return array<string, mixed>|WP_Error
	 */
	public function search( string $query, array $args = array() ) {
		$args['q'] = $query;

		$native = $this->request_native( Origins::NATIVE_SEARCH_PATH, $args );
		if ( ! $this->is_error( $native ) && is_array( $native ) ) {
			$this->last_used_core = false;
			return $native;
		}

		$core = $this->request_core( $args );
		if ( ! $this->is_error( $core ) && is_array( $core ) ) {
			$this->last_used_core = true;
			return $core;
		}

		return $this->as_error( $native, $core );
	}

	/**
	 * Last HTTP URL (empty if none).
	 *
	 * @since 4.0.0
	 */
	public function last_url(): string {
		return $this->last_url;
	}

	/**
	 * Whether the last successful list/search used the core Openverse source.
	 *
	 * @since 4.0.0
	 */
	public function last_used_core(): bool {
		return $this->last_used_core;
	}

	/**
	 * GET native_api_base + path. Empty / invalid base is an error (caller falls back).
	 *
	 * @param string               $path Relative path.
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request_native( string $path, array $args ) {
		$base = $this->native_base();
		if ( '' === $base ) {
			return new WP_Error( 'wpcy_motucloud_unavailable', 'wpcy_motucloud_unavailable' );
		}

		return $this->get_json( $base . $path, $args, Origins::NATIVE_TIMEOUT );
	}

	/**
	 * GET the core Openverse catalog (same shape used by the media modal).
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request_core( array $args ) {
		return $this->get_json( Origins::CORE_SEARCH_ORIGIN . Origins::CORE_IMAGES_PATH, $args, Origins::NATIVE_TIMEOUT );
	}

	/**
	 * HTTPS GET, JSON-decode the body. Transport / non-2xx / bad JSON → WP_Error.
	 *
	 * @param string               $url     Absolute URL.
	 * @param array<string, mixed> $args    Query args.
	 * @param int                  $timeout Seconds.
	 * @return array<string, mixed>|WP_Error
	 */
	private function get_json( string $url, array $args, int $timeout ) {
		$query = $this->query_string( $args );
		if ( '' !== $query ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
		}

		$this->last_url = $url;

		$response = ( $this->http_get )(
			$url,
			array(
				'timeout'   => $timeout,
				'sslverify' => true,
			)
		);

		if ( $this->is_error( $response ) ) {
			return $response instanceof WP_Error
				? $response
				: new WP_Error( 'wpcy_motucloud_unavailable', 'wpcy_motucloud_unavailable' );
		}

		$code = $this->response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'wpcy_motucloud_unavailable', 'wpcy_motucloud_unavailable' );
		}

		$body = $this->response_body( $response );
		if ( '' === $body ) {
			return new WP_Error( 'wpcy_motucloud_unavailable', 'wpcy_motucloud_unavailable' );
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'wpcy_motucloud_unavailable', 'wpcy_motucloud_unavailable' );
		}

		return $decoded;
	}

	/**
	 * Valid native_api_base origin, or empty.
	 */
	private function native_base(): string {
		$row = $this->config->get( 'connectivity.icon_photos', array() );
		if ( ! is_array( $row ) ) {
			return '';
		}
		$base = isset( $row['native_api_base'] ) && is_string( $row['native_api_base'] )
			? trim( $row['native_api_base'] )
			: '';
		if ( '' === $base || ! UrlGuard::allows( $base ) ) {
			return '';
		}

		return rtrim( $base, '/' );
	}

	/**
	 * Build a query string from scalar args. Nested values are skipped.
	 *
	 * @param array<string, mixed> $args Query args.
	 */
	private function query_string( array $args ): string {
		$out = array();
		foreach ( $args as $key => $value ) {
			$key = (string) $key;
			if ( '' === $key ) {
				continue;
			}
			if ( is_bool( $value ) ) {
				$out[ $key ] = $value ? '1' : '0';
				continue;
			}
			if ( is_scalar( $value ) ) {
				$out[ $key ] = (string) $value;
			}
		}

		if ( array() === $out ) {
			return '';
		}

		return http_build_query( $out, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Prefer native error, else core error, else a generic unavailable.
	 *
	 * @param mixed $native Native result.
	 * @param mixed $core   Core result.
	 */
	private function as_error( $native, $core ): WP_Error {
		if ( $native instanceof WP_Error ) {
			return $native;
		}
		if ( $core instanceof WP_Error ) {
			return $core;
		}

		return new WP_Error( 'wpcy_motucloud_unavailable', 'wpcy_motucloud_unavailable' );
	}

	/**
	 * Whether $value is a transport failure.
	 *
	 * @param mixed $value Candidate.
	 */
	private function is_error( $value ): bool {
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $value ) ) {
			return true;
		}

		return $value instanceof WP_Error;
	}

	/**
	 * HTTP status from a canned array or WP HTTP response.
	 *
	 * @param mixed $response HTTP result.
	 */
	private function response_code( $response ): int {
		if ( ! is_array( $response ) ) {
			return 0;
		}
		if ( isset( $response['code'] ) ) {
			return (int) $response['code'];
		}
		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			return (int) wp_remote_retrieve_response_code( $response );
		}
		if ( isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}

		return 0;
	}

	/**
	 * Response body as string.
	 *
	 * @param mixed $response HTTP result.
	 */
	private function response_body( $response ): string {
		if ( ! is_array( $response ) ) {
			return '';
		}
		if ( function_exists( 'wp_remote_retrieve_body' ) ) {
			return (string) wp_remote_retrieve_body( $response );
		}
		if ( isset( $response['body'] ) && is_string( $response['body'] ) ) {
			return $response['body'];
		}

		return '';
	}
}
