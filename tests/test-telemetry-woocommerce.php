<?php
/**
 * 每日兼容性报告：WooCommerce 激活时的 woocommerce 块。
 *
 * 覆盖 test-telemetry.php 没走到的路径：聚合查询、网关/配送、allow_tracking、
 * base_location 只带国家、WP 4.9 无 has_block() 时的守卫、以及禁词检查。
 */

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'DB_NAME', 'wp_test' );
	define( 'WC_VERSION', '9.9.0' );
	$GLOBALS['transients'] = [];
	$GLOBALS['count_users_calls'] = 0;

	class WenPai_Bridge_Site_Identity {
		public static function get_uuid() { return 'test-uuid'; }
	}
	class WooCommerce {}

	class Fake_Wpdb {
		public $prefix = 'wp_';
		public $posts = 'wp_posts';
		public $term_relationships = 'wp_term_relationships';
		public $term_taxonomy = 'wp_term_taxonomy';
		public $terms = 'wp_terms';
		public function prepare( $sql, ...$args ) { return $sql; }
		public function get_var( $sql ) {
			if ( false !== strpos( $sql, 'SELECT VERSION()' ) ) { return '8.0.36'; }
			if ( false !== strpos( $sql, 'information_schema' ) ) { return '2'; }
			if ( false !== strpos( $sql, 'SHOW TABLES LIKE' ) ) { return 'wp_woocommerce_shipping_zones'; }
			if ( false !== strpos( $sql, 'woocommerce_shipping_zones' ) ) { return '3'; }
			return null;
		}
		public function get_results( $sql ) {
			if ( false !== strpos( $sql, 'product_type' ) ) {
				return [ (object) [ 'slug' => 'simple', 'cnt' => 12 ], (object) [ 'slug' => 'variable', 'cnt' => 3 ] ];
			}
			if ( false !== strpos( $sql, 'shop_order' ) ) {
				return [ (object) [ 'status' => 'wc-completed', 'cnt' => 40 ], (object) [ 'status' => 'wc-processing', 'cnt' => 2 ] ];
			}
			return [];
		}
	}
	$wpdb = new Fake_Wpdb();

	class Fake_Gateway { public $id; public $enabled; function __construct( $id, $enabled ) { $this->id = $id; $this->enabled = $enabled; } }
	class Fake_Gateways { public function payment_gateways() { return [ new Fake_Gateway( 'alipay', 'yes' ), new Fake_Gateway( 'cod', 'no' ) ]; } }
	class Fake_Shipping { public function get_shipping_methods() { return [ new Fake_Gateway( 'flat_rate', 'yes' ) ]; } }
	class Fake_WC {
		public $payment_gateways;
		public $shipping;
		function __construct() { $this->payment_gateways = new Fake_Gateways(); $this->shipping = new Fake_Shipping(); }
		public function plugin_path() { return '/nonexistent/woocommerce'; }
	}
	function WC() { static $wc = null; return $wc ?: ( $wc = new Fake_WC() ); }

	function add_action() {}
	function wp_next_scheduled() { return false; }
	function wp_schedule_single_event() { return true; }
	function wp_rand() { return 0; }
	function get_bloginfo() { return '4.9.26'; }
	function get_locale() { return 'zh_CN'; }
	function is_multisite() { return false; }
	function is_ssl() { return true; }
	function get_stylesheet() { return 'demo-theme'; }
	function get_stylesheet_directory() { return '/nonexistent/theme'; }
	function get_option( $key, $default = false ) {
		switch ( $key ) {
			case 'active_plugins': return [ 'woocommerce/woocommerce.php' ];
			case 'woocommerce_allow_tracking': return 'yes';
			case 'woocommerce_cart_page_id': return 7;
			case 'woocommerce_checkout_page_id': return 8;
			case 'woocommerce_calc_taxes': return 'yes';
			case 'woocommerce_enable_coupons': return 'yes';
			case 'woocommerce_enable_guest_checkout': return 'no';
		}
		return $default;
	}
	function get_site_option( $key, $default = false ) { return $default; }
	function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
	function set_transient( $key, $value ) { $GLOBALS['transients'][ $key ] = $value; return true; }
	function get_plugins() { return [ 'woocommerce/woocommerce.php' => [ 'Version' => WC_VERSION, 'Name' => 'WooCommerce', 'Author' => 'Automattic' ] ]; }
	function wp_get_themes() { return []; }
	function wp_get_installed_translations() { return []; }
	function count_users() {
		$GLOBALS['count_users_calls']++;
		return [ 'total_users' => 120, 'avail_roles' => [ 'administrator' => 1, 'customer' => 119 ] ];
	}
	function get_post( $id ) { return (object) [ 'ID' => $id, 'post_content' => '<!-- wp:woocommerce/cart /-->' ]; }
	function get_woocommerce_currency() { return 'CNY'; }
	function wc_get_base_location() { return [ 'country' => 'CN', 'state' => 'GD' ]; }
	function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
	function home_url() { return 'https://shop.example'; }
	// 故意不定义 has_block()：模拟 WP 4.9。
}

namespace {
	require_once __DIR__ . '/../client/class-site-health.php';

	$method = new ReflectionMethod( WenPai_Bridge_Site_Health::class, 'collect_report' );
	$method->setAccessible( true );
	$report = $method->invoke( null );

	if ( ! isset( $report['woocommerce'] ) || ! is_array( $report['woocommerce'] ) ) {
		fwrite( STDERR, "FAIL woocommerce block missing when WooCommerce is active\n" );
		exit( 1 );
	}
	$wc = $report['woocommerce'];

	if ( WC_VERSION !== $wc['wc_version'] || 'CNY' !== $wc['currency'] ) {
		fwrite( STDERR, "FAIL wc_version/currency not collected\n" );
		exit( 1 );
	}
	if ( 'CN' !== $wc['base_location'] ) {
		fwrite( STDERR, "FAIL base_location must be country code only, got: " . var_export( $wc['base_location'], true ) . "\n" );
		exit( 1 );
	}
	if ( true !== $wc['allow_tracking'] ) {
		fwrite( STDERR, "FAIL allow_tracking flag not collected\n" );
		exit( 1 );
	}
	if ( 15 !== $wc['products_total'] || [ 'simple' => 12, 'variable' => 3 ] !== $wc['products_by_type'] ) {
		fwrite( STDERR, "FAIL product aggregates wrong\n" );
		exit( 1 );
	}
	if ( 42 !== $wc['orders_total'] || [ 'wc-completed' => 40, 'wc-processing' => 2 ] !== $wc['orders_by_status'] ) {
		fwrite( STDERR, "FAIL order aggregates wrong\n" );
		exit( 1 );
	}
	if ( [ [ 'id' => 'alipay', 'enabled' => true ], [ 'id' => 'cod', 'enabled' => false ] ] !== $wc['gateways'] ) {
		fwrite( STDERR, "FAIL gateway list wrong\n" );
		exit( 1 );
	}
	if ( false !== $wc['block_cart'] ) {
		fwrite( STDERR, "FAIL page_has_block must return false when has_block() is unavailable (WP 4.9)\n" );
		exit( 1 );
	}
	if ( [] !== $wc['template_overrides'] ) {
		fwrite( STDERR, "FAIL template_overrides must be empty for a missing theme directory\n" );
		exit( 1 );
	}
	if ( 3 !== $wc['shipping_zones_count'] || [ 'administrator' => 1, 'customer' => 119 ] !== $wc['user_roles'] ) {
		fwrite( STDERR, "FAIL shipping zones / user roles aggregates wrong\n" );
		exit( 1 );
	}
	if ( 1 !== $GLOBALS['count_users_calls'] ) {
		fwrite( STDERR, "FAIL count_users() must run once per report, ran {$GLOBALS['count_users_calls']} times\n" );
		exit( 1 );
	}
	if ( 120 !== $report['platform']['users_count'] ) {
		fwrite( STDERR, "FAIL platform.users_count wrong\n" );
		exit( 1 );
	}

	// 业务正文、顾客/管理员联系方式、凭据、精确地理不得出现在任何层级（键名与字符串值都查）。
	$forbidden_keys   = [ 'admin_email', 'customer_email', 'billing_email', 'order_items', 'line_items', 'license_key', 'api_key', 'password', 'user_email', 'store_id', 'blog_id', 'state', 'postcode', 'city' ];
	$forbidden_values = [ 'GD' ];
	$walk = function ( $node ) use ( &$walk, $forbidden_keys, $forbidden_values ) {
		if ( is_string( $node ) && in_array( $node, $forbidden_values, true ) ) {
			fwrite( STDERR, "FAIL forbidden value present in report: {$node}\n" );
			exit( 1 );
		}
		if ( ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $key => $value ) {
			if ( is_string( $key ) && in_array( $key, $forbidden_keys, true ) ) {
				fwrite( STDERR, "FAIL forbidden key present in report: {$key}\n" );
				exit( 1 );
			}
			$walk( $value );
		}
	};
	$walk( $report );

	echo "PASS woocommerce block carries aggregates only, country without state, tracking flag, and survives WP 4.9\n";
}
