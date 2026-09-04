<?php
/**
 * Minimal WordPress stubs for Windfonts unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

use WenPai\ChinaYes\Tests\Unit\Integrations\HookStore;

require_once __DIR__ . '/HookStore.php';

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Record a filter callback.
	 *
	 * @param string $tag      Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $accepted Accepted args.
	 * @return true
	 */
	function add_filter( $tag, $callback, $priority = 10, $accepted = 1 ) {
		unset( $priority, $accepted );
		HookStore::$hooks[ $tag ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Record an action callback.
	 *
	 * @param string $tag      Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $accepted Accepted args.
	 * @return true
	 */
	function add_action( $tag, $callback, $priority = 10, $accepted = 1 ) {
		return add_filter( $tag, $callback, $priority, $accepted );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Read a stub transient.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return array_key_exists( $key, HookStore::$transients ) ? HookStore::$transients[ $key ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Write a stub transient.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Unused.
	 * @return true
	 */
	function set_transient( $key, $value, $ttl = 0 ) {
		unset( $ttl );
		HookStore::$transients[ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Append query args. Enough for family/subset assertions.
	 *
	 * @param array<string, string> $args Query args.
	 * @param string                $url  Base URL.
	 */
	function add_query_arg( $args, $url ) {
		$pairs = array();
		foreach ( $args as $key => $value ) {
			$pairs[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
		}

		$sep = false === strpos( $url, '?' ) ? '?' : '&';
		return $url . $sep . implode( '&', $pairs );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Identity URL escape.
	 *
	 * @param string $url URL.
	 */
	function esc_url( $url ) {
		return $url;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Identity attribute escape.
	 *
	 * @param string $text Text.
	 */
	function esc_attr( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Return the value unchanged (no entitlement client in unit tests).
	 *
	 * @param string $tag   Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) {
		unset( $tag );
		return $value;
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * Pop a queued response. Never contacts a network.
	 *
	 * @param string               $url  Request URL.
	 * @param array<string, mixed> $args Unused.
	 * @return mixed
	 */
	function wp_remote_get( $url, $args = array() ) {
		unset( $args );
		HookStore::$last_http_url = $url;
		if ( array() === HookStore::$http_queue ) {
			return array(
				'response' => array(
					'code' => 500,
				),
				'body'     => '',
			);
		}

		return array_shift( HookStore::$http_queue );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Detect a WP_Error stand-in.
	 *
	 * @param mixed $thing Value.
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Status from a stub response array.
	 *
	 * @param mixed $response HTTP result.
	 */
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}

		return 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Body from a stub response array.
	 *
	 * @param mixed $response HTTP result.
	 */
	function wp_remote_retrieve_body( $response ) {
		if ( is_array( $response ) && isset( $response['body'] ) ) {
			return (string) $response['body'];
		}

		return '';
	}
}
