<?php
/**
 * AdminModule M-UI-2 additions: network-admin settings page (CO-06),
 * subsite lock flag, and the first-run wizard redirect (spec §2).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\AdminModule;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Migration\LegacyReader;
use WenPai\ChinaYes\Migration\Runner;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-rest-stubs.php';
require_once __DIR__ . '/wp-network-admin-stubs.php';

/**
 * Network view, lock flag, onboarding redirect.
 */
class AdminNetworkOnboardingTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		OptionStore::reset();
		$_GET                           = array();
		$GLOBALS['wpcy_test_main_site'] = true;
	}

	/**
	 * Network admin gets one 文派叶子 → 设置 page gated by manage_network_options.
	 */
	public function test_network_menu_registers_settings_page() {
		$module = new AdminModule( new Repository() );
		$module->add_network_pages();

		$this->assertCount( 1, RestStore::$menus );
		$menu = RestStore::$menus[0];
		$this->assertSame( 'wpcy-connect', $menu['menu_slug'] );
		$this->assertSame( 'manage_network_options', $menu['capability'] );
		$this->assertSame( '文派叶子', $menu['menu_title'] );
	}

	/**
	 * Register wires network_admin_menu and admin_init.
	 */
	public function test_register_hooks_network_menu_and_redirect() {
		$module = new AdminModule( new Repository() );
		$module->register();

		$this->assertArrayHasKey( 'network_admin_menu', RestStore::$hooks );
		$this->assertArrayHasKey( 'admin_init', RestStore::$hooks );
		$this->assertArrayHasKey( 'admin_head', RestStore::$hooks );
	}

	/**
	 * The wizard stays registered during admin_menu (core access check) and
	 * is only dropped from the sidebar on admin_head.
	 */
	public function test_wizard_hidden_on_admin_head_not_admin_menu() {
		RestStore::$caps['manage_options']     = true;
		$GLOBALS['wpcy_test_removed_submenus'] = array();
		$module                                = new AdminModule( new Repository() );

		$module->add_pages();
		$this->assertSame( array(), $GLOBALS['wpcy_test_removed_submenus'] );
		$this->assertContains( 'wpcy-onboarding', array_column( RestStore::$pages, 'menu_slug' ) );

		$module->hide_onboarding_submenu();
		$this->assertSame(
			array( array( 'wpcy', 'wpcy-onboarding' ) ),
			$GLOBALS['wpcy_test_removed_submenus']
		);
	}

	/**
	 * Bootstrap capabilities carry network_admin and network_locked.
	 */
	public function test_bootstrap_capabilities_flag_network_admin() {
		RestStore::$caps['manage_options']         = true;
		RestStore::$caps['manage_network_options'] = true;
		RestStore::$network_admin                  = true;
		OptionStore::$multisite                    = true;

		$payload = ( new AdminModule( new Repository() ) )->bootstrap_payload();

		$this->assertTrue( $payload['capabilities']['network_admin'] );
		$this->assertFalse( $payload['capabilities']['network_locked'] );
	}

	/**
	 * Single site is never locked.
	 */
	public function test_single_site_is_not_locked() {
		RestStore::$caps['manage_options'] = true;
		$module                            = new AdminModule( new Repository() );

		$this->assertFalse( $module->network_locked() );
		$this->assertFalse( $module->bootstrap_payload()['capabilities']['network_admin'] );
	}

	/**
	 * Subsite admin is locked only when the network closed allow_site_override.
	 */
	public function test_subsite_locked_follows_allow_site_override() {
		RestStore::$caps['manage_options'] = true;
		OptionStore::$multisite            = true;

		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 2,
				'allow_site_override' => true,
			)
		);
		$this->assertFalse( ( new AdminModule( new Repository() ) )->network_locked() );

		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 2,
				'allow_site_override' => false,
			)
		);
		$this->assertTrue( ( new AdminModule( new Repository() ) )->network_locked() );

		// Super admins on a subsite are locked too: the server does not keep
		// subsite writes while the override is closed.
		RestStore::$caps['manage_network_options'] = true;
		$this->assertTrue( ( new AdminModule( new Repository() ) )->network_locked() );
		$this->assertTrue(
			( new AdminModule( new Repository() ) )->bootstrap_payload()['capabilities']['network_locked']
		);
	}

	/**
	 * Fresh single site, first leaf page: redirect to the wizard.
	 */
	public function test_redirects_fresh_site_to_wizard() {
		RestStore::$caps['manage_options'] = true;
		$_GET['page']                      = 'wpcy-connect';

		$target = ( new AdminModule( new Repository() ) )->onboarding_redirect_target();
		$this->assertSame( 'http://example.test/wp-admin/admin.php?page=wpcy-onboarding', $target );
	}

	/**
	 * No redirect for the wizard itself, recovery, or non-plugin pages.
	 */
	public function test_no_redirect_on_wizard_recovery_or_other_pages() {
		RestStore::$caps['manage_options'] = true;
		$module                            = new AdminModule( new Repository() );

		foreach ( array( 'wpcy-onboarding', 'wpcy-recovery', 'plugins' ) as $page ) {
			$_GET['page'] = $page;
			$this->assertSame( '', $module->onboarding_redirect_target(), $page );
		}
	}

	/**
	 * Confirmed scene, 3.x migration report, or a previous redirect: no jump.
	 */
	public function test_no_redirect_when_confirmed_migrated_or_already_redirected() {
		RestStore::$caps['manage_options'] = true;
		$_GET['page']                      = 'wpcy';

		OptionStore::$options[ Runner::REPORT_OPTION ] = array( 'kept' => array( 'x' ) );
		$this->assertSame( '', ( new AdminModule( new Repository() ) )->onboarding_redirect_target() );
		unset( OptionStore::$options[ Runner::REPORT_OPTION ] );

		OptionStore::$options[ AdminModule::ONBOARDING_REDIRECTED ] = '1';
		$this->assertSame( '', ( new AdminModule( new Repository() ) )->onboarding_redirect_target() );
		unset( OptionStore::$options[ AdminModule::ONBOARDING_REDIRECTED ] );

		$repo = new Repository();
		$repo->set( 'profile_confirmed_at', '2026-10-19T12:00:00Z' );
		$this->assertSame( '', ( new AdminModule( $repo ) )->onboarding_redirect_target() );
	}

	/**
	 * Multisite: the main site redirects; subsites (L-7 ⑤) and the network
	 * admin do not.
	 */
	public function test_multisite_main_site_only() {
		RestStore::$caps['manage_options'] = true;
		$_GET['page']                      = 'wpcy';
		OptionStore::$multisite            = true;

		$this->assertSame(
			'http://example.test/wp-admin/admin.php?page=wpcy-onboarding',
			( new AdminModule( new Repository() ) )->onboarding_redirect_target()
		);

		$GLOBALS['wpcy_test_main_site'] = false;
		$this->assertSame( '', ( new AdminModule( new Repository() ) )->onboarding_redirect_target() );

		$GLOBALS['wpcy_test_main_site'] = true;
		RestStore::$network_admin       = true;
		$this->assertSame( '', ( new AdminModule( new Repository() ) )->onboarding_redirect_target() );
	}

	/**
	 * Multisite main site: a network-scoped 3.x migration report blocks it.
	 */
	public function test_multisite_reads_report_through_runner() {
		RestStore::$caps['manage_options']                  = true;
		$_GET['page']                                       = 'wpcy';
		OptionStore::$multisite                             = true;
		OptionStore::$site_options[ Runner::REPORT_OPTION ] = array( 'kept' => array( 'x' ) );

		$this->assertSame( '', ( new AdminModule( new Repository() ) )->onboarding_redirect_target() );
	}

	/**
	 * 3.x option present but no report yet (migration failed, retry pending),
	 * or recovery mode on: no redirect.
	 */
	public function test_no_redirect_for_pending_migration_or_recovery() {
		RestStore::$caps['manage_options'] = true;
		$_GET['page']                      = 'wpcy';

		OptionStore::$options[ LegacyReader::OPTION ] = array( 'store' => 'off' );
		$this->assertSame( '', ( new AdminModule( new Repository() ) )->onboarding_redirect_target() );
		unset( OptionStore::$options[ LegacyReader::OPTION ] );

		$repo = new Repository();
		$repo->set( 'recovery_mode', true );
		$this->assertSame( '', ( new AdminModule( $repo ) )->onboarding_redirect_target() );
	}

	/**
	 * Redirect writes the per-site marker (get_option scope) and leaves.
	 */
	public function test_maybe_redirect_writes_marker_and_redirects() {
		RestStore::$caps['manage_options'] = true;
		$_GET['page']                      = 'wpcy';
		$module                            = new AdminModule( new Repository() );

		try {
			$module->maybe_redirect_onboarding();
			$this->fail( 'Expected redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'http://example.test/wp-admin/admin.php?page=wpcy-onboarding', RestStore::$redirect );
		}
		$this->assertSame( '1', OptionStore::$options[ AdminModule::ONBOARDING_REDIRECTED ] );
		$this->assertArrayNotHasKey( AdminModule::ONBOARDING_REDIRECTED, OptionStore::$site_options );

		RestStore::$redirect = null;
		$module->maybe_redirect_onboarding();
		$this->assertNull( RestStore::$redirect );
	}

	/**
	 * Read-only subsite opening the wizard goes to settings; no marker.
	 */
	public function test_locked_subsite_wizard_goes_to_settings() {
		RestStore::$caps['manage_options'] = true;
		OptionStore::$multisite            = true;
		$GLOBALS['wpcy_test_main_site']    = false;
		$_GET['page']                      = 'wpcy-onboarding';
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 2,
				'allow_site_override' => false,
			)
		);
		$module = new AdminModule( new Repository() );

		$this->assertSame( 'http://example.test/wp-admin/admin.php?page=wpcy-connect', $module->locked_wizard_target() );
		try {
			$module->maybe_redirect_onboarding();
			$this->fail( 'Expected redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'http://example.test/wp-admin/admin.php?page=wpcy-connect', RestStore::$redirect );
		}
		$this->assertArrayNotHasKey( AdminModule::ONBOARDING_REDIRECTED, OptionStore::$options );

		$_GET['page'] = 'wpcy-connect';
		$this->assertSame( '', $module->locked_wizard_target() );
	}

	/**
	 * Network admin render: needs manage_network_options, then mounts the app.
	 */
	public function test_render_network_branch() {
		RestStore::$network_admin = true;
		OptionStore::$multisite   = true;
		$module                   = new AdminModule( new Repository() );

		ob_start();
		$module->render();
		$html = (string) ob_get_clean();
		$this->assertNotNull( RestStore::$die );
		$this->assertSame( 403, RestStore::$die_status );

		RestStore::reset();
		RestStore::$network_admin                  = true;
		RestStore::$caps['manage_network_options'] = true;
		ob_start();
		$module->render();
		$html = (string) ob_get_clean();
		$this->assertNull( RestStore::$die );
		$this->assertStringContainsString( 'id="wpcy-admin-root"', $html );
	}

	/**
	 * Users without manage_options are not redirected.
	 */
	public function test_no_redirect_without_capability() {
		$_GET['page'] = 'wpcy';
		$this->assertSame( '', ( new AdminModule( new Repository() ) )->onboarding_redirect_target() );
	}
}
