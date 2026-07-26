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
	'public.admincdn.com'                   => 'public.admincdn.com',
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

echo "\n== 探测目标必须用真实资源路径而非根路径 ==\n";
$targets = MirrorHealth::probe_targets();
ok( isset( $targets['cdnjs.admincdn.com'] ) && $targets['cdnjs.admincdn.com'] !== '/', 'cdnjs 探测路径不是根路径' );
ok( isset( $targets['jsd.admincdn.com'] ) && $targets['jsd.admincdn.com'] !== '/', 'jsd 探测路径不是根路径' );
ok( strpos( $targets['jsd.admincdn.com'], '/npm/' ) === 0, 'jsd 用 jsDelivr 的 /npm/ 路径约定' );
ok( strpos( $targets['cdnjs.admincdn.com'], '/ajax/libs/' ) === 0, 'cdnjs 用 /ajax/libs/ 路径约定' );

echo "\n---- {$pass} passed, {$fail} failed ----\n";
exit( $fail > 0 ? 1 : 0 );
