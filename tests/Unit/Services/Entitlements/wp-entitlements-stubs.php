<?php
/**
 * WordPress stubs for Entitlements unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WenPai\ChinaYes\Tests\Unit\Services\Entitlements\EntitlementsStore;

require_once dirname( __DIR__, 2 ) . '/Rest/wp-rest-stubs.php';

if ( ! class_exists( EntitlementsStore::class, false ) ) {
	require_once __DIR__ . '/EntitlementsStore.php';
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Drop a test transient.
	 *
	 * @param string $key Name.
	 * @return true
	 */
	function delete_transient( $key ) {
		unset( RestStore::$transients[ $key ] );
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Return EntitlementsStore::$api for wpcy_services_api.
	 *
	 * @param string $tag   Filter name.
	 * @param mixed  $value Default.
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) {
		if ( 'wpcy_services_api' === $tag ) {
			return EntitlementsStore::$api;
		}
		return $value;
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * Record the GET. Never contacts a network.
	 *
	 * @param string               $url  URL.
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>|WP_Error
	 */
	function wp_remote_get( $url, $args = array() ) {
		EntitlementsStore::$requests[] = array(
			'url'  => (string) $url,
			'args' => $args,
		);
		if ( array() === EntitlementsStore::$responses ) {
			return new WP_Error( 'http_request_failed', 'No mock response queued.' );
		}
		$next = array_shift( EntitlementsStore::$responses );
		if ( $next instanceof WP_Error ) {
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
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * HTTP status from a mock response.
	 *
	 * @param mixed $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		if ( ! is_array( $response ) || ! isset( $response['response']['code'] ) ) {
			return 0;
		}
		return (int) $response['response']['code'];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Body from a mock response.
	 *
	 * @param mixed $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		if ( ! is_array( $response ) || ! isset( $response['body'] ) ) {
			return '';
		}
		return (string) $response['body'];
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Parse a URL.
	 *
	 * @param string $url URL.
	 * @return array<string, mixed>|false
	 */
	function wp_parse_url( $url ) {
		return parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit stub.
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * Strip trailing slashes.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}
}
