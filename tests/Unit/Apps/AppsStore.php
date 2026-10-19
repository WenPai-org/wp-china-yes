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
	 * Whether is_multisite() reports a network.
	 *
	 * @var bool
	 */
	public static $multisite = false;

	/**
	 * Current blog id (switch_to_blog()).
	 *
	 * @var int
	 */
	public static $blog_id = 1;

	/**
	 * Blog ids returned by get_sites().
	 *
	 * @var list<int>
	 */
	public static $sites = array( 1 );

	/**
	 * Transients of blogs that are not current, by blog id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $blog_transients = array();

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
		self::$transients      = array();
		self::$routes          = array();
		self::$transient_ttls  = array();
		self::$filters         = array();
		self::$multisite       = false;
		self::$blog_id         = 1;
		self::$sites           = array( 1 );
		self::$blog_transients = array();
		self::$blog_stack      = array();
	}

	/**
	 * Make $blog_id current: park the current transients, load that blog's.
	 *
	 * @param int $blog_id Blog id.
	 * @return void
	 */
	public static function use_blog( int $blog_id ): void {
		self::$blog_transients[ self::$blog_id ] = self::$transients;
		self::$transients                        = self::$blog_transients[ $blog_id ] ?? array();
		unset( self::$blog_transients[ $blog_id ] );
		self::$blog_id = $blog_id;
	}

	/**
	 * Transients of $blog_id, whether or not it is current.
	 *
	 * @param int $blog_id Blog id.
	 * @return array<string, mixed>
	 */
	public static function transients_of( int $blog_id ): array {
		if ( $blog_id === self::$blog_id ) {
			return self::$transients;
		}
		return self::$blog_transients[ $blog_id ] ?? array();
	}
}
