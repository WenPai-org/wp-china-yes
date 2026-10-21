<?php
/**
 * Apps index cache: failures never re-store it; its name is bound to the trusted keys.
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
use WP_Error;

require_once __DIR__ . '/wp-apps-stubs.php';

/**
 * Previous apps stay readable until their original TTL, and no longer.
 */
class IndexCacheLifetimeTest extends TestCase {

	private const SOURCE = 'https://apps.wpcy.com/index.json';

	/**
	 * Reset transients and filters.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AppsStore::reset();
	}

	/**
	 * Second fetch outcomes after a successful first fetch.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function failing_bodies(): array {
		return array(
			'unreachable (empty body)' => array( '', 'unreachable' ),
			'invalid JSON'             => array( '{"apps":', 'invalid' ),
			'bad signature'            => array( 'BAD_SIGNATURE', 'invalid' ),
		);
	}

	/**
	 * A failed refresh returns the previous apps but does not write the cache.
	 *
	 * @dataProvider failing_bodies
	 *
	 * @param string $second Body of the second fetch ('BAD_SIGNATURE' = badly signed index).
	 * @param string $status Expected index_status.
	 */
	public function test_failed_refresh_does_not_restore_previous_cache( string $second, string $status ) {
		$bodies = array( SignedIndexFixture::index_json(), 'BAD_SIGNATURE' === $second ? SignedIndexFixture::badly_signed_index_json() : $second );
		$index  = $this->index_with_fetcher( $bodies );

		$first = $index->refresh();
		$this->assertNotSame( array(), $first );
		$name = $this->apps_cache_name();
		$this->assertNotSame( '', $name );

		AppsStore::$transient_ttls = array();
		$this->assertSame( $first, $index->refresh() );
		$this->assertSame( $status, $index->index_status() );
		$this->assertArrayNotHasKey( $name, AppsStore::$transient_ttls, 'cache was re-stored, extending its TTL' );
	}

	/**
	 * HTTP transport error and non-2xx responses do not write the cache either.
	 */
	public function test_http_failures_do_not_restore_previous_cache() {
		$responses = array(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => SignedIndexFixture::index_json(),
			),
			new WP_Error( 'http_request_failed', 'down' ),
			array(
				'response' => array( 'code' => 503 ),
				'body'     => SignedIndexFixture::index_json(),
			),
		);
		add_filter(
			'pre_http_request',
			static function () use ( &$responses ) {
				return array_shift( $responses );
			}
		);
		$index = new Index( SignedIndexFixture::verifier(), self::SOURCE, null, new Logger( 'debug', static function (): void {} ), '4.0.0' );
		$first = $index->refresh();
		$this->assertNotSame( array(), $first );
		$name = $this->apps_cache_name();

		foreach ( array( 'WP_Error', '503' ) as $label ) {
			unset( AppsStore::$transients[ Index::NEGATIVE_TRANSIENT_KEY ] );
			AppsStore::$transient_ttls = array();
			$this->assertSame( $first, $index->refresh(), $label );
			$this->assertSame( 'unreachable', $index->index_status(), $label );
			$this->assertArrayNotHasKey( $name, AppsStore::$transient_ttls, $label . ': cache was re-stored' );
		}
	}

	/**
	 * Later requests served from the unexpired cache still report the failure.
	 */
	public function test_failure_status_is_reported_on_later_requests() {
		$index = $this->index_with_fetcher( array( SignedIndexFixture::index_json(), '' ) );
		$first = $index->refresh();
		$index->refresh();

		$later = new Index( SignedIndexFixture::verifier(), self::SOURCE, null, new Logger( 'debug', static function (): void {} ), '4.0.0' );
		$this->assertSame( $first, $later->apps() );
		$this->assertSame( 'unreachable', $later->index_status() );
	}

	/**
	 * Cache name carries the cache version and the verifier's trust fingerprint.
	 */
	public function test_cache_name_is_versioned_and_bound_to_trusted_keys() {
		$a = new Index( SignedIndexFixture::verifier(), self::SOURCE );
		$b = new Index( SignedIndexFixture::verifier(), self::SOURCE );
		$c = new Index( SignedIndexFixture::other_verifier(), self::SOURCE );
		$d = new Index( new ManifestVerifier(), self::SOURCE );

		$this->assertMatchesRegularExpression( '/^wpcy_apps_index_v2_[0-9a-f]{16}$/', $a->cache_key() );
		$this->assertSame( $a->cache_key(), $b->cache_key() );
		$this->assertNotSame( $a->cache_key(), $c->cache_key() );
		$this->assertNotSame( $a->cache_key(), $d->cache_key() );
		$this->assertNotSame( Index::TRANSIENT_KEY, $d->cache_key() );
	}

	/**
	 * Apps cached under other trusted keys are never read.
	 */
	public function test_cache_written_under_other_keys_is_not_read() {
		$this->assertNotSame( array(), $this->index_with_fetcher( array( SignedIndexFixture::index_json() ) )->refresh() );

		$other = new Index( SignedIndexFixture::other_verifier(), self::SOURCE, null, new Logger( 'debug', static function (): void {} ), '4.0.0' );
		$this->assertSame( array(), $other->cached() );
	}

	/**
	 * A cache from an older release (bare base name) is ignored and dropped on the next store.
	 */
	public function test_legacy_unversioned_cache_is_ignored_and_dropped() {
		AppsStore::$transients[ Index::TRANSIENT_KEY ] = array(
			'apps'         => array( array( 'id' => 'legacy-app' ) ),
			'index_status' => 'ok',
		);
		$index = $this->index_with_fetcher( array( SignedIndexFixture::index_json() ) );
		$this->assertSame( array(), $index->cached() );

		$this->assertNotSame( array(), $index->refresh() );
		$this->assertArrayNotHasKey( Index::TRANSIENT_KEY, AppsStore::$transients );
	}

	/**
	 * Index whose fetcher answers $bodies in order.
	 *
	 * @param array<int, string> $bodies Bodies.
	 */
	private function index_with_fetcher( array $bodies ): Index {
		return new Index(
			SignedIndexFixture::verifier(),
			self::SOURCE,
			static function () use ( &$bodies ) {
				return (string) array_shift( $bodies );
			},
			new Logger( 'debug', static function (): void {} ),
			'4.0.0'
		);
	}

	/**
	 * Name of the stored apps cache transient, '' when none.
	 */
	private function apps_cache_name(): string {
		foreach ( array_keys( AppsStore::$transients ) as $name ) {
			if ( preg_match( '/^wpcy_apps_index(_v\d+_[0-9a-f]+)?$/', (string) $name ) ) {
				return (string) $name;
			}
		}
		return '';
	}
}
