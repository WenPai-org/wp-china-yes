<?php
/**
 * wp-china-yes 序列化损坏防护测试
 *
 * 测试 get_settings() 和 Acceleration 在 options 数据损坏时的行为。
 * 纯 PHP 测试，不依赖 WordPress 运行时。
 */

// 模拟 WordPress 函数
function get_option( $key ) {
	global $mock_option;
	return $mock_option[ $key ] ?? false;
}

function get_site_option( $key ) {
	return get_option( $key );
}

function is_multisite() {
	return false;
}

function wp_parse_args( $args, $defaults = [] ) {
	if ( is_object( $args ) ) {
		$parsed_args = get_object_vars( $args );
	} elseif ( is_array( $args ) ) {
		$parsed_args = $args;
	} else {
		parse_str( $args, $parsed_args );
	}

	return array_merge( $defaults, $parsed_args );
}

function get_settings_test( $mock_value ) {
	global $mock_option;
	$mock_option = [ 'wp_china_yes' => $mock_value ];

	$settings = is_multisite() ? get_site_option( 'wp_china_yes' ) : get_option( 'wp_china_yes' );

	if ( ! is_array( $settings ) ) {
		$settings = [];
	}

	$defaults = [
		'admincdn'        => [ 'admin' ],
		'admincdn_public' => [ 'googlefonts' ],
		'admincdn_files'  => [ 'admin', 'emoji' ],
		'admincdn_dev'    => [ 'jquery' ],
	];

	return wp_parse_args( $settings, $defaults );
}

function has_admin_acceleration( $settings ) {
	return ! empty( $settings['admincdn'] ) &&
	       is_array( $settings['admincdn'] ) &&
	       in_array( 'admin', $settings['admincdn'] );
}

function has_public_library( $settings ) {
	return ! empty( $settings['admincdn_public'] ) &&
	       is_array( $settings['admincdn_public'] ) &&
	       count( $settings['admincdn_public'] ) > 0;
}

// --- 测试用例 ---

$passed = 0;
$failed = 0;

function assert_test( $name, $condition, $message = '' ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "  PASS: {$name}\n";
		$passed++;
	} else {
		echo "  FAIL: {$name}" . ( $message ? " -- {$message}" : '' ) . "\n";
		$failed++;
	}
}

echo "=== get_settings() 序列化损坏防护 ===\n\n";

echo "1. 正常数组数据\n";
$result = get_settings_test( [
	'admincdn' => [ 'admin' ],
	'admincdn_public' => [ 'googlefonts', 'cdnjs' ],
] );
assert_test( 'admincdn 是数组', is_array( $result['admincdn'] ) );
assert_test( 'admincdn 值正确', $result['admincdn'] === [ 'admin' ] );
assert_test( 'admincdn_public 值正确', $result['admincdn_public'] === [ 'googlefonts', 'cdnjs' ] );
assert_test( 'admincdn_dev 用默认值', $result['admincdn_dev'] === [ 'jquery' ] );

echo "\n2. get_option 返回 false（选项不存在）\n";
$result = get_settings_test( false );
assert_test( 'admincdn 用默认值', $result['admincdn'] === [ 'admin' ] );
assert_test( 'admincdn_files 用默认值', $result['admincdn_files'] === [ 'admin', 'emoji' ] );

echo "\n3. get_option 返回损坏字符串\n";
$result = get_settings_test( 'a:1:{s:8:"admincdn";s:5:    ' );
assert_test( 'admincdn 用默认值', $result['admincdn'] === [ 'admin' ] );
assert_test( 'admincdn_public 用默认值', $result['admincdn_public'] === [ 'googlefonts' ] );
assert_test( 'admincdn_files 用默认值', $result['admincdn_files'] === [ 'admin', 'emoji' ] );

echo "\n4. get_option 返回空字符串\n";
$result = get_settings_test( '' );
assert_test( 'admincdn 用默认值', $result['admincdn'] === [ 'admin' ] );

echo "\n5. get_option 返回数字\n";
$result = get_settings_test( 12345 );
assert_test( 'admincdn 用默认值', $result['admincdn'] === [ 'admin' ] );

echo "\n6. get_option 返回 null\n";
$result = get_settings_test( null );
assert_test( 'admincdn 用默认值', $result['admincdn'] === [ 'admin' ] );

echo "\n7. get_option 返回 stdClass\n";
$obj = new \stdClass();
$obj->admincdn = [ 'admin' ];
$result = get_settings_test( $obj );
assert_test( 'admincdn 用默认值', $result['admincdn'] === [ 'admin' ] );

echo "\n=== Acceleration is_array 守卫测试 ===\n\n";

echo "8. 正常 settings\n";
$s = [ 'admincdn' => [ 'admin' ], 'admincdn_public' => [ 'googlefonts' ] ];
assert_test( 'has_admin_acceleration 正常', has_admin_acceleration( $s ) === true );
assert_test( 'has_public_library 正常', has_public_library( $s ) === true );

echo "\n9. admincdn 变成字符串（损坏场景）\n";
$s = [ 'admincdn' => 'admin', 'admincdn_public' => 'googlefonts' ];
assert_test( 'has_admin_acceleration 返回 false', has_admin_acceleration( $s ) === false );
assert_test( 'has_public_library 返回 false', has_public_library( $s ) === false );

echo "\n10. admincdn 为空字符串\n";
$s = [ 'admincdn' => '' ];
assert_test( 'has_admin_acceleration 返回 false', has_admin_acceleration( $s ) === false );

echo "\n11. 旧逻辑对比\n";
$corrupted_string = 'a:1:{s:8:"admincdn";s:5:    ';
$old_result = wp_parse_args( $corrupted_string, [ 'admincdn' => [ 'admin' ] ] );
$new_result = get_settings_test( $corrupted_string );

$new_is_array = is_array( $new_result['admincdn'] ) && ! empty( $new_result['admincdn'] );
echo "  旧逻辑 admincdn 类型: " . gettype( $old_result['admincdn'] ) . "\n";
echo "  旧逻辑 admincdn 值: " . var_export( $old_result['admincdn'], true ) . "\n";
echo "  新逻辑 admincdn 类型: " . gettype( $new_result['admincdn'] ) . "\n";
echo "  新逻辑 admincdn 值: " . var_export( $new_result['admincdn'], true ) . "\n";
assert_test( '新逻辑 admincdn 一定是数组', $new_is_array === true );

echo "\n" . str_repeat( '-', 40 ) . "\n";
$total = $passed + $failed;
echo "结果: {$passed}/{$total} 通过";
if ( $failed > 0 ) {
	echo " ({$failed} 失败)";
}
echo "\n";

exit( $failed > 0 ? 1 : 0 );
