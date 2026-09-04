<?php
/**
 * Manifest and index Ed25519 verify pass / fail.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Apps;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Apps\Index;
use WenPai\ChinaYes\Apps\ManifestVerifier;
use WenPai\ChinaYes\Apps\Registry;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;

require_once __DIR__ . '/wp-apps-stubs.php';

/**
 * Acceptance: signed fixture verifies; tampered signature does not.
 */
class VerifyTest extends TestCase {

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
	 * Valid motusnap fixture verifies with the TEST ONLY public key.
	 */
	public function test_valid_manifest_verifies() {
		$doc = $this->load_json( 'motusnap.valid.json' );
		$this->assertTrue( ( new ManifestVerifier() )->verify( $doc ) );
	}

	/**
	 * Tampered signature fails closed.
	 */
	public function test_invalid_manifest_signature_fails() {
		$doc = $this->load_json( 'motusnap.invalid-signature.json' );
		$this->assertFalse( ( new ManifestVerifier() )->verify( $doc ) );
	}

	/**
	 * Canonical bytes match the residency signer (same §1.3 rules).
	 */
	public function test_canonicalize_matches_ruleset_script() {
		$payload = array(
			'b' => 1,
			'a' => array(
				'z' => 2,
				'y' => 3,
			),
		);
		$this->assertSame(
			Ruleset::canonicalize( $payload ),
			ManifestVerifier::canonicalize( $payload )
		);
	}

	/**
	 * Valid index refresh keeps signed apps and writes the 24h transient.
	 */
	public function test_valid_index_refresh_caches_verified_apps() {
		$index = $this->index_from( 'index.valid.json' );
		$apps  = $index->refresh();
		$ids   = array();
		foreach ( $apps as $app ) {
			$ids[] = $app['id'];
		}
		$this->assertSame( array( 'motusnap', 'noteboard', 'paidtool' ), $ids );
		$this->assertSame( $apps, AppsStore::$transients[ Index::TRANSIENT_KEY ] );
	}

	/**
	 * Index-level signature failure discards the document and keeps the previous cache.
	 */
	public function test_invalid_index_keeps_previous_cache() {
		$previous                                      = array(
			array(
				'id' => 'cached-app',
			),
		);
		AppsStore::$transients[ Index::TRANSIENT_KEY ] = $previous;

		$logger = new Logger( 'debug' );
		$index  = $this->index_from( 'index.invalid-signature.json', $logger );
		$apps   = $index->refresh();

		$this->assertSame( $previous, $apps );
		$codes = array();
		foreach ( $logger->records() as $row ) {
			if ( isset( $row['context']['code'] ) ) {
				$codes[] = $row['context']['code'];
			}
		}
		$this->assertContains( 'wpcy_apps_signature_invalid', $codes );
	}

	/**
	 * One bad child manifest is omitted; the rest of a valid index remains.
	 */
	public function test_one_invalid_app_is_omitted() {
		$index = $this->index_from( 'index.one-invalid-app.json' );
		$apps  = $index->refresh();
		$ids   = array();
		foreach ( $apps as $app ) {
			$ids[] = $app['id'];
		}
		$this->assertSame( array( 'motusnap' ), $ids );
	}

	/**
	 * Default Index source is empty: production apps.wpcy.com is not fetched.
	 */
	public function test_default_index_does_not_use_production_url() {
		$index = new Index( new ManifestVerifier() );
		$this->assertSame( '', $index->source() );
		$this->assertNotSame( Index::PRODUCTION_URL, $index->source() );
	}

	/**
	 * HTTPS wpcy.com / wenpai.net hosts are allowed; others are not.
	 */
	public function test_origin_allowlist() {
		$this->assertTrue( ManifestVerifier::origin_allowed( 'https://apps.wpcy.com/motusnap/' ) );
		$this->assertTrue( ManifestVerifier::origin_allowed( 'https://tools.wenpai.net/x' ) );
		$this->assertFalse( ManifestVerifier::origin_allowed( 'http://apps.wpcy.com/motusnap/' ) );
		$this->assertFalse( ManifestVerifier::origin_allowed( 'https://example.com/x' ) );
	}

	/**
	 * Iframe sandbox must not include allow-same-origin.
	 */
	public function test_iframe_sandbox_omits_allow_same_origin() {
		$this->assertStringNotContainsString( 'allow-same-origin', Registry::iframe_sandbox() );
		$this->assertSame( Registry::ERR_ORIGIN_MISMATCH, 'wpcy_apps_origin_mismatch' );
	}

	/**
	 * TEST ONLY public key file header.
	 */
	public function test_public_key_file_header_is_test_only() {
		$path = dirname( __DIR__, 2 ) . '/fixtures/keys/wpcy-test-ed25519.pub';
		$this->assertFileExists( $path );
		$line = (string) file( $path )[0];
		$this->assertStringContainsString( 'TEST ONLY', $line );
		$this->assertSame( ManifestVerifier::TEST_PUBLIC_KEY, Ruleset::TEST_PUBLIC_KEY );
	}

	/**
	 * Decode a fixture object.
	 *
	 * @param string $name File name under tests/fixtures/apps/.
	 * @return array<string, mixed>
	 */
	private function load_json( string $name ): array {
		$path = dirname( __DIR__, 2 ) . '/fixtures/apps/' . $name;
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$this->assertNotFalse( $raw );
		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}

	/**
	 * Index pointed at a fixture file.
	 *
	 * @param string      $name   File name.
	 * @param Logger|null $logger Logger.
	 */
	private function index_from( string $name, $logger = null ): Index {
		$path = dirname( __DIR__, 2 ) . '/fixtures/apps/' . $name;
		return new Index( new ManifestVerifier(), $path, null, $logger, '4.0.0' );
	}
}
