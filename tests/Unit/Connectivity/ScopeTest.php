<?php
/**
 * Connectivity\Scope::current() D2 order: CLI, cron, is_admin, REST+nonce, frontend.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\Scope;

require_once __DIR__ . '/scope-function-stubs.php';

/**
 * Frozen detection order from M-SCOPE-1.
 */
class ScopeTest extends TestCase {

	/**
	 * Reset harness flags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		ScopeHarness::reset();
	}

	/**
	 * Default request with no WP flags is frontend.
	 */
	public function test_default_is_frontend() {
		$this->assertSame( Scope::FRONTEND, Scope::current() );
	}

	/**
	 * Admin flag (including admin-ajax) is admin.
	 */
	public function test_is_admin_is_admin() {
		ScopeHarness::$is_admin = true;
		$this->assertSame( Scope::ADMIN, Scope::current() );
	}

	/**
	 * Cron wins over is_admin so a dashboard-spawned cron stays frontend.
	 */
	public function test_cron_before_is_admin_is_frontend() {
		ScopeHarness::$cron     = true;
		ScopeHarness::$is_admin = true;
		$this->assertSame( Scope::FRONTEND, Scope::current() );
	}

	/**
	 * REST with a valid wp_rest nonce and /wp-admin referer is admin.
	 */
	public function test_rest_with_nonce_from_wp_admin_is_admin() {
		ScopeHarness::$rest         = true;
		ScopeHarness::$nonce_ok     = true;
		ScopeHarness::$referer      = 'https://example.test/wp-admin/index.php';
		$_SERVER['HTTP_X_WP_NONCE'] = 'rest-nonce';

		$this->assertSame( Scope::ADMIN, Scope::current() );
	}

	/**
	 * Frontend REST (no nonce / no wp-admin referer) is frontend.
	 */
	public function test_frontend_rest_is_frontend() {
		ScopeHarness::$rest         = true;
		ScopeHarness::$nonce_ok     = false;
		ScopeHarness::$referer      = 'https://example.test/';
		$_SERVER['HTTP_X_WP_NONCE'] = 'rest-nonce';

		$this->assertSame( Scope::FRONTEND, Scope::current() );
	}

	/**
	 * REST nonce without /wp-admin referer is frontend.
	 */
	public function test_rest_nonce_without_admin_referer_is_frontend() {
		ScopeHarness::$rest         = true;
		ScopeHarness::$nonce_ok     = true;
		ScopeHarness::$referer      = 'https://example.test/shop/';
		$_SERVER['HTTP_X_WP_NONCE'] = 'rest-nonce';

		$this->assertSame( Scope::FRONTEND, Scope::current() );
	}

	/**
	 * WP-CLI is admin. Isolated because WP_CLI is a constant.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_cli_is_admin() {
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}
		$this->assertSame( Scope::ADMIN, Scope::current() );
	}

	/**
	 * WP-Cron (DOING_CRON) is frontend. Isolated because DOING_CRON is a constant.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_doing_cron_is_frontend() {
		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}
		ScopeHarness::$is_admin = true;
		$this->assertSame( Scope::FRONTEND, Scope::current() );
	}
}
