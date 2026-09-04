<?php
/**
 * WP-CLI status / doctor / config JSON contracts.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Cli\ConfigCommand;
use WenPai\ChinaYes\Cli\DoctorCommand;
use WenPai\ChinaYes\Cli\StatusCommand;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WenPai\ChinaYes\Tests\Unit\Diagnostics\DiagnosticsStore;

require_once dirname( __DIR__ ) . '/Diagnostics/wp-diagnostics-stubs.php';
require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';

/**
 * CLI JSON schema and credential stripping.
 */
class CliTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		DiagnosticsStore::reset();
		OptionStore::reset();
	}

	/**
	 * Status JSON has kernel, recovery_mode, targets.
	 */
	public function test_status_payload_has_kernel_recovery_and_targets() {
		DiagnosticsStore::$transients[ Checker::STORE_KEY ] = array(
			array(
				'target'     => 'cdnjs.admincdn.com',
				'result'     => Checker::RESULT_OK,
				'latency_ms' => 11,
				'checked_at' => '2026-09-04T12:00:00Z',
				'suggestion' => null,
			),
		);

		$payload = ( new StatusCommand( new Checker(), new MapConfig( array( 'recovery_mode' => true ) ) ) )->payload();

		$this->assertArrayHasKey( 'kernel', $payload );
		$this->assertArrayHasKey( 'recovery_mode', $payload );
		$this->assertArrayHasKey( 'targets', $payload );
		$this->assertTrue( $payload['recovery_mode'] );
		$this->assertIsArray( $payload['targets'] );
		$this->assertSame( 'cdnjs.admincdn.com', $payload['targets'][0]['target'] );
		$this->assertSame(
			array( 'target', 'result', 'latency_ms', 'checked_at', 'suggestion' ),
			array_keys( $payload['targets'][0] )
		);
	}

	/**
	 * Doctor runs a probe then prints the same envelope. Exit 1 on down.
	 */
	public function test_doctor_runs_and_exits_one_when_down() {
		$checker = new Checker(
			static function () {
				return new \WP_Error();
			},
			'get_transient',
			'set_transient',
			null,
			static function () {
				return '2026-09-04T12:00:00Z';
			}
		);

		ob_start();
		$code = ( new DoctorCommand( $checker, new MapConfig( array( 'recovery_mode' => false ) ) ) )->run();
		$json = ob_get_clean();

		$this->assertSame( 1, $code );
		$decoded = json_decode( $json, true );
		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'targets', $decoded );
		$this->assertNotEmpty( $decoded['targets'] );
		$this->assertSame( Checker::RESULT_DOWN, $decoded['targets'][0]['result'] );
	}

	/**
	 * Doctor exit 0 when every target is ok.
	 */
	public function test_doctor_exits_zero_when_all_ok() {
		$checker = new Checker(
			static function () {
				return array(
					'response' => array(
						'code' => 200,
					),
				);
			},
			'get_transient',
			'set_transient'
		);

		ob_start();
		$code = ( new DoctorCommand( $checker ) )->run();
		ob_end_clean();

		$this->assertSame( 0, $code );
	}

	/**
	 * Export has settings, no credential, no email.
	 */
	public function test_config_export_strips_credential() {
		OptionStore::$options[ Schema::SITE_IDENTITY ] = array(
			'schema_version' => 1,
			'site_uuid'      => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
			'binding'        => array(
				'status'     => 'bound',
				'credential' => 'secret-token',
				'site_hash'  => 'abcd',
			),
		);

		$doc = ( new ConfigCommand( new Repository() ) )->export_document();

		$encoded = wp_json_encode( $doc );
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( 'secret-token', $encoded );
		$this->assertArrayHasKey( Schema::SETTINGS, $doc );
		$this->assertArrayHasKey( Schema::SITE_IDENTITY, $doc );
		$this->assertArrayNotHasKey( 'credential', $doc[ Schema::SITE_IDENTITY ]['binding'] );
		$this->assertSame( 'bound', $doc[ Schema::SITE_IDENTITY ]['binding']['status'] );
	}

	/**
	 * Import runs through Validator; unknown keys are discarded.
	 */
	public function test_config_import_discards_unknown_keys() {
		$cmd = new ConfigCommand( new Repository() );
		$cmd->import_document(
			array(
				Schema::SETTINGS => array(
					'schema_version' => 1,
					'recovery_mode'  => true,
					'unknown_key'    => 'drop-me',
					'connectivity'   => array(
						'wordpress_org' => 'off',
						'bogus'         => 'nope',
					),
				),
			)
		);

		$saved = OptionStore::$options[ Schema::SETTINGS ];
		$this->assertTrue( $saved['recovery_mode'] );
		$this->assertSame( 'off', $saved['connectivity']['wordpress_org'] );
		$this->assertArrayNotHasKey( 'unknown_key', $saved );
		$this->assertArrayNotHasKey( 'bogus', $saved['connectivity'] );
	}

	/**
	 * Multisite export is network, site_overrides, and read-only effective.
	 */
	public function test_config_export_multisite_three_sections() {
		OptionStore::$multisite = true;
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 1,
				'allow_site_override' => true,
				'recovery_mode'       => false,
				'connectivity'        => array( 'wordpress_org' => 'auto' ),
			)
		);
		update_option(
			Schema::SITE_OVERRIDES,
			array(
				'schema_version' => 1,
				'recovery_mode'  => true,
				'connectivity'   => array( 'wordpress_org' => 'off' ),
			)
		);

		$doc = ( new ConfigCommand( new Repository() ) )->export_document();

		$this->assertArrayHasKey( 'network', $doc );
		$this->assertArrayHasKey( 'site_overrides', $doc );
		$this->assertArrayHasKey( 'effective', $doc );
		$this->assertArrayNotHasKey( Schema::SETTINGS, $doc );
		$this->assertFalse( $doc['network']['recovery_mode'] );
		$this->assertTrue( $doc['site_overrides']['recovery_mode'] );
		$this->assertTrue( $doc['effective']['recovery_mode'] );
		$this->assertSame( 'off', $doc['effective']['connectivity']['wordpress_org'] );
		$this->assertSame( 'auto', $doc['network']['connectivity']['wordpress_org'] );
	}

	/**
	 * Multisite import ignores effective and does not copy it into network.
	 */
	public function test_config_import_rejects_effective() {
		OptionStore::$multisite = true;
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 1,
				'allow_site_override' => true,
				'recovery_mode'       => false,
			)
		);

		$cmd = new ConfigCommand( new Repository() );
		$cmd->import_document(
			array(
				'network'        => array(
					'schema_version'      => 1,
					'allow_site_override' => true,
					'recovery_mode'       => false,
					'connectivity'        => array( 'wordpress_org' => 'auto' ),
				),
				'site_overrides' => array(
					'schema_version' => 1,
					'recovery_mode'  => true,
				),
				'effective'      => array(
					'schema_version' => 1,
					'recovery_mode'  => true,
					'connectivity'   => array( 'wordpress_org' => 'off' ),
				),
			)
		);

		$network   = get_site_option( Schema::NETWORK_SETTINGS );
		$overrides = get_option( Schema::SITE_OVERRIDES );
		$this->assertIsArray( $network );
		$this->assertIsArray( $overrides );
		$this->assertFalse( $network['recovery_mode'] );
		$this->assertSame( 'auto', $network['connectivity']['wordpress_org'] );
		$this->assertTrue( $overrides['recovery_mode'] );
		$this->assertArrayNotHasKey( 'effective', OptionStore::$options );
		$this->assertArrayNotHasKey( 'effective', OptionStore::$site_options );
	}

	/**
	 * Doctor exit-code helper treats down as failure.
	 */
	public function test_has_down_detects_down_only() {
		$this->assertFalse(
			DoctorCommand::has_down(
				array(
					'targets' => array(
						array( 'result' => Checker::RESULT_OK ),
						array( 'result' => Checker::RESULT_FALLBACK ),
					),
				)
			)
		);
		$this->assertTrue(
			DoctorCommand::has_down(
				array(
					'targets' => array(
						array( 'result' => Checker::RESULT_OK ),
						array( 'result' => Checker::RESULT_DOWN ),
					),
				)
			)
		);
	}
}
