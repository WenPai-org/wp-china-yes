<?php
/**
 * WP_Error stand-in for Connectivity unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * Transport-error stand-in.
	 */
	class WP_Error {}
}
