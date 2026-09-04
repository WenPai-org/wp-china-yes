<?php
/**
 * DiagnosticsModule cron gating and Site Health section.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Diagnostics\DiagnosticsModule;
use WenPai\ChinaYes\Diagnostics\SiteHealth;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;

require_once __DIR__ . '/wp-diagnostics-stubs.php';

/**
 * Module id, scheduled_checks, Site Health fields.
 */
class ModuleAndSiteHealthTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		DiagnosticsStore::reset();
	}

	/**
	 * Module id is diagnostics.
	 */
	public function test_module_id_is_diagnostics() {
		$module = new DiagnosticsModule();
		$this->assertSame( 'diagnostics', $module->id() );
		$this->assertContains( Environment::ADMIN, $module->contexts() );
		$this->assertContains( Environment::CLI, $module->contexts() );
		$this->assertContains( Environment::CRON, $module->contexts() );
	}

	/**
	 * Scheduled checks on: cron is registered.
	 */
	public function test_register_schedules_cron_when_enabled() {
		$config = new MapConfig( array( 'diagnostics.scheduled_checks' => true ) );
		$module = new DiagnosticsModule( $config, $this->idle_checker() );
		$module->register();

		$this->assertArrayHasKey( DiagnosticsModule::CRON_HOOK, DiagnosticsStore::$hooks );
		$this->assertTrue( DiagnosticsStore::$scheduled[ DiagnosticsModule::CRON_HOOK ] );
		$this->assertArrayHasKey( 'debug_information', DiagnosticsStore::$hooks );
	}

	/**
	 * Scheduled checks off: cron is cleared, run_scheduled does not probe.
	 */
	public function test_register_clears_cron_when_disabled() {
		$ran     = array( 'n' => 0 );
		$http    = static function () use ( &$ran ) {
			++$ran['n'];
			return array(
				'response' => array(
					'code' => 200,
				),
			);
		};
		$checker = new Checker( $http, 'get_transient', 'set_transient' );
		$config  = new MapConfig( array( 'diagnostics.scheduled_checks' => false ) );
		$module  = new DiagnosticsModule( $config, $checker );
		$module->register();

		$this->assertTrue( DiagnosticsStore::$cleared );
		$module->run_scheduled();
		$this->assertSame( 0, $ran['n'] );
	}

	/**
	 * Site Health section is 文派叶子, fields match result objects, no telemetry word.
	 */
	public function test_site_health_section_has_result_summary_and_no_telemetry_copy() {
		DiagnosticsStore::$transients[ Checker::STORE_KEY ] = array(
			array(
				'target'     => 'downloads.wenpai.net',
				'result'     => Checker::RESULT_OK,
				'latency_ms' => 42,
				'checked_at' => '2026-09-04T12:00:00Z',
				'suggestion' => null,
			),
			array(
				'target'     => 'cdnjs.admincdn.com',
				'result'     => Checker::RESULT_DOWN,
				'latency_ms' => 8,
				'checked_at' => '2026-09-04T12:00:00Z',
				'suggestion' => '目标不可达，请稍后重试或检查网络。',
			),
		);

		$health = new SiteHealth( new Checker() );
		$info   = $health->add_debug_info( array() );

		$this->assertArrayHasKey( SiteHealth::SECTION, $info );
		$section = $info[ SiteHealth::SECTION ];
		$this->assertSame( '文派叶子', $section['label'] );
		$this->assertStringNotContainsString( '遥测', wp_json_encode( $section ) );
		$this->assertStringNotContainsString( 'telemetry', strtolower( wp_json_encode( $section ) ) );

		$ok = $section['fields']['downloadswenpainet'];
		$this->assertSame( 'downloads.wenpai.net', $ok['label'] );
		$this->assertStringContainsString( 'ok', $ok['value'] );
		$this->assertSame( Checker::RESULT_OK, $ok['debug']['result'] );
		$this->assertNull( $ok['debug']['suggestion'] );

		$down = $section['fields']['cdnjsadmincdncom'];
		$this->assertSame( Checker::RESULT_DOWN, $down['debug']['result'] );
		$this->assertIsString( $down['debug']['suggestion'] );
	}

	/**
	 * Checker that never hits the network.
	 */
	private function idle_checker(): Checker {
		return new Checker(
			static function () {
				return array(
					'response' => array(
						'code' => 200,
					),
				);
			},
			'get_transient',
			'set_transient'
		);
	}
}
