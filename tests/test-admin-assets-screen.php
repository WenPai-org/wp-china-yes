<?php
/**
 * 后台框架资源只在文派叶子设置页（单站与多站点网络后台）入队。
 * 只加载 Setting 类定义并直接调用静态过滤器，不需要 WordPress。
 */

define( 'ABSPATH', __DIR__ . '/' );

$failures = [];

function check_admin_assets( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
		fwrite( STDERR, "FAIL {$message}\n" );
		return;
	}
	echo "PASS {$message}\n";
}

require dirname( __DIR__ ) . '/Service/Setting.php';

$filter = [ 'WenPai\ChinaYes\Service\Setting', 'filter_enqueue_framework_assets' ];

check_admin_assets( true === $filter( false, 'settings_page_wp-china-yes' ), 'single-site settings screen enqueues framework assets' );
check_admin_assets( true === $filter( false, 'settings_page_wp-china-yes-network' ), 'network settings screen enqueues framework assets' );
check_admin_assets( false === $filter( false, 'plugins' ), 'single-site plugins screen does not enqueue framework assets' );
check_admin_assets( false === $filter( false, 'plugins-network' ), 'network plugins screen does not enqueue framework assets' );
check_admin_assets( false === $filter( false, 'dashboard' ), 'dashboard does not enqueue framework assets' );
check_admin_assets( false === $filter( false, 'dashboard-network' ), 'network dashboard does not enqueue framework assets' );
check_admin_assets( false === $filter( false, 'settings_page_wp-china-yes-extra' ), 'screen ids only containing the slug do not enqueue framework assets' );
check_admin_assets( true === $filter( true, 'customize' ), 'framework own decision is preserved' );
check_admin_assets( false === $filter( false, '' ), 'missing screen does not enqueue framework assets' );

if ( $failures ) {
	exit( 1 );
}
