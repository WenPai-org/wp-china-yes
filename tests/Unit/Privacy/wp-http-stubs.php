<?php
/**
 * HTTP stubs for DataResidency unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * No-op filter registration.
	 *
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted Accepted args.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) {
		unset( $hook, $callback, $priority, $accepted );
		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * No-op filter removal.
	 *
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @return true
	 */
	function remove_filter( $hook, $callback, $priority = 10 ) {
		unset( $hook, $callback, $priority );
		return true;
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	/**
	 * Record the rewritten URL. Never contacts a network.
	 *
	 * @param string $url Request URL.
	 * @return array<string, mixed>
	 */
	function wp_remote_request( $url ) {
		if ( ! isset( $GLOBALS['wpcy_privacy_remote_urls'] ) || ! is_array( $GLOBALS['wpcy_privacy_remote_urls'] ) ) {
			$GLOBALS['wpcy_privacy_remote_urls'] = array();
		}
		$GLOBALS['wpcy_privacy_remote_urls'][] = $url;
		return array(
			'response' => array(
				'code' => 200,
			),
			'body'     => '',
		);
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
