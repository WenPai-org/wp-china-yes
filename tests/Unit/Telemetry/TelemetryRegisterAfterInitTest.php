<?php
/**
 * TelemetryModule::register() when init has already fired.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Telemetry;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Telemetry\TelemetryModule;

/**
 * A late register() (after init) schedules at once and adds no init hook.
 */
class TelemetryRegisterAfterInitTest extends TestCase {

	/**
	 * Schedules immediately; only the cron callback is hooked.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_register_after_init_schedules_immediately() {
		require __DIR__ . '/wp-telemetry-after-init-stubs.php';
		require __DIR__ . '/wp-telemetry-before-pluggable-stubs.php';
		$this->assertSame( 1, did_action( 'init' ) );

		$module = new TelemetryModule();
		$module->register();

		$this->assertTrue( TelemetryStore::$scheduled[ TelemetryModule::CRON_HOOK ] );
		$this->assertArrayHasKey( TelemetryModule::CRON_HOOK, TelemetryStore::$actions );
		$this->assertArrayNotHasKey( 'init', TelemetryStore::$actions );
	}

	/**
	 * After init with an event already pending, nothing new is scheduled.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_register_after_init_keeps_pending_event() {
		require __DIR__ . '/wp-telemetry-after-init-stubs.php';
		require __DIR__ . '/wp-telemetry-before-pluggable-stubs.php';
		TelemetryStore::$scheduled[ TelemetryModule::CRON_HOOK ] = 'pending';

		$module = new TelemetryModule();
		$module->register();

		$this->assertSame( 'pending', TelemetryStore::$scheduled[ TelemetryModule::CRON_HOOK ] );
		$this->assertArrayNotHasKey( 'init', TelemetryStore::$actions );
	}
}
