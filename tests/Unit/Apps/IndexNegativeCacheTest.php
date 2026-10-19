<?php
/**
 * Apps index negative cache: upstream failure is not retried for NEGATIVE_TTL.
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

require_once __DIR__ . '/wp-apps-stubs.php';

/**
 * Upstream calls are counted through pre_http_request.
 */
class IndexNegativeCacheTest extends TestCase {

	/**
	 * HTTPS source on the allowed origin, so Index uses wp_remote_get().
	 */
	private const SOURCE = 'https://apps.wpcy.com/index.json';

	/**
	 * Upstream requests seen by pre_http_request.
	 *
	 * @var int
	 */
	private int $calls = 0;

	/**
	 * Next upstream answer: WP_Error or a response array.
	 *
	 * @var mixed
	 */
	private $answer;

	/**
	 * Reset bags and hook the counter.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AppsStore::reset();
		$this->calls  = 0;
		$this->answer = new \WP_Error( 'http_request_failed', 'down' );
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				unset( $pre, $args );
				if ( self::SOURCE === $url ) {
					++$this->calls;
				}
				return $this->answer;
			},
			10,
			3
		);
	}

	/**
	 * Constants are the documented key and TTL.
	 */
	public function test_constants() {
		$this->assertSame( 'wpcy_apps_index_negative', Index::NEGATIVE_TRANSIENT_KEY );
		$this->assertSame( 0, strpos( Index::NEGATIVE_TRANSIENT_KEY, 'wpcy_' ) );
		$this->assertSame( 60, Index::NEGATIVE_TTL );
	}

	/**
	 * WP_Error: second call within the window does not hit upstream and answers the same.
	 */
	public function test_wp_error_is_not_retried_within_window() {
		$index = $this->index();
		$first = $index->refresh();
		$this->assertSame( 1, $this->calls );
		$this->assertSame( 'unreachable', $index->index_status() );
		$this->assertSame( 'unreachable', AppsStore::$transients[ Index::NEGATIVE_TRANSIENT_KEY ] );
		$this->assertSame( Index::NEGATIVE_TTL, AppsStore::$transient_ttls[ Index::NEGATIVE_TRANSIENT_KEY ] );

		$again  = $this->index();
		$second = $again->refresh();
		$this->assertSame( 1, $this->calls );
		$this->assertSame( $first, $second );
		$this->assertSame( 'unreachable', $again->index_status() );
	}

	/**
	 * Non-2xx is negative-cached as unreachable.
	 */
	public function test_non_2xx_is_not_retried_within_window() {
		$this->answer = $this->response( 503, '{"error":"busy"}' );
		$this->index()->refresh();
		$index = $this->index();
		$index->refresh();
		$this->assertSame( 1, $this->calls );
		$this->assertSame( 'unreachable', $index->index_status() );
	}

	/**
	 * Invalid JSON is negative-cached as invalid.
	 */
	public function test_invalid_json_is_not_retried_within_window() {
		$this->answer = $this->response( 200, 'not json{' );
		$this->index()->refresh();
		$index = $this->index();
		$index->refresh();
		$this->assertSame( 1, $this->calls );
		$this->assertSame( 'invalid', $index->index_status() );
		$this->assertSame( 'invalid', AppsStore::$transients[ Index::NEGATIVE_TRANSIENT_KEY ] );
	}

	/**
	 * GET /apps path (Registry → Index::apps) with an empty cache does not hit upstream again.
	 */
	public function test_registry_does_not_retry_within_window() {
		$registry = new Registry( $this->index() );
		$this->assertSame( array(), $registry->all() );
		$this->assertSame( array(), ( new Registry( $this->index() ) )->all() );
		$this->assertSame( 1, $this->calls );
	}

	/**
	 * Once the transient expires (WordPress drops it), upstream is requested again.
	 */
	public function test_expired_window_requests_upstream_again() {
		$this->index()->refresh();
		$this->assertSame( 1, $this->calls );

		// Expiry: WordPress no longer returns the transient after NEGATIVE_TTL.
		unset( AppsStore::$transients[ Index::NEGATIVE_TRANSIENT_KEY ] );

		$this->index()->refresh();
		$this->assertSame( 2, $this->calls );
		$this->assertArrayHasKey( Index::NEGATIVE_TRANSIENT_KEY, AppsStore::$transients );
	}

	/**
	 * Failure, expiry, then success: verified apps, status ok, no negative cache left.
	 */
	public function test_success_clears_negative_cache() {
		$this->index()->refresh();
		$this->assertArrayHasKey( Index::NEGATIVE_TRANSIENT_KEY, AppsStore::$transients );
		unset( AppsStore::$transients[ Index::NEGATIVE_TRANSIENT_KEY ] );

		$this->answer = $this->response( 200, $this->fixture( 'index.valid.json' ) );
		$index        = $this->index();
		$apps         = $index->refresh();
		$this->assertSame( 2, $this->calls );
		$this->assertSame( 'ok', $index->index_status() );
		$this->assertNotSame( array(), $apps );
		$this->assertArrayNotHasKey( Index::NEGATIVE_TRANSIENT_KEY, AppsStore::$transients );
	}

	/**
	 * Success deletes a negative transient that does not block (unknown value).
	 */
	public function test_success_deletes_existing_transient() {
		AppsStore::$transients[ Index::NEGATIVE_TRANSIENT_KEY ] = 'legacy';
		AppsStore::$transients['unrelated']                     = 'keep';

		$this->answer = $this->response( 200, $this->fixture( 'index.valid.json' ) );
		$this->index()->refresh();
		$this->assertSame( 1, $this->calls );
		$this->assertArrayNotHasKey( Index::NEGATIVE_TRANSIENT_KEY, AppsStore::$transients );
		$this->assertSame( 'keep', AppsStore::$transients['unrelated'] );
	}

	/**
	 * Index pointed at the HTTPS source.
	 */
	private function index(): Index {
		return new Index( new ManifestVerifier(), self::SOURCE, null, null, '4.0.0' );
	}

	/**
	 * WordPress-shaped HTTP response.
	 *
	 * @param int    $code Status.
	 * @param string $body Body.
	 * @return array<string, mixed>
	 */
	private function response( int $code, string $body ): array {
		return array(
			'response' => array( 'code' => $code ),
			'body'     => $body,
		);
	}

	/**
	 * Fixture body.
	 *
	 * @param string $name File name.
	 */
	private function fixture( string $name ): string {
		$raw = file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/apps/' . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$this->assertIsString( $raw );
		return $raw;
	}
}
