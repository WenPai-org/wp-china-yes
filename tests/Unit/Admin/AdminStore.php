<?php
/**
 * In-memory bags for Admin unit tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

/**
 * Process-wide transients, routes, and hooks. Reset in each test setUp().
 */
final class AdminStore {

	/**
	 * Transients.
	 *
	 * @var array<string, mixed>
	 */
	public static $transients = array();

	/**
	 * Routes recorded by register_rest_route().
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $routes = array();

	/**
	 * Hooks recorded by add_action / add_filter.
	 *
	 * @var array<string, array<int, mixed>>
	 */
	public static $hooks = array();

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
	 * Clear bags between tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$transients = array();
		self::$routes     = array();
		self::$hooks      = array();
		self::$caps       = array();
		self::$nonces     = array();
	}
}
