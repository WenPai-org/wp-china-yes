<?php
/**
 * WC() stand-in for ReportWooCommerceFieldsTest. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

if ( ! function_exists( 'WC' ) ) {
	/**
	 * WooCommerce container.
	 *
	 * @return Wpcy_Telemetry_Fake_WC
	 */
	function WC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- WooCommerce API name.
		static $wc = null;
		if ( null === $wc ) {
			$wc = new Wpcy_Telemetry_Fake_WC();
		}
		return $wc;
	}
}
