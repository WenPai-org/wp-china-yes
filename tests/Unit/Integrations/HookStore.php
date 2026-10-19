<?php
/**
 * In-memory hooks and transients for Integrations unit tests.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Integrations;

/**
 * Shared bags used by wp-windfonts-stubs.php.
 */
final class HookStore {

	/**
	 * Filters and actions: tag => list of callbacks.
	 *
	 * @var array<string, list<mixed>>
	 */
	public static $hooks = array();

	/**
	 * Transient bag.
	 *
	 * @var array<string, mixed>
	 */
	public static $transients = array();

	/**
	 * Last HTTP GET URL.
	 *
	 * @var string
	 */
	public static $last_http_url = '';

	/**
	 * Last HTTP GET args.
	 *
	 * @var array<string, mixed>
	 */
	public static $last_http_args = array();

	/**
	 * Queued HTTP responses for wp_remote_get.
	 *
	 * @var list<mixed>
	 */
	public static $http_queue = array();

	/**
	 * Reset bags.
	 */
	public static function reset(): void {
		self::$hooks          = array();
		self::$transients     = array();
		self::$last_http_url  = '';
		self::$last_http_args = array();
		self::$http_queue     = array();
	}
}
