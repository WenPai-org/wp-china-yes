<?php
/**
 * Keep Composer files autoload from exiting in CLI tools.
 *
 * helpers.php and framework/classes/setup.class.php abort when ABSPATH is
 * missing. WordPress already defines ABSPATH and core functions, so this
 * file returns immediately in production.
 *
 * @package WenPai\ChinaYes
 */

if ( defined( 'ABSPATH' ) && function_exists( 'wp_normalize_path' ) ) {
	return;
}

if ( defined( '__PHPSTAN_RUNNING__' ) ) {
	set_error_handler(
		static function ( $severity, $message ) {
			if ( E_WARNING === $severity && false !== strpos( $message, 'ABSPATH already defined' ) ) {
				return true;
			}
			return false;
		}
	);
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'CHINA_YES_VERSION' ) ) {
	define( 'CHINA_YES_VERSION', '4.0.0-dev' );
}

/*
 * setup.class.php always calls WP_CHINA_YES_Setup::init() at load time.
 * Provide empty stand-ins so CLI autoload does not need WordPress functions
 * (PHPStan later loads wordpress-stubs, which cannot redeclare them).
 */
if ( ! class_exists( 'WP_CHINA_YES_Setup', false ) ) {
	/**
	 * CLI stand-in; unused in WordPress.
	 */
	class WP_CHINA_YES_Setup {
		/**
		 * No-op init used only when WordPress is not loaded.
		 *
		 * @param string $file    Framework file.
		 * @param bool   $premium Premium flag.
		 * @return void
		 */
		public static function init( $file = '', $premium = false ) {
			unset( $file, $premium );
		}
	}
}

if ( ! class_exists( 'WP_CHINA_YES', false ) ) {
	class_alias( 'WP_CHINA_YES_Setup', 'WP_CHINA_YES' );
}
