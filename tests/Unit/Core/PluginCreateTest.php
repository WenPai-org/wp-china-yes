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
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Core\Plugin;
use WenPai\ChinaYes\Integrations\Windfonts\Catalog;
use WenPai\ChinaYes\Migration\LegacyReader;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

/**
 * Kernel create() contract from M1-05b.
 */
class PluginCreateTest extends TestCase {

	/**
	 * Load option stubs for first-boot migration tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		require_once dirname( __DIR__ ) . '/Migration/wp-option-stubs.php';
		OptionStore::reset();
	}

	/**
	 * Module ids in registration order; config is Repository.
	 */
	public function test_create_registers_modules_and_repository() {
		$plugin = Plugin::create();

		$this->assertSame(
			array(
				'stats',
				'connectivity.wordpress_org',
				'connectivity.public_assets',
				'connectivity.avatar',
				'connectivity.heartbeat',
				'connectivity.dashboard_feeds',
				'connectivity.admin_locale_follow',
				'connectivity.icon_photos',
				'modules.windfonts',
				'telemetry',
				'privacy.data_residency',
				'privacy.site_blocklist',
				'diagnostics',
				'services.site_binding',
				'services.apps',
				'rest',
				'admin',
				'services.entitlements',
				'admin.notice_control',
				'admin.announcements',
				'admin.element_hide',
				'providers',
			),
			$plugin->registry()->ids()
		);

		$this->assertInstanceOf( Repository::class, $plugin->container()->get( 'config' ) );
		$this->assertInstanceOf( Catalog::class, $plugin->container()->get( 'windfonts.catalog' ) );
	}

	/**
	 * V4 activate must not write the 3.x option.
	 */
	public function test_activate_does_not_write_legacy_option() {
		$source = file_get_contents( dirname( __DIR__, 3 ) . '/src/Core/Plugin.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file, not a remote URL.
		$this->assertNotFalse( $source );
		$this->assertSame( 0, preg_match( '/(?:update_option|update_site_option|add_option)\s*\(\s*[\'"]wp_china_yes[\'"]/', $source ) );
		$this->assertSame( 0, preg_match( '/register_uninstall_hook/', $source ) );
		Plugin::activate();
		$this->assertArrayHasKey( 'wpcy_installed_at', OptionStore::$options );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
			OptionStore::$options['wpcy_installed_at']
		);
		Plugin::activate();
		$first = OptionStore::$options['wpcy_installed_at'];
		Plugin::activate();
		$this->assertSame( $first, OptionStore::$options['wpcy_installed_at'] );
	}

	/**
	 * Missing 4.0 option + existing wp_china_yes → Runner::execute().
	 */
	public function test_first_boot_migrates_when_settings_absent() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'    => 'off',
			'cravatar' => 'off',
		);

		Plugin::maybe_migrate_from_legacy();

		$this->assertArrayHasKey( Schema::SETTINGS, OptionStore::$options );
		$this->assertArrayHasKey( Schema::MIGRATION_BACKUP, OptionStore::$options );
		$this->assertSame( 'off', OptionStore::$options[ LegacyReader::OPTION ]['store'] );
		$this->assertSame( 'off', OptionStore::$options[ Schema::SETTINGS ]['connectivity']['wordpress_org'] );
		$this->assertSame( 'off', OptionStore::$options[ Schema::SETTINGS ]['connectivity']['avatar'] );
	}

	/**
	 * Existing 4.0 option must not be overwritten.
	 */
	public function test_first_boot_skips_when_settings_exist() {
		OptionStore::$options[ Schema::SETTINGS ]     = array( 'schema_version' => 1 );
		OptionStore::$options[ LegacyReader::OPTION ] = array( 'store' => 'off' );

		Plugin::maybe_migrate_from_legacy();

		$this->assertSame( array( 'schema_version' => 1 ), OptionStore::$options[ Schema::SETTINGS ] );
		$this->assertArrayNotHasKey( Schema::MIGRATION_BACKUP, OptionStore::$options );
	}

	/**
	 * Fresh install with no 3.x option does not write 4.0 settings.
	 */
	public function test_first_boot_skips_when_legacy_absent() {
		Plugin::maybe_migrate_from_legacy();

		$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );
		$this->assertArrayNotHasKey( Schema::MIGRATION_BACKUP, OptionStore::$options );
	}

	/**
	 * Damaged non-array wp_china_yes is treated as empty; no Fatal; does not write the 3.x key.
	 */
	public function test_first_boot_treats_damaged_legacy_as_empty() {
		OptionStore::$options[ LegacyReader::OPTION ] = 'corrupted-string';

		Plugin::maybe_migrate_from_legacy();

		$this->assertArrayHasKey( Schema::SETTINGS, OptionStore::$options );
		$this->assertIsArray( OptionStore::$options[ Schema::SETTINGS ] );
		$this->assertSame( 'corrupted-string', OptionStore::$options[ LegacyReader::OPTION ] );
	}

	/**
	 * Multisite uses network settings + site_option for the 3.x key.
	 */
	public function test_first_boot_migrates_network_settings_on_multisite() {
		OptionStore::$multisite                            = true;
		OptionStore::$site_options[ LegacyReader::OPTION ] = array(
			'store'    => 'off',
			'cravatar' => 'off',
		);

		Plugin::maybe_migrate_from_legacy();

		$this->assertArrayHasKey( Schema::NETWORK_SETTINGS, OptionStore::$site_options );
		$this->assertArrayHasKey( Schema::MIGRATION_BACKUP, OptionStore::$site_options );
		$this->assertSame( 'off', OptionStore::$site_options[ LegacyReader::OPTION ]['store'] );
		$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );
	}

	/**
	 * Runner::execute() throwing must not Fatal boot; Logger warning includes class + message; 4.0 option stays absent.
	 */
	public function test_boot_survives_migration_throw_and_logs_warning() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'    => 'off',
			'cravatar' => 'off',
		);
		OptionStore::$on_update                       = static function ( $key ) {
			if ( Schema::SETTINGS === $key || Schema::MIGRATION_BACKUP === $key || Schema::NETWORK_SETTINGS === $key ) {
				throw new \RuntimeException( 'forced migration failure' );
			}
		};

		$log_file = tempnam( sys_get_temp_dir(), 'wpcy-migrate-' );
		$this->assertNotFalse( $log_file );
		// phpcs:disable WordPress.PHP.IniSet.Risky,WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_restore -- capture Logger error_log sink.
		$previous = ini_set( 'error_log', $log_file );

		$threw = null;
		try {
			Plugin::boot();
		} catch ( \Throwable $e ) {
			$threw = $e;
		}

		$logged = (string) file_get_contents( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp log, not a remote URL.
		if ( is_string( $previous ) ) {
			ini_set( 'error_log', $previous );
		} else {
			ini_restore( 'error_log' );
		}
		// phpcs:enable WordPress.PHP.IniSet.Risky,WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_restore
		unlink( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temp log file.

		$this->assertNull( $threw, null === $threw ? '' : ( get_class( $threw ) . ': ' . $threw->getMessage() ) );
		$this->assertStringContainsString( 'WPCY.warning:', $logged );
		$this->assertStringContainsString( 'RuntimeException', $logged );
		$this->assertStringContainsString( 'forced migration failure', $logged );
		$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );
		$this->assertArrayNotHasKey( Schema::MIGRATION_BACKUP, OptionStore::$options );
	}
}
