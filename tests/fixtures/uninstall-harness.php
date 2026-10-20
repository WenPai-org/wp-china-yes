<?php
/**
 * Runs uninstall.php against recording stubs and prints the calls as JSON.
 *
 * Usage: php uninstall-harness.php <single|multisite|no-constant>
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

// phpcs:disable

$GLOBALS['wpcy_calls'] = array();
$GLOBALS['wpcy_blog']  = 1;
$wpcy_mode             = $argv[1] ?? 'single';

register_shutdown_function(
	static function () {
		echo json_encode( $GLOBALS['wpcy_calls'] );
	}
);

function delete_option( $name ) {
	$GLOBALS['wpcy_calls'][] = array( 'delete_option', $GLOBALS['wpcy_blog'], $name );
	return true;
}
function delete_transient( $name ) {
	$GLOBALS['wpcy_calls'][] = array( 'delete_transient', $GLOBALS['wpcy_blog'], $name );
	return true;
}
function is_multisite() {
	return 'multisite' === $GLOBALS['wpcy_mode'];
}
function get_sites( $args ) {
	$GLOBALS['wpcy_calls'][] = array( 'get_sites', $GLOBALS['wpcy_blog'], $args );
	return array( 1, 2, 3 );
}
function switch_to_blog( $id ) {
	$GLOBALS['wpcy_blog'] = (int) $id;
	return true;
}
function restore_current_blog() {
	$GLOBALS['wpcy_blog'] = 1;
	return true;
}

$GLOBALS['wpcy_mode'] = $wpcy_mode;
define( 'ABSPATH', __DIR__ . '/' );
if ( 'no-constant' !== $wpcy_mode ) {
	define( 'WP_UNINSTALL_PLUGIN', 'wp-china-yes/wp-china-yes.php' );
}

require dirname( __DIR__, 2 ) . '/uninstall.php';
