<?php
/**
 * Minimal WordPress hook and transient stubs for Connectivity unit tests.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

use WenPai\ChinaYes\Tests\Unit\Connectivity\HookStore;

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

if ( ! function_exists( 'remove_action' ) ) {
	/**
	 * No-op remove.
	 *
	 * @return true
	 */
	function remove_action() {
		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * No-op remove.
	 *
	 * @return true
	 */
	function remove_filter() {
		return true;
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
	 * Run recorded callbacks when present; otherwise return $value.
	 *
	 * @param string $tag   Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) {
		$args = func_get_args();
		if ( isset( HookStore::$hooks[ $tag ] ) ) {
			foreach ( HookStore::$hooks[ $tag ] as $callback ) {
				$call  = array_merge( array( $value ), array_slice( $args, 2 ) );
				$value = call_user_func_array( $callback, $call );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'wp_deregister_script' ) ) {
	/**
	 * Record a deregistered script handle.
	 *
	 * @param string $handle Script handle.
	 * @return true
	 */
	function wp_deregister_script( $handle ) {
		HookStore::$deregistered[] = (string) $handle;
		return true;
	}
}

if ( ! function_exists( 'remove_meta_box' ) ) {
	/**
	 * Record a removed meta box.
	 *
	 * @param string $id      Box id.
	 * @param string $screen  Screen.
	 * @param string $context Context.
	 * @return true
	 */
	function remove_meta_box( $id, $screen, $context ) {
		HookStore::$removed_boxes[] = array(
			'id'      => (string) $id,
			'screen'  => (string) $screen,
			'context' => (string) $context,
		);
		return true;
	}
}

if ( ! function_exists( 'apply_filters_deprecated' ) ) {
	/**
	 * Pass the first extra argument through (WordPress deprecated-filter stand-in).
	 *
	 * @param string            $tag         Hook.
	 * @param array<int, mixed> $args        Args; [0] is the filtered value.
	 * @param string            $version     Unused.
	 * @param string            $replacement Unused.
	 * @return mixed
	 */
	function apply_filters_deprecated( $tag, $args, $version, $replacement = '' ) {
		unset( $tag, $version, $replacement );
		return is_array( $args ) && array_key_exists( 0, $args ) ? $args[0] : null;
	}
}
