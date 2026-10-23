<?php
/**
 * Ruleset trust anchor: production keys only, test keys only in test mode.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Privacy;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Privacy\DataResidency\DataResidencyModule;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Support\LegacyFixtureKey;
use WenPai\ChinaYes\Tests\Unit\Support\TestKeys;

require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';
require_once __DIR__ . '/wp-http-stubs.php';

/**
 * The default (production) ruleset trusts no test key and fails closed to built-in L0.
 */
class RulesetTrustAnchorTest extends TestCase {

	/**
	 * Temp files to remove in tearDown.
	 *
	 * @var list<string>
	 */
	private array $tmp = array();

	/**
	 * Reset option and HTTP stubs.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		$GLOBALS['wpcy_privacy_remote_urls'] = array();
		$GLOBALS['wpcy_privacy_remote_args'] = array();
	}

	/**
	 * Remove temp rulesets.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->tmp as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temp ruleset.
			}
		}
		$this->tmp = array();
		parent::tearDown();
	}

	/**
	 * Non-test mode: the shipped baseline (test-key signature) is rejected; only built-in L0 applies.
	 */
	public function test_production_ruleset_rejects_test_key_baseline() {
		$ruleset = new Ruleset();
		$this->assertFalse( $ruleset->verified() );
		$this->assertSame( 'builtin', $ruleset->protected_source() );
		$this->assertSame( array(), $ruleset->noise_hosts() );
		$this->assertNull( $ruleset->match( 'https://rest.akismet.com/1.1/comment-check' ) );
		$this->assertTrue( $ruleset->is_protected( 'api.wenpai.net' ) );
	}

	/**
	 * Test mode: the same baseline verifies once the test key is injected.
	 */
	public function test_test_mode_accepts_test_key_baseline() {
		$ruleset = new Ruleset( null, LegacyFixtureKey::public_key(), true, LegacyFixtureKey::keys_by_kid() );
		$this->assertTrue( $ruleset->verified() );
		$rule = $ruleset->match( 'https://rest.akismet.com/1.1/comment-check' );
		$this->assertIsArray( $rule );
		$this->assertSame( 'record', $rule['action'] );
	}

	/**
	 * Production key map is placeholders only.
	 */
	public function test_production_ruleset_keys_are_unconfigured_placeholders() {
		$this->assertNotSame( array(), Ruleset::PRODUCTION_KEYS_BY_KID );
		foreach ( Ruleset::PRODUCTION_KEYS_BY_KID as $kid => $key ) {
			$this->assertSame( '', $key, $kid );
		}
	}

	/**
	 * No production key configured: a ruleset correctly signed by any key is rejected.
	 */
	public function test_no_production_key_rejects_any_signed_ruleset() {
		foreach ( array( 'wpcy-ruleset-2026', 'wpcy-apps-2026', '' ) as $kid ) {
			$path = $this->write_signed_baseline( $kid );
			$this->assertFalse( ( new Ruleset( $path ) )->verified(), 'kid=' . $kid );
			$this->assertFalse( ( new Ruleset( $path, '', true, array() ) )->verified(), 'kid=' . $kid );
			$this->assertTrue(
				( new Ruleset( $path, TestKeys::public_key(), true, TestKeys::keys_by_kid() ) )->verified(),
				'control, kid=' . $kid
			);
		}
	}

	/**
	 * A well-formed Base64 signature of the wrong length is rejected, not thrown.
	 */
	public function test_wrong_length_signature_is_rejected_without_exception() {
		$path = $this->write_signed_baseline( 'wpcy-ruleset-2026', sodium_bin2base64( str_repeat( "\x01", 10 ), SODIUM_BASE64_VARIANT_ORIGINAL ) );
		$this->assertFalse( ( new Ruleset( $path, TestKeys::public_key(), true, TestKeys::keys_by_kid() ) )->verified() );
	}

	/**
	 * Caller: data residency with the default ruleset passes requests through, records nothing, does not crash.
	 */
	public function test_residency_with_production_ruleset_applies_no_unverified_rule() {
		$url    = 'https://rest.akismet.com/1.1/comment-check';
		$module = new DataResidencyModule();
		$this->assertFalse( $module->filter_pre_http_request( false, array(), $url ) );
		$this->assertArrayNotHasKey( 'rest.akismet.com', $module->log() );
		$this->assertSame( array(), $GLOBALS['wpcy_privacy_remote_urls'] );

		$test_mode = new DataResidencyModule( new Ruleset( null, LegacyFixtureKey::public_key(), true, LegacyFixtureKey::keys_by_kid() ) );
		$this->assertFalse( $test_mode->filter_pre_http_request( false, array(), $url ) );
		$this->assertArrayHasKey( 'rest.akismet.com', $test_mode->log() );
	}

	/**
	 * Re-sign the shipped baseline with the throwaway key and write it to a temp file.
	 *
	 * @param string      $kid       Key id, or '' for none.
	 * @param string|null $signature Override signature.
	 */
	private function write_signed_baseline( string $kid, ?string $signature = null ): string {
		$raw = file_get_contents( dirname( __DIR__, 3 ) . '/src/Privacy/rulesets/baseline.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local shipped ruleset.
		$this->assertIsString( $raw );
		$doc = json_decode( $raw, true );
		$this->assertIsArray( $doc );
		unset( $doc['signature'], $doc['kid'] );
		if ( '' !== $kid ) {
			$doc['kid'] = $kid;
		}
		$sig              = sodium_crypto_sign_detached( Ruleset::canonicalize( $doc ), TestKeys::secret() );
		$doc['signature'] = null === $signature ? sodium_bin2base64( $sig, SODIUM_BASE64_VARIANT_ORIGINAL ) : $signature;

		$path = tempnam( sys_get_temp_dir(), 'wpcy-ta-' );
		$this->assertNotFalse( $path );
		file_put_contents( $path, json_encode( $doc ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- temp signed ruleset.
		$this->tmp[] = $path;
		return $path;
	}
}
