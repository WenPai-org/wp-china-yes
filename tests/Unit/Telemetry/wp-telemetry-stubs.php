<?php
/**
 * WordPress stubs for Telemetry unit tests. Does not load WordPress.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

use WenPai\ChinaYes\Tests\Unit\Telemetry\TelemetryStore;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'CHINA_YES_VERSION' ) ) {
	define( 'CHINA_YES_VERSION', '3.9.3-test' );
}

require_once dirname( __DIR__ ) . '/Config/OptionStore.php';
require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';

if ( ! class_exists( 'WenPai\\ChinaYes\\Tests\\Unit\\Telemetry\\TelemetryStore', false ) ) {
	require_once __DIR__ . '/TelemetryStore.php';
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Record a cron hook registration.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted Accepted args.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) {
		unset( $priority, $accepted );
		TelemetryStore::$actions[ $hook ] = $callback;
		return true;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * Whether a hook is already scheduled.
	 *
	 * @param string $hook Hook name.
	 * @return int|false
	 */
	function wp_next_scheduled( $hook ) {
		return ! empty( TelemetryStore::$scheduled[ $hook ] ) ? 123 : false;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	/**
	 * Mark a hook as scheduled.
	 *
	 * @param int    $timestamp When.
	 * @param string $hook      Hook name.
	 * @return true
	 */
	function wp_schedule_single_event( $timestamp, $hook ) {
		unset( $timestamp );
		TelemetryStore::$scheduled[ $hook ] = true;
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * Clear a scheduled hook.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	function wp_clear_scheduled_hook( $hook ) {
		TelemetryStore::$scheduled[ $hook ] = false;
		TelemetryStore::$cleared            = true;
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	/**
	 * Deterministic jitter.
	 *
	 * @return int
	 */
	function wp_rand() {
		return 0;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * WordPress version stub.
	 *
	 * @return string
	 */
	function get_bloginfo() {
		return TelemetryStore::$wp_version;
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	/**
	 * Locale stub.
	 *
	 * @return string
	 */
	function get_locale() {
		return 'zh_CN';
	}
}

if ( ! function_exists( 'is_ssl' ) ) {
	/**
	 * SSL flag.
	 *
	 * @return bool
	 */
	function is_ssl() {
		return true;
	}
}

if ( ! function_exists( 'get_stylesheet' ) ) {
	/**
	 * Active theme slug.
	 *
	 * @return string
	 */
	function get_stylesheet() {
		return 'demo-theme';
	}
}

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	/**
	 * Theme directory that does not exist.
	 *
	 * @return string
	 */
	function get_stylesheet_directory() {
		return '/nonexistent/theme';
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Read a test transient.
	 *
	 * @param string $key Name.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return TelemetryStore::$transients[ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Write a test transient.
	 *
	 * @param string $key   Name.
	 * @param mixed  $value Value.
	 * @return true
	 */
	function set_transient( $key, $value ) {
		TelemetryStore::$transients[ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_plugins' ) ) {
	/**
	 * Installed plugins.
	 *
	 * @return array<string, array<string, string>>
	 */
	function get_plugins() {
		return TelemetryStore::$plugins;
	}
}

if ( ! function_exists( 'wp_get_themes' ) ) {
	/**
	 * Installed themes.
	 *
	 * @return array<string, mixed>
	 */
	function wp_get_themes() {
		return array();
	}
}

if ( ! function_exists( 'wp_get_installed_translations' ) ) {
	/**
	 * Installed translations.
	 *
	 * @return array<string, mixed>
	 */
	function wp_get_installed_translations() {
		return array();
	}
}

if ( ! function_exists( 'count_users' ) ) {
	/**
	 * User counts. Increments a call counter.
	 *
	 * @return array<string, mixed>
	 */
	function count_users() {
		++TelemetryStore::$count_users_calls;
		return TelemetryStore::$user_counts;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Strip tags.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- unit stub.
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Strip tags.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	function wp_strip_all_tags( $value ) {
		return trim( strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- unit stub.
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Site URL.
	 *
	 * @return string
	 */
	function home_url() {
		return TelemetryStore::$site_url;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Fake cart page.
	 *
	 * @param int $id Post id.
	 * @return object
	 */
	function get_post( $id ) {
		return (object) array(
			'ID'           => $id,
			'post_content' => '<!-- wp:woocommerce/cart /-->',
		);
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	/**
	 * Shop currency.
	 *
	 * @return string
	 */
	function get_woocommerce_currency() {
		return 'CNY';
	}
}

if ( ! function_exists( 'wc_get_base_location' ) ) {
	/**
	 * Shop location. State must never leak into the report.
	 *
	 * @return array{country: string, state: string}
	 */
	function wc_get_base_location() {
		return array(
			'country' => 'CN',
			'state'   => 'GD',
		);
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	/**
	 * Deterministic UUID.
	 *
	 * @return string
	 */
	function wp_generate_uuid4() {
		return 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
	}
}
