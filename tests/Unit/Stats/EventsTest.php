<?php
/**
 * Events: ring of 50, ULID length 26, template table, Checker transitions.
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

require_once __DIR__ . '/wp-stats-stubs.php';

/**
 * Event log templates and ring buffer.
 */
class EventsTest extends TestCase {

	/**
	 * Sequential ULID counter for tests.
	 *
	 * @var int
	 */
	private $ulid_n = 0;

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		RestStore::reset();
		$this->ulid_n = 0;
	}

	/**
	 * Ring keeps the newest 50.
	 */
	public function test_ring_keeps_fifty() {
		$events = $this->events();
		for ( $i = 0; $i < 55; $i++ ) {
			$events->record( 'recovery_exited' );
		}
		$this->assertCount( 50, $events->entries() );
		$this->assertSame( '01TEST00000000000000000055', $events->entries()[0]['id'] );
	}

	/**
	 * Generated ULID is 26 Crockford characters.
	 */
	public function test_ulid_is_twenty_six_crockford_chars() {
		$id = Events::generate_ulid();
		$this->assertSame( 26, strlen( $id ) );
		$this->assertMatchesRegularExpression( '/^[0-9A-HJKMNP-TV-Z]{26}$/', $id );
	}

	/**
	 * Each type renders the rest-api template.
	 *
	 * @param string               $type    Event type.
	 * @param array<string, mixed> $vars    Template vars.
	 * @param string               $tone    Expected tone.
	 * @param string               $title   Expected title.
	 * @param string               $detail  Expected detail.
	 *
	 * @dataProvider type_templates
	 */
	public function test_type_matches_template( string $type, array $vars, string $tone, string $title, string $detail ) {
		$events = $this->events();
		$events->record( $type, $vars );
		$row = $events->entries()[0];
		$this->assertSame( $type, $row['type'] );
		$this->assertSame( $tone, $row['tone'] );
		$this->assertSame( $title, $row['title'] );
		$this->assertSame( $detail, $row['detail'] );
	}

	/**
	 * Template table from rest-api.md §/events.
	 *
	 * @return array<string, array{0: string, 1: array<string, mixed>, 2: string, 3: string, 4: string}>
	 */
	public function type_templates(): array {
		return array(
			'first_check_all_ok'      => array(
				'first_check',
				array(
					'ok'    => 4,
					'total' => 4,
				),
				'ok',
				'首次线路检查完成',
				'4 条线路全部正常',
			),
			'first_check_partial'     => array(
				'first_check',
				array(
					'ok'    => 2,
					'total' => 4,
				),
				'ok',
				'首次线路检查完成',
				'2/4 条线路正常',
			),
			'route_recovered'         => array(
				'route_recovered',
				array(
					'route'    => 'CDNJS 源',
					'provider' => 'adminCDN',
					'minutes'  => 12,
				),
				'ok',
				'CDNJS 源 恢复，已切回',
				'adminCDN 中断 12 分钟，期间走原始上游，访客不受影响',
			),
			'mirror_fallback'         => array(
				'mirror_fallback',
				array(),
				'warn',
				'WordPress.org 镜像不可达，已回原始上游',
				'WenPai.org 镜像暂时不可达，每 1 分钟重试，恢复后自动切回',
			),
			'route_fallback'          => array(
				'route_fallback',
				array(
					'route'    => '公共库源',
					'provider' => 'adminCDN',
				),
				'warn',
				'公共库源 不可达，已回原始上游',
				'adminCDN 暂时不可达，恢复后自动切回',
			),
			'route_down'              => array(
				'route_down',
				array(
					'route'    => 'Cravatar',
					'provider' => 'Cravatar',
				),
				'warn',
				'Cravatar 不可达',
				'Cravatar 暂时不可达，该项已暂停改写，恢复后自动继续',
			),
			'update_check_mirror'     => array(
				'update_check',
				array(
					'version'    => '6.8.2',
					'seconds'    => '1.2',
					'via_mirror' => true,
				),
				'ok',
				'完成 WordPress 6.8.2 更新检查',
				'经国内镜像，耗时 1.2 秒',
			),
			'update_check_direct'     => array(
				'update_check',
				array(
					'version'    => '6.8.2',
					'seconds'    => '0.4',
					'via_mirror' => false,
				),
				'neutral',
				'完成 WordPress 6.8.2 更新检查',
				'直连 WordPress.org，耗时 0.4 秒',
			),
			'migrated'                => array(
				'migrated',
				array(
					'version'        => '4.0.0',
					'source_version' => '3.9.3',
					'kept'           => 5,
				),
				'neutral',
				'插件更新到 4.0.0',
				'从 3.9.3 迁移 5 项设置',
			),
			'profile_set_domestic'    => array(
				'profile_set',
				array(
					'profile'       => 'domestic',
					'profile_label' => '国内站',
				),
				'neutral',
				'已按「国内站」配置',
				'更新走国内镜像，前端资源与头像走国内节点',
			),
			'profile_set_crossborder' => array(
				'profile_set',
				array(
					'profile'       => 'crossborder',
					'profile_label' => '跨境 / 外贸站',
				),
				'neutral',
				'已按「跨境 / 外贸站」配置',
				'后台资源只在后台加速，更新直连 WordPress.org',
			),
			'recovery_entered'        => array(
				'recovery_entered',
				array(),
				'warn',
				'已进入恢复模式',
				'全部 URL 改写与模块已停用',
			),
			'recovery_exited'         => array(
				'recovery_exited',
				array(),
				'ok',
				'已退出恢复模式',
				'设置已恢复',
			),
		);
	}

	/**
	 * Recovery mode records only recovery_*.
	 */
	public function test_recovery_mode_records_only_recovery_events() {
		$events = $this->events( true );
		$events->record(
			'first_check',
			array(
				'ok'    => 1,
				'total' => 1,
			)
		);
		$events->record( 'recovery_entered' );
		$this->assertCount( 1, $events->entries() );
		$this->assertSame( 'recovery_entered', $events->entries()[0]['type'] );
	}

	/**
	 * Ok then fallback then ok produces first_check, route_fallback, route_recovered.
	 */
	public function test_checker_ok_fallback_ok_sequence() {
		$now    = '2026-09-06T06:00:00Z';
		$events = new Events(
			new MapConfig( array() ),
			static function () use ( &$now ) {
				return $now;
			},
			array( $this, 'next_ulid' )
		);
		$ok     = $this->group_rows( Checker::RESULT_OK );
		$fall   = $this->group_rows( Checker::RESULT_FALLBACK );

		$events->record_checker_transition( array(), $ok );
		$this->assertSame( 'first_check', $events->entries()[0]['type'] );

		$now = '2026-09-06T06:10:00Z';
		$events->record_checker_transition( $ok, $fall );
		$this->assertSame( 'route_fallback', $events->entries()[0]['type'] );
		$this->assertSame( 'CDNJS 源 不可达，已回原始上游', $events->entries()[0]['title'] );

		$now = '2026-09-06T06:22:00Z';
		$events->record_checker_transition( $fall, $ok );
		$row = $events->entries()[0];
		$this->assertSame( 'route_recovered', $row['type'] );
		$this->assertSame( 'CDNJS 源 恢复，已切回', $row['title'] );
		$this->assertSame( 'adminCDN 中断 12 分钟，期间走原始上游，访客不受影响', $row['detail'] );
	}

	/**
	 * WordPress.org group ok→fallback is mirror_fallback, not route_fallback.
	 */
	public function test_wordpress_org_group_uses_mirror_fallback() {
		$events = $this->events();
		$ok     = array(
			$this->row( 'api.wenpai.net', Checker::RESULT_OK ),
			$this->row( 'downloads.wenpai.net', Checker::RESULT_OK ),
		);
		$fall   = array(
			$this->row( 'api.wenpai.net', Checker::RESULT_FALLBACK ),
			$this->row( 'downloads.wenpai.net', Checker::RESULT_OK ),
		);
		$events->record_checker_transition( array(), $ok );
		$events->record_checker_transition( $ok, $fall );
		$this->assertSame( 'mirror_fallback', $events->entries()[0]['type'] );
	}

	/**
	 * Frozen-clock Events.
	 *
	 * @param bool $recovery Recovery mode.
	 */
	private function events( bool $recovery = false ): Events {
		return new Events(
			new MapConfig( array( 'recovery_mode' => $recovery ) ),
			static function () {
				return '2026-09-06T06:02:11Z';
			},
			array( $this, 'next_ulid' )
		);
	}

	/**
	 * Deterministic 26-char id.
	 */
	public function next_ulid(): string {
		++$this->ulid_n;
		return sprintf( '01TEST%020d', $this->ulid_n );
	}

	/**
	 * One Checker row.
	 *
	 * @param string $target Target host.
	 * @param string $result ok|fallback|down.
	 * @return array{target: string, result: string, latency_ms: int, checked_at: string, suggestion: string|null}
	 */
	private function row( string $target, string $result ): array {
		return array(
			'target'     => $target,
			'result'     => $result,
			'latency_ms' => 10,
			'checked_at' => '2026-09-06T06:00:00Z',
			'suggestion' => Checker::RESULT_OK === $result ? null : 'x',
		);
	}

	/**
	 * CDNJS-only rows so group transitions are a single route.
	 *
	 * @param string $result ok|fallback|down.
	 * @return list<array{target: string, result: string, latency_ms: int, checked_at: string, suggestion: string|null}>
	 */
	private function group_rows( string $result ): array {
		return array( $this->row( 'cdnjs.admincdn.com', $result ) );
	}
}
