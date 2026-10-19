<?php
/**
 * Uninstall deletes the per-site dismissed announcements option (uninstall.php).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\Announcements\AnnouncementsModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Runs the real uninstall.php against the in-memory option bags.
 */
class AnnouncementsUninstallTest extends TestCase {

	/**
	 * Single site: the option is deleted, unrelated options stay.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstall_deletes_dismissed_option() {
		AdminStore::reset();
		OptionStore::reset();
		OptionStore::$options[ AnnouncementsModule::DISMISSED_OPTION ] = array( 'wptea-1' );
		OptionStore::$options['unrelated']                             = 'keep';

		$this->run_uninstall();

		$this->assertSame( 'wpcy_announcements_dismissed', AnnouncementsModule::DISMISSED_OPTION );
		$this->assertArrayNotHasKey( AnnouncementsModule::DISMISSED_OPTION, OptionStore::$options );
		$this->assertSame( 'keep', OptionStore::$options['unrelated'] );
	}

	/**
	 * Multisite: the option is deleted on every blog from get_sites(), unrelated
	 * options stay, and the current blog is restored.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstall_deletes_dismissed_option_on_every_blog() {
		AdminStore::reset();
		OptionStore::reset();
		OptionStore::$multisite = true;
		AdminStore::$sites      = array( 1, 2, 3 );

		OptionStore::$options[ AnnouncementsModule::DISMISSED_OPTION ] = array( 'wptea-1' );
		OptionStore::$options['unrelated']                             = 'keep-1';
		AdminStore::$blog_options[2]                                   = array(
			AnnouncementsModule::DISMISSED_OPTION => array( 'one-2' ),
			'unrelated'                           => 'keep-2',
		);
		AdminStore::$blog_options[3]                                   = array(
			AnnouncementsModule::DISMISSED_OPTION => array( 'one-3' ),
		);

		$this->run_uninstall();

		$this->assertSame( 1, AdminStore::$blog_id );
		$this->assertSame( array(), AdminStore::$blog_stack );
		foreach ( array( 1, 2, 3 ) as $blog_id ) {
			$this->assertArrayNotHasKey(
				AnnouncementsModule::DISMISSED_OPTION,
				AdminStore::options_of( $blog_id ),
				'blog ' . $blog_id
			);
		}
		$this->assertSame( 'keep-1', AdminStore::options_of( 1 )['unrelated'] );
		$this->assertSame( 'keep-2', AdminStore::options_of( 2 )['unrelated'] );
	}

	/**
	 * Require the plugin's uninstall.php.
	 *
	 * @return void
	 */
	private function run_uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-china-yes/wp-china-yes.php' );
		}
		require dirname( __DIR__, 3 ) . '/uninstall.php';
	}
}
