<?php
/**
 * WordPress stubs for Providers unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WenPai\ChinaYes\Tests\Unit\Services\SiteBinding\BindingStore;

require_once dirname( __DIR__ ) . '/Services/SiteBinding/wp-binding-stubs.php';

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Drop a site option.
	 *
	 * @param string $key Name.
	 * @return true
	 */
	function delete_option( $key ) {
		unset( OptionStore::$options[ $key ] );
		return true;
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * Loose email check.
	 *
	 * @param string $email Address.
	 * @return string|false
	 */
	function is_email( $email ) {
		return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL ) ? (string) $email : false;
	}
}

if ( ! function_exists( 'get_plugins' ) ) {
	/**
	 * Installed plugins from BindingStore::$plugins when set, else empty.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function get_plugins() {
		return isset( $GLOBALS['wpcy_test_plugins'] ) && is_array( $GLOBALS['wpcy_test_plugins'] )
			? $GLOBALS['wpcy_test_plugins']
			: array();
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * Record a GET. Never contacts a network.
	 *
	 * @param string               $url  URL.
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>|WP_Error
	 */
	function wp_remote_get( $url, $args = array() ) {
		return wp_remote_post( $url, $args );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Home URL stand-in.
	 *
	 * @return string
	 */
	function home_url() {
		return BindingStore::$site_url;
	}
}
