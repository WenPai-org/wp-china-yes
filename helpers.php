<?php

namespace WenPai\ChinaYes;

defined( 'ABSPATH' ) || exit;

// 获取插件设置
function get_settings() {
	$settings = is_multisite() ? get_site_option( 'wp_china_yes' ) : get_option( 'wp_china_yes' );

	return wp_parse_args( $settings, [
		'store'     => 'wenpai',
		'admincdn'  => [
			'admin' => 'admin',
		],
		'cravatar'  => 'cn',
		'windfonts' => 'off',
		'adblock'   => 'off',
	] );
}
