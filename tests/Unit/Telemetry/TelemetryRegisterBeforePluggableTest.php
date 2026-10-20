<?php
/**
 * TelemetryModule::register() runs before pluggable.php is loaded.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Telemetry;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Telemetry\TelemetryModule;

/**
 * Regression: the plugin boots while wp-settings.php includes plugins, before
 * pluggable.php defines wp_rand(). On a (sub)site with no pending event,
 * register() used to throw "Call to undefined function wp_rand()" and
 * ModuleRegistry logged "Module register() failed: telemetry" on every request.
 *
 * Runs in a separate process so wp_rand() from other stub files is not defined.
 */
class TelemetryRegisterBeforePluggableTest extends TestCase {

	/**
	 * Register without wp_rand(): no throw, no scheduling yet; init schedules once.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_register_before_pluggable_defers_scheduling_to_init() {
		require __DIR__ . '/wp-telemetry-before-pluggable-stubs.php';
		$this->assertFalse( function_exists( 'wp_rand' ) );

		$module = new TelemetryModule();
		$module->register();

		$this->assertArrayHasKey( TelemetryModule::CRON_HOOK, TelemetryStore::$actions );
		$this->assertArrayHasKey( 'init', TelemetryStore::$actions );
		$this->assertArrayNotHasKey( TelemetryModule::CRON_HOOK, TelemetryStore::$scheduled );

		// wp-settings.php now loads pluggable.php, then fires init.
		require __DIR__ . '/wp-telemetry-pluggable-stub.php';
		call_user_func( TelemetryStore::$actions['init'] );
		$this->assertTrue( TelemetryStore::$scheduled[ TelemetryModule::CRON_HOOK ] );

		// A second init (next request) with the event pending schedules nothing new.
		TelemetryStore::$scheduled[ TelemetryModule::CRON_HOOK ] = 'pending';
		call_user_func( TelemetryStore::$actions['init'] );
		$this->assertSame( 'pending', TelemetryStore::$scheduled[ TelemetryModule::CRON_HOOK ] );
	}
}
