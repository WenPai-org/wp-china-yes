<?php
/**
 * 镜像健康检测与加速回退测试
 *
 * 纯 PHP 测试，不依赖 WordPress 运行时（沿用 tests/ 既有风格）。
 *
 * 重点覆盖 host_of()：它若解析错主机名，守卫就会【永远不触发】，
 * 而表现上一切正常 —— 这是本次改动里最容易静默失效的地方。
 *
 * 运行：php tests/test-mirror-health-fallback.php
 */

// ---- 模拟 WordPress ----
$GLOBALS['mock_transients'] = [];

function get_transient( $key ) {
	return $GLOBALS['mock_transients'][ $key ] ?? false;
}

function set_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['mock_transients'][ $key ] = $value;
	return true;
}

function apply_filters( $hook, $value ) {
	return $value;
}

function add_action() {}
function is_admin() { return false; }
function is_wp_error( $t ) { return false; }
function wp_remote_get() { return []; }
function wp_remote_retrieve_response_code() { return 200; }
function current_user_can() { return false; }
function esc_html__( $t ) { return $t; }
function esc_html( $t ) { return $t; }

define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../Service/MirrorHealth.php';

use WenPai\ChinaYes\Service\MirrorHealth;

$pass = 0;
$fail = 0;

function ok( $cond, $desc ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  PASS  {$desc}\n";
	} else {
		$fail++;
		echo "  FAIL  {$desc}\n";
	}
}

echo "== host_of() 解析 ==\n";
ok( MirrorHealth::host_of( 'jsd.admincdn.com' ) === 'jsd.admincdn.com', '裸主机名' );
ok( MirrorHealth::host_of( 'jsd.admincdn.com/npm/react' ) === 'jsd.admincdn.com', '带路径（替换表实际形态）' );
ok( MirrorHealth::host_of( 'https://jsd.admincdn.com/npm/leaflet@' ) === 'jsd.admincdn.com', '带协议' );
ok( MirrorHealth::host_of( 'cdnjs.admincdn.com' ) === 'cdnjs.admincdn.com', 'cdnjs' );
ok( MirrorHealth::host_of( '' ) === '', '空串' );
ok( MirrorHealth::host_of( '  jsd.admincdn.com/npm/vue  ' ) === 'jsd.admincdn.com', '带空白' );

echo "\n== 替换表里的真实目标都能解析出预期主机 ==\n";
// 与 Service/Acceleration.php 的映射表保持一致
$real_targets = [
	'googlefonts.admincdn.com'              => 'googlefonts.admincdn.com',
	'googleajax.admincdn.com'               => 'googleajax.admincdn.com',
	'cdnjs.admincdn.com'                    => 'cdnjs.admincdn.com',
	'jsd.admincdn.com'                      => 'jsd.admincdn.com',
	'jsd.admincdn.com/npm/react'            => 'jsd.admincdn.com',
	'jsd.admincdn.com/npm/jquery'           => 'jsd.admincdn.com',
	'jsd.admincdn.com/npm/vue'              => 'jsd.admincdn.com',
	'jsd.admincdn.com/npm/datatables.net'   => 'jsd.admincdn.com',
	'jsd.admincdn.com/npm/tailwindcss'      => 'jsd.admincdn.com',
	'jsd.admincdn.com/npm/@twemoji/api/dist' => 'jsd.admincdn.com',
	'wpstatic.admincdn.com'                 => 'wpstatic.admincdn.com',
	'ts.wenpai.net'                         => 'ts.wenpai.net',
];
foreach ( $real_targets as $target => $expected ) {
	ok( MirrorHealth::host_of( $target ) === $expected, "{$target} => {$expected}" );
}

echo "\n== is_healthy() 语义 ==\n";
$GLOBALS['mock_transients'] = [];
ok( MirrorHealth::is_healthy( 'jsd.admincdn.com' ) === true, '未知 => 视为健康（不因缺数据丢加速）' );

set_transient( MirrorHealth::STATE_PREFIX . md5( 'jsd.admincdn.com' ), 'up', 3600 );
ok( MirrorHealth::is_healthy( 'jsd.admincdn.com' ) === true, "标记 up => 健康" );

set_transient( MirrorHealth::STATE_PREFIX . md5( 'jsd.admincdn.com' ), 'down', 1800 );
ok( MirrorHealth::is_healthy( 'jsd.admincdn.com' ) === false, "标记 down => 不健康" );

ok( MirrorHealth::is_healthy( 'cdnjs.admincdn.com' ) === true, 'down 状态不串到别的主机' );
ok( MirrorHealth::is_healthy( '' ) === true, '空主机名 => 健康（不阻断）' );

echo "\n== unhealthy_hosts() ==\n";
$GLOBALS['mock_transients'] = [];
set_transient( MirrorHealth::STATE_PREFIX . md5( 'cdnjs.admincdn.com' ), 'down', 1800 );
set_transient( MirrorHealth::STATE_PREFIX . md5( 'jsd.admincdn.com' ), 'down', 1800 );
$down = MirrorHealth::unhealthy_hosts();
sort( $down );
ok( $down === [ 'cdnjs.admincdn.com', 'jsd.admincdn.com' ], '只列出被标 down 的（实得：' . implode( ',', $down ) . '）' );

echo "\n== 探测路径必须是插件实际生成的形态 ==\n";
$GLOBALS['wp_version'] = '6.8';
$targets = MirrorHealth::probe_targets();

// 没有任何端点可以用根路径探测：反代型端点根路径常返 302/404，会给出错误结论
foreach ( $targets as $host => $path ) {
	ok( '/' !== $path, "{$host} 探测路径不是根路径" );
}

// cdnjs 的替换把 /ajax/libs 前缀吃掉了，所以本端点路径【不带】该前缀。
// 这条曾经写错（照抄了上游约定），导致探测的是一个与实际使用不同的路径。
ok( strpos( $targets['cdnjs.admincdn.com'], '/ajax/libs/' ) !== 0, 'cdnjs 路径不带 /ajax/libs 前缀（替换时已被吃掉）' );
ok( $targets['cdnjs.admincdn.com'] === '/jquery/3.7.1/jquery.min.js', 'cdnjs 路径与替换后形态一致' );

// googleajax 替换 ajax.googleapis.com，其路径本身就带 /ajax/libs
ok( strpos( $targets['googleajax.admincdn.com'], '/ajax/libs/' ) === 0, 'googleajax 路径带 /ajax/libs 前缀' );

ok( strpos( $targets['jsd.admincdn.com'], '/npm/' ) === 0, 'jsd 用 jsDelivr 的 /npm/ 路径约定' );

// public.admincdn.com 于 3.9.3 随「前台加速」一并废弃，不应再出现在探测表里
ok( ! isset( $targets['public.admincdn.com'] ), 'public 已从探测目标移除（前台加速已废弃）' );

// wpstatic 形态含 WordPress 版本号，必须随运行时版本变化
ok( strpos( $targets['wpstatic.admincdn.com'], '/6.8/wp-admin/' ) === 0, 'wpstatic 路径含 wp_version 前缀' );
$GLOBALS['wp_version'] = '6.9';
$t2 = MirrorHealth::probe_targets();
ok( strpos( $t2['wpstatic.admincdn.com'], '/6.9/wp-admin/' ) === 0, 'wpstatic 路径随 wp_version 变化' );

// ts 的替换形态是主题截图路径
ok( strpos( $targets['ts.wenpai.net'], '/wp-content/themes/' ) === 0, 'ts 路径是主题截图形态' );

echo "\n---- {$pass} passed, {$fail} failed ----\n";
exit( $fail > 0 ? 1 : 0 );
