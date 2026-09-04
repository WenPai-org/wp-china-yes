<?php
/**
 * 4.0 Windfonts wp_head smoke (PHPUnit Integration suite).
 *
 * Studio / wp-env assertions live in tests/integration-windfonts.sh.
 * This file is skipped unless WordPress is loaded.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Integrations\Windfonts\Stylesheet;
use WenPai\ChinaYes\Integrations\Windfonts\WindfontsModule;

/**
 * Placeholder for wp-env / Studio. Unit suite covers the three assertions.
 */
class WindfontsSmokeTest extends TestCase {

	/**
	 * Skip when WordPress is not loaded (composer test:unit).
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'ABSPATH' ) || ! function_exists( 'do_action' ) || ! function_exists( 'update_option' ) ) {
			$this->markTestSkipped( 'Requires WordPress (wp-env / Studio). See tests/integration-windfonts.sh.' );
		}
	}

	/**
	 * WPCY_KERNEL=v4 + modules.windfonts + fonts list → family/subset, no crossorigin.
	 */
	public function test_wp_head_matches_legacy_smoke_assertions() {
		if ( ! defined( 'WPCY_KERNEL' ) || 'v4' !== WPCY_KERNEL ) {
			$this->markTestSkipped( 'Requires WPCY_KERNEL=v4.' );
		}

		$settings = get_option( Schema::SETTINGS, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings['modules']['windfonts']               = true;
		$settings['integrations']['windfonts']['fonts'] = array(
			array(
				'family'   => 'wenfeng-hcszt',
				'subset'   => 'full',
				'selector' => 'body',
				'enable'   => true,
			),
		);
		$settings['connectivity']['avatar']             = 'off';
		update_option( Schema::SETTINGS, $settings );

		$module = new WindfontsModule( new \WenPai\ChinaYes\Config\Repository() );
		$module->register();

		ob_start();
		do_action( 'wp_head' );
		$html = (string) ob_get_clean();

		if ( false === strpos( $html, 'family=wenfeng-hcszt' ) || false === strpos( $html, 'subset=full' ) || false !== strpos( $html, 'crossorigin' ) ) {
			$this->fail( 'invalid Windfonts stylesheet output' );
		}

		$this->assertNotFalse( strpos( $html, Stylesheet::LICENSE_COMMENT ) );
	}
}
