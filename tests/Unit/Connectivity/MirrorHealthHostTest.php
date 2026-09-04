<?php
/**
 * Host parse and is_healthy semantics from tests/test-mirror-health-fallback.php.
 *
 * M1-04 did not land a shared class; PublicAssets needs host_of / is_healthy.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\MirrorHealth;
use WenPai\ChinaYes\Connectivity\PublicAssets\AssetMap;

require_once __DIR__ . '/wp-hook-stubs.php';

/**
 * PHPUnit counterpart of 3.x test-mirror-health-fallback.php sections.
 */
class MirrorHealthHostTest extends TestCase {

	/**
	 * Reset transients.
	 */
	protected function setUp(): void {
		parent::setUp();
		HookStore::reset();
		unset( $GLOBALS['wp_version'] );
	}

	/**
	 * == host_of() 解析 ==
	 */
	public function test_host_of_parses_bare_host_path_scheme_and_blank() {
		$this->assertSame( 'jsd.admincdn.com', MirrorHealth::host_of( 'jsd.admincdn.com' ) );
		$this->assertSame( 'jsd.admincdn.com', MirrorHealth::host_of( 'jsd.admincdn.com/npm/react' ) );
		$this->assertSame( 'jsd.admincdn.com', MirrorHealth::host_of( 'https://jsd.admincdn.com/npm/leaflet@' ) );
		$this->assertSame( 'cdnjs.admincdn.com', MirrorHealth::host_of( 'cdnjs.admincdn.com' ) );
		$this->assertSame( '', MirrorHealth::host_of( '' ) );
		$this->assertSame( 'jsd.admincdn.com', MirrorHealth::host_of( '  jsd.admincdn.com/npm/vue  ' ) );
	}

	/**
	 * == 替换表里的真实目标都能解析出预期主机 ==
	 *
	 * @dataProvider real_target_provider
	 *
	 * @param string $target   Replacement target.
	 * @param string $expected Host.
	 */
	public function test_host_of_real_replacement_targets( string $target, string $expected ) {
		$this->assertSame( $expected, MirrorHealth::host_of( $target ) );
	}

	/**
	 * Targets from Service/Acceleration.php plus wpstatic/ts (3.x test list).
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function real_target_provider(): array {
		return array(
			'googlefonts' => array( 'googlefonts.admincdn.com', 'googlefonts.admincdn.com' ),
			'googleajax'  => array( 'googleajax.admincdn.com', 'googleajax.admincdn.com' ),
			'cdnjs'       => array( 'cdnjs.admincdn.com', 'cdnjs.admincdn.com' ),
			'jsd'         => array( 'jsd.admincdn.com', 'jsd.admincdn.com' ),
			'react'       => array( 'jsd.admincdn.com/npm/react', 'jsd.admincdn.com' ),
			'jquery'      => array( 'jsd.admincdn.com/npm/jquery', 'jsd.admincdn.com' ),
			'vue'         => array( 'jsd.admincdn.com/npm/vue', 'jsd.admincdn.com' ),
			'datatables'  => array( 'jsd.admincdn.com/npm/datatables.net', 'jsd.admincdn.com' ),
			'tailwind'    => array( 'jsd.admincdn.com/npm/tailwindcss', 'jsd.admincdn.com' ),
			'twemoji'     => array( 'jsd.admincdn.com/npm/@twemoji/api/dist', 'jsd.admincdn.com' ),
			'wpstatic'    => array( 'wpstatic.admincdn.com', 'wpstatic.admincdn.com' ),
			'ts'          => array( 'ts.wenpai.net', 'ts.wenpai.net' ),
		);
	}

	/**
	 * AssetMap replacement targets are a subset of the 3.x host_of list.
	 */
	public function test_asset_map_targets_parse() {
		$map = new AssetMap();
		foreach ( $map->targets() as $target ) {
			$host = MirrorHealth::host_of( $target );
			$this->assertNotSame( '', $host, $target );
			$this->assertStringContainsString( '.admincdn.com', $host );
		}
	}

	/**
	 * == is_healthy() 语义 ==
	 */
	public function test_is_healthy_unknown_up_down_and_empty() {
		$health = new MirrorHealth();

		$this->assertTrue( $health->is_healthy( 'jsd.admincdn.com' ) );

		HookStore::$transients[ MirrorHealth::STATE_PREFIX . md5( 'jsd.admincdn.com' ) ] = 'up';
		$this->assertTrue( $health->is_healthy( 'jsd.admincdn.com' ) );

		HookStore::$transients[ MirrorHealth::STATE_PREFIX . md5( 'jsd.admincdn.com' ) ] = 'down';
		$this->assertFalse( $health->is_healthy( 'jsd.admincdn.com' ) );

		$this->assertTrue( $health->is_healthy( 'cdnjs.admincdn.com' ) );
		$this->assertTrue( $health->is_healthy( '' ) );
	}

	/**
	 * == unhealthy_hosts() ==
	 */
	public function test_unhealthy_hosts_lists_only_down() {
		HookStore::$transients[ MirrorHealth::STATE_PREFIX . md5( 'cdnjs.admincdn.com' ) ] = 'down';
		HookStore::$transients[ MirrorHealth::STATE_PREFIX . md5( 'jsd.admincdn.com' ) ]   = 'down';

		$down = ( new MirrorHealth() )->unhealthy_hosts();
		sort( $down );

		$this->assertSame( array( 'cdnjs.admincdn.com', 'jsd.admincdn.com' ), $down );
	}

	/**
	 * == 探测路径必须是插件实际生成的形态 ==
	 */
	public function test_probe_paths_match_emitted_url_shapes() {
		$GLOBALS['wp_version'] = '6.8'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- unit fixture for probe path.
		$targets               = MirrorHealth::probe_targets();

		foreach ( $targets as $host => $path ) {
			$this->assertNotSame( '/', $path, $host );
		}

		$this->assertNotSame( 0, strpos( $targets['cdnjs.admincdn.com'], '/ajax/libs/' ) );
		$this->assertSame( '/jquery/3.7.1/jquery.min.js', $targets['cdnjs.admincdn.com'] );
		$this->assertSame( 0, strpos( $targets['googleajax.admincdn.com'], '/ajax/libs/' ) );
		$this->assertSame( 0, strpos( $targets['jsd.admincdn.com'], '/npm/' ) );
		$this->assertArrayNotHasKey( 'public.admincdn.com', $targets );
		$this->assertSame( 0, strpos( $targets['wpstatic.admincdn.com'], '/6.8/wp-admin/' ) );

		$GLOBALS['wp_version'] = '6.9'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- unit fixture for probe path.
		$again                 = MirrorHealth::probe_targets();
		$this->assertSame( 0, strpos( $again['wpstatic.admincdn.com'], '/6.9/wp-admin/' ) );
		$this->assertSame( 0, strpos( $targets['ts.wenpai.net'], '/wp-content/themes/' ) );
	}
}
