<?php
/**
 * NoticeControl: notification center drawer + built-in promo rules safety & governance tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\NoticeControl\NoticeCenter;
use WenPai\ChinaYes\Admin\NoticeControl\NoticeControlModule;
use WenPai\ChinaYes\Admin\NoticeControl\PromoRules;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_REST_Request;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Verifies PromoRules redlines, NoticeCenter native-hub yield, and NoticeControlModule wiring.
 */
class NoticeControlTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AdminStore::reset();
		OptionStore::reset();
		AdminStore::$is_admin               = true;
		AdminStore::$caps['manage_options'] = true;
	}

	/**
	 * Every built-in promotional rule passes sanitization and targets a specific plugin element.
	 */
	public function test_builtin_promo_rules_all_pass_redline_sanitizer() {
		$rules     = PromoRules::builtin_rules();
		$selectors = PromoRules::active_selectors();

		$this->assertNotEmpty( $rules );
		$this->assertCount( count( $rules ), $selectors );
		$this->assertContains( '.wpforms-review-notice', $selectors );
		$this->assertContains( '.yst-license-notice', $selectors );
		$this->assertContains( '.rank-math-review-notice', $selectors );

		$css = PromoRules::build_css();
		$this->assertStringContainsString( '.wpforms-review-notice', $css );
		$this->assertStringEndsWith( '{display:none!important;}', $css );
	}

	/**
	 * Core updates, security alerts, Site Health, generic classes, and injection payloads are rejected.
	 */
	public function test_protected_and_unsafe_selectors_are_rejected() {
		$blocked = array(
			'.update-nag',
			'.update_nag',
			'.notice',
			'.updated',
			'.error',
			'.notice-warning',
			'.notice.notice-error',
			'.site-health-notice',
			'#update-core',
			'.core-update-banner',
			'.security-alert-box',
			'#setting-error-settings_updated',
			'.settings-error',
			'*',
			'div.promo',
			'.a, .b',
			'.foo{display:block}',
		);

		foreach ( $blocked as $selector ) {
			$this->assertSame( '', PromoRules::sanitize_selector( $selector ), 'Expected rejected: ' . $selector );
		}

		$this->assertSame( '.acme-promo-notice', PromoRules::sanitize_selector( ' .acme-promo-notice ' ) );
		$this->assertSame( '#acme-upsell-bar', PromoRules::sanitize_selector( '#acme-upsell-bar' ) );
	}

	/**
	 * Module enables when either drawer or hide_promo is on, and disables in recovery mode.
	 */
	public function test_enabled_respects_both_switches_and_recovery_mode() {
		$repo = new Repository();
		$env  = new Environment( Environment::ADMIN );
		$mod  = new NoticeControlModule( $repo );

		$repo->set( 'modules.notice_control', true );
		$repo->set( 'admin.hide_promo', false );
		$this->assertTrue( $mod->enabled( $repo, $env ) );

		$repo->set( 'modules.notice_control', false );
		$repo->set( 'admin.hide_promo', true );
		$this->assertTrue( $mod->enabled( $repo, $env ) );

		$repo->set( 'modules.notice_control', false );
		$repo->set( 'admin.hide_promo', false );
		$this->assertFalse( $mod->enabled( $repo, $env ) );

		$repo->set( 'modules.notice_control', true );
		$repo->set( 'recovery_mode', true );
		$this->assertFalse( $mod->enabled( $repo, $env ) );
	}

	/**
	 * NoticeCenter excludes operational core screens and yields when native WP notification hub is detected.
	 */
	public function test_notice_center_excludes_core_screens_and_yields_to_native_hub() {
		$this->assertTrue( NoticeCenter::should_activate_on_screen( 'plugins.php' ) );
		$this->assertFalse( NoticeCenter::should_activate_on_screen( 'update-core.php' ) );
		$this->assertFalse( NoticeCenter::should_activate_on_screen( 'site-health.php' ) );
		$this->assertFalse( NoticeCenter::should_activate_on_screen( 'update.php' ) );

		AdminStore::$hooks['wpcy_notice_center_native_detected'][] = static function () {
			return true;
		};
		$this->assertTrue( NoticeCenter::has_native_notification_center() );
		$this->assertFalse( NoticeCenter::should_activate_on_screen( 'plugins.php' ) );
	}

	/**
	 * Registering hooks attaches Admin Bar drawer, assets, promo CSS, and REST status route.
	 */
	public function test_register_wires_drawer_and_rest_status() {
		$repo = new Repository();
		$repo->set( 'modules.notice_control', true );
		$repo->set( 'admin.hide_promo', true );

		$mod = new NoticeControlModule( $repo );
		$mod->register();

		$this->assertArrayHasKey( 'rest_api_init', AdminStore::$hooks );
		$this->assertArrayHasKey( 'admin_bar_menu', AdminStore::$hooks );
		$this->assertArrayHasKey( 'admin_enqueue_scripts', AdminStore::$hooks );
		$this->assertArrayHasKey( 'admin_footer', AdminStore::$hooks );

		$mod->register_routes();
		$routes = array_column( AdminStore::$routes, 'route' );
		$this->assertContains( '/notice-control/hidden', $routes );

		$response = $mod->rest_status( new WP_REST_Request() );
		$data     = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( $data['drawer_enabled'] );
		$this->assertTrue( $data['hide_promo_enabled'] );
		$this->assertSame( 'drawer', $data['mode'] );
		$this->assertGreaterThan( 10, $data['builtin_promo_count'] );
	}
}
