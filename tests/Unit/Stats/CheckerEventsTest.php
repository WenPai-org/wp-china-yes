<?php
/**
 * Checker::run emits first_check then route_fallback then route_recovered.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Stats;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Stats\Events;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WP_Error;

require_once __DIR__ . '/wp-stats-stubs.php';

/**
 * Three Checker::run() calls with canned HTTP.
 */
class CheckerEventsTest extends TestCase {

	/**
	 * Host substring => HTTP code. 0 means transport error.
	 *
	 * @var array<string, int>
	 */
	private $codes = array();

	/**
	 * Frozen clock.
	 *
	 * @var string
	 */
	private $now = '2026-09-06T06:00:00Z';

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		RestStore::reset();
		$this->codes = array();
		$this->now   = '2026-09-06T06:00:00Z';
	}

	/**
	 * Ok then fallback then ok on CDNJS produces the three event types.
	 */
	public function test_three_runs_emit_first_fallback_recovered() {
		$events  = $this->events();
		$checker = $this->checker( $events );

		$this->codes['*'] = 200;
		$checker->run();
		$this->assertSame( 'first_check', $events->entries()[0]['type'] );

		$this->now                         = '2026-09-06T06:10:00Z';
		$this->codes                       = array();
		$this->codes['cdnjs.admincdn.com'] = 0;
		$this->codes['cloudflare.com']     = 200;
		$this->codes['*']                  = 200;
		$checker->run();
		$this->assertSame( 'route_fallback', $events->entries()[0]['type'] );

		$this->now   = '2026-09-06T06:22:00Z';
		$this->codes = array( '*' => 200 );
		$checker->run();
		$row = $events->entries()[0];
		$this->assertSame( 'route_recovered', $row['type'] );
		$this->assertSame( 'CDNJS 源 恢复，已切回', $row['title'] );
		$this->assertSame( 'adminCDN 中断 12 分钟，期间走原始上游，访客不受影响', $row['detail'] );
	}

	/**
	 * Events with a moving clock.
	 */
	private function events(): Events {
		$n = 0;
		return new Events(
			new MapConfig( array() ),
			function () {
				return $this->now;
			},
			static function () use ( &$n ) {
				++$n;
				return sprintf( '01TEST%020d', $n );
			}
		);
	}

	/**
	 * Checker bound to $events.
	 *
	 * @param Events $events Event log.
	 */
	private function checker( Events $events ): Checker {
		return new Checker(
			array( $this, 'http_get' ),
			'get_transient',
			'set_transient',
			new MapConfig(
				array(
					'connectivity.avatar.admin'    => 'off',
					'connectivity.avatar.frontend' => 'off',
					'connectivity.avatar'          => 'off',
				)
			),
			function () {
				return $this->now;
			},
			$events
		);
	}

	/**
	 * Canned HTTP GET.
	 *
	 * @param string $url Probe URL.
	 * @return array<string, mixed>|WP_Error
	 */
	public function http_get( string $url ) {
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
}
