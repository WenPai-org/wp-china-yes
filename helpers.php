<?php

namespace WenPai\ChinaYes;

defined( 'ABSPATH' ) || exit;

// 获取插件设置
function get_settings() {
	static $cached_settings = null;
	
	if ($cached_settings === null) {
		$settings = is_multisite() ? get_site_option( 'wp_china_yes' ) : get_option( 'wp_china_yes' );
		$cached_settings = wp_parse_args( $settings, [
		'store'                => 'wenpai',
		'bridge'               => true,
		'arkpress'             => false,
		'admincdn'             => [ 'admin' ],
		'admincdn_public'      => [ 'googlefonts' ],
		'admincdn_files'       => [ 'admin', 'emoji' ],
		'admincdn_dev'         => [ 'jquery' ],
		'admincdn_version_enable' => false,
		'admincdn_version'     => [ 'css', 'js', 'timestamp' ],
		'cravatar'             => 'cn',
		'windfonts'            => 'off',
		'windfonts_list'       => [
			[
				'family'   => 'cszt',
				'subset'   => 'regular',
				'lang'     => '',
				'weight'   => 400,
				'style'    => 'normal',
				'selector' => 'a:not([class]),p,h1,h2,h3,h4,h5,h6,ul,ol,li,button,blockquote,pre,code,table,th,td,label,b,i:not([class]),em,small,strong,sub,sup,ins,del,mark,abbr,dfn,span:not([class])',
				'enable'   => true,
			]
		],
		'windfonts_typography_cn' => [],
		'windfonts_typography_en' => [],
		'windfonts_reading_enable' => false,
		'windfonts_reading' => 'off',
		'motucloud'            => 'off',
		'fewmail'              => 'off',
		'comments_enable'      => false,
		'comments_role_badge'  => true,
		'comments_remove_website' => false,
		'comments_validation'  => true,
		'comments_herp_derp'   => false,
		'comments_sticky_moderate' => false,
		'wordyeah'             => 'off',
		'bisheng'              => 'off',
		'deerlogin'            => 'off',
		'waimao'               => 'off',
		'waimao_enable'        => false,
		'waimao_language_split' => false,
		'waimao_admin_language' => 'zh_CN',
		'waimao_frontend_language' => 'en_US',
		'waimao_auto_detect'   => false,
		'woocn'                => 'off',
		'lelms'                => 'off',
		'wapuu'                => 'off',
		'adblock'              => 'off',
		'adblock_rule'         => [],
		'plane'                => 'off',
		'plane_rule'           => [],
		'monitor'              => true,
		'memory'               => true,
		'hide'                 => false,
		'custom_name'          => 'WP-China-Yes',
		'enabled_sections'     => [ 'welcome', 'store', 'admincdn', 'cravatar', 'other', 'about' ],
		'wp_memory_limit'      => '256M',
		'wp_max_memory_limit'  => '512M',
		'wp_post_revisions'    => 5,
		'autosave_interval'    => 300,
		'custom_rss_url'       => '',
		'custom_rss_refresh'   => 3600,
		'rss_display_options'  => [ 'show_date', 'show_summary', 'show_footer' ],
		] );
	}
	
	return $cached_settings;
}

function clear_settings_cache() {
	static $cached_settings = null;
	$cached_settings = null;
}
