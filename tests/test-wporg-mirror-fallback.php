<?php
/**
 * WordPress.org 镜像可用性兜底测试
 *
 * 纯 PHP，不依赖 WordPress 运行时，也不发真实网络请求。
 *
 * 背景：`filter_wordpress_org()` 把 api.wordpress.org / downloads.wordpress.org
 * 的请求改写到自家镜像，而这个替换**默认开启**（store 默认为 wenpai）。
 * 镜像一旦不能提供安装包，站点的插件/主题搜索、信息查询、安装、更新下载
 * 会全链路失效 —— 把可用的上游换成不可用的镜像，比不加速糟得多。
 *
 * 探测判据必须同时看**状态码和内容类型**：镜像坏掉时会以 WP REST 的 404
 * （application/json，正文 {"code":"rest_no_route"}）或主题化 HTML 404 应答，
 * 光看状态码或光看"有没有响应"都会误判。本测试重点覆盖这一点。
 *
 * 运行：php tests/test-wporg-mirror-fallback.php
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'HOUR_IN_SECONDS', 3600 );

	$GLOBALS['pass']       = 0;
	$GLOBALS['fail']       = 0;
	$GLOBALS['transients'] = array();
	$GLOBALS['http_calls'] = 0;
	$GLOBALS['canned']     = array();

	function ok( $cond, $desc ) {
		if ( $cond ) { $GLOBALS['pass']++; echo "  PASS  {$desc}\n"; }
		else { $GLOBALS['fail']++; echo "  FAIL  {$desc}\n"; }
	}

	function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
	function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
	function is_wp_error( $t ) { return $t instanceof \WP_Error; }

	function wp_remote_get( $url, $args = array() ) {
		$GLOBALS['http_calls']++;
		$GLOBALS['last_url']  = $url;
		$GLOBALS['last_args'] = $args;
		return $GLOBALS['canned'];
	}

	function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 0; }
	function wp_remote_retrieve_header( $r, $h ) { return $r['headers'][ $h ] ?? ''; }

	// Super.php 顶层需要的符号
	class WP_Error {}
	function add_filter() {}
	function add_action() {}
	function is_admin() { return false; }
	function wp_doing_cron() { return false; }
	function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
	function wp_remote_request() { return array(); }
	function esc_html__( $t ) { return $t; }
}

namespace WenPai\ChinaYes {
	function get_settings() { return array( 'store' => 'wenpai' ); }
}

namespace WenPai\ChinaYes\Service {
	// Super 构造函数会 new 这些；本测试只调静态方法，给出空壳即可
	class Widget {}
	class Language {}
	class Migration {}
	class Fonts {}
	class Comments {}
}

namespace {

	require_once __DIR__ . '/../Service/Super.php';

	use WenPai\ChinaYes\Service\Super;

	function probe_with( $canned ): bool {
		$GLOBALS['transients'] = array();
		$GLOBALS['http_calls'] = 0;
		$GLOBALS['canned']     = $canned;

		return Super::is_mirror_usable();
	}

	echo "== 镜像可用 ==\n";
	ok( probe_with( array( 'code' => 200, 'headers' => array( 'content-type' => 'application/octet-stream' ) ) ), '200 + octet-stream => 可用' );
	ok( probe_with( array( 'code' => 206, 'headers' => array( 'content-type' => 'application/zip' ) ) ), '206(Range 正常返回) + zip => 可用' );
	ok( probe_with( array( 'code' => 302, 'headers' => array( 'content-type' => 'application/octet-stream' ) ) ), '302 => 可用' );

	echo "\n== 镜像不可用：本次故障的真实形态 ==\n";
	ok( ! probe_with( array( 'code' => 404, 'headers' => array( 'content-type' => 'application/json; charset=UTF-8' ) ) ), '404 + JSON(rest_no_route) => 不可用' );
	ok( ! probe_with( array( 'code' => 504, 'headers' => array() ) ), '504 网关超时 => 不可用' );
	ok( ! probe_with( array( 'code' => 404, 'headers' => array( 'content-type' => 'text/html; charset=utf-8' ) ) ), '404 + HTML(主题化404页) => 不可用' );

	echo "\n== 关键：状态码 200 但内容类型不对，也必须判不可用 ==\n";
	ok( ! probe_with( array( 'code' => 200, 'headers' => array( 'content-type' => 'application/json' ) ) ), '200 + JSON => 不可用（光看状态码会误判）' );
	ok( ! probe_with( array( 'code' => 200, 'headers' => array( 'content-type' => 'text/html; charset=utf-8' ) ) ), '200 + HTML => 不可用（同上）' );

	echo "\n== 传输层失败 ==\n";
	ok( ! probe_with( new WP_Error() ), 'WP_Error => 不可用' );

	echo "\n== 探测路径必须是真实安装包路径 ==\n";
	probe_with( array( 'code' => 200, 'headers' => array( 'content-type' => 'application/zip' ) ) );
	ok( strpos( (string) $GLOBALS['last_url'], '/plugin/' ) !== false, '探测 /plugin/ 包路径而非根路径或 API 路径' );
	ok( strpos( (string) $GLOBALS['last_url'], '.zip' ) !== false, '探测路径以 .zip 结尾' );
	ok( isset( $GLOBALS['last_args']['headers']['Range'] ), '带 Range 头，不下载整个安装包' );
	ok( ! empty( $GLOBALS['last_args']['_wp_china_yes'] ), '标记自身请求，避免被本过滤器再次改写' );

	echo "\n== 体积校验：200 + 正确内容类型但只有十几字节的空壳，也必须判不可用 ==\n";
	// 实例：lib.baomitu.com 返回 200 但 size 只有 14 字节（modiqi 实测）
	ok( ! probe_with( array( 'code' => 206, 'headers' => array( 'content-type' => 'application/zip', 'content-range' => 'bytes 0-0/14' ) ) ), '206 + content-range 总体积 14B => 不可用' );
	ok( probe_with( array( 'code' => 206, 'headers' => array( 'content-type' => 'application/zip', 'content-range' => 'bytes 0-0/87533' ) ) ), '206 + 总体积 87533B => 可用' );
	ok( ! probe_with( array( 'code' => 200, 'headers' => array( 'content-type' => 'application/octet-stream', 'content-length' => '14' ) ) ), '200 + content-length 14B（上游忽略 Range）=> 不可用' );
	ok( probe_with( array( 'code' => 200, 'headers' => array( 'content-type' => 'application/octet-stream', 'content-length' => '38489' ) ) ), '200 + content-length 38489B => 可用' );
	ok( probe_with( array( 'code' => 206, 'headers' => array( 'content-type' => 'application/zip' ) ) ), '无体积头时不因体积判不可用（避免过度拒绝）' );
	ok( ! probe_with( array( 'code' => 200, 'headers' => array( 'content-type' => 'application/zip', 'content-range' => 'bytes 0-0/1023' ) ) ), '恰好低于阈值(1023B) => 不可用' );

	echo "\n== 状态缓存：不能每次请求都去探测 ==\n";
	$GLOBALS['transients'] = array();
	$GLOBALS['http_calls'] = 0;
	$GLOBALS['canned']     = array( 'code' => 200, 'headers' => array( 'content-type' => 'application/zip' ) );
	Super::is_mirror_usable();
	Super::is_mirror_usable();
	Super::is_mirror_usable();
	ok( 1 === $GLOBALS['http_calls'], '三次调用只探测一次（实得 ' . $GLOBALS['http_calls'] . ' 次）' );

	echo "\n== 不可用状态同样被缓存，但 TTL 更短以便自动恢复 ==\n";
	ok( Super::MIRROR_DOWN_TTL < Super::MIRROR_UP_TTL, '不可用 TTL 短于可用 TTL（镜像修好后能较快恢复加速）' );

	echo "\n---- {$GLOBALS['pass']} passed, {$GLOBALS['fail']} failed ----\n";
	exit( $GLOBALS['fail'] > 0 ? 1 : 0 );
}
