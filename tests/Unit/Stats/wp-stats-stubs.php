<?php
/**
 * WordPress stubs for Stats unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';
require_once dirname( __DIR__ ) . '/Rest/wp-rest-stubs.php';
require_once dirname( __DIR__ ) . '/Connectivity/wp-error-stub.php';

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Run recorded action callbacks.
	 *
	 * @param string $tag Hook.
	 * @return void
	 */
	function do_action( $tag ) {
		$args = func_get_args();
		array_shift( $args );
		if ( ! isset( RestStore::$hooks[ $tag ] ) ) {
			return;
		}
		foreach ( RestStore::$hooks[ $tag ] as $callback ) {
			call_user_func_array( $callback, $args );
		}
	}
}



if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Body from a canned WP HTTP array.
	 *
	 * @param mixed $response Response.
	 */
	function wp_remote_retrieve_body( $response ) {
		return is_array( $response ) && isset( $response['body'] ) ? (string) $response['body'] : '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
	/**
	 * Header from a canned WP HTTP array.
	 *
	 * @param mixed  $response Response.
	 * @param string $header   Header name.
	 */
	function wp_remote_retrieve_header( $response, $header ) {
		if ( ! is_array( $response ) || ! isset( $response['headers'] ) || ! is_array( $response['headers'] ) ) {
			return '';
		}
		foreach ( $response['headers'] as $key => $value ) {
			if ( is_string( $key ) && 0 === strcasecmp( $key, $header ) ) {
				return (string) $value;
			}
		}
		return '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Status from a canned WP HTTP array.
	 *
	 * @param mixed $response Response.
	 */
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}
		if ( is_array( $response ) && isset( $response['code'] ) ) {
			return (int) $response['code'];
		}
		return 0;
	}
}
