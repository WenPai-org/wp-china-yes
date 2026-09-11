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
	 * Legacy-option fixtures under tests/fixtures/legacy-options/.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function fixture_files(): array {
		return array(
			'single-3.6.2-01'           => array( 'single-3.6.2-01.json' ),
			'single-3.8-02'             => array( 'single-3.8-02.json' ),
			'single-3.9.3-03'           => array( 'single-3.9.3-03.json' ),
			'multisite-3.7.1-04'        => array( 'multisite-3.7.1-04.json' ),
			'multisite-3.8-05'          => array( 'multisite-3.8-05.json' ),
			'multisite-3.8-06'          => array( 'multisite-3.8-06.json' ),
			'single-3.9-07-store-proxy' => array( 'single-3.9-07-store-proxy.json' ),
			'single-3.9-08-admincdn'    => array( 'single-3.9-08-admincdn-files-admin.json' ),
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
			$this->assertArrayNotHasKey( Runner::REPORT_OPTION, OptionStore::$site_options );
		} else {
			$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );
			$this->assertArrayNotHasKey( Schema::MIGRATION_BACKUP, OptionStore::$options );
			$this->assertArrayNotHasKey( Runner::REPORT_OPTION, OptionStore::$options );
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

		$this->assertSame( array( 'google_fonts', 'cdnjs' ), $report->settings()['connectivity']['public_assets']['items'] );
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

		$this->assertSame( array( 'google_fonts' ), $report->settings()['connectivity']['public_assets']['items'] );
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

		$this->assertSame( Schema::PUBLIC_ASSETS, $report->settings()['connectivity']['public_assets']['items'] );
		$this->assertCount( 5, $report->settings()['connectivity']['public_assets']['items'] );
	}

	/**
	 * §5: store=proxy is equivalent to wenpai → connectivity.wordpress_org=auto.
	 */
	public function test_store_proxy_maps_like_wenpai() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store' => 'proxy',
		);

		$report = ( new Runner() )->dry_run();

		$this->assertContains( 'store', $report->kept() );
		$this->assertNotContains( 'store', $report->ignored() );
		$this->assertSame( 'auto', $report->settings()['connectivity']['wordpress_org'] );
	}

	/**
	 * §5: hide_option / hide_menu / hide_menu_confirm any true. 4.0 has no white-label menu.
	 */
	public function test_hide_keys_are_discarded_not_mapped() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'             => 'off',
			'hide'              => true,
			'hide_option'       => '1',
			'hide_menu'         => true,
			'hide_menu_confirm' => array( 'yes' ),
		);

		$report = ( new Runner() )->dry_run();
		$json   = wp_json_encode( $report->settings() );

		foreach ( array( 'hide', 'hide_option', 'hide_menu', 'hide_menu_confirm' ) as $key ) {
			$this->assertContains( $key, $report->ignored(), $key );
			$this->assertNotContains( $key, $report->kept(), $key );
			$this->assertSame( 'feature_removed', $report->ignored_reasons()[ $key ], $key );
		}

		$this->assertIsString( $json );
		$this->assertStringNotContainsString( 'hide_option', $json );
		$this->assertStringNotContainsString( 'hide_menu', $json );
		$this->assert_modules_connectivity_keys_match_schema( $report->settings() );
	}

	/**
	 * §5: 3.8 admincdn=['admin'] and 3.9 admincdn_files=['admin'] land on the same 4.0 public_assets.
	 */
	public function test_admincdn_v38_and_files_admin_same_public_assets() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'    => 'off',
			'admincdn' => array( 'admin' ),
		);
		$from_v38                                     = ( new Runner() )->dry_run();

		OptionStore::reset();
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'          => 'off',
			'admincdn_files' => array( 'admin' ),
		);
		$from_files                                   = ( new Runner() )->dry_run();

		$this->assertSame( array(), $from_v38->settings()['connectivity']['public_assets']['items'] );
		$this->assertSame( 'both', $from_v38->settings()['connectivity']['public_assets']['scope'] );
		$this->assertSame(
			$from_v38->settings()['connectivity']['public_assets'],
			$from_files->settings()['connectivity']['public_assets']
		);
		$this->assertNotContains( 'admin', $from_v38->settings()['connectivity']['public_assets']['items'] );
		$this->assertNotContains( 'admin', $from_files->settings()['connectivity']['public_assets']['items'] );
		$this->assertArrayNotHasKey( 'admin_assets', $from_v38->settings() );
		$this->assertArrayNotHasKey( 'admin_assets', $from_files->settings() );
		$this->assertContains( 'admincdn', $from_v38->ignored() );
		$this->assertNotContains( 'admin', $from_v38->ignored() );
		$this->assertContains( 'admincdn_files', $from_files->kept() );
		$this->assertNotContains( 'admin', $from_files->ignored() );
		$this->assertStringContainsString( '后台静态加速已取消（易致后台界面问题）', implode( ' ', $from_v38->to_array()['messages'] ) );
	}

	/**
	 * 3.8 admincdn mixed tokens: mapped enum + ignored admin/bootstrapcdn.
	 */
	public function test_admincdn_v38_mixed_tokens_map_and_ignore() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'    => 'off',
			'admincdn' => array( 'admin', 'googlefonts', 'jsdelivr', 'bootstrapcdn' ),
		);

		$report = ( new Runner() )->dry_run();

		$this->assertSame(
			array( 'google_fonts', 'jsdelivr' ),
			$report->settings()['connectivity']['public_assets']['items']
		);
		$this->assertArrayNotHasKey( 'admin_assets', $report->settings() );
		$this->assertContains( 'admincdn', $report->ignored() );
		$this->assertNotContains( 'admin', $report->ignored() );
		$this->assertContains( 'bootstrapcdn', $report->ignored() );
		$this->assertSame( 'unsupported_whitelist', $report->ignored_reasons()['bootstrapcdn'] );
		$this->assertNotContains( 'googlefonts', $report->ignored() );
		$this->assertNotContains( 'jsdelivr', $report->ignored() );
	}

	/**
	 * 3.x frontend token is ignored as unsupported_whitelist.
	 */
	public function test_frontend_token_is_ignored() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'    => 'off',
			'admincdn' => array( 'frontend', 'googlefonts' ),
		);

		$report = ( new Runner() )->dry_run();

		$this->assertContains( 'frontend', $report->ignored() );
		$this->assertSame( 'unsupported_whitelist', $report->ignored_reasons()['frontend'] );
		$this->assertSame( array( 'google_fonts' ), $report->settings()['connectivity']['public_assets']['items'] );
		$this->assertNotContains( 'frontend', $report->settings()['connectivity']['public_assets']['items'] );
	}

	/**
	 * 3.x cravatar=weavatar maps to cravatar_cn and records an ignored row.
	 */
	public function test_weavatar_maps_to_cravatar_cn_with_ignored_entry() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'cravatar' => 'weavatar',
		);

		$report  = ( new Runner() )->dry_run();
		$ignored = $report->to_array()['ignored'];
		$entry   = null;
		foreach ( $ignored as $row ) {
			if ( is_array( $row ) && isset( $row['key'] ) && 'cravatar' === $row['key'] ) {
				$entry = $row;
				break;
			}
		}

		$this->assertSame( 'cravatar_cn', $report->settings()['connectivity']['avatar'] );
		$this->assertContains( 'cravatar', $report->kept() );
		$this->assertIsArray( $entry );
		$this->assertSame( 'weavatar', $entry['value'] );
		$this->assertSame( 'WeAvatar 已不再支持，已改为 Cravatar 中国线路', $entry['reason'] );
	}

	/**
	 * §5: memory four keys are dropped even when performance is true. 4.0 has no those constants.
	 */
	public function test_memory_keys_discarded_even_when_performance_true() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'               => 'off',
			'performance'         => true,
			'wp_memory_limit'     => '1024M',
			'wp_max_memory_limit' => '512M',
			'wp_post_revisions'   => 5,
			'autosave_interval'   => 60,
		);

		$report = ( new Runner() )->dry_run();
		$json   = wp_json_encode( $report->settings() );

		foreach ( array( 'performance', 'wp_memory_limit', 'wp_max_memory_limit', 'wp_post_revisions', 'autosave_interval' ) as $key ) {
			$this->assertContains( $key, $report->ignored(), $key );
			$this->assertNotContains( $key, $report->kept(), $key );
		}

		$this->assertIsString( $json );
		$this->assertStringNotContainsString( 'wp_memory_limit', $json );
		$this->assertStringNotContainsString( 'autosave_interval', $json );
		$this->assert_modules_connectivity_keys_match_schema( $report->settings() );
	}

	/**
	 * §5: product shells and ghost enabled_sections values are discarded.
	 */
	public function test_product_shells_and_ghost_sections_discarded() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'            => 'off',
			'arkpress'         => true,
			'motucloud'        => 'cn',
			'fewmail'          => 'cn',
			'bisheng'          => 'cn',
			'deerlogin'        => 'cn',
			'woocn'            => 'cn',
			'lelms'            => 'cn',
			'wapuu'            => 'cn',
			'yoodefender'      => 'cn',
			'docs'             => 'cn',
			'wordyeah'         => 'off',
			'monitor'          => true,
			'waimao'           => 'off',
			'enabled_sections' => array(
				'store',
				'forums',
				'forms',
				'panel',
				'domain',
				'sms',
				'chat',
				'translate',
				'ecosystem',
			),
		);

		$report = ( new Runner() )->dry_run();

		$shells = array(
			'arkpress',
			'motucloud',
			'fewmail',
			'bisheng',
			'deerlogin',
			'woocn',
			'lelms',
			'wapuu',
			'yoodefender',
			'docs',
			'wordyeah',
			'monitor',
			'waimao',
			'enabled_sections',
		);
		foreach ( $shells as $key ) {
			$this->assertContains( $key, $report->ignored(), $key );
			$this->assertNotContains( $key, $report->kept(), $key );
			$this->assertSame( 'feature_removed', $report->ignored_reasons()[ $key ], $key );
		}

		$json = wp_json_encode( $report->settings() );
		$this->assertIsString( $json );
		$this->assertStringNotContainsString( 'motucloud', $json );
		$this->assertStringNotContainsString( 'enabled_sections', $json );
		$this->assertStringNotContainsString( 'ecosystem', $json );
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
			case 'single-3.9-07-store-proxy.json':
			case 'single-3.9-08-admincdn-files-admin.json':
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
	 * Expected ignored: unkept fixture keys plus explicit unknown tokens.
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
		foreach ( $this->expected_ignored_tokens( $file ) as $token ) {
			if ( ! in_array( $token, $out, true ) ) {
				$out[] = $token;
			}
		}
		return $out;
	}

	/**
	 * Unknown admincdn tokens that must appear in ignored alongside the key.
	 *
	 * @param string $file Fixture basename.
	 * @return array<int, string>
	 */
	private function expected_ignored_tokens( string $file ): array {
		unset( $file );
		return array();
	}

	/**
	 * Modules and connectivity key sets equal the schema.
	 *
	 * @param array<string, mixed> $settings Sanitized 4.0 document.
	 */
	private function assert_modules_connectivity_keys_match_schema( array $settings ): void {
		$schema = Schema::definition( Schema::SETTINGS );
		$this->assertEqualsCanonicalizing(
			array_keys( $schema['properties']['modules']['properties'] ),
			array_keys( $settings['modules'] )
		);
		$this->assertEqualsCanonicalizing(
			array_keys( $schema['properties']['connectivity']['properties'] ),
			array_keys( $settings['connectivity'] )
		);
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
				$this->assertSame( array(), $connectivity['public_assets']['items'] );
				$this->assertSame( 'both', $connectivity['public_assets']['scope'] );
				$this->assertSame( 'cravatar_cn', $connectivity['avatar'] );
				$this->assertSame( 'domestic', $settings['profile'] );
				$this->assertArrayNotHasKey( 'admin_assets', $settings );
				$this->assertFalse( $modules['windfonts'] );
				$this->assertFalse( $modules['notice_control'] );
				$this->assertArrayNotHasKey( 'allow_site_override', $settings );
				break;

			case 'single-3.8-02.json':
				$this->assertSame( 'off', $connectivity['wordpress_org'] );
				$this->assertSame( array(), $connectivity['public_assets']['items'] );
				$this->assertSame( 'both', $connectivity['public_assets']['scope'] );
				$this->assertSame( 'cravatar_cn', $connectivity['avatar'] );
				$this->assertSame( 'domestic', $settings['profile'] );
				$this->assertArrayNotHasKey( 'admin_assets', $settings );
				$this->assertTrue( $modules['windfonts'] );
				$this->assertFalse( $modules['notice_control'] );
				break;

			case 'single-3.9.3-03.json':
			case 'single-3.9-07-store-proxy.json':
				$this->assertSame( 'auto', $connectivity['wordpress_org'] );
				$this->assertSame( array(), $connectivity['public_assets']['items'] );
				$this->assertSame( 'both', $connectivity['public_assets']['scope'] );
				$this->assertSame( 'cravatar_cn', $connectivity['avatar'] );
				$this->assertSame( 'domestic', $settings['profile'] );
				$this->assertArrayNotHasKey( 'admin_assets', $settings );
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

			case 'single-3.9-08-admincdn-files-admin.json':
				$this->assertSame( 'auto', $connectivity['wordpress_org'] );
				$this->assertSame( array(), $connectivity['public_assets']['items'] );
				$this->assertArrayNotHasKey( 'admin_assets', $settings );
				$this->assertSame( 'domestic', $settings['profile'] );
				break;

			case 'multisite-3.7.1-04.json':
			case 'multisite-3.8-05.json':
			case 'multisite-3.8-06.json':
				$this->assertSame( 'off', $connectivity['wordpress_org'] );
				$this->assertSame( array(), $connectivity['public_assets']['items'] );
				$this->assertSame( 'cravatar_cn', $connectivity['avatar'] );
				$this->assertFalse( $modules['windfonts'] );
				$this->assertFalse( $modules['notice_control'] );
				$this->assertTrue( $settings['allow_site_override'] );
				break;
		}
	}
}
