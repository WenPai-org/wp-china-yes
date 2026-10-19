<?php
/**
 * Recording do_action() for Rest tests that assert events (SettingsNetworkAuthzTest).
 *
 * Kept out of wp-rest-stubs.php: other suites (Stats) load those stubs and
 * need their own do_action() that runs callbacks.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;

require_once __DIR__ . '/wp-rest-stubs.php';

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Record an action call; registered callbacks are not run.
	 *
	 * @param string $tag     Hook.
	 * @param mixed  ...$args Args.
	 * @return void
	 */
	function do_action( $tag, ...$args ) {
		RestStore::$actions[] = array(
			'tag'  => (string) $tag,
			'args' => $args,
		);
	}
}
