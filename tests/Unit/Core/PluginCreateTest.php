<?php
/**
 * Plugin::create() wires Repository, connectivity modules, and REST.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Plugin;

/**
 * Kernel create() contract from M1-05b.
 */
class PluginCreateTest extends TestCase {

	/**
	 * Module ids in registration order; config is Repository.
	 */
	public function test_create_registers_modules_and_repository() {
		$plugin = Plugin::create();

		$this->assertSame(
			array(
				'connectivity.wordpress_org',
				'connectivity.public_assets',
				'connectivity.avatar',
				'modules.windfonts',
				'telemetry',
				'privacy.data_residency',
				'diagnostics',
				'services.site_binding',
				'services.apps',
				'rest',
				'admin',
				'services.entitlements',
			),
			$plugin->registry()->ids()
		);

		$this->assertInstanceOf( Repository::class, $plugin->container()->get( 'config' ) );
	}

	/**
	 * V4 activate must not write the 3.x option.
	 */
	public function test_activate_does_not_write_legacy_option() {
		$source = file_get_contents( dirname( __DIR__, 3 ) . '/src/Core/Plugin.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file, not a remote URL.
		$this->assertNotFalse( $source );
		$this->assertSame( 0, preg_match( '/(?:update_option|update_site_option|add_option)\s*\(\s*[\'"]wp_china_yes[\'"]/', $source ) );
		Plugin::activate();
	}
}
