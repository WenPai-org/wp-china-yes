<?php
/**
 * Sign-ruleset CLI signs a JSON file that Ruleset then verifies.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Privacy;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;

/**
 * Acceptance: exec the signer, then Ruleset::verified() is true.
 */
class SignRulesetScriptTest extends TestCase {

	/**
	 * Temporary ruleset path.
	 *
	 * @var string
	 */
	private $tmp = '';

	/**
	 * Remove the temp file.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( is_string( $this->tmp ) && '' !== $this->tmp && is_file( $this->tmp ) ) {
			unlink( $this->tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temp signed JSON.
		}
		parent::tearDown();
	}

	/**
	 * Signing a temp JSON with the TEST ONLY key verifies under Ruleset.
	 */
	public function test_script_signs_payload_that_ruleset_verifies() {
		$root   = dirname( __DIR__, 3 );
		$script = $root . '/scripts/sign-ruleset.php';
		$key    = $root . '/tests/fixtures/keys/wpcy-test-ed25519.key';

		$this->assertFileExists( $script );
		$this->assertFileExists( $key );

		$payload = array(
			'issued_at'       => '2026-09-04T00:00:00Z',
			'ruleset_version' => 99,
			'tiers'           => array(
				'C' => array(
					array(
						'action' => 'ignore',
						'host'   => '*',
					),
				),
			),
		);
		$encoded = json_encode( $payload ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap has no WordPress.
		$this->assertIsString( $encoded );

		$this->tmp = tempnam( sys_get_temp_dir(), 'wpcy-sign-' );
		$this->assertNotFalse( $this->tmp );
		file_put_contents( $this->tmp, $encoded ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temp unsigned JSON.

		$cmd    = sprintf(
			'%s %s %s %s --kid %s',
			escapeshellarg( PHP_BINARY ),
			escapeshellarg( $script ),
			escapeshellarg( $this->tmp ),
			escapeshellarg( $key ),
			escapeshellarg( 'wpcy-ruleset-2026' )
		);
		$output = array();
		$code   = 0;
		exec( $cmd . ' 2>&1', $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- CLI signer subprocess.

		$this->assertSame( 0, $code, implode( "\n", $output ) );

		$signed = json_decode( (string) file_get_contents( $this->tmp ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp signed JSON.
		$this->assertIsArray( $signed );
		$this->assertSame( 'wpcy-ruleset-2026', $signed['kid'] );
		$this->assertNotSame( '', $signed['signature'] );

		$ruleset = new Ruleset( $this->tmp );
		$this->assertTrue( $ruleset->verified() );
		$this->assertSame( 99, $ruleset->version() );
	}
}
