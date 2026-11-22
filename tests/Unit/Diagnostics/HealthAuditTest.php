<?php
/**
 * Unit tests for HealthAudit scoring and safe one-click fix patch generation.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Diagnostics\HealthAudit;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-diagnostics-stubs.php';

/**
 * Covers domestic full-score, fixable gaps, crossborder respect, conflicts, and recovery mode.
 */
class HealthAuditTest extends TestCase {

	/**
	 * Reset option store before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
	}

	/**
	 * Healthy domestic site with cached ok probes scores 100 with zero fixable items.
	 */
	public function test_healthy_domestic_site_scores_100(): void {
		$settings                                    = Defaults::settings();
		$settings['connectivity']['heartbeat']       = 'on';
		$settings['connectivity']['dashboard_feeds'] = 'block';

		$probes = array(
			array(
				'target'     => 'api.wenpai.net',
				'result'     => 'ok',
				'latency_ms' => 110,
			),
			array(
				'target'     => 'cdnjs.admincdn.com',
				'result'     => 'ok',
				'latency_ms' => 70,
			),
		);

		$res = HealthAudit::evaluate( $settings, $probes );
		$this->assertSame( 100, $res['score'] );
		$this->assertSame( 'ok', $res['status'] );
		$this->assertSame( 0, $res['fixable_count'] );
		$this->assertSame( 90, $res['avg_latency_ms'] );
		$this->assertCount( 6, $res['items'] );
		$this->assertSame( array(), HealthAudit::build_fix_patch( $settings ) );
	}

	/**
	 * Disabled domestic channels produce fixable items and build_fix_patch restores them safely.
	 */
	public function test_disabled_domestic_channels_are_fixable(): void {
		$settings                                  = Defaults::settings();
		$settings['connectivity']['wordpress_org'] = 'off';
		$settings['connectivity']['public_assets']['scope'] = 'off';
		$settings['connectivity']['avatar']                 = 'off';
		$settings['connectivity']['heartbeat']              = 'off';
		$settings['connectivity']['dashboard_feeds']        = 'allow';

		$res = HealthAudit::evaluate( $settings, array() );
		$this->assertSame( 25, $res['score'] );
		$this->assertSame( 'bad', $res['status'] );
		$this->assertSame( 4, $res['fixable_count'] );

		$patch = HealthAudit::build_fix_patch( $settings );
		$this->assertSame( 'auto', $patch['connectivity.wordpress_org'] );
		$this->assertSame( 'both', $patch['connectivity.public_assets.scope'] );
		$this->assertSame( 'cravatar_cn', $patch['connectivity.avatar'] );
		$this->assertSame( 'on', $patch['connectivity.heartbeat'] );
		$this->assertSame( 'block', $patch['connectivity.dashboard_feeds'] );
	}

	/**
	 * Crossborder profile respects intentional direct WP.org / public assets and picks global Cravatar.
	 */
	public function test_crossborder_respects_direct_strategy(): void {
		$settings                                  = Defaults::settings();
		$settings['profile']                       = 'crossborder';
		$settings['connectivity']['wordpress_org'] = 'off';
		$settings['connectivity']['public_assets']['scope'] = 'off';
		$settings['connectivity']['avatar']                 = 'off';
		$settings['connectivity']['heartbeat']              = 'on';

		$patch = HealthAudit::build_fix_patch( $settings );
		$this->assertArrayNotHasKey( 'connectivity.wordpress_org', $patch );
		$this->assertArrayNotHasKey( 'connectivity.public_assets.scope', $patch );
		$this->assertSame( 'cravatar_global', $patch['connectivity.avatar'] );
	}

	/**
	 * Recovery mode never auto-fixes settings silently.
	 */
	public function test_recovery_mode_blocks_auto_fix_patch(): void {
		$settings                                  = Defaults::settings();
		$settings['recovery_mode']                 = true;
		$settings['connectivity']['wordpress_org'] = 'off';

		$this->assertSame( array(), HealthAudit::build_fix_patch( $settings ) );
	}
}
