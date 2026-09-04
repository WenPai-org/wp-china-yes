<?php
/**
 * WordPress stubs for Diagnostics unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

use WenPai\ChinaYes\Tests\Unit\Diagnostics\DiagnosticsStore;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! class_exists( 'WenPai\\ChinaYes\\Tests\\Unit\\Diagnostics\\DiagnosticsStore', false ) ) {
	require_once __DIR__ . '/DiagnosticsStore.php';
}

require_once dirname( __DIR__ ) . '/Connectivity/wp-error-stub.php';

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Whether $thing is WP_Error.
	 *
	 * @param mixed $thing Candidate.
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
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
		unset( $priority, $accepted );
		DiagnosticsStore::$hooks[ $tag ][] = $callback;
		return true;
	}
}

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
		return add_action( $tag, $callback, $priority, $accepted );
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * Whether a hook is already scheduled.
	 *
	 * @param string $hook Hook name.
	 * @return int|false
	 */
	function wp_next_scheduled( $hook ) {
		return ! empty( DiagnosticsStore::$scheduled[ $hook ] ) ? 123 : false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * Mark a recurring hook as scheduled.
	 *
	 * @param int    $timestamp When.
	 * @param string $recurrence Recurrence.
	 * @param string $hook      Hook.
	 * @return true
	 */
	function wp_schedule_event( $timestamp, $recurrence, $hook ) {
		unset( $timestamp, $recurrence );
		DiagnosticsStore::$scheduled[ $hook ] = true;
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * Clear a scheduled hook.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	function wp_clear_scheduled_hook( $hook ) {
		DiagnosticsStore::$scheduled[ $hook ] = false;
		DiagnosticsStore::$cleared            = true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Read a test transient.
	 *
	 * @param string $key Name.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return DiagnosticsStore::$transients[ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Write a test transient.
	 *
	 * @param string $key   Name.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Unused.
	 * @return true
	 */
	function set_transient( $key, $value, $ttl = 0 ) {
		unset( $ttl );
		DiagnosticsStore::$transients[ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Identity translation.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Identity escaped translation.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 */
	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Lowercase slug.
	 *
	 * @param mixed $key Raw key.
	 */
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encode stand-in.
	 *
	 * @param mixed $data Value.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap has no WordPress.
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

		return 0;
	}
}
