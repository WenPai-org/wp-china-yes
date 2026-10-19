<?php
/**
 * Stand-in for is_network_admin() for AdminModule network tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

// phpcs:disable Universal.Files.SeparateFunctionsFromOO

use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;

if ( ! function_exists( 'is_network_admin' ) ) {
	/**
	 * Network admin flag from RestStore.
	 *
	 * @return bool
	 */
	function is_network_admin() {
		return RestStore::$network_admin;
	}
}

if ( ! function_exists( 'remove_submenu_page' ) ) {
	/**
	 * Record removed submenu entries in $GLOBALS['wpcy_test_removed_submenus'].
	 *
	 * @param string $menu_slug    Parent slug.
	 * @param string $submenu_slug Submenu slug.
	 * @return false
	 */
	function remove_submenu_page( $menu_slug, $submenu_slug ) {
		$GLOBALS['wpcy_test_removed_submenus'][] = array( $menu_slug, $submenu_slug );
		return false;
	}
}
