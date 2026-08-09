<?php

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'HOUR_IN_SECONDS', 3600 );
	$GLOBALS['scheduled'] = false;
	$GLOBALS['cleared']   = false;
	$GLOBALS['wpcy_test_settings'] = [ 'telemetry' => false, 'telemetry_site_url' => false ];

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
	function get_option( $key, $default = false ) {
		if ( 'active_plugins' === $key ) { return [ 'demo/demo.php' ]; }
		return $default;
	}
	function get_plugins() { return [ 'demo/demo.php' => [ 'Version' => '1.2.3' ] ]; }
	function home_url() { return 'https://private.example'; }
}

namespace WenPai\ChinaYes {
	function get_settings() { return $GLOBALS['wpcy_test_settings']; }
}

namespace {
	require_once __DIR__ . '/../client/class-site-health.php';

	WenPai_Bridge_Site_Health::init();
	if ( $GLOBALS['scheduled'] || ! $GLOBALS['cleared'] ) {
		fwrite( STDERR, "FAIL telemetry disabled must clear cron and schedule nothing\n" );
		exit( 1 );
	}

	$GLOBALS['wpcy_test_settings']['telemetry'] = true;
	$GLOBALS['cleared'] = false;
	WenPai_Bridge_Site_Health::init();
	if ( ! $GLOBALS['scheduled'] ) {
		fwrite( STDERR, "FAIL explicit opt-in must schedule telemetry\n" );
		exit( 1 );
	}

	$method = new ReflectionMethod( WenPai_Bridge_Site_Health::class, 'collect_report' );
	$method->setAccessible( true );
	$report = $method->invoke( null );
	foreach ( [ 'woocommerce', 'platform', 'themes', 'translations', 'server_software' ] as $forbidden ) {
		if ( array_key_exists( $forbidden, $report ) ) {
			fwrite( STDERR, "FAIL sensitive telemetry field present: {$forbidden}\n" );
			exit( 1 );
		}
	}
	if ( '' !== $report['site_url'] ) {
		fwrite( STDERR, "FAIL site URL must be separately opt-in\n" );
		exit( 1 );
	}
	if ( [ [ 'slug' => 'demo', 'version' => '1.2.3' ] ] !== $report['plugins'] ) {
		fwrite( STDERR, "FAIL telemetry plugin payload is not minimized\n" );
		exit( 1 );
	}

	echo "PASS telemetry is explicit opt-in and payload is minimized\n";
}
