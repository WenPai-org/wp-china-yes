<?php
/**
 * GET /stats: days bounds, missing buckets as 0, installed_at fallbacks.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Stats;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Migration\Runner;
use WenPai\ChinaYes\Rest\StatsController;
use WenPai\ChinaYes\Stats\Counters;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WP_Error;
use WP_REST_Request;

require_once __DIR__ . '/wp-stats-stubs.php';

/**
 * Stats REST envelope.
 */
class StatsControllerTest extends TestCase {

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
	 * Days 0 / 31 / "x" are 400; 1 and 30 are accepted.
	 *
	 * @param mixed $days Query days.
	 * @param bool  $ok   Whether the request should succeed.
	 *
	 * @dataProvider days_cases
	 */
	public function test_days_bounds( $days, bool $ok ) {
		$controller      = new StatsController( $this->counters() );
		$request         = new WP_REST_Request();
		$request->params = array( 'days' => $days );
		$result          = $controller->get_item( $request );

		if ( $ok ) {
			$this->assertNotInstanceOf( WP_Error::class, $result );
			$this->assertSame( (int) $days, $result->get_data()['days'] );
			$this->assertCount( (int) $days, $result->get_data()['series']['mirror_downloads'] );
			return;
		}

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_invalid_schema', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * Days cases from the task book.
	 *
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public function days_cases(): array {
		return array(
			'zero'       => array( 0, false ),
			'one'        => array( 1, true ),
			'thirty'     => array( 30, true ),
			'thirty_one' => array( 31, false ),
			'x'          => array( 'x', false ),
		);
	}

	/**
	 * Missing buckets pad 0; dates ascend.
	 */
	public function test_missing_buckets_are_zero() {
		$counters = $this->counters();
		$counters->increment( 'mirror_downloads', 2 );
		$controller      = new StatsController( $counters );
		$request         = new WP_REST_Request();
		$request->params = array( 'days' => 3 );
		$data            = $controller->get_item( $request )->get_data();

		$this->assertSame( '2026-09-04', $data['from'] );
		$this->assertSame( '2026-09-06', $data['to'] );
		$this->assertSame( 0, $data['series']['mirror_downloads'][0]['value'] );
		$this->assertSame( 2, $data['series']['mirror_downloads'][2]['value'] );
		$this->assertArrayNotHasKey( 'url', $data );
	}

	/**
	 * Installed_at prefers the dedicated option.
	 */
	public function test_installed_at_from_option() {
		update_option( StatsController::INSTALLED_AT_OPTION, '2026-07-31T06:12:00Z', false );
		$data = ( new StatsController( $this->counters() ) )->get_item( new WP_REST_Request() )->get_data();
		$this->assertSame( '2026-07-31T06:12:00Z', $data['installed_at'] );
	}

	/**
	 * Missing option falls back to migration report migrated_at and writes it.
	 */
	public function test_installed_at_from_migration_report() {
		update_option(
			Runner::REPORT_OPTION,
			array( 'migrated_at' => '2026-08-01T00:00:00Z' ),
			false
		);
		$data = ( new StatsController( $this->counters() ) )->get_item( new WP_REST_Request() )->get_data();
		$this->assertSame( '2026-08-01T00:00:00Z', $data['installed_at'] );
		$this->assertSame( '2026-08-01T00:00:00Z', OptionStore::$options[ StatsController::INSTALLED_AT_OPTION ] );
	}

	/**
	 * Multisite: site_option report is used when the site option is empty.
	 */
	public function test_installed_at_from_site_option_on_multisite() {
		OptionStore::$multisite = true;
		update_site_option(
			Runner::REPORT_OPTION,
			array( 'migrated_at' => '2026-08-02T00:00:00Z' )
		);
		$data = ( new StatsController( $this->counters() ) )->get_item( new WP_REST_Request() )->get_data();
		$this->assertSame( '2026-08-02T00:00:00Z', $data['installed_at'] );
		$this->assertSame( '2026-08-02T00:00:00Z', OptionStore::$options[ StatsController::INSTALLED_AT_OPTION ] );
	}

	/**
	 * Neither option nor report: write now.
	 */
	public function test_installed_at_writes_now_when_missing() {
		$data = ( new StatsController( $this->counters() ) )->get_item( new WP_REST_Request() )->get_data();
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $data['installed_at'] );
		$this->assertSame( $data['installed_at'], OptionStore::$options[ StatsController::INSTALLED_AT_OPTION ] );
	}

	/**
	 * Frozen-clock counters.
	 */
	private function counters(): Counters {
		return new Counters(
			new MapConfig( array() ),
			null,
			static function () {
				return '2026-09-06T12:00:00Z';
			}
		);
	}
}
