<?php
/**
 * WordPress option stubs for Migration unit tests. Does not load WordPress.
 *
 * Reuses Config\OptionStore so --filter FixturesTest still works if the
 * config suite already defined get_option().
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Drop a site option from OptionStore.
	 *
	 * @param string $key Option name.
	 * @return bool
	 */
	function delete_option( $key ) {
		unset( OptionStore::$options[ $key ] );
		return true;
	}
}

if ( ! function_exists( 'delete_site_option' ) ) {
	/**
	 * Drop a network option from OptionStore.
	 *
	 * @param string $key Option name.
	 * @return bool
	 */
	function delete_site_option( $key ) {
		unset( OptionStore::$site_options[ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encode stand-in.
	 *
	 * @param mixed $data Tree.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap has no WordPress.
	}
}
