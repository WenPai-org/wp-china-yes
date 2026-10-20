<?php
/**
 * Pluggable wp_rand(), defined once pluggable.php has loaded.
 *
 * @package WenPai\ChinaYes
 */

if ( ! function_exists( 'wp_rand' ) ) {
	/**
	 * Deterministic jitter.
	 *
	 * @return int
	 */
	function wp_rand() {
		return 0;
	}
}
