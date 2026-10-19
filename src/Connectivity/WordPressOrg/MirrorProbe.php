<?php
/**
 * WordPress.org package-mirror probe: status, content type, and size.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\WordPressOrg;

/**
 * Caches package-host usability with a shorter TTL when down.
 */
final class MirrorProbe {

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
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable
	 */
	private $get_transient;

	/**
	 * Transient writer.
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable
	 */
	private $set_transient;

	/**
	 * Last probe URL.
	 *
	 * @var string
	 */
	private string $last_url = '';

	/**
	 * Last probe args.
	 *
	 * @var array<string, mixed>
	 */
	private array $last_args = array();

	/**
	 * Number of HTTP probes in this instance.
	 *
	 * @var int
	 */
	private int $probe_count = 0;

	/**
	 * Wire optional HTTP and transient accessors for unit tests.
	 *
	 * @param callable|null $http_get      Defaults to wp_remote_get().
	 * @param callable|null $get_transient Defaults to get_transient().
	 * @param callable|null $set_transient Defaults to set_transient().
	 */
	public function __construct( $http_get = null, $get_transient = null, $set_transient = null ) {
		$this->http_get      = null !== $http_get ? $http_get : 'wp_remote_get';
		$this->get_transient = null !== $get_transient ? $get_transient : 'get_transient';
		$this->set_transient = null !== $set_transient ? $set_transient : 'set_transient';
	}

	/**
	 * Whether the package mirror can serve install zips.
	 *
	 * Unknown state probes once; the result is cached.
	 */
	public function is_usable(): bool {
		$state = ( $this->get_transient )( Origins::STATE_KEY );

		if ( 'up' === $state ) {
			return true;
		}

		if ( 'down' === $state ) {
			return false;
		}

		$usable = $this->probe();

		( $this->set_transient )(
			Origins::STATE_KEY,
			$usable ? 'up' : 'down',
			$usable ? Origins::UP_TTL : Origins::DOWN_TTL
		);

		return $usable;
	}

	/**
	 * Last probe URL (empty if no probe ran).
	 */
	public function last_url(): string {
		return $this->last_url;
	}

	/**
	 * Last probe args.
	 *
	 * @return array<string, mixed>
	 */
	public function last_args(): array {
		return $this->last_args;
	}

	/**
	 * How many HTTP probes this instance has issued.
	 */
	public function probe_count(): int {
		return $this->probe_count;
	}

	/**
	 * Hit the package host with a Range GET and judge the response.
	 */
	private function probe(): bool {
		$url  = Origins::PACKAGE_ORIGIN . Origins::PROBE_PATH;
		$args = array(
			'timeout'       => Origins::PROBE_TIMEOUT,
			'redirection'   => 2,
			'headers'       => array( 'Range' => 'bytes=0-0' ),
			'_wp_china_yes' => true,
		);

		$this->last_url  = $url;
		$this->last_args = $args;
		++$this->probe_count;

		$response = ( $this->http_get )( $url, $args );

		if ( $this->is_error( $response ) || ! is_array( $response ) ) {
			return false;
		}

		$code = $this->response_code( $response );

		if ( $code < 200 || $code >= 400 ) {
			return false;
		}

		$type = strtolower( $this->header( $response, 'content-type' ) );

		if ( false !== strpos( $type, 'json' ) || false !== strpos( $type, 'text/html' ) ) {
			return false;
		}

		$total = $this->parse_total_bytes( $response );

		if ( null !== $total && $total < Origins::MIN_PACKAGE_BYTES ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether $response is a transport failure.
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
	 * Status code from a canned `{code}` array or a WP HTTP response.
	 *
	 * @param array<string, mixed> $response Response.
	 */
	private function response_code( array $response ): int {
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
	 * Total resource size from Content-Range (preferred) or Content-Length.
	 *
	 * @param array<string, mixed> $response Canned or normalized response.
	 * @return int|null Null means do not reject on size.
	 */
	private function parse_total_bytes( array $response ): ?int {
		$range = $this->header( $response, 'content-range' );

		if ( '' !== $range && false !== strpos( $range, '/' ) ) {
			$total = substr( $range, strpos( $range, '/' ) + 1 );

			if ( is_numeric( $total ) ) {
				return (int) $total;
			}
		}

		$length = $this->header( $response, 'content-length' );

		if ( '' !== $length && is_numeric( $length ) ) {
			return (int) $length;
		}

		return null;
	}

	/**
	 * Header value from a canned response array.
	 *
	 * @param array<string, mixed> $response Response.
	 * @param string               $name     Header name.
	 */
	private function header( array $response, string $name ): string {
		if ( function_exists( 'wp_remote_retrieve_header' ) && isset( $response['response'] ) ) {
			$value = wp_remote_retrieve_header( $response, $name );
			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}

		$headers = isset( $response['headers'] ) && is_array( $response['headers'] )
			? $response['headers']
			: array();

		if ( isset( $headers[ $name ] ) ) {
			return (string) $headers[ $name ];
		}

		foreach ( $headers as $key => $value ) {
			if ( is_string( $key ) && 0 === strcasecmp( $key, $name ) ) {
				return (string) $value;
			}
		}

		return '';
	}
}
