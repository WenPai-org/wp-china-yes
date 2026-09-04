<?php
/**
 * WordPress option function stubs for Config unit tests.
 *
 * Loaded by RepositoryTest and MultisiteReadOrderTest. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/OptionStore.php';

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Read a site option from OptionStore.
	 *
	 * @param string $key            Option name.
	 * @param mixed  $fallback_value Fallback.
	 * @return mixed
	 */
	function get_option( $key, $fallback_value = false ) {
		return array_key_exists( $key, OptionStore::$options ) ? OptionStore::$options[ $key ] : $fallback_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Write a site option into OptionStore.
	 *
	 * @param string $key      Option name.
	 * @param mixed  $value    Value.
	 * @param mixed  $autoload Unused; WP network autoload is 待定（M0）.
	 * @return bool
	 */
	function update_option( $key, $value, $autoload = null ) {
		unset( $autoload );
		OptionStore::$options[ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	/**
	 * Read a network option from OptionStore.
	 *
	 * @param string $key            Option name.
	 * @param mixed  $fallback_value Fallback.
	 * @return mixed
	 */
	function get_site_option( $key, $fallback_value = false ) {
		return array_key_exists( $key, OptionStore::$site_options ) ? OptionStore::$site_options[ $key ] : $fallback_value;
	}
}

if ( ! function_exists( 'update_site_option' ) ) {
	/**
	 * Write a network option into OptionStore.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function update_site_option( $key, $value ) {
		OptionStore::$site_options[ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	/**
	 * Multisite flag from OptionStore.
	 *
	 * @return bool
	 */
	function is_multisite() {
		return OptionStore::$multisite;
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * Auth salt used to derive the credential key.
	 *
	 * @param string $scheme Salt scheme.
	 * @return string
	 */
	function wp_salt( $scheme = 'auth' ) {
		unset( $scheme );
		return OptionStore::$salt;
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	/**
	 * Deterministic UUID for first-run identity tests.
	 *
	 * @return string
	 */
	function wp_generate_uuid4() {
		return 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
	}
}
