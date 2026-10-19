<?php
/**
 * PHPUnit rewrite of tests/test-mirror-health-fallback.php.
 *
 * Host parsing and probe-target shapes live in Connectivity\MirrorHealth
 * so M1-05 PublicAssets can reuse them. Acceleration replacement *sources*
 * are not reimplemented here.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\WordPressOrg;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\MirrorHealth;

/**
 * Sections map 1:1 onto tests/test-mirror-health-fallback.php.
 */
class MirrorHealthHostTest extends TestCase {

	/**
	 * Transient bag for the current test.
	 *
	 * @var array<string, mixed>
	 */
	private $transients = array();

	/**
	 * Reset transients and wp_version.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->transients      = array();
		$GLOBALS['wp_version'] = '6.8'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- probe_targets() reads $GLOBALS['wp_version'] like 3.x.
	}

	/**
	 * Host extraction from bare names, paths, URLs, and blanks.
	 *
	 * @testdox host_of() 解析
	 */
	public function test_host_of_parsing(): void {
		$this->assertSame( 'jsd.admincdn.com', MirrorHealth::host_of( 'jsd.admincdn.com' ), '裸主机名' );
		$this->assertSame( 'jsd.admincdn.com', MirrorHealth::host_of( 'jsd.admincdn.com/npm/react' ), '带路径（替换表实际形态）' );
		$this->assertSame( 'jsd.admincdn.com', MirrorHealth::host_of( 'https://jsd.admincdn.com/npm/leaflet@' ), '带协议' );
		$this->assertSame( 'cdnjs.admincdn.com', MirrorHealth::host_of( 'cdnjs.admincdn.com' ), 'cdnjs' );
		$this->assertSame( '', MirrorHealth::host_of( '' ), '空串' );
		$this->assertSame( 'jsd.admincdn.com', MirrorHealth::host_of( '  jsd.admincdn.com/npm/vue  ' ), '带空白' );
	}

	/**
	 * Each Acceleration replacement target parses to the expected host.
	 *
	 * @testdox 替换表里的真实目标都能解析出预期主机
	 *
	 * @dataProvider replacement_target_provider
	 *
	 * @param string $target   Acceleration replacement target.
	 * @param string $expected Expected host.
	 */
	public function test_replacement_table_targets_parse_to_expected_hosts( string $target, string $expected ): void {
		$this->assertSame( $expected, MirrorHealth::host_of( $target ), "{$target} => {$expected}" );
	}

	/**
	 * Twelve targets from tests/test-mirror-health-fallback.php (Acceleration map).
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function replacement_target_provider(): array {
		return array(
			'googlefonts'    => array( 'googlefonts.admincdn.com', 'googlefonts.admincdn.com' ),
			'googleajax'     => array( 'googleajax.admincdn.com', 'googleajax.admincdn.com' ),
			'cdnjs'          => array( 'cdnjs.admincdn.com', 'cdnjs.admincdn.com' ),
			'jsd'            => array( 'jsd.admincdn.com', 'jsd.admincdn.com' ),
			'jsd-react'      => array( 'jsd.admincdn.com/npm/react', 'jsd.admincdn.com' ),
			'jsd-jquery'     => array( 'jsd.admincdn.com/npm/jquery', 'jsd.admincdn.com' ),
			'jsd-vue'        => array( 'jsd.admincdn.com/npm/vue', 'jsd.admincdn.com' ),
			'jsd-datatables' => array( 'jsd.admincdn.com/npm/datatables.net', 'jsd.admincdn.com' ),
			'jsd-tailwind'   => array( 'jsd.admincdn.com/npm/tailwindcss', 'jsd.admincdn.com' ),
			'jsd-twemoji'    => array( 'jsd.admincdn.com/npm/@twemoji/api/dist', 'jsd.admincdn.com' ),
			'wpstatic'       => array( 'wpstatic.admincdn.com', 'wpstatic.admincdn.com' ),
			'ts'             => array( 'ts.wenpai.net', 'ts.wenpai.net' ),
		);
	}

	/**
	 * Unknown is healthy; down does not leak to other hosts.
	 *
	 * @testdox is_healthy() 语义
	 */
	public function test_is_healthy_semantics(): void {
		$health = $this->new_health();

		$this->assertTrue( $health->is_healthy( 'jsd.admincdn.com' ), '未知 => 视为健康（不因缺数据丢加速）' );

		$health->remember( 'jsd.admincdn.com', 'up', 3600 );
		$this->assertTrue( $health->is_healthy( 'jsd.admincdn.com' ), '标记 up => 健康' );

		$health->remember( 'jsd.admincdn.com', 'down', 1800 );
		$this->assertFalse( $health->is_healthy( 'jsd.admincdn.com' ), '标记 down => 不健康' );

		$this->assertTrue( $health->is_healthy( 'cdnjs.admincdn.com' ), 'down 状态不串到别的主机' );
		$this->assertTrue( $health->is_healthy( '' ), '空主机名 => 健康（不阻断）' );
	}

	/**
	 * Unhealthy host list contains only hosts marked down.
	 *
	 * @testdox unhealthy_hosts()
	 */
	public function test_unhealthy_hosts_lists_only_down(): void {
		$health = $this->new_health();
		$health->remember( 'cdnjs.admincdn.com', 'down', 1800 );
		$health->remember( 'jsd.admincdn.com', 'down', 1800 );

		$down = $health->unhealthy_hosts();
		sort( $down );

		$this->assertSame(
			array( 'cdnjs.admincdn.com', 'jsd.admincdn.com' ),
			$down,
			'只列出被标 down 的（实得：' . implode( ',', $down ) . '）'
		);
	}

	/**
	 * Probe paths match the URLs the plugin actually emits.
	 *
	 * @testdox 探测路径必须是插件实际生成的形态
	 */
	public function test_probe_paths_match_plugin_emitted_shapes(): void {
		$GLOBALS['wp_version'] = '6.8'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- probe_targets() reads $GLOBALS['wp_version'] like 3.x.
		$targets               = MirrorHealth::probe_targets();

		foreach ( $targets as $host => $path ) {
			$this->assertNotSame( '/', $path, "{$host} 探测路径不是根路径" );
		}

		$this->assertFalse(
			0 === strpos( $targets['cdnjs.admincdn.com'], '/ajax/libs/' ),
			'cdnjs 路径不带 /ajax/libs 前缀（替换时已被吃掉）'
		);
		$this->assertSame(
			'/jquery/3.7.1/jquery.min.js',
			$targets['cdnjs.admincdn.com'],
			'cdnjs 路径与替换后形态一致'
		);
		$this->assertSame(
			0,
			strpos( $targets['googleajax.admincdn.com'], '/ajax/libs/' ),
			'googleajax 路径带 /ajax/libs 前缀'
		);
		$this->assertSame(
			0,
			strpos( $targets['jsd.admincdn.com'], '/npm/' ),
			'jsd 用 jsDelivr 的 /npm/ 路径约定'
		);
		$this->assertArrayNotHasKey(
			'public.admincdn.com',
			$targets,
			'public 已从探测目标移除（前台加速已废弃）'
		);
		$this->assertSame(
			0,
			strpos( $targets['wpstatic.admincdn.com'], '/6.8/wp-admin/' ),
			'wpstatic 路径含 wp_version 前缀'
		);

		$GLOBALS['wp_version'] = '6.9'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- probe_targets() reads $GLOBALS['wp_version'] like 3.x.
		$later                 = MirrorHealth::probe_targets();
		$this->assertSame(
			0,
			strpos( $later['wpstatic.admincdn.com'], '/6.9/wp-admin/' ),
			'wpstatic 路径随 wp_version 变化'
		);
		$this->assertSame(
			0,
			strpos( $targets['ts.wenpai.net'], '/wp-content/themes/' ),
			'ts 路径是主题截图形态'
		);
	}

	/**
	 * Health helper bound to this test's transients.
	 */
	private function new_health(): MirrorHealth {
		return new MirrorHealth(
			function ( $key ) {
				return $this->transients[ $key ] ?? false;
			},
			function ( $key, $value, $ttl = 0 ) {
				unset( $ttl );
				$this->transients[ $key ] = $value;
				return true;
			}
		);
	}
}
