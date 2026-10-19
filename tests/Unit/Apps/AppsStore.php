<?php
/**
 * In-memory bags for Apps unit tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Apps;

/**
 * Process-wide transients and REST bits. Reset in each test setUp().
 */
final class AppsStore {

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
	 * TTL passed to set_transient(), by name.
	 *
	 * @var array<string, int>
	 */
	public static $transient_ttls = array();

	/**
	 * Filters registered with add_filter(), by hook.
	 *
	 * @var array<string, array<int, callable>>
	 */
	public static $filters = array();

	/**
	 * Clear bags between tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$transients     = array();
		self::$routes         = array();
		self::$transient_ttls = array();
		self::$filters        = array();
	}
}
