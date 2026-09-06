<?php
/**
 * Counters: UTC buckets, 31-day roll, unknown names, recovery, one shutdown write.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Stats;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Stats\Counters;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;

require_once __DIR__ . '/wp-stats-stubs.php';

/**
 * Daily counter storage.
 */
class CountersTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		RestStore::reset();
	}

	/**
	 * Increment lands in today's UTC bucket.
	 */
	public function test_increment_buckets_by_utc_day() {
		$counters = $this->counters( '2026-09-06T12:00:00Z' );
		$counters->increment( 'mirror_downloads', 2 );
		$buckets = $counters->buckets();

		$this->assertSame( 2, $buckets['2026-09-06']['mirror_downloads'] );
	}

	/**
	 * Flush drops buckets older than 31 UTC days.
	 */
	public function test_flush_prunes_buckets_older_than_31_days() {
		update_option(
			Counters::OPTION,
			array(
				'buckets' => array(
					'2026-08-01' => array( 'mirror_downloads' => 9 ),
					'2026-08-07' => array( 'mirror_downloads' => 3 ),
					'2026-09-06' => array( 'mirror_downloads' => 1 ),
				),
			),
			false
		);
		$counters = $this->counters( '2026-09-06T00:00:00Z' );
		$counters->increment( 'mirror_downloads', 1 );
		$counters->flush();

		$stored = OptionStore::$options[ Counters::OPTION ];
		$this->assertArrayNotHasKey( '2026-08-01', $stored['buckets'] );
		$this->assertArrayHasKey( '2026-08-07', $stored['buckets'] );
		$this->assertSame( 2, $stored['buckets']['2026-09-06']['mirror_downloads'] );
	}

	/**
	 * Unknown counter names are ignored and logged at warning.
	 */
	public function test_unknown_counter_is_ignored_and_logged() {
		$logger   = new Logger( 'warning' );
		$counters = new Counters(
			new MapConfig( array() ),
			$logger,
			static function () {
				return '2026-09-06T00:00:00Z';
			}
		);
		$counters->increment( 'not_a_counter', 5 );

		$this->assertSame( array(), $counters->buckets() );
		$records = $logger->records();
		$this->assertNotEmpty( $records );
		$this->assertSame( 'warning', $records[0]['level'] );
		$this->assertStringContainsString( 'Unknown stats counter', $records[0]['message'] );
	}

	/**
	 * Recovery mode does not count.
	 */
	public function test_recovery_mode_skips_increment() {
		$counters = new Counters(
			new MapConfig( array( 'recovery_mode' => true ) ),
			null,
			static function () {
				return '2026-09-06T00:00:00Z';
			}
		);
		$counters->increment( 'mirror_downloads', 1 );
		$this->assertSame( array(), $counters->buckets() );
	}

	/**
	 * Shutdown / flush writes once; a second flush is a no-op.
	 */
	public function test_flush_writes_once() {
		$writes                 = 0;
		OptionStore::$on_update = static function ( $key ) use ( &$writes ) {
			if ( Counters::OPTION === $key ) {
				++$writes;
			}
		};
		$counters               = $this->counters( '2026-09-06T00:00:00Z' );
		$counters->increment( 'heartbeat_saved', 1 );
		$counters->flush();
		$counters->flush();
		$counters->increment( 'heartbeat_saved', 1 );
		$counters->flush();

		$this->assertSame( 1, $writes );
	}

	/**
	 * Snapshot fills missing days with 0 and dates ascend.
	 */
	public function test_snapshot_pads_missing_days() {
		$counters = $this->counters( '2026-09-06T00:00:00Z' );
		$counters->increment( 'mirror_downloads', 4 );
		$snap = $counters->snapshot( 3 );

		$this->assertSame( 3, $snap['days'] );
		$this->assertSame( '2026-09-04', $snap['from'] );
		$this->assertSame( '2026-09-06', $snap['to'] );
		$this->assertCount( 3, $snap['series']['mirror_downloads'] );
		$this->assertSame( '2026-09-04', $snap['series']['mirror_downloads'][0]['date'] );
		$this->assertSame( 0, $snap['series']['mirror_downloads'][0]['value'] );
		$this->assertSame( 4, $snap['series']['mirror_downloads'][2]['value'] );
		$this->assertSame( 4, $snap['totals']['mirror_downloads'] );
		$this->assertCount( 10, $snap['series'] );
	}

	/**
	 * Counters helper with a frozen clock.
	 *
	 * @param string $now UTC ISO 8601.
	 */
	private function counters( string $now ): Counters {
		return new Counters(
			new MapConfig( array() ),
			null,
			static function () use ( $now ) {
				return $now;
			}
		);
	}
}
