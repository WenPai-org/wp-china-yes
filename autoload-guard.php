<?php
/**
 * Keep Composer files autoload from exiting in CLI tools.
 *
 * helpers.php aborts when ABSPATH is missing. WordPress already defines
 * ABSPATH and core functions, so this file returns immediately in production.
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
