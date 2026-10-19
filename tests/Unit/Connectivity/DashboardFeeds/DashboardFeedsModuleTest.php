<?php
/**
 * Dashboard feeds: block widgets, empty feed URLs, events short-circuit.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\DashboardFeeds;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\DashboardFeeds\DashboardFeedsModule;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Tests\Unit\Connectivity\HookStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WP_Error;

require_once dirname( __DIR__ ) . '/wp-hook-stubs.php';
require_once dirname( __DIR__ ) . '/wp-error-stub.php';

/**
 * Dashboard feeds block|allow.
 */
class DashboardFeedsModuleTest extends TestCase {

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
	 * Block: drop dashboard_primary, empty feed filters, events URL errors.
	 */
	public function test_block_removes_widget_and_shorts_events() {
		$module = $this->module( 'block' );
		$config = $this->config( 'block' );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertTrue( $module->enabled( $config, $env ) );
		$module->register();
		$module->on_dashboard_setup();

		$this->assertSame(
			array(
				array(
					'id'      => 'dashboard_primary',
					'screen'  => 'dashboard',
					'context' => 'side',
				),
				array(
					'id'      => 'dashboard_primary',
					'screen'  => 'dashboard',
					'context' => 'normal',
				),
			),
			HookStore::$removed_boxes
		);
		$this->assertSame( '', $module->empty_feed( 'https://wordpress.org/news/feed/' ) );

		$blocked = $module->filter_pre_http_request( false, array(), 'https://api.wordpress.org/events/1.0/' );
		$this->assertInstanceOf( WP_Error::class, $blocked );
		$this->assertSame( 'wpcy_dashboard_feed_blocked', $blocked->get_error_code() );
	}

	/**
	 * Payment host URLs are not short-circuited.
	 */
	public function test_payment_url_is_not_blocked() {
		$module = $this->module( 'block' );
		$out    = $module->filter_pre_http_request( false, array(), 'https://api.stripe.com/v1/charges' );
		$this->assertFalse( $out );
	}

	/**
	 * Other api.wordpress.org paths are not short-circuited.
	 */
	public function test_other_wordpress_org_path_untouched() {
		$module = $this->module( 'block' );
		$out    = $module->filter_pre_http_request( false, array(), 'https://api.wordpress.org/plugins/update-check/1.1/' );
		$this->assertFalse( $out );
	}

	/**
	 * Allow: enabled() is false.
	 */
	public function test_allow_does_not_enable() {
		$config = $this->config( 'allow' );
		$module = new DashboardFeedsModule( $config );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
		$this->assertSame( array(), HookStore::$hooks );
	}

	/**
	 * Filter can force-unblock.
	 */
	public function test_filter_can_disable() {
		add_filter(
			'wpcy_block_dashboard_feeds',
			static function ( $block ) {
				unset( $block );
				return false;
			}
		);
		$config = $this->config( 'block' );
		$module = new DashboardFeedsModule( $config );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * Config bag.
	 *
	 * @param string $value block|allow.
	 */
	private function config( string $value ): MapConfig {
		return new MapConfig(
			array(
				'connectivity.dashboard_feeds' => $value,
				'recovery_mode'                => false,
			)
		);
	}

	/**
	 * Module under the given setting.
	 *
	 * @param string $value block|allow.
	 */
	private function module( string $value ): DashboardFeedsModule {
		return new DashboardFeedsModule( $this->config( $value ) );
	}
}
