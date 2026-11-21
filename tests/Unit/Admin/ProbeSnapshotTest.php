<?php
/**
 * Unit tests for ProbeSnapshot SSR hydration and vocabulary guardrails.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\ProbeSnapshot;
use WenPai\ChinaYes\Rest\ClientProbeController;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Verifies Overview 4th tile and Diagnose browser rows across empty, ok, and down states.
 */
class ProbeSnapshotTest extends TestCase {

	/**
	 * Reset option and admin stores before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AdminStore::reset();
		OptionStore::reset();
	}

	/**
	 * Empty option returns placeholder em-dash on Overview and pending state on Diagnose.
	 */
	public function test_empty_cache_returns_default_placeholders(): void {
		$tile = ProbeSnapshot::overview_browser_tile();

		$this->assertSame( '浏览器直测', $tile['label'] );
		$this->assertSame( '—', $tile['value'] );
		$this->assertSame( '', $tile['unit'] );
		$this->assertSame( '访客侧可达', $tile['sub'] );
		$this->assertSame( '', $tile['tone'] );
		$this->assertSame( array( 'data-wpcy-tile' => 'browser' ), $tile['attrs'] );
		$this->assertSame( array( 'data-wpcy-tile-val' => 'browser' ), $tile['value_attrs'] );
		$this->assertSame( array( 'data-wpcy-tile-sub' => 'browser' ), $tile['sub_attrs'] );

		$items = ProbeSnapshot::diagnose_browser_items();
		$this->assertCount( 3, $items );
		$this->assertSame(
			array(
				array(
					'label' => 'Cravatar 头像节点',
					'value' => '等待检测',
				),
				array(
					'label' => 'adminCDN 公共前端库',
					'value' => '等待检测',
				),
				array(
					'label' => 'Windfonts 中文字体源',
					'value' => '等待检测',
				),
			),
			$items
		);
	}

	/**
	 * Healthy cached probes hydrate average latency on Overview and connected state on Diagnose.
	 */
	public function test_healthy_cache_hydrates_average_latency_and_diagnose_rows(): void {
		update_option(
			ClientProbeController::OPTION,
			array(
				'checked_at' => '2026-11-21T08:00:00Z',
				'probes'     => array(
					array(
						'target'     => 'cn.cravatar.com',
						'result'     => 'ok',
						'latency_ms' => 36,
					),
					array(
						'target'     => 'googlefonts.admincdn.com',
						'result'     => 'ok',
						'latency_ms' => 64,
					),
				),
			)
		);

		$tile = ProbeSnapshot::overview_browser_tile();
		$this->assertSame( '50', $tile['value'] );
		$this->assertSame( 'ms', $tile['unit'] );
		$this->assertSame( '访客侧可达', $tile['sub'] );
		$this->assertSame( '', $tile['tone'] );

		$items = ProbeSnapshot::diagnose_browser_items();
		$this->assertSame(
			array(
				'label'       => 'Cravatar 头像节点',
				'value'       => '36ms',
				'state'       => 'ok',
				'state_label' => '已连接',
			),
			$items[0]
		);
		$this->assertSame(
			array(
				'label'       => 'adminCDN 公共前端库',
				'value'       => '64ms',
				'state'       => 'ok',
				'state_label' => '已连接',
			),
			$items[1]
		);
		$this->assertSame(
			array(
				'label' => 'Windfonts 中文字体源',
				'value' => '等待检测',
			),
			$items[2]
		);
	}

	/**
	 * Partial failure averages remaining ok probes while marking failed Diagnose rows.
	 */
	public function test_mixed_ok_and_down_cache_uses_ok_average_and_per_row_status(): void {
		update_option(
			ClientProbeController::OPTION,
			array(
				'checked_at' => '2026-11-21T08:05:00Z',
				'probes'     => array(
					array(
						'target'     => 'cn.cravatar.com',
						'result'     => 'ok',
						'latency_ms' => 41,
					),
					array(
						'target'     => 'googlefonts.admincdn.com',
						'result'     => 'down',
						'latency_ms' => null,
					),
				),
			)
		);

		$tile = ProbeSnapshot::overview_browser_tile();
		$this->assertSame( '41', $tile['value'] );
		$this->assertSame( 'ms', $tile['unit'] );
		$this->assertSame( '访客侧可达', $tile['sub'] );
		$this->assertSame( '', $tile['tone'] );

		$items = ProbeSnapshot::diagnose_browser_items();
		$this->assertSame( '41ms', $items[0]['value'] );
		$this->assertSame( 'ok', $items[0]['state'] );
		$this->assertSame( '连接超时', $items[1]['value'] );
		$this->assertSame( 'bad', $items[1]['state'] );
		$this->assertSame( '未连接', $items[1]['state_label'] );
	}

	/**
	 * All-down probes return timeout warning tile on Overview and bad rows on Diagnose.
	 */
	public function test_all_down_cache_returns_timeout_warn_tile(): void {
		update_option(
			ClientProbeController::OPTION,
			array(
				'checked_at' => '2026-11-21T08:10:00Z',
				'probes'     => array(
					array(
						'target'     => 'cn.cravatar.com',
						'result'     => 'down',
						'latency_ms' => null,
					),
					array(
						'target'     => 'googlefonts.admincdn.com',
						'result'     => 'down',
						'latency_ms' => null,
					),
				),
			)
		);

		$tile = ProbeSnapshot::overview_browser_tile();
		$this->assertSame( '浏览器直测', $tile['label'] );
		$this->assertSame( '超时', $tile['value'] );
		$this->assertSame( '', $tile['unit'] );
		$this->assertSame( '连接波动', $tile['sub'] );
		$this->assertSame( 'warn', $tile['tone'] );

		$items = ProbeSnapshot::diagnose_browser_items();
		$this->assertSame( 'bad', $items[0]['state'] );
		$this->assertSame( '未连接', $items[0]['state_label'] );
		$this->assertSame( 'bad', $items[1]['state'] );
		$this->assertSame( '未连接', $items[1]['state_label'] );
		$this->assertSame( '等待检测', $items[2]['value'] );
	}

	/**
	 * ProbeSnapshot.php and wpcy-probes.js contain zero forbidden vocabulary or inline styles.
	 */
	public function test_files_contain_zero_forbidden_words_or_inline_styles(): void {
		$root  = dirname( __DIR__, 3 );
		$files = array(
			$root . '/src/Admin/ProbeSnapshot.php',
			$root . '/assets/js/wpcy-probes.js',
		);

		$forbidden = '/遥测|匿名数据|opt-in|entitlement|套餐|\bPro\b/';

		foreach ( $files as $path ) {
			$this->assertFileExists( $path );
			$contents = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file inspection in unit test.
			$this->assertDoesNotMatchRegularExpression( $forbidden, $contents, basename( $path ) . ' matched forbidden vocabulary.' );
			$this->assertStringNotContainsString( 'style="', $contents, basename( $path ) . ' must not contain inline styles.' );
		}
	}
}
