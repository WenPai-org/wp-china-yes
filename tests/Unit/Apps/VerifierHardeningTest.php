<?php
/**
 * Verification never throws: unencodable payloads and malformed signatures fail closed.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Apps;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Apps\Index;
use WenPai\ChinaYes\Apps\ManifestVerifier;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WenPai\ChinaYes\Tests\Unit\Support\TestKeys;

require_once __DIR__ . '/wp-apps-stubs.php';

/**
 * ManifestVerifier, Ruleset and Index answer false / invalid instead of throwing.
 */
class VerifierHardeningTest extends TestCase {

	/**
	 * Temp files to remove in tearDown.
	 *
	 * @var list<string>
	 */
	private array $tmp = array();

	/**
	 * Reset transients.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AppsStore::reset();
	}

	/**
	 * Remove temp files.
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
	 * Values json_encode() rejects.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function unencodable_values(): array {
		$deep = 1;
		for ( $i = 0; $i < 600; $i++ ) {
			$deep = array( $deep );
		}
		return array(
			'INF from 1e999'  => array( json_decode( '{"a":1e999}', true ) ),
			'-INF'            => array( array( 'a' => -INF ) ),
			'NaN'             => array( array( 'a' => NAN ) ),
			'malformed UTF-8' => array( array( 'a' => "\xff" ) ),
			'depth over 512'  => array( array( 'a' => $deep ) ),
		);
	}

	/**
	 * Canonicalization of an unencodable value is '' for both signers' rules.
	 *
	 * @dataProvider unencodable_values
	 *
	 * @param mixed $value Value.
	 */
	public function test_canonicalize_returns_empty_for_unencodable_values( $value ) {
		$this->assertSame( '', ManifestVerifier::canonicalize( $value ) );
		$this->assertSame( '', Ruleset::canonicalize( $value ) );
	}

	/**
	 * A signed document with an injected 1e999 is rejected, not thrown.
	 */
	public function test_verify_rejects_document_with_inf_without_exception() {
		$json = $this->inject_inf( (string) json_encode( SignedIndexFixture::sign( array( 'id' => 'probe' ) ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test payload.
		$doc  = json_decode( $json, true );
		$this->assertIsArray( $doc );
		$this->assertSame( INF, $doc['zz'] );
		$this->assertFalse( SignedIndexFixture::verifier()->verify( $doc ) );
	}

	/**
	 * Unencodable payloads passed to verify_payload() are rejected, not thrown.
	 *
	 * @dataProvider unencodable_values
	 *
	 * @param mixed $value Value.
	 */
	public function test_verify_payload_rejects_unencodable_payload_without_exception( $value ) {
		$sig = sodium_bin2base64( str_repeat( "\x01", SODIUM_CRYPTO_SIGN_BYTES ), SODIUM_BASE64_VARIANT_ORIGINAL );
		$this->assertFalse( SignedIndexFixture::verifier()->verify_payload( $value, $sig ) );
	}

	/**
	 * A well-formed Base64 signature of the wrong length is rejected, not thrown.
	 */
	public function test_verify_rejects_wrong_length_signature_without_exception() {
		$doc              = SignedIndexFixture::sign( array( 'id' => 'probe' ) );
		$doc['signature'] = sodium_bin2base64( str_repeat( "\x01", 10 ), SODIUM_BASE64_VARIANT_ORIGINAL );
		$this->assertFalse( SignedIndexFixture::verifier()->verify( $doc ) );
	}

	/**
	 * Ruleset with an injected 1e999 or a short signature stays unverified, not thrown.
	 */
	public function test_ruleset_rejects_inf_and_short_signature_without_exception() {
		$raw = file_get_contents( dirname( __DIR__, 3 ) . '/src/Privacy/rulesets/baseline.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- shipped ruleset.
		$doc = json_decode( (string) $raw, true );
		$this->assertIsArray( $doc );
		unset( $doc['signature'] );
		$doc['kid'] = 'wpcy-ruleset-2026';
		$signed     = SignedIndexFixture::sign( $doc );
		$keys       = TestKeys::keys_by_kid();

		$ok = $this->write_tmp( (string) json_encode( $signed ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test payload.
		$this->assertTrue( ( new Ruleset( $ok, TestKeys::public_key(), true, $keys ) )->verified(), 'control' );

		$inf = $this->write_tmp( $this->inject_inf( (string) json_encode( $signed ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test payload.
		$this->assertFalse( ( new Ruleset( $inf, TestKeys::public_key(), true, $keys ) )->verified() );

		$signed['signature'] = sodium_bin2base64( str_repeat( "\x01", 10 ), SODIUM_BASE64_VARIANT_ORIGINAL );
		$short               = $this->write_tmp( (string) json_encode( $signed ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test payload.
		$this->assertFalse( ( new Ruleset( $short, TestKeys::public_key(), true, $keys ) )->verified() );
	}

	/**
	 * Apps index with an injected 1e999 is reported invalid, not thrown.
	 */
	public function test_index_with_inf_is_invalid_without_exception() {
		$json  = $this->inject_inf( SignedIndexFixture::index_json() );
		$codes = array();
		$index = new Index(
			SignedIndexFixture::verifier(),
			'https://apps.wpcy.com/index.json',
			static function () use ( $json ) {
				return $json;
			},
			$this->logger( $codes ),
			'4.0.0'
		);
		$this->assertSame( array(), $index->refresh() );
		$this->assertSame( 'invalid', $index->index_status() );
		$this->assertContains( 'wpcy_apps_signature_invalid', $codes );
	}

	/**
	 * Fixture index re-signed with the throwaway key (control: it verifies).
	 */
	public function test_signed_index_control_verifies() {
		$json  = SignedIndexFixture::index_json();
		$index = new Index(
			SignedIndexFixture::verifier(),
			'https://apps.wpcy.com/index.json',
			static function () use ( $json ) {
				return $json;
			},
			new Logger( 'debug', static function (): void {} ),
			'4.0.0'
		);
		$this->assertNotSame( array(), $index->refresh() );
		$this->assertSame( 'ok', $index->index_status() );
	}

	/**
	 * Insert `"zz":1e999` as the first member of a JSON object.
	 *
	 * @param string $json JSON object text.
	 */
	private function inject_inf( string $json ): string {
		return '{"zz":1e999,' . substr( $json, 1 );
	}

	/**
	 * Logger that records context codes.
	 *
	 * @param array<int, string> $codes Collected codes (by reference).
	 */
	private function logger( array &$codes ): Logger {
		return new Logger(
			'debug',
			static function ( string $level, string $message, array $context ) use ( &$codes ): void {
				unset( $level, $message );
				$codes[] = isset( $context['code'] ) ? (string) $context['code'] : '';
			}
		);
	}

	/**
	 * Write a temp file.
	 *
	 * @param string $body Contents.
	 */
	private function write_tmp( string $body ): string {
		$path = tempnam( sys_get_temp_dir(), 'wpcy-vh-' );
		$this->assertNotFalse( $path );
		file_put_contents( $path, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temp ruleset.
		$this->tmp[] = $path;
		return $path;
	}
}
