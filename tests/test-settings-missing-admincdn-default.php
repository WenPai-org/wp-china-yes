<?php
declare(strict_types=1);

defined('ABSPATH') || define('ABSPATH', __DIR__);

function get_option( $key, $default = false ) {
	global $mock_option;
	return $mock_option[ $key ] ?? $default;
}

function get_site_option( $key, $default = false ) {
	return get_option( $key, $default );
}

function is_multisite() {
	return false;
}

function wp_parse_args( $args, $defaults = [] ) {
	if ( is_object( $args ) ) {
		$parsed_args = get_object_vars( $args );
	} elseif ( is_array( $args ) ) {
		$parsed_args = $args;
	} else {
		parse_str( $args, $parsed_args );
	}

	return array_merge( $defaults, $parsed_args );
}

global $mock_option;
$mock_option = [
	'wp_china_yes' => [
		'bridge' => true,
		'cravatar' => 'cn',
	],
];

require_once __DIR__ . '/../helpers.php';

$settings = \WenPai\ChinaYes\get_settings();

if ( $settings['admincdn'] !== [] ) {
	fwrite( STDERR, "FAIL: missing admincdn key should stay disabled for existing settings.\n" );
	var_export( $settings['admincdn'] );
	exit( 1 );
}

echo "PASS: missing admincdn key stays disabled for existing settings.\n";
