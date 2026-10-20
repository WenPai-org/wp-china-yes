<?php
/**
 * Minimal multisite / cron stubs for Plugin::deactivate().
 *
 * Loaded in a separate process only.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed

use WenPai\ChinaYes\Tests\Unit\Core\DeactivateStore;

if ( ! function_exists( 'is_multisite' ) ) {
	/**
	 * Multisite flag.
	 *
	 * @return bool
	 */
	function is_multisite() {
		return DeactivateStore::$multisite;
	}
}

if ( ! function_exists( 'get_current_network_id' ) ) {
	/**
	 * Single network.
	 *
	 * @return int
	 */
	function get_current_network_id() {
		return 1;
	}
}

if ( ! function_exists( 'get_sites' ) ) {
	/**
	 * Page of site ids, honouring number / offset like WP_Site_Query.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int[]
	 */
	function get_sites( $args = array() ) {
		DeactivateStore::$get_sites_calls[] = $args;
		$offset                             = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
		$number                             = isset( $args['number'] ) ? (int) $args['number'] : 100;
		return array_slice( DeactivateStore::$sites, $offset, $number );
	}
}

if ( ! function_exists( 'switch_to_blog' ) ) {
	/**
	 * Switch blog.
	 *
	 * @param int $blog_id Blog id.
	 * @return true
	 */
	function switch_to_blog( $blog_id ) {
		DeactivateStore::$stack[] = DeactivateStore::$blog;
		DeactivateStore::$blog    = (int) $blog_id;
		return true;
	}
}

if ( ! function_exists( 'restore_current_blog' ) ) {
	/**
	 * Back to the previous blog.
	 *
	 * @return bool
	 */
	function restore_current_blog() {
		if ( array() === DeactivateStore::$stack ) {
			return false;
		}
		DeactivateStore::$blog = (int) array_pop( DeactivateStore::$stack );
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * Record which blog cleared which hook.
	 *
	 * @param string $hook Hook name.
	 * @return int
	 */
	function wp_clear_scheduled_hook( $hook ) {
		DeactivateStore::$cleared[] = array( DeactivateStore::$blog, (string) $hook );
		return 1;
	}
}
