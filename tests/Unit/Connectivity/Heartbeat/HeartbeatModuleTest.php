<?php
/**
 * Heartbeat throttle: dashboard deregister, editor interval 60, filter override.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\Heartbeat;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\Heartbeat\HeartbeatModule;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Tests\Unit\Connectivity\HookStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;

require_once dirname( __DIR__ ) . '/wp-hook-stubs.php';

/**
 * Heartbeat on|off plus wpcy_heartbeat_throttle.
 */
class HeartbeatModuleTest extends TestCase {

	/**
	 * Reset hook bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		HookStore::reset();
	}

	/**
	 * On: dashboard drops heartbeat; editor interval is 60.
	 */
	public function test_on_deregisters_dashboard_and_slows_editor() {
		$module = $this->module( 'on' );
		$config = $this->config( 'on' );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertTrue( $module->enabled( $config, $env ) );
		$module->register();
		$this->assertArrayHasKey( 'admin_enqueue_scripts', HookStore::$hooks );
		$this->assertArrayHasKey( 'heartbeat_settings', HookStore::$hooks );

		$module->on_admin_enqueue_scripts( 'index.php' );
		$this->assertContains( 'heartbeat', HookStore::$deregistered );

		HookStore::$screen_base = 'post.php';
		$out                    = $module->filter_heartbeat_settings( array( 'interval' => 15 ) );
		$this->assertSame( 60, $out['interval'] );

		HookStore::$screen_base = 'post-new.php';
		$out                    = $module->filter_heartbeat_settings( array( 'interval' => 15 ) );
		$this->assertSame( 60, $out['interval'] );
	}

	/**
	 * Editor interval is 60 from get_current_screen without enqueue first.
	 */
	public function test_editor_interval_from_screen_without_enqueue() {
		$module                 = $this->module( 'on' );
		HookStore::$screen_base = 'post.php';
		$out                    = $module->filter_heartbeat_settings( array( 'interval' => 15 ) );
		$this->assertSame( 60, $out['interval'] );
	}

	/**
	 * Off: enabled() is false so the registry will not hook.
	 */
	public function test_off_does_not_enable() {
		$config = $this->config( 'off' );
		$module = new HeartbeatModule( $config );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
		$this->assertSame( array(), HookStore::$hooks );
	}

	/**
	 * Filter returning false disables even when the setting is on.
	 */
	public function test_filter_can_disable() {
		add_filter(
			'wpcy_heartbeat_throttle',
			static function ( $enabled ) {
				unset( $enabled );
				return false;
			}
		);
		$config = $this->config( 'on' );
		$module = new HeartbeatModule( $config );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * Recovery mode is always off.
	 */
	public function test_recovery_mode_disables() {
		$config = new MapConfig(
			array(
				'connectivity.heartbeat' => 'on',
				'recovery_mode'          => true,
			)
		);
		$module = new HeartbeatModule( $config );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * Two heartbeat_received calls within 60s count once (lower-bound estimate).
	 */
	public function test_heartbeat_received_throttled_to_once_per_minute() {
		$seen                   = 0;
		HookStore::$screen_base = 'post.php';
		HookStore::$user_id     = 7;
		add_action(
			'wpcy_stats_increment',
			static function ( $counter, $n ) use ( &$seen ) {
				if ( 'heartbeat_saved' === $counter ) {
					$seen += (int) $n;
				}
			}
		);

		$module = $this->module( 'on' );
		$module->on_heartbeat_received();
		$module->on_heartbeat_received();

		$this->assertSame( 3, $seen );
	}

	/**
	 * Config bag.
	 *
	 * @param string $value on|off.
	 */
	private function config( string $value ): MapConfig {
		return new MapConfig(
			array(
				'connectivity.heartbeat' => $value,
				'recovery_mode'          => false,
			)
		);
	}

	/**
	 * Module under the given setting.
	 *
	 * @param string $value on|off.
	 */
	private function module( string $value ): HeartbeatModule {
		return new HeartbeatModule( $this->config( $value ) );
	}
}
