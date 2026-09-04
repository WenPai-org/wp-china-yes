<?php
/**
 * In-memory bags for REST unit tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

/**
 * Process-wide REST / admin stubs. Reset in each test setUp().
 */
final class RestStore {

	/**
	 * Capabilities that current_user_can() should allow.
	 *
	 * @var array<string, bool>
	 */
	public static $caps = array();

	/**
	 * Nonces that wp_verify_nonce() accepts for wp_rest.
	 *
	 * @var array<string, bool>
	 */
	public static $nonces = array();

	/**
	 * Admin referer nonces keyed by action.
	 *
	 * @var array<string, string>
	 */
	public static $admin_nonces = array();

	/**
	 * Routes recorded by register_rest_route().
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $routes = array();

	/**
	 * Submenu pages recorded by add_submenu_page().
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $pages = array();

	/**
	 * Top-level pages recorded by add_menu_page().
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $menus = array();

	/**
	 * Scripts recorded by wp_enqueue_script().
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $scripts = array();

	/**
	 * Inline scripts recorded by wp_add_inline_script().
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $inline = array();

	/**
	 * Hooks recorded by add_action / add_filter.
	 *
	 * @var array<string, array<int, mixed>>
	 */
	public static $hooks = array();

	/**
	 * Last wp_die() message, or null.
	 *
	 * @var string|null
	 */
	public static $die = null;

	/**
	 * Last wp_die() status.
	 *
	 * @var int
	 */
	public static $die_status = 0;

	/**
	 * Whether wp_die() should throw instead of returning.
	 *
	 * @var bool
	 */
	public static $die_throws = false;

	/**
	 * Transients for Checker.
	 *
	 * @var array<string, mixed>
	 */
	public static $transients = array();

	/**
	 * Last wp_safe_redirect location, or null.
	 *
	 * @var string|null
	 */
	public static $redirect = null;

	/**
	 * Clear bags between tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$caps         = array();
		self::$nonces       = array();
		self::$admin_nonces = array();
		self::$routes       = array();
		self::$pages        = array();
		self::$menus        = array();
		self::$scripts      = array();
		self::$inline       = array();
		self::$hooks        = array();
		self::$die          = null;
		self::$die_status   = 0;
		self::$die_throws   = false;
		self::$transients   = array();
		self::$redirect     = null;
	}
}
