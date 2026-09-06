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
	 * Deregistered script handles.
	 *
	 * @var list<string>
	 */
	public static $deregistered = array();

	/**
	 * Removed meta-box calls.
	 *
	 * @var list<array{id: string, screen: string, context: string}>
	 */
	public static $removed_boxes = array();

	/**
	 * Reset bags.
	 */
	public static function reset(): void {
		self::$hooks         = array();
		self::$transients    = array();
		self::$deregistered  = array();
		self::$removed_boxes = array();
	}
}
