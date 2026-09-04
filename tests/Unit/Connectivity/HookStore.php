<?php
/**
 * In-memory hooks and transients for Connectivity unit tests.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity;

/**
 * Shared bags used by wp-hook-stubs.php.
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
	 * Reset bags.
	 */
	public static function reset(): void {
		self::$hooks      = array();
		self::$transients = array();
	}
}
