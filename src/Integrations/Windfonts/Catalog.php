<?php
/**
 * Windfonts family catalog: fetch from API, cache in a transient.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Integrations\Windfonts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Directory is not a long-lived PHP array. Empty on transport failure.
 *
 * Catalog URL path is not frozen in-repo (only /api/css is). Default is the
 * sibling /api/fonts on the same 3.x CSS host; inject another URL in tests.
 */
final class Catalog {

	/**
	 * Transient holding the last successful list.
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_KEY = 'wpcy_windfonts_catalog';

	/**
	 * Cache TTL in seconds. Not specified in the task book; 12h.
	 *
	 * @since 4.0.0
	 */
	public const TTL = 43200;

	/**
	 * Default catalog endpoint. Overridable via constructor.
	 *
	 * @since 4.0.0
	 */
	public const DEFAULT_URL = 'https://app.windfonts.com/api/fonts';

	/**
	 * HTTP GET: function( string $url, array $args ): mixed
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable
	 */
	private $http_get;

	/**
	 * Transient reader.
	 *
	 * @var callable
	 */
	private $get_transient;

	/**
	 * Transient writer.
	 *
	 * @var callable
	 */
	private $set_transient;

	/**
	 * Catalog URL. Not a baked-in family list.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Wire HTTP and transient accessors. Constructor does not hit the network.
	 *
	 * @since 4.0.0
	 *
	 * @param callable|null $http_get      Defaults to wp_remote_get().
	 * @param callable|null $get_transient Defaults to get_transient().
	 * @param callable|null $set_transient Defaults to set_transient().
	 * @param string|null   $url           Catalog URL. Null uses DEFAULT_URL.
	 */
	public function __construct( $http_get = null, $get_transient = null, $set_transient = null, $url = null ) {
		$this->http_get      = null !== $http_get ? $http_get : 'wp_remote_get';
		$this->get_transient = null !== $get_transient ? $get_transient : 'get_transient';
		$this->set_transient = null !== $set_transient ? $set_transient : 'set_transient';
		$this->url           = is_string( $url ) && '' !== $url ? $url : self::DEFAULT_URL;
	}

	/**
	 * Cached family records. Fetches once per TTL. Never a shipped fallback list.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>>
	 */
	public function fonts(): array {
		$cached = ( $this->get_transient )( self::TRANSIENT_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$fetched = $this->fetch();
		if ( array() !== $fetched ) {
			( $this->set_transient )( self::TRANSIENT_KEY, $fetched, self::TTL );
		}

		return $fetched;
	}

	/**
	 * Whether $family is in the cached (or freshly fetched) directory.
	 *
	 * @since 4.0.0
	 *
	 * @param string $family Font family slug.
	 */
	public function has_family( string $family ): bool {
		foreach ( $this->fonts() as $item ) {
			if ( isset( $item['family'] ) && $family === $item['family'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Catalog URL in use.
	 *
	 * @since 4.0.0
	 */
	public function url(): string {
		return $this->url;
	}

	/**
	 * GET the catalog. Transport or decode failure → empty (site stays up).
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>>
	 */
	private function fetch(): array {
		$response = ( $this->http_get )(
			$this->url,
			array(
				'timeout'     => 8,
				'sslverify'   => true,
				'redirection' => 2,
			)
		);

		if ( $this->is_error( $response ) || ! is_array( $response ) ) {
			return array();
		}

		$code = $this->response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array();
		}

		$body = $this->response_body( $response );
		if ( '' === $body ) {
			return array();
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		return $this->normalize( $decoded );
	}

	/**
	 * Accept {fonts:[]}, {data:[]}, or a top-level list of slugs / objects.
	 *
	 * @since 4.0.0
	 *
	 * @param array<mixed> $decoded Decoded JSON.
	 * @return list<array<string, mixed>>
	 */
	private function normalize( array $decoded ): array {
		$list = $decoded;
		if ( isset( $decoded['fonts'] ) && is_array( $decoded['fonts'] ) ) {
			$list = $decoded['fonts'];
		} elseif ( isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) {
			$list = $decoded['data'];
		}

		$out = array();
		foreach ( $list as $item ) {
			$family = '';
			$record = array();
			if ( is_string( $item ) ) {
				$family = $item;
				$record = array( 'family' => $family );
			} elseif ( is_array( $item ) && isset( $item['family'] ) && is_string( $item['family'] ) ) {
				$family = $item['family'];
				$record = $item;
			}

			if ( 1 !== preg_match( '/^[a-z0-9-]{1,64}$/', $family ) ) {
				continue;
			}

			$record['family'] = $family;
			$out[]            = $record;
		}

		return $out;
	}

	/**
	 * Whether $response is a transport failure.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $response HTTP result.
	 */
	private function is_error( $response ): bool {
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return true;
		}

		return $response instanceof \WP_Error;
	}

	/**
	 * HTTP status from a wp_remote_* array.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $response HTTP result.
	 */
	private function response_code( array $response ): int {
		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			return (int) wp_remote_retrieve_response_code( $response );
		}

		if ( isset( $response['response'] ) && is_array( $response['response'] ) && isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}

		return 0;
	}

	/**
	 * Body from a wp_remote_* array.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $response HTTP result.
	 */
	private function response_body( array $response ): string {
		if ( function_exists( 'wp_remote_retrieve_body' ) ) {
			return (string) wp_remote_retrieve_body( $response );
		}

		return isset( $response['body'] ) ? (string) $response['body'] : '';
	}
}
