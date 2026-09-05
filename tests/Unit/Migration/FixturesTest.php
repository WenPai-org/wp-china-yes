<?php
/**
 * Six legacy-option fixtures: dry-run, execute, schema, leftover 3.x, rollback.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Cli\MigrateCommand;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Config\Validator;
use WenPai\ChinaYes\Migration\Backup;
use WenPai\ChinaYes\Migration\LegacyReader;
use WenPai\ChinaYes\Migration\Runner;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-option-stubs.php';

/**
 * Fixture-driven migration. Does not load WordPress.
 */
class FixturesTest extends TestCase {

	/**
	 * Fixture directory.
	 *
	 * @var string
	 */
	private $fixtures;

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		$this->fixtures = dirname( __DIR__, 2 ) . '/fixtures/legacy-options';
	}

	/**
	 * Six files under tests/fixtures/legacy-options/.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function fixture_files(): array {
		return array(
			'single-3.6.2-01'    => array( 'single-3.6.2-01.json' ),
			'single-3.8-02'      => array( 'single-3.8-02.json' ),
			'single-3.9.3-03'    => array( 'single-3.9.3-03.json' ),
			'multisite-3.7.1-04' => array( 'multisite-3.7.1-04.json' ),
			'multisite-3.8-05'   => array( 'multisite-3.8-05.json' ),
			'multisite-3.8-06'   => array( 'multisite-3.8-06.json' ),
		);
	}

	/**
	 * Dry-run kept / ignored sets match the §7.2 table (order ignored).
	 *
	 * @dataProvider fixture_files
	 *
	 * @param string $file Fixture basename.
	 */
	public function test_dry_run_kept_ignored_sets( string $file ) {
		$loaded = $this->load_fixture( $file );
		$this->install_legacy( $loaded );

		$report = ( new Runner() )->dry_run();

		$this->assertEqualsCanonicalizing( $this->expected_kept( $file ), $report->kept(), $file . ' kept' );
		$this->assertEqualsCanonicalizing( $this->expected_ignored( $file ), $report->ignored(), $file . ' ignored' );
		$this->assert_mapped_values( $file, $report->settings() );
		$this->assert_legacy_untouched( $loaded );
		$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );
		$this->assertArrayNotHasKey( Schema::NETWORK_SETTINGS, OptionStore::$site_options );
	}

	/**
	 * Execute writes a schema-valid 4.0 option, leaves wp_china_yes, records backup.
	 *
	 * @dataProvider fixture_files
	 *
	 * @param string $file Fixture basename.
	 */
	public function test_execute_validates_and_keeps_legacy( string $file ) {
		$loaded = $this->load_fixture( $file );
		$this->install_legacy( $loaded );

		$report = ( new Runner() )->execute();

		$this->assertEqualsCanonicalizing( $this->expected_kept( $file ), $report->kept(), $file . ' kept' );
		$this->assertEqualsCanonicalizing( $this->expected_ignored( $file ), $report->ignored(), $file . ' ignored' );
		$this->assert_mapped_values( $file, $report->settings() );
		$this->assert_legacy_untouched( $loaded );

		$option = $this->is_network( $loaded ) ? Schema::NETWORK_SETTINGS : Schema::SETTINGS;
		$stored = $this->stored_settings( $loaded );
		$this->assertIsArray( $stored );
		$this->assertSame( $report->settings(), $stored );

		$validator = new Validator();
		$clean     = $validator->sanitize( $stored, $option );
		$this->assertSame( array(), $validator->warnings(), $file . ' schema warnings' );
		$this->assertSame( $stored, $clean );

		$backup = $this->stored_backup( $loaded );
		$this->assertNotSame( array(), $backup );
		$this->assertArrayHasKey( 'from_version', $backup );
		$this->assertArrayHasKey( 'migrated_at', $backup );
		$this->assertArrayHasKey( 'legacy_hash', $backup );
		$this->assertArrayHasKey( 'ignored_fields', $backup );
		$this->assertSame( Backup::hash( $loaded['wp_china_yes'] ), $backup['legacy_hash'] );
		$this->assertEqualsCanonicalizing( $report->ignored(), $backup['ignored_fields'] );
		$this->assertArrayNotHasKey( 'credential', $backup );
		$this->assertArrayNotHasKey( 'bridge', $backup );
	}

	/**
	 * Execute is idempotent: second run keeps 3.x and rewrites the same 4.0 document.
	 *
	 * @dataProvider fixture_files
	 *
	 * @param string $file Fixture basename.
	 */
	public function test_execute_is_idempotent( string $file ) {
		$loaded = $this->load_fixture( $file );
		$this->install_legacy( $loaded );

		$first  = ( new Runner() )->execute();
		$second = ( new Runner() )->execute();

		$this->assertSame( $first->settings(), $second->settings() );
		$this->assertEqualsCanonicalizing( $first->kept(), $second->kept() );
		$this->assertEqualsCanonicalizing( $first->ignored(), $second->ignored() );
		$this->assert_legacy_untouched( $loaded );
	}

	/**
	 * Rollback drops 4.0 options and backup; never writes into wp_china_yes.
	 *
	 * @dataProvider fixture_files
	 *
	 * @param string $file Fixture basename.
	 */
	public function test_rollback_restores_without_rewriting_legacy( string $file ) {
		$loaded = $this->load_fixture( $file );
		$this->install_legacy( $loaded );

		$runner = new Runner();
		$runner->execute();
		$this->assertTrue( $runner->rollback() );

		$this->assert_legacy_untouched( $loaded );

		if ( $this->is_network( $loaded ) ) {
			$this->assertArrayNotHasKey( Schema::NETWORK_SETTINGS, OptionStore::$site_options );
			$this->assertArrayNotHasKey( Schema::MIGRATION_BACKUP, OptionStore::$site_options );
		} else {
			$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );
			$this->assertArrayNotHasKey( Schema::MIGRATION_BACKUP, OptionStore::$options );
		}

		$this->assertFalse( $runner->rollback() );
	}

	/**
	 * Telemetry keys are ignored when present and omitted from the report when absent.
	 */
	public function test_telemetry_keys_never_migrate() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'              => 'off',
			'telemetry'          => 'off',
			'telemetry_site_url' => 'https://example.com',
		);

		$report = ( new Runner() )->dry_run();

		$this->assertContains( 'telemetry', $report->ignored() );
		$this->assertContains( 'telemetry_site_url', $report->ignored() );
		$this->assertSame( 'not_migrated', $report->ignored_reasons()['telemetry'] );
		$this->assertSame( 'not_migrated', $report->ignored_reasons()['telemetry_site_url'] );
		$this->assertNotContains( 'telemetry', $report->kept() );

		$three = $this->load_fixture( 'single-3.9.3-03.json' );
		$this->assertArrayNotHasKey( 'telemetry', $three['wp_china_yes'] );
		OptionStore::reset();
		$this->install_legacy( $three );
		$from_three = ( new Runner() )->dry_run();
		$this->assertNotContains( 'telemetry', $from_three->ignored() );
		$this->assertNotContains( 'telemetry', $from_three->kept() );
	}

	/**
	 * Only admincdn_public present: googlefonts/cdnjs map; files/dev absent.
	 */
	public function test_public_assets_from_admincdn_public_only() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'admincdn_public' => array( 'googlefonts', 'cdnjs' ),
		);

		$report = ( new Runner() )->dry_run();

		$this->assertSame( array( 'google_fonts', 'cdnjs' ), $report->settings()['connectivity']['public_assets'] );
		$this->assertContains( 'admincdn_public', $report->kept() );
		$this->assertNotContains( 'admincdn_public', $report->ignored() );
	}

	/**
	 * Unknown jquery and react tokens are unsupported_whitelist.
	 */
	public function test_jquery_react_are_unsupported_whitelist() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'admincdn_public' => array( 'googlefonts', 'jquery', 'react' ),
		);

		$report = ( new Runner() )->dry_run();

		$this->assertSame( array( 'google_fonts' ), $report->settings()['connectivity']['public_assets'] );
		$this->assertContains( 'jquery', $report->ignored() );
		$this->assertContains( 'react', $report->ignored() );
		$this->assertSame( 'unsupported_whitelist', $report->ignored_reasons()['jquery'] );
		$this->assertSame( 'unsupported_whitelist', $report->ignored_reasons()['react'] );
	}

	/**
	 * All three admincdn_public / files / dev keys missing → schema default five items.
	 */
	public function test_public_assets_default_when_admincdn_keys_absent() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store' => 'off',
		);

		$report = ( new Runner() )->dry_run();

		$this->assertSame( Schema::PUBLIC_ASSETS, $report->settings()['connectivity']['public_assets'] );
		$this->assertCount( 5, $report->settings()['connectivity']['public_assets'] );
	}

	/**
	 * CLI dry-run / execute / rollback JSON contracts.
	 */
	public function test_cli_dry_run_execute_rollback() {
		$loaded = $this->load_fixture( 'single-3.6.2-01.json' );
		$this->install_legacy( $loaded );
		$cmd = new MigrateCommand();

		ob_start();
		$code = $cmd->__invoke( array(), array( 'dry-run' => true ) );
		$json = ob_get_clean();
		$this->assertSame( 0, $code );
		$decoded = json_decode( $json, true );
		$this->assertSame( 'dry-run', $decoded['action'] );
		$this->assertEqualsCanonicalizing( $this->expected_kept( 'single-3.6.2-01.json' ), $decoded['kept'] );
		$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );

		ob_start();
		$code = $cmd->__invoke( array(), array() );
		$json = ob_get_clean();
		$this->assertSame( 0, $code );
		$decoded = json_decode( $json, true );
		$this->assertSame( 'execute', $decoded['action'] );
		$this->assertArrayHasKey( Schema::SETTINGS, OptionStore::$options );

		ob_start();
		$code = $cmd->__invoke( array(), array( 'rollback' => true ) );
		$json = ob_get_clean();
		$this->assertSame( 0, $code );
		$decoded = json_decode( $json, true );
		$this->assertSame( 'rollback', $decoded['action'] );
		$this->assertTrue( $decoded['ok'] );
		$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );
		$this->assert_legacy_untouched( $loaded );
	}

	/**
	 * Decode one fixture file. Data portion is not modified.
	 *
	 * @param string $file Basename.
	 * @return array{wp_china_yes: array<string, mixed>, _fixture: array<string, mixed>}
	 */
	private function load_fixture( string $file ): array {
		$path = $this->fixtures . '/' . $file;
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$this->assertNotFalse( $raw, $file );
		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'wp_china_yes', $decoded );
		return $decoded;
	}

	/**
	 * Put the 3.x option into the matching bag.
	 *
	 * @param array<string, mixed> $loaded Fixture document.
	 */
	private function install_legacy( array $loaded ): void {
		$legacy = $loaded['wp_china_yes'];
		if ( $this->is_network( $loaded ) ) {
			OptionStore::$multisite                            = true;
			OptionStore::$site_options[ LegacyReader::OPTION ] = $legacy;
			return;
		}
		OptionStore::$options[ LegacyReader::OPTION ] = $legacy;
	}

	/**
	 * Network fixture?
	 *
	 * @param array<string, mixed> $loaded Fixture document.
	 */
	private function is_network( array $loaded ): bool {
		return isset( $loaded['_fixture']['scope'] ) && 'network' === $loaded['_fixture']['scope'];
	}

	/**
	 * Assert wp_china_yes is byte-identical to the fixture payload.
	 *
	 * @param array<string, mixed> $loaded Fixture document.
	 */
	private function assert_legacy_untouched( array $loaded ): void {
		$expected = $loaded['wp_china_yes'];
		if ( $this->is_network( $loaded ) ) {
			$this->assertSame( $expected, OptionStore::$site_options[ LegacyReader::OPTION ] );
			$this->assertArrayNotHasKey( LegacyReader::OPTION, OptionStore::$options );
			return;
		}
		$this->assertSame( $expected, OptionStore::$options[ LegacyReader::OPTION ] );
	}

	/**
	 * Stored 4.0 settings for this fixture's scope.
	 *
	 * @param array<string, mixed> $loaded Fixture document.
	 * @return array<string, mixed>|null
	 */
	private function stored_settings( array $loaded ) {
		if ( $this->is_network( $loaded ) ) {
			return OptionStore::$site_options[ Schema::NETWORK_SETTINGS ] ?? null;
		}
		return OptionStore::$options[ Schema::SETTINGS ] ?? null;
	}

	/**
	 * Stored backup for this fixture's scope.
	 *
	 * @param array<string, mixed> $loaded Fixture document.
	 * @return array<string, mixed>
	 */
	private function stored_backup( array $loaded ): array {
		if ( $this->is_network( $loaded ) ) {
			$raw = OptionStore::$site_options[ Schema::MIGRATION_BACKUP ] ?? array();
		} else {
			$raw = OptionStore::$options[ Schema::MIGRATION_BACKUP ] ?? array();
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Expected kept keys (task book + spec §7.2).
	 *
	 * @param string $file Fixture basename.
	 * @return array<int, string>
	 */
	private function expected_kept( string $file ): array {
		switch ( $file ) {
			case 'single-3.6.2-01.json':
				return array( 'store', 'cravatar', 'windfonts', 'adblock' );
			case 'single-3.8-02.json':
				return array( 'store', 'cravatar', 'windfonts', 'adblock' );
			case 'single-3.9.3-03.json':
				return array( 'store', 'admincdn_public', 'admincdn_files', 'admincdn_dev', 'cravatar', 'windfonts', 'windfonts_list', 'adblock' );
			case 'multisite-3.7.1-04.json':
			case 'multisite-3.8-05.json':
			case 'multisite-3.8-06.json':
				return array( 'store', 'cravatar', 'windfonts', 'adblock' );
			default:
				return array();
		}
	}

	/**
	 * Expected ignored keys: every fixture key that is not kept.
	 *
	 * @param string $file Fixture basename.
	 * @return array<int, string>
	 */
	private function expected_ignored( string $file ): array {
		$loaded = $this->load_fixture( $file );
		$keys   = array_keys( $loaded['wp_china_yes'] );
		$kept   = $this->expected_kept( $file );
		$out    = array();
		foreach ( $keys as $key ) {
			if ( ! in_array( $key, $kept, true ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * Mapped 4.0 values for each fixture (spec / closed M0).
	 *
	 * @param string               $file     Fixture basename.
	 * @param array<string, mixed> $settings Sanitized 4.0 document.
	 */
	private function assert_mapped_values( string $file, array $settings ): void {
		$connectivity = $settings['connectivity'];
		$modules      = $settings['modules'];

		switch ( $file ) {
			case 'single-3.6.2-01.json':
				$this->assertSame( 'off', $connectivity['wordpress_org'] );
				$this->assertSame( Schema::PUBLIC_ASSETS, $connectivity['public_assets'] );
				$this->assertSame( 'weavatar', $connectivity['avatar'] );
				$this->assertFalse( $modules['windfonts'] );
				$this->assertFalse( $modules['notice_control'] );
				$this->assertArrayNotHasKey( 'allow_site_override', $settings );
				break;

			case 'single-3.8-02.json':
				$this->assertSame( 'off', $connectivity['wordpress_org'] );
				$this->assertSame( Schema::PUBLIC_ASSETS, $connectivity['public_assets'] );
				$this->assertSame( 'cravatar_cn', $connectivity['avatar'] );
				$this->assertTrue( $modules['windfonts'] );
				$this->assertFalse( $modules['notice_control'] );
				break;

			case 'single-3.9.3-03.json':
				$this->assertSame( 'auto', $connectivity['wordpress_org'] );
				$this->assertSame( array(), $connectivity['public_assets'] );
				$this->assertSame( 'cravatar_cn', $connectivity['avatar'] );
				$this->assertTrue( $modules['windfonts'] );
				$this->assertTrue( $modules['notice_control'] );
				$fonts = $settings['integrations']['windfonts']['fonts'];
				$this->assertCount( 3, $fonts );
				$this->assertSame( 'wenfeng-albbpht', $fonts[0]['family'] );
				$this->assertSame( 'full', $fonts[0]['subset'] );
				$this->assertTrue( $fonts[0]['enable'] );
				$this->assertSame( 'wenfeng-syhtcjk', $fonts[1]['family'] );
				$this->assertFalse( $fonts[1]['enable'] );
				$this->assertSame( 'wenfeng-ibmps', $fonts[2]['family'] );
				$this->assertFalse( $fonts[2]['enable'] );
				break;

			case 'multisite-3.7.1-04.json':
			case 'multisite-3.8-05.json':
			case 'multisite-3.8-06.json':
				$this->assertSame( 'off', $connectivity['wordpress_org'] );
				$this->assertSame( Schema::PUBLIC_ASSETS, $connectivity['public_assets'] );
				$this->assertSame( 'weavatar', $connectivity['avatar'] );
				$this->assertFalse( $modules['windfonts'] );
				$this->assertFalse( $modules['notice_control'] );
				$this->assertTrue( $settings['allow_site_override'] );
				break;
		}
	}
}
