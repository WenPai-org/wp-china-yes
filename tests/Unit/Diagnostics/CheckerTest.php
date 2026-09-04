<?php
/**
 * Diagnostics result object fields and honest failure mapping.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WP_Error;

require_once __DIR__ . '/wp-diagnostics-stubs.php';

/**
 * Frozen fields for REST / CLI / Site Health.
 */
class CheckerTest extends TestCase {

	/**
	 * HTTP status keyed by URL host.
	 *
	 * @var array<string, int>
	 */
	private $codes = array();

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		DiagnosticsStore::reset();
		$this->codes = array();
	}

	/**
	 * Each result has the five frozen keys.
	 */
	public function test_run_rows_have_frozen_fields() {
		$this->codes['*'] = 200;
		$rows             = $this->checker()->run();

		$this->assertNotEmpty( $rows );
		foreach ( $rows as $row ) {
			$this->assertSame(
				array( 'target', 'result', 'latency_ms', 'checked_at', 'suggestion' ),
				array_keys( $row )
			);
			$this->assertIsString( $row['target'] );
			$this->assertContains( $row['result'], array( Checker::RESULT_OK, Checker::RESULT_FALLBACK, Checker::RESULT_DOWN ) );
			$this->assertTrue( is_int( $row['latency_ms'] ) || null === $row['latency_ms'] );
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $row['checked_at'] );
		}
	}

	/**
	 * Domestic 200 is ok and suggestion is null.
	 */
	public function test_ok_has_null_suggestion() {
		$this->codes['*'] = 200;
		$rows             = $this->checker()->run();

		foreach ( $rows as $row ) {
			$this->assertSame( Checker::RESULT_OK, $row['result'] );
			$this->assertNull( $row['suggestion'] );
		}
	}

	/**
	 * Domestic failure plus upstream 200 is fallback, not ok.
	 */
	public function test_domestic_failure_with_upstream_is_fallback() {
		$this->codes['wenpai.net']     = 0;
		$this->codes['admincdn.com']   = 0;
		$this->codes['cravatar.com']   = 0;
		$this->codes['wordpress.org']  = 200;
		$this->codes['cloudflare.com'] = 200;
		$this->codes['jsdelivr.net']   = 200;
		$this->codes['googleapis.com'] = 200;
		$this->codes['gravatar.com']   = 200;

		$rows = $this->checker()->run();
		$this->assertNotEmpty( $rows );
		foreach ( $rows as $row ) {
			$this->assertSame( Checker::RESULT_FALLBACK, $row['result'], $row['target'] );
			$this->assertIsString( $row['suggestion'] );
			$this->assertNotSame( Checker::RESULT_OK, $row['result'] );
		}
	}

	/**
	 * Both sides failing is down. Never reported as ok.
	 */
	public function test_both_failing_is_down_not_ok() {
		$this->codes['*'] = 0;
		$rows             = $this->checker()->run();

		foreach ( $rows as $row ) {
			$this->assertSame( Checker::RESULT_DOWN, $row['result'], $row['target'] );
			$this->assertIsString( $row['suggestion'] );
		}
	}

	/**
	 * Transport WP_Error is down, not ok.
	 */
	public function test_wp_error_is_down() {
		$checker = new Checker(
			static function () {
				return new WP_Error();
			},
			'get_transient',
			'set_transient',
			null,
			static function () {
				return '2026-09-04T00:00:00Z';
			}
		);

		foreach ( $checker->run() as $row ) {
			$this->assertSame( Checker::RESULT_DOWN, $row['result'] );
		}
	}

	/**
	 * Latest returns the stored run; empty store is an empty list.
	 */
	public function test_latest_reads_store_and_empty_is_empty() {
		$checker = $this->checker();
		$this->assertSame( array(), $checker->latest() );
		$this->assertSame( array( 'targets' => array() ), $checker->snapshot() );

		$this->codes['*'] = 200;
		$run              = $checker->run();
		$this->assertSame( $run, $checker->latest() );
		$this->assertSame( array( 'targets' => $run ), $checker->snapshot() );
	}

	/**
	 * Checked_at is the injected clock.
	 */
	public function test_checked_at_is_utc_iso8601_from_clock() {
		$this->codes['*'] = 200;
		$row              = $this->checker()->run()[0];
		$this->assertSame( '2026-09-04T12:00:00Z', $row['checked_at'] );
	}

	/**
	 * Avatar off drops the avatar target.
	 */
	public function test_avatar_off_omits_avatar_target() {
		$config  = new MapConfig( array( 'connectivity.avatar' => 'off' ) );
		$checker = new Checker(
			array( $this, 'http_get' ),
			'get_transient',
			'set_transient',
			$config
		);
		$names   = array();
		foreach ( $checker->targets() as $spec ) {
			$names[] = $spec['target'];
		}

		$this->assertContains( 'downloads.wenpai.net', $names );
		$this->assertContains( 'cdnjs.admincdn.com', $names );
		$this->assertNotContains( 'cn.cravatar.com', $names );
		$this->assertNotContains( 'weavatar.com', $names );
	}

	/**
	 * Malformed stored rows are dropped, not rewritten as ok.
	 */
	public function test_latest_drops_malformed_and_does_not_invent_ok() {
		DiagnosticsStore::$transients[ Checker::STORE_KEY ] = array(
			array( 'target' => 'x' ),
			array(
				'target'     => 'cdnjs.admincdn.com',
				'result'     => 'mystery',
				'checked_at' => '2026-09-04T00:00:00Z',
			),
			array(
				'target'     => 'api.wenpai.net',
				'result'     => Checker::RESULT_DOWN,
				'latency_ms' => 12,
				'checked_at' => '2026-09-04T00:00:00Z',
				'suggestion' => 'nope',
			),
		);

		$latest = $this->checker()->latest();
		$this->assertCount( 1, $latest );
		$this->assertSame( Checker::RESULT_DOWN, $latest[0]['result'] );
	}

	/**
	 * HTTP GET used by Checker. Public so it is a valid callable.
	 *
	 * @param string               $url  Request URL.
	 * @param array<string, mixed> $args Unused.
	 * @return array<string, mixed>
	 */
	public function http_get( $url, $args ) {
		unset( $args );
		$code = 0;
		if ( isset( $this->codes['*'] ) ) {
			$code = $this->codes['*'];
		}
		foreach ( $this->codes as $needle => $value ) {
			if ( '*' !== $needle && false !== strpos( $url, $needle ) ) {
				$code = $value;
			}
		}

		if ( $code < 1 ) {
			return new WP_Error();
		}

		return array(
			'response' => array(
				'code' => $code,
			),
		);
	}

	/**
	 * Checker with canned HTTP.
	 */
	private function checker(): Checker {
		return new Checker(
			array( $this, 'http_get' ),
			'get_transient',
			'set_transient',
			new MapConfig( array( 'connectivity.avatar' => 'cravatar_cn' ) ),
			static function () {
				return '2026-09-04T12:00:00Z';
			}
		);
	}
}
