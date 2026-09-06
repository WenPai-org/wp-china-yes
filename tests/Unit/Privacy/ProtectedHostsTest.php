<?php
/**
 * L0 protected hosts: builtin list, signed increment, failed verify fallback.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Privacy;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;

/**
 * Hardcoded L0 plus signed increment union.
 */
class ProtectedHostsTest extends TestCase {

	/**
	 * Builtin hosts stay protected even without a signed increment.
	 */
	public function test_builtin_wenpai_net_is_protected() {
		$ruleset = new Ruleset( null, null, false );
		$this->assertTrue( $ruleset->is_protected( 'wenpai.net' ) );
		$this->assertTrue( $ruleset->is_protected( 'api.wenpai.net' ) );
		$this->assertTrue( $ruleset->is_protected( 'WPCY.COM' ) );
		$this->assertTrue( $ruleset->is_protected( 'cdn.admincdn.com' ) );
		$this->assertTrue( $ruleset->is_protected( 'cn.cravatar.com' ) );
		$this->assertTrue( $ruleset->is_protected( 'cravatar.cn' ) );
		$this->assertFalse( $ruleset->is_protected( 'notwenpai.net' ) );
		$this->assertFalse( $ruleset->is_protected( 'example.com' ) );
	}

	/**
	 * Signed increment appends; it cannot drop builtin rows.
	 */
	public function test_signed_increment_appends_without_removing_builtin() {
		$path = $this->write_ruleset(
			array(
				'issued_at'       => '2026-09-06T00:00:00Z',
				'ruleset_version' => 2,
				'tiers'           => array(
					'C' => array(
						array(
							'action' => 'ignore',
							'host'   => '*',
						),
					),
				),
				'protected_hosts' => array(
					array(
						'host'  => 'extra.example',
						'match' => 'exact',
					),
				),
				'noise_block'     => array(),
			)
		);

		try {
			$ruleset = new Ruleset( $path, null, false );
			$this->assertTrue( $ruleset->is_protected( 'api.wenpai.net' ) );
			$this->assertTrue( $ruleset->is_protected( 'extra.example' ) );
		} finally {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temp ruleset.
		}
	}

	/**
	 * A verified signature merges the increment; builtin rows stay.
	 */
	public function test_verified_increment_is_merged() {
		$root    = dirname( __DIR__, 3 );
		$script  = $root . '/scripts/sign-ruleset.php';
		$key     = $root . '/tests/fixtures/keys/wpcy-test-ed25519.key';
		$payload = array(
			'issued_at'       => '2026-09-06T00:00:00Z',
			'ruleset_version' => 2,
			'tiers'           => array(
				'C' => array(
					array(
						'action' => 'ignore',
						'host'   => '*',
					),
				),
			),
			'protected_hosts' => array(
				array(
					'host'  => 'extra.example',
					'match' => 'exact',
				),
			),
			'noise_block'     => array(),
		);
		$tmp     = tempnam( sys_get_temp_dir(), 'wpcy-ph-signed-' );
		$this->assertNotFalse( $tmp );
		file_put_contents( $tmp, json_encode( $payload ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- temp unsigned JSON.

		try {
			$cmd    = sprintf(
				'%s %s %s %s --kid %s',
				escapeshellarg( PHP_BINARY ),
				escapeshellarg( $script ),
				escapeshellarg( $tmp ),
				escapeshellarg( $key ),
				escapeshellarg( 'wpcy-ruleset-2026' )
			);
			$output = array();
			$code   = 0;
			exec( $cmd . ' 2>&1', $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- CLI signer subprocess.
			$this->assertSame( 0, $code, implode( "\n", $output ) );

			$ruleset = new Ruleset( $tmp );
			$this->assertTrue( $ruleset->verified() );
			$this->assertTrue( $ruleset->is_protected( 'api.wenpai.net' ) );
			$this->assertTrue( $ruleset->is_protected( 'extra.example' ) );
			$this->assertSame( 'builtin+signed', $ruleset->protected_source() );
		} finally {
			unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temp signed ruleset.
		}
	}

	/**
	 * Bad signature drops the increment; hardcoded wenpai.net stays protected.
	 */
	public function test_bad_signature_does_not_merge_increment() {
		$src = dirname( __DIR__, 3 ) . '/src/Privacy/rulesets/baseline.json';
		$raw = file_get_contents( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local shipped ruleset.
		$this->assertIsString( $raw );
		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded );
		$decoded['protected_hosts'][] = array(
			'host'  => 'evil.example',
			'match' => 'exact',
		);
		$decoded['signature']         = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==';

		$tmp = tempnam( sys_get_temp_dir(), 'wpcy-ph-' );
		$this->assertNotFalse( $tmp );
		file_put_contents( $tmp, json_encode( $decoded ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- temp tampered payload.

		try {
			$ruleset = new Ruleset( $tmp );
			$this->assertFalse( $ruleset->verified() );
			$this->assertTrue( $ruleset->is_protected( 'wenpai.net' ) );
			$this->assertTrue( $ruleset->is_protected( 'api.wenpai.net' ) );
			$this->assertFalse( $ruleset->is_protected( 'evil.example' ) );
			$this->assertSame( 'builtin', $ruleset->protected_source() );
		} finally {
			unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temp tampered payload.
		}
	}

	/**
	 * Write an unsigned JSON ruleset and return its path.
	 *
	 * @param array<string, mixed> $payload Document.
	 */
	private function write_ruleset( array $payload ): string {
		$tmp = tempnam( sys_get_temp_dir(), 'wpcy-ph-' );
		$this->assertNotFalse( $tmp );
		file_put_contents( $tmp, json_encode( $payload ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- temp unsigned JSON.
		return $tmp;
	}
}
