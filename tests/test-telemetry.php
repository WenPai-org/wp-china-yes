<?php
/**
 * 每日兼容性报告：始终调度、字段完整、不含业务正文。
 *
 * 2026-09-03 定稿（docs/plans/2026-09-03-wpcy-revamp.md §7.1-1）：报告没有开关，
 * 2.1 全集随插件发送；订单正文、顾客联系方式、管理员邮箱、许可密钥不得出现。
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'CHINA_YES_VERSION', '3.9.3-test' );
	$GLOBALS['scheduled'] = false;
	$GLOBALS['cleared']   = false;
	$GLOBALS['transients'] = [];
	// 旧站可能残留的 3.9.3 候选期开关值：必须被忽略。
	$GLOBALS['wpcy_test_settings'] = [ 'telemetry' => false, 'telemetry_site_url' => false, 'bridge' => false ];

	class WenPai_Bridge_Site_Identity {
		public static function get_uuid() { return 'test-uuid'; }
	}

	function add_action() {}
	function wp_next_scheduled() { return $GLOBALS['scheduled'] ? 123 : false; }
	function wp_schedule_single_event() { $GLOBALS['scheduled'] = true; return true; }
	function wp_clear_scheduled_hook() { $GLOBALS['scheduled'] = false; $GLOBALS['cleared'] = true; }
	function wp_rand() { return 0; }
	function get_bloginfo() { return '7.0'; }
	function get_locale() { return 'zh_CN'; }
	function is_multisite() { return false; }
	function is_ssl() { return true; }
	function get_stylesheet() { return 'demo-theme'; }
	function get_option( $key, $default = false ) {
		if ( 'active_plugins' === $key ) { return [ 'demo/demo.php' ]; }
		return $default;
	}
	function get_site_option( $key, $default = false ) { return $default; }
	function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
	function set_transient( $key, $value ) { $GLOBALS['transients'][ $key ] = $value; return true; }
	function get_plugins() {
		return [ 'demo/demo.php' => [ 'Version' => '1.2.3', 'Name' => 'Demo', 'Author' => '<a href="#">Demo Co</a>' ] ];
	}
	function wp_get_themes() { return []; }
	function wp_get_installed_translations() { return []; }
	function count_users() { return [ 'total_users' => 3, 'avail_roles' => [ 'administrator' => 1, 'subscriber' => 2 ] ]; }
	function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
	function home_url() { return 'https://site.example'; }
}

namespace WenPai\ChinaYes {
	function get_settings() { return $GLOBALS['wpcy_test_settings']; }
}

namespace {
	require_once __DIR__ . '/../client/class-site-health.php';

	$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.26';

	WenPai_Bridge_Site_Health::init();
	if ( ! $GLOBALS['scheduled'] || $GLOBALS['cleared'] ) {
		fwrite( STDERR, "FAIL report must be scheduled regardless of legacy telemetry/bridge settings\n" );
		exit( 1 );
	}

	$method = new ReflectionMethod( WenPai_Bridge_Site_Health::class, 'collect_report' );
	$method->setAccessible( true );
	$report = $method->invoke( null );

	foreach ( [ 'site_uuid', 'site_url', 'wp_version', 'php_version', 'mysql_version', 'active_theme', 'locale', 'server_software', 'wpcy_version', 'telemetry_version', 'plugins', 'platform', 'themes', 'translations' ] as $required ) {
		if ( ! array_key_exists( $required, $report ) ) {
			fwrite( STDERR, "FAIL report missing field: {$required}\n" );
			exit( 1 );
		}
	}
	if ( 'https://site.example' !== $report['site_url'] ) {
		fwrite( STDERR, "FAIL site_url must always be sent\n" );
		exit( 1 );
	}
	if ( PHP_VERSION !== $report['php_version'] ) {
		fwrite( STDERR, "FAIL php_version must be real PHP_VERSION\n" );
		exit( 1 );
	}
	if ( 'nginx/1.26' !== $report['server_software'] ) {
		fwrite( STDERR, "FAIL server_software must be collected\n" );
		exit( 1 );
	}
	if ( 'demo' !== $report['plugins'][0]['slug'] || 'Demo Co' !== $report['plugins'][0]['author'] ) {
		fwrite( STDERR, "FAIL plugin list must carry full metadata with tags stripped\n" );
		exit( 1 );
	}
	if ( ! isset( $report['platform']['php_extensions'] ) || 3 !== $report['platform']['users_count'] ) {
		fwrite( STDERR, "FAIL platform block incomplete\n" );
		exit( 1 );
	}
	if ( array_key_exists( 'woocommerce', $report ) ) {
		fwrite( STDERR, "FAIL woocommerce block must be absent when WooCommerce is not active\n" );
		exit( 1 );
	}

	// 业务正文与凭据永远不得出现在报告任何层级。
	$forbidden = [ 'admin_email', 'customer_email', 'billing_email', 'order_items', 'line_items', 'license_key', 'api_key', 'password', 'user_email' ];
	$walk = function ( $node ) use ( &$walk, $forbidden ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $key => $value ) {
			if ( is_string( $key ) && in_array( $key, $forbidden, true ) ) {
				fwrite( STDERR, "FAIL forbidden field present in report: {$key}\n" );
				exit( 1 );
			}
			$walk( $value );
		}
	};
	$walk( $report );

	echo "PASS compatibility report is always scheduled, carries the 2.1 field set, and excludes business content\n";
}
