<?php
/**
 * WPCY_KERNEL switch: off path must not instantiate Core\Plugin.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * Bootstrap switch from wp-china-yes.php. M1-01 did not add Brain\Monkey.
 */
class KernelSwitchTest extends TestCase {

	/**
	 * Constant is not defined in the plugin file; 3.x new Plugin() remains.
	 */
	public function test_source_keeps_legacy_new_plugin_and_does_not_define_constant() {
		$source = file_get_contents( $this->plugin_file() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file, not a remote URL.
		$this->assertNotFalse( $source );

		$this->assertSame( 0, preg_match( '/define\s*\(\s*[\'"]WPCY_KERNEL[\'"]/', $source ) );
		$this->assertStringContainsString( 'new Plugin()', $source );

		$file_exists = strpos( $source, "file_exists(CHINA_YES_PLUGIN_PATH . 'vendor/autoload.php')" );
		$require     = strpos( $source, "require_once(CHINA_YES_PLUGIN_PATH . 'vendor/autoload.php')" );
		$switch      = strpos( $source, "defined( 'WPCY_KERNEL' ) && 'v4' === WPCY_KERNEL" );
		$legacy      = strpos( $source, 'new Plugin()' );

		$this->assertNotFalse( $file_exists );
		$this->assertNotFalse( $require );
		$this->assertNotFalse( $switch );
		$this->assertNotFalse( $legacy );
		$this->assertGreaterThan( $file_exists, $require, 'require autoload must be inside file_exists branch' );
		$this->assertGreaterThan( $require, $switch, 'switch must be after vendor/autoload.php require' );
		$this->assertGreaterThan( $switch, $legacy, 'legacy new Plugin() must remain after the v4 return' );
	}

	/**
	 * Undefined WPCY_KERNEL loads 3.x Plugin and does not load Core\Plugin.
	 */
	public function test_undefined_constant_does_not_instantiate_core_plugin() {
		$result = $this->run_bootstrap( null );
		$this->assertSame( 'core_no', $result['core'] );
		$this->assertSame( 'legacy_yes', $result['legacy'] );
	}

	/**
	 * A non-v4 value keeps the 3.x path.
	 */
	public function test_non_v4_value_does_not_instantiate_core_plugin() {
		$result = $this->run_bootstrap( 'off' );
		$this->assertSame( 'core_no', $result['core'] );
		$this->assertSame( 'legacy_yes', $result['legacy'] );
	}

	/**
	 * WPCY_KERNEL=v4 boots Core\Plugin and skips 3.x Plugin.
	 */
	public function test_v4_boots_core_and_skips_legacy_plugin() {
		$result = $this->run_bootstrap( 'v4' );
		$this->assertSame( 'core_yes', $result['core'] );
		$this->assertSame( 'legacy_no', $result['legacy'] );
	}

	/**
	 * V4 bootstrap must not include any framework/ path or define WP_CHINA_YES_Setup.
	 */
	public function test_v4_bootstrap_does_not_include_framework() {
		$result = $this->run_bootstrap( 'v4' );
		$this->assertSame( 'framework_no', $result['framework'] );
		$this->assertSame( 'setup_no', $result['setup'] );
	}

	/**
	 * Undefined WPCY_KERNEL still loads setup.class.php after autoload.
	 */
	public function test_undefined_constant_includes_framework_setup() {
		$result = $this->run_bootstrap( null );
		$this->assertSame( 'framework_yes', $result['framework'] );
		$this->assertSame( 'setup_yes', $result['setup'] );
	}

	/**
	 * Path to wp-china-yes.php.
	 */
	private function plugin_file(): string {
		return dirname( __DIR__, 3 ) . '/wp-china-yes.php';
	}

	/**
	 * Load the plugin bootstrap in a subprocess with WordPress stubs.
	 *
	 * @param string|null $kernel_value Null leaves WPCY_KERNEL undefined.
	 * @return array{core: string, legacy: string, framework: string, setup: string, code: int, raw: string}
	 */
	private function run_bootstrap( ?string $kernel_value ): array {
		$stub = <<<'PHP'
<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', '/tmp/wordpress/wp-content/plugins' );
}
if ( ! defined( 'WP_PLUGIN_URL' ) ) {
	define( 'WP_PLUGIN_URL', 'http://example.test/wp-content/plugins' );
}
function plugin_dir_url( $file ) {
	unset( $file );
	return 'http://example.test/wp-content/plugins/wp-china-yes/';
}
function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
function is_multisite() {
	return false;
}
function get_option( $key, $default = false ) {
	unset( $key );
	return $default;
}
function get_site_option( $key, $default = false ) {
	unset( $key );
	return $default;
}
function register_activation_hook( $file, $callback ) {
	unset( $file, $callback );
}
function register_deactivation_hook( $file, $callback ) {
	unset( $file, $callback );
}
function register_uninstall_hook( $file, $callback ) {
	unset( $file, $callback );
}
function wp_next_scheduled( $hook ) {
	unset( $hook );
	return false;
}
function wp_rand( $min = 0, $max = 0 ) {
	unset( $max );
	return (int) $min;
}
function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
	unset( $timestamp, $hook, $args );
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $hook );
}
function remove_action( $hook, $callback = '', $priority = 10 ) {
	unset( $hook, $callback, $priority );
	return true;
}
function remove_filter( $hook, $callback = '', $priority = 10 ) {
	unset( $hook, $callback, $priority );
	return true;
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook, $callback, $priority, $accepted_args );
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook, $callback, $priority, $accepted_args );
}
function plugin_basename( $file ) {
	unset( $file );
	return 'wp-china-yes/wp-china-yes.php';
}
function is_admin() {
	return false;
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function apply_filters( $tag, $value ) {
	unset( $tag );
	return $value;
}
function do_action( $tag ) {
	unset( $tag );
}
function wp_normalize_path( $path ) {
	$path = str_replace( '\\', '/', (string) $path );
	$path = preg_replace( '|(?<=.)/+|', '/', $path );
	return $path;
}
function get_parent_theme_file_path( $file = '' ) {
	$base = '/tmp/wordpress/wp-content/themes/default';
	return '' === $file ? $base : $base . '/' . ltrim( $file, '/' );
}
function get_theme_file_path( $file = '' ) {
	return get_parent_theme_file_path( $file );
}
function get_parent_theme_file_uri( $file = '' ) {
	$base = 'http://example.test/wp-content/themes/default';
	return '' === $file ? $base : $base . '/' . ltrim( $file, '/' );
}
function is_ssl() {
	return false;
}
function set_url_scheme( $url, $scheme = null ) {
	unset( $scheme );
	return $url;
}
function esc_url( $url ) {
	return $url;
}
function esc_html__( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}
function load_template( $path, $require_once = true ) {
	if ( $require_once ) {
		require_once $path;
	} else {
		require $path;
	}
}
function load_textdomain( $domain, $mofile ) {
	unset( $domain, $mofile );
	return false;
}
function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
	unset( $domain, $deprecated, $plugin_rel_path );
	return false;
}
function determine_locale() {
	return 'en_US';
}
function get_locale() {
	return 'en_US';
}
function sanitize_text_field( $str ) {
	return is_string( $str ) ? $str : '';
}
function wp_unslash( $value ) {
	return $value;
}
function current_user_can( $capability ) {
	unset( $capability );
	return false;
}

if ( 'undef' !== $argv[2] ) {
	define( 'WPCY_KERNEL', $argv[2] );
}

require $argv[1];

$core      = class_exists( 'WenPai\\ChinaYes\\Core\\Plugin', false ) ? 'core_yes' : 'core_no';
$legacy    = class_exists( 'WenPai\\ChinaYes\\Plugin', false ) ? 'legacy_yes' : 'legacy_no';
$setup     = class_exists( 'WP_CHINA_YES_Setup', false ) ? 'setup_yes' : 'setup_no';
$framework = 'framework_no';
foreach ( get_included_files() as $included ) {
	$normalized = str_replace( '\\', '/', $included );
	if ( false !== strpos( $normalized, '/framework/' ) ) {
		$framework = 'framework_yes';
		break;
	}
}
fwrite( STDOUT, $core . "\n" . $legacy . "\n" . $framework . "\n" . $setup . "\n" );
PHP;

		$tmp = tempnam( sys_get_temp_dir(), 'wpcy-kernel-' );
		$this->assertNotFalse( $tmp );
		file_put_contents( $tmp, $stub ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test subprocess stub.

		$kernel = null === $kernel_value ? 'undef' : $kernel_value;
		$cmd    = sprintf(
			'%s %s %s %s',
			escapeshellarg( PHP_BINARY ),
			escapeshellarg( $tmp ),
			escapeshellarg( $this->plugin_file() ),
			escapeshellarg( $kernel )
		);

		$output = array();
		$code   = 0;
		exec( $cmd . ' 2>&1', $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- isolated bootstrap subprocess.
		unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temp stub file.

		$raw = implode( "\n", $output );
		$this->assertSame( 0, $code, 'bootstrap stub exited ' . $code . ': ' . $raw );
		$this->assertGreaterThanOrEqual( 4, count( $output ), $raw );

		return array(
			'core'      => $output[0],
			'legacy'    => $output[1],
			'framework' => $output[2],
			'setup'     => $output[3],
			'code'      => $code,
			'raw'       => $raw,
		);
	}
}
