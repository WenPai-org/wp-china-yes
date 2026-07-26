<?php
/**
 * 「前台加速」废弃迁移测试
 *
 * 纯 PHP，不依赖 WordPress 运行时（沿用 tests/ 既有风格）。
 *
 * 这是本次改动里**唯一会写入站点数据**的部分，必须验：
 * - checkbox 字段有 ['frontend'] 和 ['frontend' => 'frontend'] 两种形态，都要能摘除
 * - 不能顺手动到 admin / emoji / sworg
 * - 没有 frontend 的站点必须是空操作（不产生无谓写入）
 *
 * 运行：php tests/test-deprecate-frontend-acceleration.php
 */

namespace {
$GLOBALS['mock_option']  = [];
$GLOBALS['write_count']  = 0;

function get_option( $key, $default = false ) {
	return $GLOBALS['mock_option'][ $key ] ?? $default;
}

function update_option( $key, $value ) {
	$GLOBALS['mock_option'][ $key ] = $value;
	$GLOBALS['write_count']++;
	return true;
}

function get_site_option( $key, $default = false ) { return get_option( $key, $default ); }
function is_multisite() { return false; }
function add_action() {}
function wp_parse_args( $args, $defaults = [] ) { return array_merge( $defaults, (array) $args ); }

define( 'ABSPATH', __DIR__ );
}

// Migration 依赖 get_settings()，给个最小实现
namespace WenPai\ChinaYes {
	function get_settings() { return \get_option( 'wp_china_yes', [] ); }
	function clear_settings_cache() {}
}

namespace {
	require_once __DIR__ . '/../Service/Migration.php';

	use WenPai\ChinaYes\Service\Migration;

	$pass = 0;
	$fail = 0;

	function ok( $cond, $desc ) {
		global $pass, $fail;
		if ( $cond ) { $pass++; echo "  PASS  {$desc}\n"; }
		else { $fail++; echo "  FAIL  {$desc}\n"; }
	}

	function run_migration( array $saved ): array {
		$GLOBALS['mock_option']['wp_china_yes'] = $saved;
		$GLOBALS['write_count'] = 0;
		$m = new Migration();
		$m->migrate_deprecate_frontend_acceleration();
		return $GLOBALS['mock_option']['wp_china_yes'];
	}

	echo "== 形态一：索引数组 ['admin','frontend','emoji'] ==\n";
	$r = run_migration( [ 'admincdn_files' => [ 'admin', 'frontend', 'emoji' ] ] );
	$files = array_values( $r['admincdn_files'] );
	sort( $files );
	ok( $files === [ 'admin', 'emoji' ], 'frontend 已摘除，admin/emoji 保留（实得：' . implode( ',', $files ) . '）' );
	ok( $GLOBALS['write_count'] === 1, '发生了一次写入' );

	echo "\n== 形态二：关联数组 ['admin'=>'admin','frontend'=>'frontend'] ==\n";
	$r = run_migration( [ 'admincdn_files' => [ 'admin' => 'admin', 'frontend' => 'frontend', 'emoji' => 'emoji' ] ] );
	ok( ! array_key_exists( 'frontend', $r['admincdn_files'] ), 'frontend 键已摘除' );
	ok( array_key_exists( 'admin', $r['admincdn_files'] ), 'admin 键保留' );
	ok( array_key_exists( 'emoji', $r['admincdn_files'] ), 'emoji 键保留' );

	echo "\n== 形态三：关联数组里 frontend 值为空串（默认关闭的存量形态）==\n";
	$r = run_migration( [ 'admincdn_files' => [ 'admin' => 'admin', 'frontend' => '', 'emoji' => 'emoji' ] ] );
	ok( ! array_key_exists( 'frontend', $r['admincdn_files'] ), 'frontend 键仍被摘除（键存在即清理）' );

	echo "\n== 不该动的情况：没有 frontend ==\n";
	$r = run_migration( [ 'admincdn_files' => [ 'admin', 'emoji' ] ] );
	ok( $GLOBALS['write_count'] === 0, '空操作，不产生无谓写入' );
	ok( array_values( $r['admincdn_files'] ) === [ 'admin', 'emoji' ], '设置未被改动' );

	echo "\n== 不该动的情况：admincdn_files 不存在 / 非数组 ==\n";
	$r = run_migration( [ 'store' => 'wenpai' ] );
	ok( $GLOBALS['write_count'] === 0, 'admincdn_files 缺失时空操作' );

	$GLOBALS['mock_option']['wp_china_yes'] = 'corrupted-string';
	$GLOBALS['write_count'] = 0;
	$m = new Migration();
	$m->migrate_deprecate_frontend_acceleration();
	ok( $GLOBALS['write_count'] === 0, '设置序列化损坏时不写入（不放大已有损坏）' );

	echo "\n== 其他加速项不受影响 ==\n";
	$r = run_migration( [
		'admincdn'        => [ 'admin' ],
		'admincdn_public' => [ 'googlefonts' ],
		'admincdn_files'  => [ 'admin', 'frontend', 'emoji', 'sworg' ],
		'admincdn_dev'    => [ 'jquery' ],
	] );
	ok( $r['admincdn'] === [ 'admin' ], 'admincdn 未动' );
	ok( $r['admincdn_public'] === [ 'googlefonts' ], 'admincdn_public 未动' );
	ok( $r['admincdn_dev'] === [ 'jquery' ], 'admincdn_dev 未动' );
	$files = array_values( $r['admincdn_files'] );
	sort( $files );
	ok( $files === [ 'admin', 'emoji', 'sworg' ], 'admincdn_files 只少了 frontend（实得：' . implode( ',', $files ) . '）' );

	echo "\n---- {$pass} passed, {$fail} failed ----\n";
	exit( $fail > 0 ? 1 : 0 );
}
