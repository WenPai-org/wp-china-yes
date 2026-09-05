<?php
/**
 * NoticeControl: core notices never match; third-party rules do.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\NoticeControl\NoticeControlModule;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Acceptance: core / update-nag / Site Health selectors never hide.
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
	}

	/**
	 * Sample fixture has no core / update-nag / Site Health selectors.
	 */
	public function test_sample_fixture_has_no_protected_selectors() {
		$raw = file_get_contents( $this->fixture() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$this->assertNotFalse( $raw );
		$this->assertSame( 0, preg_match( '/update-nag|site-health|site_health|health-check|update-core|core-update/i', $raw ) );

		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded );
		foreach ( $decoded['rules'] as $rule ) {
			$this->assertArrayNotHasKey( 'selector', $rule );
		}
	}

	/**
	 * Core update, security, and Site Health hooks never match.
	 */
	public function test_core_notice_hooks_never_match() {
		$module = $this->module_from_fixture();
		$module->refresh();

		$this->assertFalse( $module->should_hide( 'admin_notices', 'update-nag' ) );
		$this->assertFalse( $module->should_hide( 'admin_notices', 'update_nag notice notice-warning' ) );
		$this->assertFalse( $module->should_hide( 'admin_notices', 'notice-error', 'core' ) );
		$this->assertFalse( $module->should_hide( 'admin_notices', 'site-health-notice' ) );
		$this->assertFalse( $module->should_hide( 'site_health_notice', 'notice' ) );
		$this->assertFalse( $module->should_hide( 'admin_notices', 'health-check-wp-update-available' ) );
		$this->assertFalse( $module->should_hide( 'admin_notices', 'notice-warning', 'health-check' ) );
		$this->assertTrue( $module->is_protected( 'admin_notices', 'update-nag' ) );
		$this->assertTrue( $module->is_protected( 'admin_notices', 'notice', 'core' ) );
	}

	/**
	 * A rule that names a protected class is dropped at load.
	 */
	public function test_protected_rule_in_payload_is_dropped() {
		$payload = wp_json_encode(
			array(
				'rules_version' => 1,
				'issued_at'     => '2026-09-03T00:00:00Z',
				'rules'         => array(
					array(
						'id'     => 'hide-update-nag',
						'plugin' => 'wordpress',
						'hook'   => 'admin_notices',
						'class'  => 'update-nag',
					),
					array(
						'id'     => 'acme-dashboard-banner',
						'plugin' => 'acme-seo',
						'hook'   => 'admin_notices',
						'class'  => 'acme-promo-banner',
					),
				),
			)
		);

		$module = new NoticeControlModule(
			new Repository(),
			'mock://rules',
			static function () use ( $payload ) {
				return $payload;
			}
		);
		$module->refresh();

		$ids = array();
		foreach ( $module->active_rules() as $rule ) {
			$ids[] = $rule['id'];
		}
		$this->assertNotContains( 'hide-update-nag', $ids );
		$this->assertContains( 'acme-dashboard-banner', $ids );
		$this->assertFalse( $module->should_hide( 'admin_notices', 'update-nag' ) );
	}

	/**
	 * Third-party promo class from the sample fixture does match.
	 */
	public function test_third_party_promo_matches() {
		$module = $this->module_from_fixture();
		$module->refresh();

		$this->assertTrue( $module->should_hide( 'admin_notices', 'woocommerce-message' ) );
		$this->assertTrue( $module->consider( 'admin_notices', 'woocommerce-message', 'woocommerce' ) );

		$hidden = $module->hidden_items();
		$this->assertNotEmpty( $hidden );
		$this->assertSame( 'woocommerce', $hidden[0]['plugin'] );
		$this->assertSame( 'woo-connect-promo', $hidden[0]['rule'] );
		$this->assertNotSame( '', $hidden[0]['first_hidden'] );
		$this->assertSame( 1, $hidden[0]['count'] );
	}

	/**
	 * Protected notices are not recorded even when consider() is called.
	 */
	public function test_protected_consider_does_not_record() {
		$module = $this->module_from_fixture();
		$module->refresh();

		$this->assertFalse( $module->consider( 'admin_notices', 'update-nag', 'core' ) );
		$this->assertSame( array(), $module->hidden_items() );
	}

	/**
	 * Arbitrary CSS selector field is rejected (3.x adblock_rule is not used).
	 */
	public function test_selector_field_is_rejected() {
		$payload = wp_json_encode(
			array(
				'rules_version' => 1,
				'issued_at'     => '2026-09-03T00:00:00Z',
				'rules'         => array(
					array(
						'id'       => 'css-selector',
						'plugin'   => 'acme',
						'hook'     => 'admin_notices',
						'class'    => 'acme-banner',
						'selector' => '.notice, #wpfooter',
					),
				),
			)
		);

		$module = new NoticeControlModule(
			new Repository(),
			'mock://rules',
			static function () use ( $payload ) {
				return $payload;
			}
		);
		$module->refresh();
		$this->assertSame( array(), $module->active_rules() );
	}

	/**
	 * Module is gated by modules.notice_control.
	 */
	public function test_enabled_follows_config() {
		$repo = new Repository();
		$env  = new Environment( Environment::ADMIN );
		$mod  = new NoticeControlModule( $repo );

		$repo->set( 'modules.notice_control', true );
		$this->assertTrue( $mod->enabled( $repo, $env ) );

		$repo->set( 'modules.notice_control', false );
		$this->assertFalse( $mod->enabled( $repo, $env ) );
	}

	/**
	 * Generic WP class notice-warning is dropped and prints no CSS.
	 */
	public function test_generic_notice_warning_rule_prints_no_css() {
		$payload = wp_json_encode(
			array(
				'rules_version' => 1,
				'issued_at'     => '2026-09-03T00:00:00Z',
				'rules'         => array(
					array(
						'id'     => 'hide-warning',
						'plugin' => 'acme',
						'hook'   => 'admin_notices',
						'class'  => 'notice-warning',
					),
				),
			)
		);

		$module = new NoticeControlModule(
			new Repository(),
			'mock://rules',
			static function () use ( $payload ) {
				return $payload;
			}
		);
		$module->refresh();

		$this->assertSame( array(), $module->active_rules() );
		$this->assertNotEmpty( $module->discarded_rules() );

		ob_start();
		$module->print_hide_styles();
		$css = (string) ob_get_clean();
		$this->assertSame( '', $css );
		$this->assertStringNotContainsString( 'update-nag', $css );
		$this->assertStringNotContainsString( 'notice-warning', $css );
		$this->assertSame( array(), $module->hidden_items() );
	}

	/**
	 * Update-nag is never emitted as a hide selector.
	 */
	public function test_update_nag_never_appears_in_hide_css() {
		$payload = wp_json_encode(
			array(
				'rules_version' => 1,
				'issued_at'     => '2026-09-03T00:00:00Z',
				'rules'         => array(
					array(
						'id'     => 'hide-nag',
						'plugin' => 'wordpress',
						'hook'   => 'admin_notices',
						'class'  => 'update-nag',
					),
					array(
						'id'     => 'acme-banner',
						'plugin' => 'acme-seo',
						'hook'   => 'admin_notices',
						'class'  => 'acme-promo-banner',
					),
				),
			)
		);

		$module = new NoticeControlModule(
			new Repository(),
			'mock://rules',
			static function () use ( $payload ) {
				return $payload;
			}
		);
		$module->refresh();

		ob_start();
		$module->print_hide_styles();
		$css = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'update-nag', $css );
		$this->assertStringContainsString( 'acme-promo-banner', $css );
		$this->assertSame( array(), $module->hidden_items() );
	}

	/**
	 * Empty source does not invent production rules.
	 */
	public function test_empty_source_has_no_rules() {
		$module = new NoticeControlModule( new Repository() );
		$this->assertSame( '', $module->source() );
		$this->assertSame( array(), $module->active_rules() );
	}

	/**
	 * Fixture path.
	 */
	private function fixture(): string {
		return dirname( __DIR__, 2 ) . '/fixtures/notice-rules/sample.json';
	}

	/**
	 * Module pointed at the sample fixture.
	 */
	private function module_from_fixture(): NoticeControlModule {
		return new NoticeControlModule( new Repository(), $this->fixture() );
	}
}
