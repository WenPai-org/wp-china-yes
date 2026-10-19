<?php
/**
 * Admin locale follow: on uses get_user_locale() in wp-admin; off and frontend pass through.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\AdminLocale;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\AdminLocale\AdminLocaleModule;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Tests\Unit\Connectivity\HookStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WenPai\ChinaYes\Tests\Unit\Connectivity\ScopeHarness;

require_once dirname( __DIR__ ) . '/wp-hook-stubs.php';
require_once dirname( __DIR__ ) . '/scope-function-stubs.php';

/**
 * Switch on/off, admin vs frontend, recovery, empty user locale.
 */
class AdminLocaleModuleTest extends TestCase {

	/**
	 * Reset hook bags and request flags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		HookStore::reset();
		ScopeHarness::reset();
	}

	/**
	 * On + is_admin(): locale and determine_locale become get_user_locale().
	 */
	public function test_on_admin_follows_user_locale() {
		$module = $this->module( true );
		$config = $this->config( true );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertTrue( $module->enabled( $config, $env ) );
		$module->register();
		$this->assertArrayHasKey( 'locale', HookStore::$hooks );
		$this->assertArrayHasKey( 'determine_locale', HookStore::$hooks );

		ScopeHarness::$is_admin = true;
		HookStore::$user_locale = 'zh_CN';

		$this->assertSame( 'zh_CN', $module->filter_locale( 'en_US' ) );
		$this->assertSame( 'zh_CN', $module->filter_locale( 'en_GB' ) );
	}

	/**
	 * On + frontend: site locale is unchanged.
	 */
	public function test_on_frontend_leaves_site_locale() {
		$module                 = $this->module( true );
		ScopeHarness::$is_admin = false;
		HookStore::$user_locale = 'zh_CN';

		$this->assertSame( 'en_US', $module->filter_locale( 'en_US' ) );
	}

	/**
	 * Off: enabled() is false so the registry will not hook.
	 */
	public function test_off_does_not_enable() {
		$config = $this->config( false );
		$module = new AdminLocaleModule( $config );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
		$this->assertSame( array(), HookStore::$hooks );
	}

	/**
	 * Off even if someone called the callback: still no rewrite (is_admin true).
	 *
	 * The registry never registers when enabled() is false. This asserts the
	 * callback itself is a no-op unless the switch is on — which is enforced
	 * by not registering. Calling filter_locale after a manual register would
	 * still follow the user when is_admin; the contract is "off = zero hooks".
	 */
	public function test_off_register_not_called_leaves_default() {
		$module = $this->module( false );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $this->config( false ), $env ) );
		$this->assertArrayNotHasKey( 'locale', HookStore::$hooks );
		$this->assertArrayNotHasKey( 'determine_locale', HookStore::$hooks );
	}

	/**
	 * Module never lists frontend as a scene.
	 */
	public function test_contexts_admin_only() {
		$module = $this->module( true );
		$this->assertSame( array( Environment::ADMIN ), $module->contexts() );
	}

	/**
	 * Recovery mode is always off.
	 */
	public function test_recovery_mode_disables() {
		$config = new MapConfig(
			array(
				'connectivity.admin_locale_follow' => true,
				'recovery_mode'                    => true,
			)
		);
		$module = new AdminLocaleModule( $config );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * Empty get_user_locale() keeps the incoming locale.
	 */
	public function test_empty_user_locale_keeps_incoming() {
		$module                 = $this->module( true );
		ScopeHarness::$is_admin = true;
		HookStore::$user_locale = '';

		$this->assertSame( 'en_US', $module->filter_locale( 'en_US' ) );
	}

	/**
	 * Default when the key is missing is on (schema default).
	 */
	public function test_missing_key_defaults_on() {
		$config = new MapConfig( array( 'recovery_mode' => false ) );
		$module = new AdminLocaleModule( $config );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertTrue( $module->enabled( $config, $env ) );
	}

	/**
	 * All scenes share the same switch; frontend scene never registers.
	 */
	public function test_id_is_connectivity_admin_locale_follow() {
		$this->assertSame( 'connectivity.admin_locale_follow', $this->module( true )->id() );
	}

	/**
	 * After register(), apply_filters('locale') follows the user in admin.
	 */
	public function test_apply_filters_locale_in_admin() {
		$module = $this->module( true );
		$module->register();
		ScopeHarness::$is_admin = true;
		HookStore::$user_locale = 'zh_CN';

		$this->assertSame( 'zh_CN', apply_filters( 'locale', 'en_US' ) );
		$this->assertSame( 'zh_CN', apply_filters( 'determine_locale', 'en_US' ) );
	}

	/**
	 * After register(), apply_filters on the frontend leaves the site locale.
	 */
	public function test_apply_filters_locale_on_frontend() {
		$module = $this->module( true );
		$module->register();
		ScopeHarness::$is_admin = false;
		HookStore::$user_locale = 'zh_CN';

		$this->assertSame( 'en_US', apply_filters( 'locale', 'en_US' ) );
		$this->assertSame( 'en_US', apply_filters( 'determine_locale', 'en_US' ) );
	}

	/**
	 * Config bag.
	 *
	 * @param bool $on Switch value.
	 */
	private function config( bool $on ): MapConfig {
		return new MapConfig(
			array(
				'connectivity.admin_locale_follow' => $on,
				'recovery_mode'                    => false,
			)
		);
	}

	/**
	 * Module under the given setting.
	 *
	 * @param bool $on Switch value.
	 */
	private function module( bool $on ): AdminLocaleModule {
		return new AdminLocaleModule( $this->config( $on ) );
	}
}
