<?php
/**
 * CLI config import: the removed announcements root is not stored.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Cli\ConfigCommand;
use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once dirname( __DIR__ ) . '/Diagnostics/wp-diagnostics-stubs.php';
require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';

/**
 * Import from a JSON file carrying the legacy root.
 */
class ConfigImportAnnouncementsTest extends TestCase {

	/**
	 * Temp file path.
	 *
	 * @var string
	 */
	private $file = '';

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
	}

	/**
	 * Remove the temp file.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( '' !== $this->file && is_file( $this->file ) ) {
			unlink( $this->file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test temp file.
		}
		parent::tearDown();
	}

	/**
	 * Single site: envelope import drops announcements from wpcy_settings.
	 */
	public function test_single_site_import_file_drops_announcements() {
		$settings                  = Defaults::settings();
		$settings['announcements'] = array( 'dismissed' => array( 'a1' ) );
		$settings['recovery_mode'] = true;

		$code = $this->import( array( Schema::SETTINGS => $settings ) );

		$this->assertSame( 0, $code );
		$stored = get_option( Schema::SETTINGS );
		$this->assertIsArray( $stored );
		$this->assertTrue( $stored['recovery_mode'] );
		$this->assertArrayNotHasKey( 'announcements', $stored );
	}

	/**
	 * Single site: a bare settings object import also drops it.
	 */
	public function test_single_site_bare_import_file_drops_announcements() {
		$code = $this->import(
			array(
				'schema_version' => Schema::VERSION,
				'recovery_mode'  => true,
				'announcements'  => array( 'dismissed' => array( 'a1' ) ),
			)
		);

		$this->assertSame( 0, $code );
		$stored = get_option( Schema::SETTINGS );
		$this->assertIsArray( $stored );
		$this->assertTrue( $stored['recovery_mode'] );
		$this->assertArrayNotHasKey( 'announcements', $stored );
	}

	/**
	 * Multisite: network and site_overrides sections both drop it.
	 */
	public function test_multisite_import_file_drops_announcements() {
		OptionStore::$multisite         = true;
		$network                        = Defaults::network_settings();
		$network['allow_site_override'] = true;
		$network['announcements']       = array( 'dismissed' => array( 'n1' ) );
		OptionStore::$site_options[ Schema::NETWORK_SETTINGS ] = Defaults::network_settings();

		$code = $this->import(
			array(
				'network'        => $network,
				'site_overrides' => array(
					'schema_version' => Schema::VERSION,
					'recovery_mode'  => true,
					'announcements'  => array( 'dismissed' => array( 's1' ) ),
				),
			)
		);

		$this->assertSame( 0, $code );
		$stored_network = get_site_option( Schema::NETWORK_SETTINGS );
		$overrides      = get_option( Schema::SITE_OVERRIDES );
		$this->assertIsArray( $stored_network );
		$this->assertIsArray( $overrides );
		$this->assertTrue( $stored_network['allow_site_override'] );
		$this->assertTrue( $overrides['recovery_mode'] );
		$this->assertArrayNotHasKey( 'announcements', $stored_network );
		$this->assertArrayNotHasKey( 'announcements', $overrides );
	}

	/**
	 * Write $doc to a temp JSON file and run `config import <file>`.
	 *
	 * @param array<string, mixed> $doc Document.
	 * @return int
	 */
	private function import( array $doc ): int {
		$file = tempnam( sys_get_temp_dir(), 'wpcy-import-' );
		$this->assertIsString( $file );
		$this->file = $file;
		file_put_contents( $file, (string) wp_json_encode( $doc ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture file.

		return ( new ConfigCommand( new Repository() ) )->import( array( $file ), array() );
	}
}
