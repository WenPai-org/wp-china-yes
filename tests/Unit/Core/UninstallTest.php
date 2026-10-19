<?php
/**
 * Cleanup done by uninstall.php, single site and multisite.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * Runs uninstall.php in a child PHP process against recording stubs.
 */
class UninstallTest extends TestCase {

	/**
	 * Run the harness and decode the recorded calls.
	 *
	 * @param string $mode single, multisite or no-constant.
	 * @return array<int, array<int, mixed>>
	 */
	private function run_uninstall( string $mode ): array {
		$harness = dirname( __DIR__, 2 ) . '/fixtures/uninstall-harness.php';
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $harness ) . ' ' . escapeshellarg( $mode );
		$output  = array();
		$status  = 0;
		exec( $command, $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- isolated child process so uninstall.php can exit and define constants.
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$calls = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $calls );
		return $calls;
	}

	/**
	 * Option names deleted on one blog.
	 *
	 * @param array<int, array<int, mixed>> $calls Calls.
	 * @param int                           $blog  Blog id.
	 * @return string[]
	 */
	private function deleted_options( array $calls, int $blog ): array {
		$names = array();
		foreach ( $calls as $call ) {
			if ( 'delete_option' === $call[0] && $blog === $call[1] ) {
				$names[] = $call[2];
			}
		}
		return $names;
	}

	/**
	 * Single site drops the wizard redirect flag with the provider options.
	 */
	public function test_single_site_deletes_onboarding_flag() {
		$calls   = $this->run_uninstall( 'single' );
		$deleted = $this->deleted_options( $calls, 1 );

		$this->assertContains( 'wpcy_onboarding_redirected', $deleted );
		$this->assertContains( 'wpcy_providers', $deleted );
		$this->assertNotContains( 'wp_china_yes', $deleted );
	}

	/**
	 * Multisite drops the flag on every site of the network.
	 */
	public function test_multisite_deletes_onboarding_flag_on_every_site() {
		$calls = $this->run_uninstall( 'multisite' );

		foreach ( array( 1, 2, 3 ) as $blog ) {
			$deleted = $this->deleted_options( $calls, $blog );
			$this->assertContains( 'wpcy_onboarding_redirected', $deleted, 'blog ' . $blog );
			$this->assertContains( 'wpcy_providers', $deleted, 'blog ' . $blog );
			$this->assertNotContains( 'wp_china_yes', $deleted, 'blog ' . $blog );
		}
	}

	/**
	 * Without WP_UNINSTALL_PLUGIN nothing is deleted.
	 */
	public function test_requires_uninstall_constant() {
		$this->assertSame( array(), $this->run_uninstall( 'no-constant' ) );
	}
}
