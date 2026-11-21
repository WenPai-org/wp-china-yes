<?php
/**
 * WPCY 4.0 (文派叶子) 离线静态原型生成器 — 直接复用真实 `KitRenderer::render()` 单一真源。
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

$GLOBALS['_wp_options'] = array();
$GLOBALS['_wp_transients'] = array();

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_url_raw( $s ) { return (string) $s; }
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
	function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['_wp_transients'] ) ? $GLOBALS['_wp_transients'][ $k ] : false; }
	function get_plugins() { return array(); }
	function is_plugin_active() { return false; }
	function get_settings_errors() { return array(); }
	function wp_nonce_field( $a = -1, $n = '_wpnonce', $r = true, $e = true ) {
		unset( $a, $r );
		$h = '<input type="hidden" name="' . esc_attr( $n ) . '" value="demo">';
		if ( $e ) { echo $h; }
		return $h;
	}
	function wp_create_nonce() { return 'demo'; }
	function admin_url( $p = '' ) { return (string) $p; }
	function network_admin_url( $p = '' ) { return (string) $p; }
	function add_query_arg( $args, $url = '' ) {
		if ( ! is_array( $args ) ) {
			return (string) $url;
		}
		$page_files = array(
			'wpcy'            => 'overview.html',
			'wpcy-connect'    => 'settings.html',
			'wpcy-features'   => 'features.html',
			'wpcy-services'   => 'services.html',
			'wpcy-diagnose'   => 'diagnose.html',
			'wpcy-onboarding' => 'wizard.html',
		);
		if ( isset( $args['page'] ) && isset( $page_files[ $args['page'] ] ) ) {
			if ( isset( $args['view'] ) && 'help' === $args['view'] ) {
				return 'help.html';
			}
			$base = $page_files[ $args['page'] ];
			unset( $args['page'] );
			if ( empty( $args ) ) {
				return $base;
			}
			return $base . '?' . http_build_query( $args );
		}
		return (string) $url . ( empty( $args ) ? '' : '?' . http_build_query( $args ) );
	}
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

spl_autoload_register(
	static function ( string $class ) use ( $plugin_root ): void {
		$prefix = 'WenPai\\ChinaYes\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$file = $plugin_root . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);

function wpcy_proto_wrap( string $title, string $body ): string {
	$h  = "<!DOCTYPE html>\n";
	$h .= "<!-- 由 prototypes/wpcy/build.php 经真实 KitRenderer::render() 生成，请勿手工修改。 -->\n";
	$h .= "<html lang=\"zh-CN\">\n<head>\n<meta charset=\"utf-8\">\n";
	$h .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
	$h .= '<title>' . esc_html( $title ) . "</title>\n";
	foreach ( array( 'wenpai-tokens.css', 'wenpai-admin.css', 'wenpai-admin-wp.css' ) as $css ) {
		$h .= '<link rel="stylesheet" href="../../lib/wenpai-admin-ui/css/' . $css . "\">\n";
	}
	$h .= "<link rel=\"stylesheet\" href=\"../../assets/css/wpcy-admin.css\">\n";
	$h .= "</head>\n<body class=\"wp-admin wp-core-ui wenpai-ui-page\">\n";
	$h .= $body . "\n";
	$h .= "<script src=\"../../lib/wenpai-admin-ui/js/wenpai-admin.js\"></script>\n";
	$h .= "</body>\n</html>\n";
	return $h;
}

function wpcy_proto_render( string $page, array $settings_override = array(), array $get_override = array() ): string {
	$settings = array_replace_recursive( \WenPai\ChinaYes\Config\Defaults::settings(), $settings_override );
	$payload  = array(
		'pluginVersion' => '4.0.0',
		'settings'      => $settings,
		'capabilities'  => array(
			'network_admin'  => false,
			'network_locked' => false,
		),
		'links'         => array(
			'help' => 'https://wpcy.com/go/support',
		),
	);
	$prev_get = $_GET;
	$_GET     = $get_override;
	ob_start();
	\WenPai\ChinaYes\Admin\KitRenderer::render( $page, $payload );
	$html = (string) ob_get_clean();
	$_GET = $prev_get;
	return $html;
}

$pages = array(
	'overview.html'       => wpcy_proto_wrap( '概览 — 文派叶子 4.0', wpcy_proto_render( 'wpcy' ) ),
	'overview-error.html' => wpcy_proto_wrap( '概览（恢复模式） — 文派叶子 4.0', wpcy_proto_render( 'wpcy', array( 'recovery_mode' => true ) ) ),
	'settings.html'       => wpcy_proto_wrap( '设置 — 文派叶子 4.0', wpcy_proto_render( 'wpcy-connect' ) ),
	'features.html'       => wpcy_proto_wrap( '功能 — 文派叶子 4.0', wpcy_proto_render( 'wpcy-features' ) ),
	'services.html'       => wpcy_proto_wrap( '服务 — 文派叶子 4.0', wpcy_proto_render( 'wpcy-services' ) ),
	'diagnose.html'       => wpcy_proto_wrap( '诊断 — 文派叶子 4.0', wpcy_proto_render( 'wpcy-diagnose' ) ),
	'wizard.html'         => wpcy_proto_wrap( '首次配置 — 文派叶子 4.0', wpcy_proto_render( 'wpcy-onboarding' ) ),
	'help.html'           => wpcy_proto_wrap( '帮助与支持 — 文派叶子 4.0', wpcy_proto_render( 'wpcy', array(), array( 'view' => 'help' ) ) ),
);

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
