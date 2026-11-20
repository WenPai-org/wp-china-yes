<?php
/**
 * wpcy 4.0 (文派叶子) 正式定版原型生成器 — 路线 A · 方案二（数据中枢组合）。
 * 全部基于自带 lib/wenpai-admin-ui (v0.11.1) 真实 PHP helper，0 自造 CSS、0 内联 style。
 *
 * Usage:
 *   php prototypes/wpcy/build.php
 *   php prototypes/wpcy/build.php --check
 */

$plugin_root = dirname( __DIR__, 2 );
$kit_root    = $plugin_root . '/lib/wenpai-admin-ui';
$out_dir     = __DIR__;
$check       = in_array( '--check', $argv, true );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $plugin_root . '/' );
}
$GLOBALS['_wp_options'] = array(
	'wpcy_settings' => array(
		'profile'           => 'domestic',
		'public_assets'     => true,
		'avatar_enabled'    => true,
		'windfonts_enabled' => false,
		'wporg_mirror'      => 'auto',
		'assets_scope'      => 'both',
		'avatar_admin'      => 'cravatar_cn',
		'avatar_front'      => 'cravatar_cn',
		'heartbeat'         => true,
		'block_feeds'       => true,
	),
);
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_html__( $s ) { return esc_html( $s ); }
	function esc_attr__( $s ) { return esc_attr( $s ); }
	function esc_html_e( $s ) { echo esc_html( $s ); }
	function esc_html_x( $s ) { return esc_html( $s ); }
	function esc_attr_x( $s ) { return esc_attr( $s ); }
	function __( $s ) { return (string) $s; }
	function _x( $s ) { return (string) $s; }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_array( $args ) ) { $r = $args; } elseif ( is_object( $args ) ) { $r = get_object_vars( $args ); } else { parse_str( (string) $args, $r ); }
		return array_merge( (array) $defaults, $r );
	}
	function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
	function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
	function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $s ); }
	function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
	function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
	function wp_unslash( $s ) { return $s; }
	function wp_kses( $s ) { return (string) $s; }
	function wp_kses_post( $s ) { return (string) $s; }
	function wp_kses_allowed_html() { return array(); }
	function is_admin() { return true; }
	function add_action() {}
	function add_filter() {}
	function apply_filters( $tag, $val ) { return $val; }
	function register_setting() {}
	function get_option( $k, $default = false ) { return array_key_exists( $k, $GLOBALS['_wp_options'] ) ? $GLOBALS['_wp_options'][ $k ] : $default; }
	function update_option( $k, $v ) { $GLOBALS['_wp_options'][ $k ] = $v; return true; }
	function get_settings_errors() { return array(); }
	function wp_nonce_field( $a = -1, $n = '_wpnonce', $r = true, $e = true ) { $h = '<input type="hidden" name="' . esc_attr( $n ) . '" value="demo">'; if ( $e ) { echo $h; } return $h; }
	function wp_create_nonce() { return 'demo'; }
	function admin_url( $p = '' ) { return '#admin-' . $p; }
	function add_query_arg( $a, $u = '' ) { return $u; }
	function get_bloginfo( $show = '' ) { return 'name' === $show ? '示例站点' : 'https://example.com'; }
	function home_url( $p = '' ) { return 'https://example.com' . $p; }
	function current_time() { return gmdate( 'Y-m-d H:i:s' ); }
	function selected( $a, $b, $e = true ) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $e ) { echo $r; } return $r; }
	function checked( $a, $b = true, $e = true ) { $r = (bool) $a === (bool) $b ? ' checked="checked"' : ''; if ( $e ) { echo $r; } return $r; }
	function disabled( $a, $b = true, $e = true ) { $r = (bool) $a === (bool) $b ? ' disabled="disabled"' : ''; if ( $e ) { echo $r; } return $r; }
	function wp_json_encode( $d ) { return json_encode( $d ); }
}

foreach ( array( 'icons', 'menu', 'ui', 'action', 'settings', 'tools', 'shell' ) as $part ) {
	require_once $kit_root . '/php/class-wenpai-admin-' . $part . '.php';
}

function wpcy_v4_mark() {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>';
}

function wpcy_v4_chart_svg() {
	// Tokenized SVG colors (var(--wenpai-*)) inside chart_frame.
	return '<svg data-wenpai-art viewBox="0 0 640 200" width="100%" height="200" role="img" aria-label="近 7 日加速请求趋势">'
		. '<defs><linearGradient id="wpcyArea" x1="0" y1="0" x2="0" y2="1">'
		. '<stop offset="0%" stop-color="var(--wenpai-accent)" stop-opacity="0.18"/>'
		. '<stop offset="100%" stop-color="var(--wenpai-accent)" stop-opacity="0.01"/>'
		. '</linearGradient></defs>'
		. '<g stroke="var(--wenpai-line)" stroke-width="1">'
		. '<line x1="40" y1="24" x2="620" y2="24"/><line x1="40" y1="74" x2="620" y2="74"/>'
		. '<line x1="40" y1="124" x2="620" y2="124"/><line x1="40" y1="172" x2="620" y2="172"/>'
		. '</g>'
		. '<g fill="var(--wenpai-ink-3)" font-size="11">'
		. '<text x="8" y="28">1.2k</text><text x="12" y="78">800</text><text x="12" y="128">400</text><text x="24" y="176">0</text>'
		. '<text x="40" y="192">周一</text><text x="136" y="192">周二</text><text x="232" y="192">周三</text>'
		. '<text x="328" y="192">周四</text><text x="424" y="192">周五</text><text x="520" y="192">周六</text><text x="596" y="192">今天</text>'
		. '</g>'
		. '<path d="M40 128 L136 104 L232 114 L328 72 L424 84 L520 52 L616 38 L616 172 L40 172 Z" fill="url(#wpcyArea)"/>'
		. '<path d="M40 128 L136 104 L232 114 L328 72 L424 84 L520 52 L616 38" fill="none" stroke="var(--wenpai-accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>'
		. '<g fill="var(--wenpai-surface)" stroke="var(--wenpai-accent)" stroke-width="2">'
		. '<circle cx="40" cy="128" r="3.5"/><circle cx="136" cy="104" r="3.5"/><circle cx="232" cy="114" r="3.5"/>'
		. '<circle cx="328" cy="72" r="3.5"/><circle cx="424" cy="84" r="3.5"/><circle cx="520" cy="52" r="3.5"/><circle cx="616" cy="38" r="4"/>'
		. '</g>'
		. '</svg>';
}

function wpcy_v4_shell( $current ) {
	return array(
		'name'      => '文派叶子',
		'mark_html' => wpcy_v4_mark(),
		'home_url'  => 'overview.html',
		'current'   => $current,
		'tabs'      => array(
			array( 'id' => 'overview', 'label' => '概览', 'url' => 'overview.html' ),
			array( 'id' => 'settings', 'label' => '设置', 'url' => 'settings.html' ),
			array( 'id' => 'services', 'label' => '服务', 'url' => 'services.html' ),
			array( 'id' => 'diagnose', 'label' => '诊断', 'url' => 'diagnose.html' ),
		),
		'version'   => '文派叶子 4.0.0 · 已运行 14 天',
		'doc_url'   => 'https://wpcy.com/document',
		'doc_label' => '帮助',
	);
}

$fw = Wenpai_Admin_Settings::register(
	'wpcy_settings',
	array(
		'profile'           => array(
			'type'    => 'choice',
			'layout'  => 'stack',
			'default' => 'domestic',
			'label'   => '站点场景',
			'choices' => array(
				'domestic'    => array( 'label' => '国内站', 'desc' => '站点和访客都在中国大陆，默认走国内镜像与 Cravatar 国内节点。' ),
				'crossborder' => array( 'label' => '跨境站', 'desc' => '访客主要在海外，后台走加速，前台保持直连。' ),
				'mixed'       => array( 'label' => '混合站', 'desc' => '国内外都有访客，按后台与前台分别选择线路。' ),
			),
		),
		'public_assets'     => array( 'type' => 'toggle', 'default' => true, 'label' => '加速常用前端资源' ),
		'avatar_enabled'    => array( 'type' => 'toggle', 'default' => true, 'label' => '头像走 Cravatar' ),
		'windfonts_enabled' => array( 'type' => 'toggle', 'default' => false, 'label' => '中文字体（Windfonts）' ),
		'wporg_mirror'      => array(
			'type'    => 'radio',
			'default' => 'auto',
			'label'   => 'WordPress.org 源',
			'choices' => array(
				'auto' => '国内镜像（不可用时自动回退官方源）',
				'off'  => '直连 WordPress.org',
			),
		),
		'assets_scope'      => array(
			'type'         => 'radio',
			'presentation' => 'seg',
			'default'      => 'both',
			'label'        => '公共前端库范围',
			'choices'      => array(
				'both'     => '后台与前台',
				'admin'    => '仅后台',
				'frontend' => '仅前台',
				'off'      => '关闭',
			),
		),
		'avatar_admin'      => array(
			'type'    => 'select',
			'default' => 'cravatar_cn',
			'label'   => '后台头像线路',
			'choices' => array(
				'cravatar_cn'     => 'Cravatar 中国线路（国内节点）',
				'cravatar_global' => 'Cravatar 国际线路（全球节点）',
				'off'             => '关闭（保留 Gravatar）',
			),
		),
		'avatar_front'      => array(
			'type'    => 'select',
			'default' => 'cravatar_cn',
			'label'   => '前台头像线路',
			'choices' => array(
				'cravatar_cn'     => 'Cravatar 中国线路（国内节点）',
				'cravatar_global' => 'Cravatar 国际线路（全球节点）',
				'off'             => '关闭（保留 Gravatar）',
			),
		),
		'heartbeat'         => array( 'type' => 'toggle', 'default' => true, 'label' => '减少后台心跳', 'desc' => '仪表盘停止轮询，编辑器降为 60 秒一次。' ),
		'block_feeds'       => array( 'type' => 'toggle', 'default' => true, 'label' => '不加载仪表盘外部内容', 'desc' => '屏蔽 WordPress 新闻、活动等出站请求。' ),
	),
	array( 'layout' => 'row' )
);

function wpcy_v4_wrap( $title, $body ) {
	$h  = "<!DOCTYPE html>\n";
	$h .= "<!-- 由 prototypes/wpcy/build.php 生成（kit v0.11.1 真实 helper），不要手改。 -->\n";
	$h .= "<html lang=\"zh-CN\">\n<head>\n<meta charset=\"utf-8\">\n";
	$h .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
	$h .= '<title>' . esc_html( $title ) . "</title>\n";
	foreach ( array( 'wenpai-tokens.css', 'wenpai-admin.css', 'wenpai-admin-wp.css' ) as $css ) {
		$h .= '<link rel="stylesheet" href="../../lib/wenpai-admin-ui/css/' . $css . "\">\n";
	}
	$h .= "</head>\n<body class=\"wp-admin wp-core-ui wenpai-ui-page\">\n";
	$h .= $body . "\n";
	$h .= "<script src=\"../../lib/wenpai-admin-ui/js/wenpai-admin.js\"></script>\n";
	$h .= "</body>\n</html>\n";
	return $h;
}

$pages = array();

// 1. 概览（正常 · 方案二数据中枢 + 折叠日志）
ob_start();
$sh = wpcy_v4_shell( 'overview' );
Wenpai_Admin_Shell::open( $sh );
Wenpai_Admin_Shell::page_head(
	'概览',
	'当前场景：国内站。过去 7 天共加速 5,766 次外部请求，平均提速 14 倍。',
	Wenpai_Admin_UI::button_html( array( 'label' => '立即诊断', 'href' => 'diagnose.html', 'variant' => 'primary' ) )
);
echo '<div class="wenpai-stack">';
Wenpai_Admin_UI::card_open();
Wenpai_Admin_UI::statbar(
	array(
		'items' => array(
			array( 'label' => '7 日总加速', 'value' => '5,766', 'unit' => '次' ),
			array( 'label' => '镜像平均延迟', 'value' => '38', 'unit' => 'ms' ),
			array( 'label' => '头像代理', 'value' => '1,126', 'unit' => '次' ),
			array( 'label' => '拦截外部慢请求', 'value' => '342', 'unit' => '次' ),
		),
	)
);
Wenpai_Admin_UI::card_close();

echo '<div class="wenpai-split">';
Wenpai_Admin_UI::card_open( array( 'title' => '近 7 日加速趋势', 'desc' => '每日代理请求总数（核心更新 + 前端资源 + 头像）', 'tile' => false ) );
Wenpai_Admin_UI::chart_frame( array( 'plot_html' => wpcy_v4_chart_svg(), 'aria_label' => '近 7 日加速请求趋势' ) );
Wenpai_Admin_UI::card_close();

Wenpai_Admin_UI::card_open( array( 'title' => '节点延迟对比', 'desc' => '越短越快（最近一次探测）', 'tile' => false ) );
Wenpai_Admin_UI::meter(
	array(
		'items' => array(
			array( 'name' => 'Cravatar 国内', 'value' => '29ms', 'percent' => 8 ),
			array( 'name' => '前端公共库 CDN', 'value' => '34ms', 'percent' => 10 ),
			array( 'name' => 'WP.org 国内镜像', 'value' => '38ms', 'percent' => 12 ),
			array( 'name' => 'WP.org 官方直连', 'value' => '612ms', 'percent' => 92 ),
		),
	)
);
Wenpai_Admin_UI::card_close();
echo '</div>';

Wenpai_Admin_UI::card_open( array( 'title' => '核心通道状态', 'icon' => 'cloud' ) );
Wenpai_Admin_UI::row( array( 'icon' => 'cloud', 'state' => 'ok', 'title' => 'WordPress.org 更新与安装', 'desc' => '国内镜像 · 平均延迟 38ms', 'pill' => array( 'text' => '正常', 'state' => 'ok' ) ) );
Wenpai_Admin_UI::row( array( 'icon' => 'flash', 'state' => 'ok', 'title' => '常用前端资源', 'desc' => '后台与前台 · Google Fonts / CDNJS / Emoji', 'pill' => array( 'text' => '已开启', 'state' => 'ok' ) ) );
Wenpai_Admin_UI::row( array( 'icon' => 'image', 'state' => 'ok', 'title' => 'Cravatar 头像', 'desc' => '后台与前台 · 国内节点', 'pill' => array( 'text' => '已开启', 'state' => 'ok' ) ) );
Wenpai_Admin_UI::row( array( 'icon' => 'feather', 'title' => '中文字体（Windfonts）', 'desc' => '绑定站点后可开启中文字体子集加速', 'pill' => array( 'text' => '未开启', 'state' => 'mute' ) ) );
Wenpai_Admin_UI::fold_list(
	array(
		'items' => array(
			array(
				'title'     => '最近事件记录',
				'desc'      => '今日 1 条 · 全部正常（点击展开）',
				'body_html' => Wenpai_Admin_UI::log_list_html(
					array(
						'items' => array(
							array( 'when' => '今天 02:18', 'state' => 'ok', 'label' => '更新', 'detail' => '完成 12 个插件版本检查', 'aside' => '41ms' ),
							array( 'when' => '昨天 19:40', 'state' => 'ok', 'label' => '语言包', 'detail' => '同步 WordPress 6.7 中文包', 'aside' => '128ms' ),
							array( 'when' => '2 天前', 'state' => 'ok', 'label' => '巡检', 'detail' => '4 条线路全部 HTTP 200', 'aside' => '正常' ),
						),
					)
				),
			),
		),
	)
);
Wenpai_Admin_UI::card_foot(
	array(
		'start'   => true,
		'note'    => '按站点场景自动配好，一般无需频繁调整。',
		'actions' => array(
			array( 'label' => '调整设置', 'href' => 'settings.html', 'variant' => 'secondary' ),
			array( 'label' => '查看异常态', 'href' => 'overview-error.html', 'variant' => 'ghost' ),
		),
	)
);
Wenpai_Admin_UI::card_close();
echo '</div>';
Wenpai_Admin_Shell::close( $sh );
$pages['overview.html'] = wpcy_v4_wrap( '概览 — 文派叶子 4.0', ob_get_clean() );

// 2. 概览（异常态）
ob_start();
Wenpai_Admin_Shell::open( $sh );
Wenpai_Admin_Shell::page_head(
	'概览',
	'有 1 条加速通道需要关注。',
	Wenpai_Admin_UI::button_html( array( 'label' => '返回正常态', 'href' => 'overview.html', 'variant' => 'ghost' ) )
);
echo '<div class="wenpai-stack">';
Wenpai_Admin_UI::next(
	array(
		'state'   => 'warn',
		'icon'    => 'info',
		'title'   => '国内镜像暂时超时，已自动回退到 WordPress.org 官方源',
		'desc'    => '前台访问不受影响，但后台插件安装可能变慢。建议运行一次诊断确认网络状况。',
		'actions' => array(
			array( 'label' => '立即诊断', 'href' => 'diagnose.html', 'variant' => 'primary' ),
			array( 'label' => '切换线路', 'href' => 'settings.html', 'variant' => 'ghost' ),
		),
	)
);
Wenpai_Admin_UI::card_open( array( 'title' => '核心通道状态', 'icon' => 'cloud' ) );
Wenpai_Admin_UI::row( array( 'icon' => 'cloud', 'state' => 'warn', 'title' => 'WordPress.org 更新与安装', 'desc' => '国内镜像超时（5s），已自动回退官方源', 'pill' => array( 'text' => '已回退', 'state' => 'warn' ) ) );
Wenpai_Admin_UI::row( array( 'icon' => 'flash', 'state' => 'ok', 'title' => '常用前端资源', 'desc' => '后台与前台 · 正常', 'pill' => array( 'text' => '正常', 'state' => 'ok' ) ) );
Wenpai_Admin_UI::row( array( 'icon' => 'image', 'state' => 'ok', 'title' => 'Cravatar 头像', 'desc' => '国内节点 · 正常', 'pill' => array( 'text' => '正常', 'state' => 'ok' ) ) );
Wenpai_Admin_UI::card_close();
echo '</div>';
Wenpai_Admin_Shell::close( $sh );
$pages['overview-error.html'] = wpcy_v4_wrap( '概览（异常态）— 文派叶子 4.0', ob_get_clean() );

// 3. 设置
ob_start();
$sh = wpcy_v4_shell( 'settings' );
Wenpai_Admin_Shell::open( $sh );
Wenpai_Admin_Shell::page_head(
	'设置',
	'核心开关即时生效；站点场景与下方高级线路点击「保存设置」后统一生效。',
	Wenpai_Admin_UI::button_html(
		array(
			'label'   => '保存设置',
			'type'    => 'submit',
			'variant' => 'primary',
			'form'    => 'wpcy-settings-form',
		)
	)
);
echo '<form id="wpcy-settings-form" class="wenpai-stack" method="post" action="options.php">';
Wenpai_Admin_UI::card_open();
$fw->render_field( 'profile' );
Wenpai_Admin_UI::card_close();

Wenpai_Admin_UI::card_open();
Wenpai_Admin_UI::section_title( array( 'title' => '核心加速', 'desc' => '按站点场景自动配好，开关即时生效。' ) );
Wenpai_Admin_UI::row( array( 'icon' => 'cloud', 'state' => 'ok', 'title' => 'WordPress 更新与安装', 'desc' => '随场景自动选源，高级项中可手动覆盖。', 'pill' => array( 'text' => '国内镜像', 'state' => 'ok' ) ) );
Wenpai_Admin_UI::row( array( 'icon' => 'flash', 'state' => 'ok', 'title' => '加速常用前端资源', 'title_id' => 'wpcy-r-assets', 'desc' => 'Google Fonts / CDNJS / jsDelivr / Emoji（后台与前台）。', 'desc_id' => 'wpcy-r-assets-d', 'control_html' => $fw->render_control_html( 'public_assets', array( 'labelledby' => 'wpcy-r-assets', 'describedby' => 'wpcy-r-assets-d' ) ) ) );
Wenpai_Admin_UI::row( array( 'icon' => 'image', 'state' => 'ok', 'title' => '头像走 Cravatar', 'title_id' => 'wpcy-r-avatar', 'desc' => '替代 Gravatar，头像不再空白（后台与前台）。', 'desc_id' => 'wpcy-r-avatar-d', 'control_html' => $fw->render_control_html( 'avatar_enabled', array( 'labelledby' => 'wpcy-r-avatar', 'describedby' => 'wpcy-r-avatar-d' ) ) ) );
Wenpai_Admin_UI::row( array( 'icon' => 'feather', 'title' => '中文字体（Windfonts）', 'title_id' => 'wpcy-r-fonts', 'desc' => '免费商用中文字体按需加速，绑定本站后可用。', 'desc_id' => 'wpcy-r-fonts-d', 'control_html' => $fw->render_control_html( 'windfonts_enabled', array( 'labelledby' => 'wpcy-r-fonts', 'describedby' => 'wpcy-r-fonts-d' ) ) ) );

$adv  = $fw->field_html( 'wporg_mirror' );
$adv .= $fw->field_html( 'assets_scope' );
$adv .= $fw->field_html( 'avatar_admin' );
$adv .= $fw->field_html( 'avatar_front' );
$adv .= $fw->field_html( 'heartbeat' );
$adv .= $fw->field_html( 'block_feeds' );
Wenpai_Admin_UI::fold_list(
	array(
		'items' => array(
			array(
				'title'     => '高级线路与后台体验',
				'desc'      => '镜像自动回退 · 前后台加速 · Cravatar 国内节点 · 心跳与外部请求已优化',
				'body_html' => $adv,
			),
		),
	)
);
Wenpai_Admin_UI::card_close();
echo '</form>';
Wenpai_Admin_Shell::close( $sh );
$pages['settings.html'] = wpcy_v4_wrap( '设置 — 文派叶子 4.0', ob_get_clean() );

// 4. 服务（方案二 · 3 列卡片网格）
ob_start();
$sh = wpcy_v4_shell( 'services' );
Wenpai_Admin_Shell::open( $sh );
Wenpai_Admin_Shell::page_head(
	'服务',
	'基础加速免费自带、无需绑定；扩展服务按需开通。',
	Wenpai_Admin_UI::button_html( array( 'label' => '绑定文派云桥', 'variant' => 'primary' ) )
);
echo '<div class="wenpai-grid-3">';
$svc_cards = array(
	array( 'WP.org 国内镜像', 'cloud', '核心、主题、插件更新与中文翻译包自动走国内镜像，上游异常自动回退。', '免费 · 无需绑定', '运行中', true ),
	array( 'Cravatar 公共头像', 'image', 'Gravatar 的中国替代服务，支持后台与前台分别指定国内或国际节点。', '免费 · 无需绑定', '运行中', true ),
	array( 'Windfonts 中文字体', 'feather', '免费商用中文字体按需子集化分发，降低首屏体积，无需自行托管字体包。', '绑定站点后解锁', '去绑定', false ),
	array( '公共前端库加速', 'flash', 'Google Fonts、CDNJS、jsDelivr 与 Emoji 静态资源自动切换至更快节点。', '免费 · 无需绑定', '运行中', true ),
	array( '后台减噪优化', 'settings', '降低 Heartbeat 轮询频率，屏蔽仪表盘外部慢请求，缩短后台等待时间。', '免费 · 无需绑定', '已开启', true ),
	array( '云桥统一授权', 'plug', '一次绑定即可同步文派生态插件的商业授权与更新通道，免逐个填密钥。', '可选扩展', '了解详情', false ),
);
foreach ( $svc_cards as $c ) {
	Wenpai_Admin_UI::card_open( array( 'title' => $c[0], 'icon' => $c[1] ) );
	echo '<p class="meta">' . esc_html( $c[2] ) . '</p>';
	Wenpai_Admin_UI::card_foot(
		array(
			'start'   => true,
			'meta'    => $c[3],
			'actions' => array(
				array(
					'label'    => $c[4],
					'variant'  => $c[5] ? 'ghost' : 'secondary',
					'disabled' => $c[5],
				),
			),
		)
	);
	Wenpai_Admin_UI::card_close();
}
echo '</div>';
Wenpai_Admin_Shell::close( $sh );
$pages['services.html'] = wpcy_v4_wrap( '服务 — 文派叶子 4.0', ob_get_clean() );

// 5. 诊断（方案二 · 左右双栏对照 + 工具 + 恢复模式）
ob_start();
$sh = wpcy_v4_shell( 'diagnose' );
Wenpai_Admin_Shell::open( $sh );
Wenpai_Admin_Shell::page_head(
	'诊断',
	'左右对照服务器与浏览器两端的连通性，一眼定位瓶颈。',
	Wenpai_Admin_UI::button_html( array( 'label' => '并发重测', 'variant' => 'primary' ) )
);
echo '<div class="wenpai-stack">';
echo '<div class="wenpai-grid-2">';
Wenpai_Admin_UI::card_open( array( 'title' => '服务器到各源', 'icon' => 'server' ) );
Wenpai_Admin_UI::status_list(
	array(
		'items' => array(
			array( 'label' => 'WP.org 国内镜像', 'value' => '38ms · HTTP 200', 'state' => 'ok' ),
			array( 'label' => 'WP.org 官方源', 'value' => '612ms · HTTP 200', 'state' => 'warn' ),
			array( 'label' => 'Cravatar API', 'value' => '29ms · HTTP 200', 'state' => 'ok' ),
			array( 'label' => '文派云桥更新源', 'value' => '45ms · HTTP 200', 'state' => 'ok' ),
		),
	)
);
Wenpai_Admin_UI::card_close();

Wenpai_Admin_UI::card_open( array( 'title' => '浏览器到各源', 'icon' => 'eye' ) );
Wenpai_Admin_UI::status_list(
	array(
		'items' => array(
			array( 'label' => 'Cravatar 头像节点', 'value' => '24ms · 正常', 'state' => 'ok' ),
			array( 'label' => '公共前端库 CDN', 'value' => '31ms · 正常', 'state' => 'ok' ),
			array( 'label' => 'Windfonts 字体源', 'value' => '42ms · 正常', 'state' => 'ok' ),
			array( 'label' => 'Gravatar 原站', 'value' => '超时 (>3s)', 'state' => 'bad' ),
		),
	)
);
Wenpai_Admin_UI::card_close();
echo '</div>';

$fw->render_tools( array( 'export' => true, 'import' => true, 'reset' => true ) );

Wenpai_Admin_UI::card_open( array( 'title' => '紧急恢复', 'desc' => '遇到资源加载异常时，一键暂停全部代理规则并保留当前设置。', 'tile' => false ) );
Wenpai_Admin_UI::task(
	array(
		'title'       => '暂停全部加速规则',
		'desc'        => '立即恢复 WordPress 原始直连，可随时在设置页重新开启。',
		'action_html' => Wenpai_Admin_UI::button_html(
			array(
				'label'   => '进入恢复模式',
				'variant' => 'danger',
				'attrs'   => Wenpai_Admin_UI::confirm_attrs( '将立即暂停所有镜像与资源代理并切回直连，确定继续？' ),
			)
		),
	)
);
Wenpai_Admin_UI::card_close();
echo '</div>';
Wenpai_Admin_Shell::close( $sh );
$pages['diagnose.html'] = wpcy_v4_wrap( '诊断 — 文派叶子 4.0', ob_get_clean() );

// 6. 向导
ob_start();
Wenpai_Admin_UI::wizard_open(
	array(
		'name'      => '文派叶子',
		'mark_html' => wpcy_v4_mark(),
		'title'     => '首次配置',
		'desc'      => '选定站点场景后，自动套用最合适的默认加速方案，30 秒完成。',
		'exit_url'  => 'overview.html',
	)
);
Wenpai_Admin_UI::steps(
	array(
		'current' => 1,
		'items'   => array(
			array( 'title' => '选站点场景', 'desc' => '国内 / 跨境 / 混合' ),
			array( 'title' => '检测连通性', 'desc' => '验证镜像与头像源' ),
			array( 'title' => '完成启用', 'desc' => '进入概览' ),
		),
	)
);
Wenpai_Admin_UI::card_open();
$fw->render_field( 'profile' );
Wenpai_Admin_UI::card_foot(
	array(
		'actions' => array(
			array( 'label' => '跳过', 'href' => 'overview.html', 'variant' => 'ghost' ),
			array( 'label' => '下一步：检测连通性', 'href' => 'overview.html', 'variant' => 'primary' ),
		),
	)
);
Wenpai_Admin_UI::card_close();
Wenpai_Admin_UI::wizard_close();
$pages['wizard.html'] = wpcy_v4_wrap( '首次配置 — 文派叶子 4.0', ob_get_clean() );

$stale = 0;
foreach ( $pages as $file => $html ) {
	$target = $out_dir . '/' . $file;
	if ( $check ) {
		if ( ! is_file( $target ) || file_get_contents( $target ) !== $html ) {
			fwrite( STDERR, "FAIL stale prototype: {$target}\n" );
			++$stale;
		}
	} else {
		file_put_contents( $target, $html );
		fwrite( STDOUT, "wrote prototypes/wpcy/{$file}\n" );
	}
}
if ( $check ) {
	if ( $stale > 0 ) {
		exit( 1 );
	}
	fwrite( STDOUT, 'PASS prototypes/wpcy up to date (' . count( $pages ) . " pages)\n" );
}
