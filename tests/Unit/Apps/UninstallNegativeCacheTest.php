<?php
/**
 * Uninstall deletes the apps index negative cache (uninstall.php).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Apps;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Apps\Index;

require_once __DIR__ . '/wp-apps-stubs.php';

/**
 * Runs the real uninstall.php against the in-memory transients.
 */
class UninstallNegativeCacheTest extends TestCase {

	/**
	 * Uninstall removes NEGATIVE_TRANSIENT_KEY and leaves unrelated transients.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstall_deletes_negative_transient() {
		AppsStore::reset();
		AppsStore::$transients[ Index::NEGATIVE_TRANSIENT_KEY ] = 'unreachable';
		AppsStore::$transients['unrelated']                     = 'keep';

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-china-yes/wp-china-yes.php' );
		}
		require dirname( __DIR__, 3 ) . '/uninstall.php';

		$this->assertArrayNotHasKey( Index::NEGATIVE_TRANSIENT_KEY, AppsStore::$transients );
		$this->assertSame( 'keep', AppsStore::$transients['unrelated'] );
	}
}
