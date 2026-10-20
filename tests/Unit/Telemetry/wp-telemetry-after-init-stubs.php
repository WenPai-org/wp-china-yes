<?php
/**
 * Stubs for a request where init has fired and pluggable.php is loaded.
 *
 * Load before wp-telemetry-before-pluggable-stubs.php, in a separate process.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

if ( ! function_exists( 'did_action' ) ) {
	/**
	 * Init has already fired.
	 *
	 * @param string $hook Hook name.
	 * @return int
	 */
	function did_action( $hook ) {
		return 'init' === $hook ? 1 : 0;
	}
}

require __DIR__ . '/wp-telemetry-pluggable-stub.php';
