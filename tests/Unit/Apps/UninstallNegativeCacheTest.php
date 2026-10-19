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

	/**
	 * Multisite: uninstall removes NEGATIVE_TRANSIENT_KEY on every blog and
	 * leaves unrelated transients; the current blog is restored afterwards.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstall_deletes_negative_transient_on_every_blog() {
		AppsStore::reset();
		AppsStore::$multisite = true;
		AppsStore::$sites     = array( 1, 2, 3 );
		AppsStore::$blog_id   = 1;

		AppsStore::$transients[ Index::NEGATIVE_TRANSIENT_KEY ] = 'unreachable';
		AppsStore::$transients['unrelated']                     = 'keep-1';
		AppsStore::$blog_transients[2]                          = array(
			Index::NEGATIVE_TRANSIENT_KEY => 'invalid',
			'unrelated'                   => 'keep-2',
		);
		AppsStore::$blog_transients[3]                          = array(
			Index::NEGATIVE_TRANSIENT_KEY => 'unreachable',
		);

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-china-yes/wp-china-yes.php' );
		}
		require dirname( __DIR__, 3 ) . '/uninstall.php';

		$this->assertSame( 1, AppsStore::$blog_id );
		$this->assertSame( array(), AppsStore::$blog_stack );
		foreach ( array( 1, 2, 3 ) as $blog_id ) {
			$this->assertArrayNotHasKey(
				Index::NEGATIVE_TRANSIENT_KEY,
				AppsStore::transients_of( $blog_id ),
				'blog ' . $blog_id
			);
		}
		$this->assertSame( 'keep-1', AppsStore::transients_of( 1 )['unrelated'] );
		$this->assertSame( 'keep-2', AppsStore::transients_of( 2 )['unrelated'] );
	}
}
