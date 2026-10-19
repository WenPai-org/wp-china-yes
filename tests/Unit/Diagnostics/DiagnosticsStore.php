<?php
/**
 * In-memory bags for Diagnostics unit tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Diagnostics;

/**
 * Process-wide hook and store bags. Reset in each test setUp().
 */
final class DiagnosticsStore {

	/**
	 * Hook callbacks keyed by tag.
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
	 * Cron events keyed by hook.
	 *
	 * @var array<string, bool>
	 */
	public static $scheduled = array();

	/**
	 * Whether wp_clear_scheduled_hook ran.
	 *
	 * @var bool
	 */
	public static $cleared = false;

	/**
	 * WP-CLI add_command log: name => callable.
	 *
	 * @var array<string, mixed>
	 */
	public static $cli_commands = array();

	/**
	 * Clear bags between tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$hooks        = array();
		self::$transients   = array();
		self::$scheduled    = array();
		self::$cleared      = false;
		self::$cli_commands = array();
	}
}
