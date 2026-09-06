<?php
/**
 * Flags for Connectivity\Scope unit tests.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity;

/**
 * Process-wide request flags. Reset in each test.
 */
final class ScopeHarness {

	/**
	 * Admin flag.
	 *
	 * @var bool
	 */
	public static $is_admin = false;

	/**
	 * Cron flag.
	 *
	 * @var bool
	 */
	public static $cron = false;

	/**
	 * REST flag.
	 *
	 * @var bool
	 */
	public static $rest = false;

	/**
	 * Nonce verification result.
	 *
	 * @var bool
	 */
	public static $nonce_ok = false;

	/**
	 * Referer value.
	 *
	 * @var string
	 */
	public static $referer = '';

	/**
	 * Reset flags.
	 */
	public static function reset(): void {
		self::$is_admin = false;
		self::$cron     = false;
		self::$rest     = false;
		self::$nonce_ok = false;
		self::$referer  = '';
		unset( $_SERVER['HTTP_X_WP_NONCE'] );
	}
}
