<?php
/**
 * Uninstall deletes the caches of signature-verified documents (uninstall.php).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Apps;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Apps\CacheKey;
use WenPai\ChinaYes\Apps\ManifestVerifier;

require_once __DIR__ . '/wp-apps-stubs.php';

/**
 * Runs the real uninstall.php against the in-memory transients.
 */
class UninstallSignedCacheTest extends TestCase {

	/**
	 * Unversioned names used by older releases.
	 */
	private const LEGACY = array(
		'wpcy_apps_index',
		'wpcy_element_hide_rules',
		'wpcy_notice_rules',
		'wpcy_announcements',
	);

	/**
	 * Legacy caches and the index status are deleted on every blog; unrelated transients stay.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstall_deletes_legacy_signed_caches_on_every_blog() {
		AppsStore::reset();
		AppsStore::$multisite = true;
		AppsStore::$sites     = array( 1, 2 );
		AppsStore::$blog_id   = 1;

		foreach ( self::LEGACY as $name ) {
			AppsStore::$transients[ $name ]         = array( 'legacy' );
			AppsStore::$blog_transients[2][ $name ] = array( 'legacy' );
		}
		AppsStore::$transients['wpcy_apps_index_status'] = 'unreachable';
		AppsStore::$transients['unrelated']              = 'keep';

		$this->run_uninstall();

		foreach ( array( 1, 2 ) as $blog_id ) {
			$left = AppsStore::transients_of( $blog_id );
			foreach ( self::LEGACY as $name ) {
				$this->assertArrayNotHasKey( $name, $left, 'blog ' . $blog_id . ' ' . $name );
			}
			$this->assertArrayNotHasKey( 'wpcy_apps_index_status', $left );
		}
		$this->assertSame( 'keep', AppsStore::transients_of( 1 )['unrelated'] );
	}

	/**
	 * The live versioned caches of the default verifier are deleted too.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstall_deletes_current_versioned_caches() {
		AppsStore::reset();
		$verifier = new ManifestVerifier();
		$names    = array();
		foreach ( self::LEGACY as $base ) {
			$names[]                                = CacheKey::for( $base, $verifier );
			AppsStore::$transients[ end( $names ) ] = array( 'cached' );
		}
		AppsStore::$transients['unrelated'] = 'keep';

		$this->run_uninstall();

		foreach ( $names as $name ) {
			$this->assertArrayNotHasKey( $name, AppsStore::$transients, $name );
		}
		$this->assertSame( 'keep', AppsStore::$transients['unrelated'] );
	}

	/**
	 * Require uninstall.php as WordPress does.
	 */
	private function run_uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-china-yes/wp-china-yes.php' );
		}
		require dirname( __DIR__, 3 ) . '/uninstall.php';
	}
}
