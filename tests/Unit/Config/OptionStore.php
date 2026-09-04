<?php
/**
 * In-memory WordPress option bags for Config unit tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Config;

/**
 * Process-wide option bags. Reset in each test setUp().
 */
final class OptionStore {

	/**
	 * Site options keyed by name.
	 *
	 * @var array<string, mixed>
	 */
	public static $options = array();

	/**
	 * Network options keyed by name.
	 *
	 * @var array<string, mixed>
	 */
	public static $site_options = array();

	/**
	 * Whether is_multisite() should return true.
	 *
	 * @var bool
	 */
	public static $multisite = false;

	/**
	 * Value returned by wp_salt().
	 *
	 * @var string
	 */
	public static $salt = 'wpcy-unit-auth-salt';

	/**
	 * Clear bags between tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$options      = array();
		self::$site_options = array();
		self::$multisite    = false;
	}
}
