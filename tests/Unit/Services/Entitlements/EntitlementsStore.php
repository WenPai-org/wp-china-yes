<?php
/**
 * In-memory bags for Entitlements unit tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Services\Entitlements;

/**
 * Process-wide HTTP / filter bags. Reset in each test setUp().
 */
final class EntitlementsStore {

	/**
	 * Filter override for wpcy_services_api. Empty string means no outbound.
	 *
	 * @var string
	 */
	public static $api = '';

	/**
	 * Recorded wp_remote_get calls.
	 *
	 * @var array<int, array{url: string, args: array<string, mixed>}>
	 */
	public static $requests = array();

	/**
	 * Queued HTTP responses, FIFO. Each is array{code: int, body: string}|WP_Error.
	 *
	 * @var array<int, mixed>
	 */
	public static $responses = array();

	/**
	 * Concatenated logger sink (level + message + json context).
	 *
	 * @var string
	 */
	public static $log_sink = '';

	/**
	 * Transient bag isolated from other suites.
	 *
	 * @var array<string, mixed>
	 */
	public static $transients = array();

	/**
	 * Clear bags between tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$api        = '';
		self::$requests   = array();
		self::$responses  = array();
		self::$log_sink   = '';
		self::$transients = array();
	}

	/**
	 * HTTP GET stand-in. Never contacts a network.
	 *
	 * @param string               $url  URL.
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function http_get( $url, $args = array() ) {
		self::$requests[] = array(
			'url'  => (string) $url,
			'args' => $args,
		);
		if ( array() === self::$responses ) {
			return new \WP_Error( 'http_request_failed', 'No mock response queued.' );
		}
		$next = array_shift( self::$responses );
		if ( $next instanceof \WP_Error ) {
			return $next;
		}
		$code = isset( $next['code'] ) ? (int) $next['code'] : 200;
		$body = isset( $next['body'] ) ? (string) $next['body'] : '';
		return array(
			'response' => array(
				'code' => $code,
			),
			'body'     => $body,
		);
	}

	/**
	 * Read a test transient.
	 *
	 * @param string $key Name.
	 * @return mixed
	 */
	public static function get_transient( $key ) {
		return array_key_exists( $key, self::$transients ) ? self::$transients[ $key ] : false;
	}

	/**
	 * Write a test transient.
	 *
	 * @param string $key   Name.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Unused.
	 * @return true
	 */
	public static function set_transient( $key, $value, $ttl = 0 ) {
		unset( $ttl );
		self::$transients[ $key ] = $value;
		return true;
	}

	/**
	 * Drop a test transient.
	 *
	 * @param string $key Name.
	 * @return true
	 */
	public static function delete_transient( $key ) {
		unset( self::$transients[ $key ] );
		return true;
	}
}
