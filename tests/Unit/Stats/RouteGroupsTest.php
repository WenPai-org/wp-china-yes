<?php
/**
 * RouteGroups: host → group, worst aggregation.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Stats;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Diagnostics\RouteGroups;

require_once __DIR__ . '/wp-stats-stubs.php';

/**
 * Diagnostics grouping table.
 */
class RouteGroupsTest extends TestCase {

	/**
	 * Known hosts map to the rest-api group labels.
	 */
	public function test_group_for_target() {
		$wp = RouteGroups::group_for_target( 'api.wenpai.net' );
		$this->assertSame( 'WordPress.org 镜像', $wp['label'] );
		$this->assertSame( 'WenPai.org', $wp['provider'] );

		$cdnjs = RouteGroups::group_for_target( 'cdnjs.admincdn.com' );
		$this->assertSame( 'CDNJS 源', $cdnjs['label'] );
		$this->assertSame( 'adminCDN', $cdnjs['provider'] );

		$this->assertNull( RouteGroups::group_for_target( 'example.com' ) );
	}

	/**
	 * Worst member wins; latency is max; checked_at is earliest.
	 */
	public function test_worst_aggregates_members() {
		$rows   = array(
			array(
				'target'     => 'api.wenpai.net',
				'result'     => Checker::RESULT_OK,
				'latency_ms' => 10,
				'checked_at' => '2026-09-06T12:00:01Z',
				'suggestion' => null,
			),
			array(
				'target'     => 'downloads.wenpai.net',
				'result'     => Checker::RESULT_FALLBACK,
				'latency_ms' => 40,
				'checked_at' => '2026-09-06T12:00:00Z',
				'suggestion' => 'x',
			),
		);
		$groups = RouteGroups::worst( $rows );
		$this->assertCount( 1, $groups );
		$this->assertSame( Checker::RESULT_FALLBACK, $groups[0]['result'] );
		$this->assertSame( 40, $groups[0]['latency_ms'] );
		$this->assertSame( '2026-09-06T12:00:00Z', $groups[0]['checked_at'] );
		$this->assertSame( 'WenPai.org', $groups[0]['provider'] );
	}
}
