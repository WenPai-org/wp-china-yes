<?php
/**
 * Signature trust anchor: production keys only, test keys only in test mode.
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
use WenPai\ChinaYes\Tests\Unit\Support\LegacyFixtureKey;
use WenPai\ChinaYes\Tests\Unit\Support\TestKeys;

require_once __DIR__ . '/wp-apps-stubs.php';

/**
 * The default (production) verifier trusts no test key and fails closed.
 */
class TrustAnchorTest extends TestCase {

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
	 * Committed documents signed with the TEST ONLY fixture key.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function fixture_signed_documents(): array {
		return array(
			'apps index, no kid'          => array( 'apps/index.valid.json' ),
			'manifest motusnap, no kid'   => array( 'apps/motusnap.valid.json' ),
			'manifest paidtool, no kid'   => array( 'apps/paidtool.valid.json' ),
			'mock-app manifest, no kid'   => array( 'mock-app/manifest.json' ),
			'element hide, apps kid'      => array( 'element-hide/sample.json' ),
			'notice rules, apps kid'      => array( 'notice-rules/sample.json' ),
			'announcements, apps kid'     => array( 'announcements/sample.json' ),
			'ruleset baseline, rules kid' => array( '../../src/Privacy/rulesets/baseline.json' ),
		);
	}

	/**
	 * Non-test mode: the default verifier rejects documents signed with the test key.
	 *
	 * @dataProvider fixture_signed_documents
	 *
	 * @param string $name Path under tests/fixtures/.
	 */
	public function test_production_verifier_rejects_test_key_signatures( string $name ) {
		$this->assertFalse( ( new ManifestVerifier() )->verify( $this->load_json( $name ) ) );
	}

	/**
	 * Test mode: the same documents verify once the test key is injected.
	 *
	 * @dataProvider fixture_signed_documents
	 *
	 * @param string $name Path under tests/fixtures/.
	 */
	public function test_test_mode_accepts_test_key_signatures( string $name ) {
		$this->assertTrue( LegacyFixtureKey::verifier()->verify( $this->load_json( $name ) ) );
	}

	/**
	 * Production key map is placeholders only: every known kid is unset.
	 */
	public function test_production_keys_are_unconfigured_placeholders() {
		$this->assertSame( TestKeys::KIDS, array_keys( array_intersect_key( array_flip( TestKeys::KIDS ), ManifestVerifier::PRODUCTION_KEYS_BY_KID ) ) );
		foreach ( ManifestVerifier::PRODUCTION_KEYS_BY_KID as $kid => $key ) {
			$this->assertSame( '', $key, $kid );
		}
	}

	/**
	 * No production key configured: a correct signature by any key is rejected.
	 */
	public function test_no_production_key_rejects_any_signature() {
		$doc = array(
			'id'      => 'probe',
			'version' => '1.0.0',
		);
		foreach ( array( 'wpcy-apps-2026', 'wpcy-ruleset-2026', '' ) as $kid ) {
			$signed = $this->sign_with_throwaway( $doc, $kid );
			$this->assertFalse( ( new ManifestVerifier() )->verify( $signed ), 'kid=' . $kid );
			$this->assertFalse( ( new ManifestVerifier( null, null ) )->verify( $signed ), 'kid=' . $kid );
			$this->assertFalse( ( new ManifestVerifier( '', array() ) )->verify( $signed ), 'kid=' . $kid );
			$this->assertTrue(
				( new ManifestVerifier( TestKeys::public_key(), TestKeys::keys_by_kid() ) )->verify( $signed ),
				'control, kid=' . $kid
			);
		}
	}

	/**
	 * A well-formed Base64 signature of the wrong length is rejected, not thrown.
	 */
	public function test_wrong_length_signature_is_rejected_without_exception() {
		$doc              = $this->sign_with_throwaway( array( 'id' => 'probe' ), 'wpcy-apps-2026' );
		$doc['signature'] = sodium_bin2base64( str_repeat( "\x01", 10 ), SODIUM_BASE64_VARIANT_ORIGINAL );
		$verifier         = new ManifestVerifier( TestKeys::public_key(), TestKeys::keys_by_kid() );
		$this->assertFalse( $verifier->verify( $doc ) );
	}

	/**
	 * Production sources carry no copy of the test public key and no test switch.
	 */
	public function test_src_does_not_embed_test_public_key() {
		$key   = LegacyFixtureKey::public_key();
		$root  = dirname( __DIR__, 3 ) . '/src';
		$found = array();
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}
			$body = (string) file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source scan.
			if ( false !== strpos( $body, $key ) || false !== strpos( $body, 'TEST_PUBLIC_KEY' ) ) {
				$found[] = substr( $file->getPathname(), strlen( $root ) + 1 );
			}
		}
		$this->assertSame( array(), $found );
	}

	/**
	 * Caller: the default Index rejects a test-key-signed index without crashing.
	 */
	public function test_index_with_production_verifier_rejects_test_key_index() {
		$codes = array();
		$index = new Index( new ManifestVerifier(), $this->fixture_path( 'apps/index.valid.json' ), null, $this->logger( $codes ), '4.0.0' );
		$this->assertSame( array(), $index->refresh() );
		$this->assertSame( 'invalid', $index->index_status() );
		$this->assertSame( array(), $index->apps() );
		$this->assertArrayNotHasKey( Index::TRANSIENT_KEY, AppsStore::$transients );
		$this->assertContains( 'wpcy_apps_signature_invalid', $codes );
	}

	/**
	 * Caller: a signature failure keeps the previous cache but does not extend its lifetime.
	 */
	public function test_index_signature_failure_does_not_restore_previous_cache() {
		$path = $this->fixture_path( 'apps/index.valid.json' );
		$ok   = ( new Index( LegacyFixtureKey::verifier(), $path, null, null, '4.0.0' ) )->refresh();
		$this->assertNotSame( array(), $ok );
		$this->assertArrayHasKey( Index::TRANSIENT_KEY, AppsStore::$transients );

		AppsStore::$transient_ttls = array();
		$codes                     = array();
		$index                     = new Index( new ManifestVerifier(), $path, null, $this->logger( $codes ), '4.0.0' );
		$this->assertSame( $ok, $index->refresh() );
		$this->assertSame( 'invalid', $index->index_status() );
		$this->assertArrayNotHasKey( Index::TRANSIENT_KEY, AppsStore::$transient_ttls );
	}

	/**
	 * Logger that records context codes instead of writing to error_log().
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
	 * Sign $doc with the throwaway runtime key.
	 *
	 * @param array<string, mixed> $doc Payload.
	 * @param string               $kid Key id, or '' for none.
	 * @return array<string, mixed>
	 */
	private function sign_with_throwaway( array $doc, string $kid ): array {
		if ( '' !== $kid ) {
			$doc['kid'] = $kid;
		}
		$raw              = sodium_crypto_sign_detached( ManifestVerifier::canonicalize( $doc ), TestKeys::secret() );
		$doc['signature'] = sodium_bin2base64( $raw, SODIUM_BASE64_VARIANT_ORIGINAL );
		return $doc;
	}

	/**
	 * Absolute fixture path.
	 *
	 * @param string $name Path under tests/fixtures/.
	 */
	private function fixture_path( string $name ): string {
		return dirname( __DIR__, 2 ) . '/fixtures/' . $name;
	}

	/**
	 * Decode a fixture object.
	 *
	 * @param string $name Path under tests/fixtures/.
	 * @return array<string, mixed>
	 */
	private function load_json( string $name ): array {
		$raw = file_get_contents( $this->fixture_path( $name ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$this->assertNotFalse( $raw );
		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}
}
