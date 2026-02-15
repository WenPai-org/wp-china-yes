<?php
/**
 * WenPai Bridge — 客户端引导加载器
 *
 * 在 wp-china-yes 主文件中 require 此文件即可启用遥测功能。
 * 不依赖任何框架，可在插件重构前后直接使用。
 *
 * 用法：
 *   require_once __DIR__ . '/client/wenpai-bridge-client.php';
 *
 * @package WenPai\Bridge
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

// 加载模块
require_once __DIR__ . '/class-site-identity.php';
require_once __DIR__ . '/class-site-health.php';
require_once __DIR__ . '/class-fallback.php';

// 初始化遥测（在 plugins_loaded 之后，确保 WordPress API 可用）
add_action( 'init', [ 'WenPai_Bridge_Site_Health', 'init' ] );
add_action( 'init', [ 'WenPai_Bridge_Fallback', 'init' ] );

// Phase 3: 在发往云桥的 HTTP 请求中注入 X-Site-UUID 头（用于灰度发布分组）
add_filter( 'http_request_args', function ( $args, $url ) {
	$parsed = parse_url( $url );
	if ( isset( $parsed['host'] ) && 'updates.wenpai.net' === $parsed['host'] ) {
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = [];
		}
		$args['headers']['X-Site-UUID'] = WenPai_Bridge_Site_Identity::get_uuid();
	}
	return $args;
}, 10, 2 );

// 插件停用时清理
register_deactivation_hook(
	defined( 'CHINA_YES_PLUGIN_FILE' ) ? CHINA_YES_PLUGIN_FILE : __FILE__,
	[ 'WenPai_Bridge_Site_Health', 'deactivate' ]
);
