<?php
/**
 * Process-wide bags for Telemetry unit tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Telemetry;

/**
 * Reset in each test setUp().
 */
final class TelemetryStore {

	/**
	 * Registered actions keyed by hook.
	 *
	 * @var array<string, callable>
	 */
	public static $actions = array();

	/**
	 * Scheduled hooks.
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
	 * Transients.
	 *
	 * @var array<string, mixed>
	 */
	public static $transients = array();

	/**
	 * WordPress version returned by get_bloginfo().
	 *
	 * @var string
	 */
	public static $wp_version = '7.0';

	/**
	 * Home URL.
	 *
	 * @var string
	 */
	public static $site_url = 'https://site.example';

	/**
	 * Plugin list returned by get_plugins().
	 *
	 * @var array<string, array<string, string>>
	 */
	public static $plugins = array();

	/**
	 * Count-users payload.
	 *
	 * @var array<string, mixed>
	 */
	public static $user_counts = array();

	/**
	 * How many times count_users() ran.
	 *
	 * @var int
	 */
	public static $count_users_calls = 0;

	/**
	 * WooCommerce option overrides.
	 *
	 * @var array<string, mixed>
	 */
	public static $wc_options = array();

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$actions           = array();
		self::$scheduled         = array();
		self::$cleared           = false;
		self::$transients        = array();
		self::$wp_version        = '7.0';
		self::$site_url          = 'https://site.example';
		self::$plugins           = array(
			'demo/demo.php' => array(
				'Version' => '1.2.3',
				'Name'    => 'Demo',
				'Author'  => '<a href="#">Demo Co</a>',
			),
		);
		self::$user_counts       = array(
			'total_users' => 3,
			'avail_roles' => array(
				'administrator' => 1,
				'subscriber'    => 2,
			),
		);
		self::$count_users_calls = 0;
		self::$wc_options        = array();
	}
}
