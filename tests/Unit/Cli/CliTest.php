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
