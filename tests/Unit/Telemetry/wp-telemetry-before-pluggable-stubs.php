<?php
/**
 * WordPress functions available while plugins load (before pluggable.php).
 *
 * Deliberately does not define wp_rand().
 *
 * @package WenPai\ChinaYes
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed

use WenPai\ChinaYes\Tests\Unit\Telemetry\TelemetryStore;

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Record the callback for a hook.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted Accepted args.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) {
		unset( $priority, $accepted );
		TelemetryStore::$actions[ $hook ] = $callback;
		return true;
	}
}

if ( ! function_exists( 'did_action' ) ) {
	/**
	 * Nothing has fired while plugins load.
	 *
	 * @param string $hook Hook name.
	 * @return int
	 */
	function did_action( $hook ) {
		unset( $hook );
		return 0;
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
		return ! empty( TelemetryStore::$scheduled[ $hook ] ) ? 123 : false;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	/**
	 * Mark a hook as scheduled.
	 *
	 * @param int    $timestamp Run time.
	 * @param string $hook      Hook name.
	 * @return true
	 */
	function wp_schedule_single_event( $timestamp, $hook ) {
		unset( $timestamp );
		TelemetryStore::$scheduled[ $hook ] = true;
		return true;
	}
}
