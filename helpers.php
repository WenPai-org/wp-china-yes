<?php

namespace WenPai\ChinaYes;

defined( 'ABSPATH' ) || exit;

// 获取插件设置
function get_settings() {
	$settings = is_multisite() ? get_site_option( 'wp_china_yes' ) : get_option( 'wp_china_yes' );

	return wp_parse_args( $settings, [
		'store'                => 'wenpai',
		'admincdn'             => [ 'admin' ],
		'cravatar'             => 'cn',
		'windfonts'            => 'off',
		'windfonts_list'       => [],
		'windfonts_typography' => [],
		'adblock'              => 'off',
		'adblock_rule'         => [],
		'plane'                => 'off',
		'plane_rule'           => [],
		'monitor'              => true,
		'memory'               => true,
		'hide'                 => false,
		'custom_name'          => 'WP-China-Yes',
	] );
}
