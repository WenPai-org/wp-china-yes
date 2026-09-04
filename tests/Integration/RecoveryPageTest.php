<?php
/**
 * Recovery page form POST (PHPUnit Integration suite).
 *
 * WP-env / Studio assertions live in tests/integration-recovery.sh.
 * This file is skipped unless WordPress admin APIs are loaded.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\RecoveryPage;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Rest\RecoveryActions;

/**
 * Placeholder for wp-env. Unit suite covers the same form POST.
 */
class RecoveryPageTest extends TestCase {

	/**
	 * Skip when WordPress is not loaded (composer test:unit).
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'ABSPATH' ) || ! function_exists( 'add_submenu_page' ) || ! function_exists( 'check_admin_referer' ) ) {
			$this->markTestSkipped( 'Requires WordPress (wp-env / Studio). See tests/integration-recovery.sh.' );
		}
	}

	/**
	 * Applying disable_rewrites persists recovery_mode on wpcy_settings.
	 */
	public function test_disable_rewrites_sets_recovery_mode_option() {
		$repo    = new Repository();
		$actions = new RecoveryActions( $repo );
		$actions->apply( RecoveryActions::DISABLE_REWRITES );

		$stored = get_option( Schema::SETTINGS, array() );
		$this->assertIsArray( $stored );
		$this->assertTrue( ! empty( $stored['recovery_mode'] ) );
		$this->assertSame( RecoveryPage::SLUG, 'wpcy-recovery' );
	}
}
