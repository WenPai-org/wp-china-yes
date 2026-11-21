<?php
/**
 * Admin React module: four pages, bootstrap payload, layout constraints.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\AdminModule;
use WenPai\ChinaYes\Config\Repository;

require_once __DIR__ . '/wp-rest-stubs.php';

/**
 * Menu slugs, enqueue payload, and ordinary-admin-page hard constraints.
 */
class AdminModuleTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		\WenPai\ChinaYes\Tests\Unit\Config\OptionStore::reset();
		$_GET = array();
	}

	/**
	 * Four React pages plus parent 文派叶子.
	 */
	public function test_add_pages_registers_four_app_slugs() {
		$module = new AdminModule( new Repository() );
		$module->add_pages();

		$slugs = array();
		foreach ( RestStore::$menus as $menu ) {
			$slugs[] = $menu['menu_slug'];
			$this->assertSame( 'manage_options', $menu['capability'] );
			$this->assertSame( '文派叶子', $menu['menu_title'] );
		}
		$this->assertSame( array( AdminModule::SLUG ), $slugs );

		$sub = array();
		foreach ( RestStore::$pages as $page ) {
			$sub[] = $page['menu_slug'];
			$this->assertSame( AdminModule::SLUG, $page['parent'] );
			$this->assertSame( 'manage_options', $page['capability'] );
		}
		$this->assertSame(
			array( 'wpcy', 'wpcy-connect', 'wpcy-features', 'wpcy-services', 'wpcy-diagnose', 'wpcy-onboarding' ),
			$sub
		);
	}

	/**
	 * Bootstrap is nonce, REST root, capabilities, settings only.
	 */
	public function test_bootstrap_payload_has_only_allowed_keys() {
		RestStore::$caps['manage_options'] = true;
		$module                            = new AdminModule( new Repository() );
		$payload                           = $module->bootstrap_payload();

		$this->assertSame(
			array( 'nonce', 'restRoot', 'capabilities', 'settings', 'pluginVersion', 'siteContext', 'links', 'providers' ),
			array_keys( $payload )
		);
		$this->assertArrayHasKey( 'help', $payload['links'] );
		$this->assertArrayHasKey( 'brands', $payload['links'] );
		$this->assertSame( 'WenPai.org', $payload['providers']['wordpress_org'] );
		$this->assertSame( 'adminCDN', $payload['providers']['public_assets'] );
		$this->assertSame( 'Cravatar', $payload['providers']['cravatar'] );
		$this->assertArrayNotHasKey( 'windfonts', $payload['providers'] );
		$this->assertIsString( $payload['pluginVersion'] );
		$this->assertIsArray( $payload['siteContext'] );
		$this->assertSame( 'nonce-wp_rest', $payload['nonce'] );
		$this->assertSame( 'http://example.test/wp-json/', $payload['restRoot'] );
		$this->assertTrue( $payload['capabilities']['manage_options'] );
		$this->assertArrayHasKey( 'recovery_mode', $payload['settings'] );
		$this->assertArrayHasKey( 'connectivity', $payload['settings'] );
		$this->assertSame( 'cravatar_cn', $payload['settings']['connectivity']['avatar'] );
		$this->assertArrayNotHasKey( 'avatar_admin', $payload['settings']['connectivity'] );
		$this->assertArrayNotHasKey( 'avatar_frontend', $payload['settings']['connectivity'] );
	}

	/**
	 * App screens are the four React slugs, not recovery.
	 */
	public function test_is_app_screen_matches_four_pages_only() {
		$module = new AdminModule( new Repository() );

		$_GET['page'] = 'wpcy';
		$this->assertTrue( $module->is_app_screen() );

		$_GET['page'] = 'wpcy-connect';
		$this->assertTrue( $module->is_app_screen() );

		$_GET['page'] = 'wpcy-features';
		$this->assertTrue( $module->is_app_screen() );

		$_GET['page'] = 'wpcy-services';
		$this->assertTrue( $module->is_app_screen() );

		$_GET['page'] = 'wpcy-diagnose';
		$this->assertTrue( $module->is_app_screen() );

		$_GET['page'] = 'wpcy-recovery';
		$this->assertFalse( $module->is_app_screen() );

		unset( $_GET['page'] );
		$this->assertFalse( $module->is_app_screen() );
	}

	/**
	 * Ordinary admin page: no fullscreen chrome, no admin-bar math, no History API.
	 */
	public function test_app_source_respects_ordinary_admin_page_constraints() {
		$root  = dirname( __DIR__, 3 );
		$files = array(
			$root . '/src/Admin/KitRenderer.php',
			$root . '/assets/js/wpcy-admin.js',
		);

		$joined = '';
		foreach ( $files as $file ) {
			$this->assertFileExists( $file );
			$joined .= (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source files.
		}

		$this->assertSame( 0, preg_match( '/#wpadminbar/', $joined ) );
		$this->assertSame( 0, preg_match( '/top:\s*32px/', $joined ) );
		$this->assertSame( 0, preg_match( '/top:\s*46px/', $joined ) );
		$this->assertSame( 0, preg_match( '/window\.top/', $joined ) );
		$this->assertSame( 0, preg_match( '/window\.parent\.location/', $joined ) );
		$this->assertSame( 0, preg_match( '/pushState\s*\(/', $joined ) );
		$this->assertSame( 0, preg_match( '/@wordpress\/interface/', $joined ) );
		$this->assertSame( 0, preg_match( '/遥测|匿名数据|opt-in|entitlement|套餐|\bPro\b/', $joined ) );
		$this->assertNotFalse( strpos( $joined, '绑定本站' ) );
		$this->assertNotFalse( strpos( $joined, '文派生态目录' ) );
	}

	/**
	 * Kit shell CSS replaces private app style.css (0 custom CSS).
	 */
	public function test_layout_css_does_not_assume_admin_bar_height() {
		$kit_css = file_get_contents( dirname( __DIR__, 3 ) . '/lib/wenpai-admin-ui/css/wenpai-admin-wp.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local stylesheet.
		$this->assertNotFalse( $kit_css );
		$this->assertSame( 0, preg_match( '/#wpcontent\s*\{[^}]*margin-left:\s*160px/', $kit_css ) );
	}

	/**
	 * Providers map is RouteGroups id to brand, not a parallel table.
	 */
	public function test_providers_reads_route_groups() {
		$expected = array();
		foreach ( \WenPai\ChinaYes\Diagnostics\RouteGroups::all() as $group ) {
			$expected[ $group['id'] ] = $group['provider'];
		}
		$this->assertSame( $expected, AdminModule::providers() );
	}

	/**
	 * Hero collapse meta auth_callback uses the object id being written.
	 */
	public function test_hero_meta_auth_callback_uses_object_id() {
		$src = file_get_contents( dirname( __DIR__, 3 ) . '/src/Admin/AdminModule.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source.
		$this->assertNotFalse( $src );
		$this->assertNotFalse( strpos( $src, 'current_user_can( \'edit_user\', (int) $object_id )' ) );
	}
}
