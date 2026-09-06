<?php
/**
 * WooCommerce API Manager client for the Weixiaoduo mall.
 *
 * Key placement (providers.md §4 / P6):
 * - Spec prefers POST body. 2026-09-06 read-only probe against
 *   https://mall.weixiaoduo.com/wc-api/wc-am-api/ with a random invalid key:
 *   POST body → HTTP 200 `success:false`「未收到请求值」; GET query → HTTP 200
 *   `success:false`「此许可证密钥不存在客户账户」on product_list. The mall
 *   therefore only reads query (same as cloud-bridge WooCommerceVendor).
 * - Action query key is `wc_am_action` (underscore). Spec text still says
 *   `wc-am-action`; live mall accepts the underscore form.
 * - Connect/test validate the key with product_list (activate/status need
 *   product_id). All five actions use GET query. Logger context never
 *   includes the full URL (host + path only); error objects never include
 *   the request URL.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Providers;

use WenPai\ChinaYes\Core\Logger;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outbound WC AM calls. HTTPS only, sslverify, 10s, no intranet hosts.
 */
final class WcAmClient {

	/**
	 * Allowed wc-am-action values.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const ACTIONS = array(
		'activate',
		'deactivate',
		'status',
		'update',
		'product_list',
	);

	/**
	 * Shop origin, no trailing slash.
	 *
	 * @var string
	 */
	private string $api_url;

	/**
	 * HTTP callable. Defaults to wp_remote_post / wp_remote_get by method.
	 *
	 * @var callable
	 */
	private $http;

	/**
	 * Optional logger. Context must not contain secrets or full URLs.
	 *
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * Constructor. Does not send HTTP.
	 *
	 * @since 4.0.0
	 *
	 * @param string        $api_url Shop origin, e.g. https://mall.weixiaoduo.com.
	 * @param callable|null $http    `fn(string $url, array $args): array|WP_Error`.
	 * @param Logger|null   $logger  Failure sink.
	 */
	public function __construct( string $api_url = '', $http = null, $logger = null ) {
		$this->api_url = function_exists( 'untrailingslashit' ) ? untrailingslashit( $api_url ) : rtrim( $api_url, '/' );
		$this->http    = null !== $http ? $http : array( $this, 'default_http' );
		$this->logger  = $logger instanceof Logger ? $logger : null;
	}

	/**
	 * Activate an instance.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $api_key License key. Never logged.
	 * @param string               $instance UUID.
	 * @param array<string, mixed> $extra    Optional product_id / object.
	 * @return array{ok: bool, kind: string, data: array<string, mixed>}
	 */
	public function activate( string $api_key, string $instance, array $extra = array() ): array {
		return $this->request( 'activate', $api_key, $instance, $extra );
	}

	/**
	 * Deactivate an instance. Best-effort.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $api_key License key. Never logged.
	 * @param string               $instance UUID.
	 * @param array<string, mixed> $extra    Optional product_id / object.
	 * @return array{ok: bool, kind: string, data: array<string, mixed>}
	 */
	public function deactivate( string $api_key, string $instance, array $extra = array() ): array {
		return $this->request( 'deactivate', $api_key, $instance, $extra );
	}

	/**
	 * Status check.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $api_key License key. Never logged.
	 * @param string               $instance UUID.
	 * @param array<string, mixed> $extra    Optional product_id / object.
	 * @return array{ok: bool, kind: string, data: array<string, mixed>}
	 */
	public function status( string $api_key, string $instance, array $extra = array() ): array {
		return $this->request( 'status', $api_key, $instance, $extra );
	}

	/**
	 * Update metadata for one product.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $api_key License key. Never logged.
	 * @param string               $instance UUID.
	 * @param array<string, mixed> $extra    product_id / slug / plugin_name / version / object.
	 * @return array{ok: bool, kind: string, data: array<string, mixed>}
	 */
	public function update( string $api_key, string $instance, array $extra = array() ): array {
		return $this->request( 'update', $api_key, $instance, $extra );
	}

	/**
	 * Purchased product list.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $api_key License key. Never logged.
	 * @param string               $instance UUID.
	 * @param array<string, mixed> $extra    Optional object.
	 * @return array{ok: bool, kind: string, data: array<string, mixed>}
	 */
	public function product_list( string $api_key, string $instance, array $extra = array() ): array {
		return $this->request( 'product_list', $api_key, $instance, $extra );
	}

	/**
	 * One WC AM call.
	 *
	 * @param string               $action     Action.
	 * @param string               $api_key    License key.
	 * @param string               $instance   UUID.
	 * @param array<string, mixed> $extra      Extra fields.
	 * @return array{ok: bool, kind: string, data: array<string, mixed>}
	 */
	private function request( string $action, string $api_key, string $instance, array $extra ): array {
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return $this->result( false, 'unreachable', array() );
		}
		if ( '' === $api_key || '' === $this->api_url ) {
			return $this->result( false, 'unreachable', array() );
		}

		$endpoint = $this->api_url . '/wc-api/wc-am-api/';
		if ( ! UrlGuard::allows( $endpoint ) ) {
			$this->warn( 'WC AM target rejected.', $endpoint, 0 );
			return $this->result( false, 'unreachable', array() );
		}

		$params = array(
			'wc-api'       => 'wc-am-api',
			'wc_am_action' => $action,
			'api_key'      => $api_key,
			'instance'     => $instance,
			'object'       => $this->object_value( $extra ),
		);
		foreach ( array( 'product_id', 'slug', 'plugin_name', 'version', 'software_version' ) as $key ) {
			if ( isset( $extra[ $key ] ) && '' !== (string) $extra[ $key ] ) {
				$params[ $key ] = $extra[ $key ];
			}
		}

		$query = $endpoint . '?' . http_build_query( $params, '', '&' );
		$got   = $this->send(
			$query,
			array(
				'method'    => 'GET',
				'timeout'   => 10,
				'sslverify' => true,
				'headers'   => array(
					'Accept' => 'application/json',
				),
			)
		);
		$kind  = $this->classify( $got );
		if ( 'ok' === $kind || 'invalid' === $kind ) {
			return $this->result( 'ok' === $kind, $kind, $this->decode( $got ) );
		}

		$this->warn( 'WC AM request unreachable.', $endpoint, $this->response_code( $got ) );
		return $this->result( false, 'unreachable', array() );
	}

	/**
	 * Classify a transport result.
	 *
	 * @param mixed $response wp_remote_* result.
	 * @return 'ok'|'invalid'|'unreachable'
	 */
	private function classify( $response ): string {
		if ( is_wp_error( $response ) ) {
			return 'unreachable';
		}
		$code = $this->response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return 'unreachable';
		}
		$data = $this->decode( $response );
		if ( array() === $data ) {
			return 'unreachable';
		}
		if ( ! empty( $data['success'] ) ) {
			return 'ok';
		}
		$status = isset( $data['status_check'] ) ? strtolower( (string) $data['status_check'] ) : '';
		if ( 'active' === $status ) {
			return 'ok';
		}
		if ( array_key_exists( 'success', $data ) && ! $data['success'] ) {
			return 'invalid';
		}

		return 'unreachable';
	}

	/**
	 * JSON body or empty array.
	 *
	 * @param mixed $response wp_remote_* result.
	 * @return array<string, mixed>
	 */
	private function decode( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array();
		}
		$body = $this->response_body( $response );
		$json = json_decode( $body, true );
		return is_array( $json ) ? $json : array();
	}

	/**
	 * Frozen result shape.
	 *
	 * @param bool                 $ok   Whether kind is ok.
	 * @param string               $kind ok / invalid / unreachable.
	 * @param array<string, mixed> $data Decoded JSON.
	 * @return array{ok: bool, kind: string, data: array<string, mixed>}
	 */
	private function result( bool $ok, string $kind, array $data ): array {
		return array(
			'ok'   => $ok,
			'kind' => $kind,
			'data' => $data,
		);
	}

	/**
	 * Object parameter: extra override, else site_url().
	 *
	 * @param array<string, mixed> $extra Extra.
	 */
	private function object_value( array $extra ): string {
		if ( isset( $extra['object'] ) && is_string( $extra['object'] ) && '' !== $extra['object'] ) {
			return $extra['object'];
		}
		if ( function_exists( 'site_url' ) ) {
			return (string) site_url();
		}

		return '';
	}

	/**
	 * Dispatch HTTP. Never logs the URL.
	 *
	 * @param string               $url  Target.
	 * @param array<string, mixed> $args wp_remote args.
	 * @return mixed
	 */
	private function send( string $url, array $args ) {
		return call_user_func( $this->http, $url, $args );
	}

	/**
	 * Default HTTP: wp_remote_post or wp_remote_get.
	 *
	 * @param string               $url  Target.
	 * @param array<string, mixed> $args Args.
	 * @return mixed
	 */
	private function default_http( string $url, array $args ) {
		$method = isset( $args['method'] ) ? strtoupper( (string) $args['method'] ) : 'POST';
		if ( 'GET' === $method && function_exists( 'wp_remote_get' ) ) {
			return wp_remote_get( $url, $args );
		}
		if ( function_exists( 'wp_remote_post' ) ) {
			return wp_remote_post( $url, $args );
		}

		return new WP_Error( 'http_request_failed', 'HTTP unavailable.' );
	}

	/**
	 * Warning without secrets or query strings.
	 *
	 * @param string $message English log line.
	 * @param string $url     Request URL (host/path only are kept).
	 * @param int    $status  HTTP status or 0.
	 */
	private function warn( string $message, string $url, int $status ): void {
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
				'host'   => $host,
				'path'   => $path,
				'status' => $status,
			)
		);
	}

	/**
	 * HTTP status.
	 *
	 * @param mixed $response Response.
	 */
	private function response_code( $response ): int {
		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			return (int) wp_remote_retrieve_response_code( $response );
		}
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}

		return 0;
	}

	/**
	 * HTTP body.
	 *
	 * @param mixed $response Response.
	 */
	private function response_body( $response ): string {
		if ( function_exists( 'wp_remote_retrieve_body' ) ) {
			return (string) wp_remote_retrieve_body( $response );
		}
		if ( is_array( $response ) && isset( $response['body'] ) ) {
			return (string) $response['body'];
		}

		return '';
	}
}
