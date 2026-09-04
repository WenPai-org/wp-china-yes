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
			array( 'wpcy', 'wpcy-connect', 'wpcy-services', 'wpcy-diagnose' ),
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
			array( 'nonce', 'restRoot', 'capabilities', 'settings' ),
			array_keys( $payload )
		);
		$this->assertSame( 'nonce-wp_rest', $payload['nonce'] );
		$this->assertSame( 'http://example.test/wp-json/', $payload['restRoot'] );
		$this->assertTrue( $payload['capabilities']['manage_options'] );
		$this->assertArrayHasKey( 'recovery_mode', $payload['settings'] );
		$this->assertArrayHasKey( 'connectivity', $payload['settings'] );
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
		$root  = dirname( __DIR__, 3 ) . '/src/Admin/app';
		$files = glob( $root . '/*.js' );
		$files = array_merge( $files, glob( $root . '/*/*.js' ) );
		$files = array_merge( $files, glob( $root . '/*/*/*.js' ) );
		$files = array_merge( $files, glob( $root . '/*.css' ) );

		$this->assertNotEmpty( $files );

		$joined = '';
		foreach ( $files as $file ) {
			$joined .= file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source files.
		}

		$this->assertSame( 0, preg_match( '/#wpadminbar/', $joined ) );
		$this->assertSame( 0, preg_match( '/top:\s*32px/', $joined ) );
		$this->assertSame( 0, preg_match( '/top:\s*46px/', $joined ) );
		$this->assertSame( 0, preg_match( '/window\.top/', $joined ) );
		$this->assertSame( 0, preg_match( '/window\.parent\.location/', $joined ) );
		$this->assertSame( 0, preg_match( '/pushState\s*\(/', $joined ) );
		$this->assertSame( 0, preg_match( '/@wordpress\/interface/', $joined ) );
		$this->assertSame( 0, preg_match( '/遥测|匿名数据|opt-in|entitlement|套餐|\bPro\b/', $joined ) );
		$this->assertNotFalse( strpos( $joined, 'wp.os' ) );
		$this->assertNotFalse( strpos( $joined, 'registerCommand' ) );
		$this->assertNotFalse( strpos( $joined, '绑定本站' ) );
		$this->assertNotFalse( strpos( $joined, '绑定后显示' ) );
		$this->assertNotFalse( strpos( $joined, 'allow-scripts allow-forms' ) );
		$this->assertSame( 0, preg_match( '/allow-same-origin/', $joined ) );
	}

	/**
	 * Chromeless iframe CSS: height is not pinned to admin-bar 32px.
	 */
	public function test_layout_css_does_not_assume_admin_bar_height() {
		$css = file_get_contents( dirname( __DIR__, 3 ) . '/src/Admin/app/style.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local stylesheet.
		$this->assertNotFalse( $css );
		$this->assertSame( 0, preg_match( '/position:\s*fixed/', $css ) );
		$this->assertSame( 0, preg_match( '/wpadminbar/', $css ) );
		$this->assertSame( 0, preg_match( '/admin-bar--height/', $css ) );
		$this->assertNotFalse( strpos( $css, 'max-width: 1080px' ) );
	}
}
