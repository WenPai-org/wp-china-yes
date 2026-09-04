<?php
/**
 * Windfonts CSS URL and head markup. family/subset; no crossorigin.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Integrations\Windfonts;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Integrations\Windfonts\Stylesheet;
use WenPai\ChinaYes\Integrations\Windfonts\WindfontsModule;
use WenPai\ChinaYes\Tests\Unit\Integrations\HookStore;
use WenPai\ChinaYes\Tests\Unit\Integrations\MapConfig;

require_once dirname( __DIR__ ) . '/wp-windfonts-stubs.php';

/**
 * Acceptance 2 of M2-07.
 */
class StylesheetTest extends TestCase {

	/**
	 * Reset hook bags.
	 */
	protected function setUp(): void {
		parent::setUp();
		HookStore::reset();
	}

	/**
	 * CSS URL contains the smoke family and subset=full.
	 */
	public function test_css_url_contains_family_and_full_subset() {
		$sheet = new Stylesheet();
		$url   = $sheet->css_url(
			array(
				'family' => 'wenfeng-hcszt',
				'subset' => 'full',
			)
		);

		$this->assertStringContainsString( 'family=wenfeng-hcszt', $url );
		$this->assertStringContainsString( 'subset=full', $url );
		$this->assertStringContainsString( 'https://app.windfonts.com/api/css', $url );
	}

	/**
	 * Missing subset defaults to full.
	 */
	public function test_missing_subset_defaults_to_full() {
		$sheet = new Stylesheet();
		$url   = $sheet->css_url( array( 'family' => 'wenfeng-hcszt' ) );

		$this->assertStringContainsString( 'subset=full', $url );
	}

	/**
	 * Invalid subset is omitted (3.x build_font_css_url).
	 */
	public function test_invalid_subset_is_omitted() {
		$sheet = new Stylesheet();
		$url   = $sheet->css_url(
			array(
				'family' => 'wenfeng-hcszt',
				'subset' => 'latin',
			)
		);

		$this->assertStringContainsString( 'family=wenfeng-hcszt', $url );
		$this->assertStringNotContainsString( 'subset=', $url );
	}

	/**
	 * Rendered HTML has family/subset and never crossorigin.
	 */
	public function test_render_has_family_subset_and_no_crossorigin() {
		$sheet = new Stylesheet();
		$html  = $sheet->render(
			array(
				array(
					'family'   => 'wenfeng-hcszt',
					'subset'   => 'full',
					'selector' => 'body',
					'enable'   => true,
				),
			)
		);

		$this->assertNotFalse( strpos( $html, 'family=wenfeng-hcszt' ) );
		$this->assertNotFalse( strpos( $html, 'subset=full' ) );
		$this->assertFalse( strpos( $html, 'crossorigin' ) );
		$this->assertNotFalse( strpos( $html, 'https://cn.windfonts.com' ) );
		$this->assertNotFalse( strpos( $html, 'https://wenfeng.org/license' ) );
		$this->assertNotFalse( strpos( $html, 'rel="stylesheet"' ) );
	}

	/**
	 * Disabled fonts are skipped. Empty enable matches 3.x empty().
	 */
	public function test_disabled_font_is_skipped() {
		$sheet = new Stylesheet();
		$html  = $sheet->render(
			array(
				array(
					'family'   => 'wenfeng-hcszt',
					'subset'   => 'full',
					'selector' => 'body',
					'enable'   => false,
				),
			)
		);

		$this->assertFalse( strpos( $html, 'family=wenfeng-hcszt' ) );
		$this->assertNotFalse( strpos( $html, 'rel="preconnect"' ) );
	}

	/**
	 * Family name strips :wght suffix (Fonts::extract_font_family_name).
	 */
	public function test_family_name_strips_weight_suffix() {
		$sheet = new Stylesheet();
		$this->assertSame( 'wenfeng-hcszt', $sheet->family_name( 'wenfeng-hcszt:wght@400;700' ) );
		$this->assertSame( 'wenfeng-hcszt', $sheet->family_name( 'wenfeng-hcszt' ) );
	}

	/**
	 * Module print_stylesheets matches the three smoke assertions.
	 */
	public function test_module_print_matches_smoke_assertions() {
		$module = new WindfontsModule(
			new MapConfig(
				array(
					'modules.windfonts'            => true,
					'recovery_mode'                => false,
					'integrations.windfonts.fonts' => array(
						array(
							'family'   => 'wenfeng-hcszt',
							'subset'   => 'full',
							'selector' => 'body',
							'enable'   => true,
						),
					),
				)
			)
		);

		ob_start();
		$module->print_stylesheets();
		$html = (string) ob_get_clean();

		if ( false === strpos( $html, 'family=wenfeng-hcszt' ) || false === strpos( $html, 'subset=full' ) || false !== strpos( $html, 'crossorigin' ) ) {
			$this->fail( 'invalid Windfonts stylesheet output' );
		}

		$this->assertTrue( true );
	}

	/**
	 * Enabled() requires modules.windfonts true, not recovery, entitlement.
	 */
	public function test_enabled_false_when_module_off() {
		$config = new MapConfig(
			array(
				'modules.windfonts' => false,
				'recovery_mode'     => false,
			)
		);
		$module = new WindfontsModule( $config );
		$env    = new Environment( Environment::FRONTEND, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * Recovery mode does not register.
	 */
	public function test_enabled_false_in_recovery_mode() {
		$config = new MapConfig(
			array(
				'modules.windfonts' => true,
				'recovery_mode'     => true,
			)
		);
		$module = new WindfontsModule( $config );
		$env    = new Environment( Environment::FRONTEND, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * Exhausted entitlement does not register (no Windfonts link).
	 */
	public function test_enabled_false_when_entitlement_exhausted() {
		$config = new MapConfig(
			array(
				'modules.windfonts' => true,
				'recovery_mode'     => false,
			)
		);
		$module = new WindfontsModule(
			$config,
			null,
			static function () {
				return false;
			}
		);
		$env    = new Environment( Environment::FRONTEND, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * On, not recovery, entitlement allows → enabled.
	 */
	public function test_enabled_true_when_on() {
		$config = new MapConfig(
			array(
				'modules.windfonts' => true,
				'recovery_mode'     => false,
			)
		);
		$module = new WindfontsModule( $config );
		$env    = new Environment( Environment::FRONTEND, true );

		$this->assertTrue( $module->enabled( $config, $env ) );
	}

	/**
	 * Register() hooks wp_head only (待定 M0 admin_head).
	 */
	public function test_register_hooks_wp_head_only() {
		$module = new WindfontsModule(
			new MapConfig(
				array(
					'modules.windfonts' => true,
					'recovery_mode'     => false,
				)
			)
		);
		$module->register();

		$this->assertArrayHasKey( 'wp_head', HookStore::$hooks );
		$this->assertArrayNotHasKey( 'admin_head', HookStore::$hooks );
	}

	/**
	 * Module id matches the config key.
	 */
	public function test_id_is_modules_windfonts() {
		$module = new WindfontsModule( new MapConfig() );
		$this->assertSame( 'modules.windfonts', $module->id() );
	}
}
