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
