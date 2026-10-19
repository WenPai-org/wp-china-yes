<?php
/**
 * In-memory bags for Admin unit tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

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
	 * Whether is_admin() should return true.
	 *
	 * @var bool
	 */
	public static $is_admin = true;

	/**
	 * Captured wp_remote_get calls.
	 *
	 * @var array<int, array{url: string, args: array<string, mixed>}>
	 */
	public static $http = array();

	/**
	 * Blog ids returned by get_sites().
	 *
	 * @var list<int>
	 */
	public static $sites = array( 1 );

	/**
	 * Current blog id (switch_to_blog()).
	 *
	 * @var int
	 */
	public static $blog_id = 1;

	/**
	 * Site options of blogs that are not current, by blog id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $blog_options = array();

	/**
	 * Blog stack for restore_current_blog().
	 *
	 * @var list<int>
	 */
	public static $blog_stack = array();

	/**
	 * Clear bags between tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$transients   = array();
		self::$routes       = array();
		self::$hooks        = array();
		self::$caps         = array();
		self::$nonces       = array();
		self::$is_admin     = true;
		self::$http         = array();
		self::$sites        = array( 1 );
		self::$blog_id      = 1;
		self::$blog_options = array();
		self::$blog_stack   = array();
	}

	/**
	 * Make $blog_id current: park OptionStore::$options, load that blog's.
	 *
	 * @param int $blog_id Blog id.
	 * @return void
	 */
	public static function use_blog( int $blog_id ): void {
		self::$blog_options[ self::$blog_id ] = OptionStore::$options;
		OptionStore::$options                 = self::$blog_options[ $blog_id ] ?? array();
		unset( self::$blog_options[ $blog_id ] );
		self::$blog_id = $blog_id;
	}

	/**
	 * Site options of $blog_id, whether or not it is current.
	 *
	 * @param int $blog_id Blog id.
	 * @return array<string, mixed>
	 */
	public static function options_of( int $blog_id ): array {
		if ( $blog_id === self::$blog_id ) {
			return OptionStore::$options;
		}
		return self::$blog_options[ $blog_id ] ?? array();
	}
}
