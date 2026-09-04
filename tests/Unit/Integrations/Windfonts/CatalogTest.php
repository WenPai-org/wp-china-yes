<?php
/**
 * Windfonts catalog: API fetch + transient cache, no baked-in family list.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Integrations\Windfonts;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Integrations\Windfonts\Catalog;
use WenPai\ChinaYes\Tests\Unit\Integrations\HookStore;

require_once dirname( __DIR__ ) . '/wp-windfonts-stubs.php';

/**
 * Directory is fetched, not a long-lived PHP array.
 */
class CatalogTest extends TestCase {

	/**
	 * Reset HTTP and transient bags.
	 */
	protected function setUp(): void {
		parent::setUp();
		HookStore::reset();
	}

	/**
	 * Successful JSON is cached and returned.
	 */
	public function test_fetch_caches_family_list() {
		HookStore::$http_queue[] = array(
			'response' => array(
				'code' => 200,
			),
			'body'     => '{"fonts":[{"family":"wenfeng-hcszt"}]}',
		);

		$catalog = new Catalog();
		$first   = $catalog->fonts();
		$second  = $catalog->fonts();

		$this->assertSame( array( array( 'family' => 'wenfeng-hcszt' ) ), $first );
		$this->assertSame( $first, $second );
		$this->assertSame( Catalog::DEFAULT_URL, HookStore::$last_http_url );
		$this->assertTrue( $catalog->has_family( 'wenfeng-hcszt' ) );
		$this->assertFalse( $catalog->has_family( 'not-a-font' ) );
	}

	/**
	 * Transport failure returns empty. Site stays up.
	 */
	public function test_http_failure_returns_empty() {
		HookStore::$http_queue[] = array(
			'response' => array(
				'code' => 503,
			),
			'body'     => '',
		);

		$catalog = new Catalog();
		$this->assertSame( array(), $catalog->fonts() );
		$this->assertArrayNotHasKey( Catalog::TRANSIENT_KEY, HookStore::$transients );
	}

	/**
	 * Cached transient skips HTTP.
	 */
	public function test_cached_transient_skips_http() {
		HookStore::$transients[ Catalog::TRANSIENT_KEY ] = array(
			array( 'family' => 'wenfeng-albbpht' ),
		);

		$catalog = new Catalog();
		$this->assertSame( array( array( 'family' => 'wenfeng-albbpht' ) ), $catalog->fonts() );
		$this->assertSame( '', HookStore::$last_http_url );
	}

	/**
	 * Catalog.php does not ship a long-lived family directory.
	 */
	public function test_catalog_source_has_no_hardcoded_family_directory() {
		$source = file_get_contents( dirname( __DIR__, 4 ) . '/src/Integrations/Windfonts/Catalog.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file.
		$this->assertNotFalse( $source );
		$this->assertFalse( strpos( $source, 'wenfeng-hcszt' ) );
		$this->assertFalse( strpos( $source, 'wenfeng-albbpht' ) );
		$this->assertFalse( strpos( $source, "'wenfeng-" ) );
	}

	/**
	 * Injected URL is used (path not frozen as a family list).
	 */
	public function test_constructor_url_override() {
		HookStore::$http_queue[] = array(
			'response' => array(
				'code' => 200,
			),
			'body'     => '[{"family":"wenfeng-ibmps"}]',
		);

		$catalog = new Catalog( null, null, null, 'https://example.test/fonts.json' );
		$this->assertSame( 'https://example.test/fonts.json', $catalog->url() );
		$this->assertTrue( $catalog->has_family( 'wenfeng-ibmps' ) );
		$this->assertSame( 'https://example.test/fonts.json', HookStore::$last_http_url );
	}
}
