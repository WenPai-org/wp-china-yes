<?php
/**
 * Recording bag for Plugin::deactivate() stubs.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

/**
 * Sites, current blog and cleared cron hooks.
 */
final class DeactivateStore {

	/**
	 * Whether is_multisite() is true.
	 *
	 * @var bool
	 */
	public static $multisite = false;

	/**
	 * Site ids in the network.
	 *
	 * @var int[]
	 */
	public static $sites = array( 1 );

	/**
	 * Current blog id.
	 *
	 * @var int
	 */
	public static $blog = 1;

	/**
	 * Blog stack for switch_to_blog().
	 *
	 * @var int[]
	 */
	public static $stack = array();

	/**
	 * Each wp_clear_scheduled_hook() as array( blog id, hook ).
	 *
	 * @var array<int, array{0: int, 1: string}>
	 */
	public static $cleared = array();

	/**
	 * Blog id on which wp_clear_scheduled_hook() throws, or 0 for none.
	 *
	 * @var int
	 */
	public static $throw_on_blog = 0;

	/**
	 * Arguments of each get_sites() call.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $get_sites_calls = array();
}
