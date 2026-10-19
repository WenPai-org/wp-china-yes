<?php
/**
 * PHPUnit unit bootstrap. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'CHINA_YES_VERSION' ) ) {
	define( 'CHINA_YES_VERSION', '3.9.3' );
}

if ( ! defined( 'CHINA_YES_PLUGIN_FILE' ) ) {
	define( 'CHINA_YES_PLUGIN_FILE', dirname( __DIR__ ) . '/wp-china-yes.php' );
}

if ( ! defined( 'CHINA_YES_PLUGIN_URL' ) ) {
	define( 'CHINA_YES_PLUGIN_URL', 'http://example.test/wp-content/plugins/wp-china-yes/' );
}

if ( ! defined( 'CHINA_YES_PLUGIN_PATH' ) ) {
	define( 'CHINA_YES_PLUGIN_PATH', dirname( __DIR__ ) . '/' );
}

require dirname( __DIR__ ) . '/vendor/autoload.php';
