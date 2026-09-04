<?php
/**
 * CLI JSON schema and Site Health section (PHPUnit Integration suite).
 *
 * wp-env / Studio assertions live in tests/integration-cli.sh. This file
 * documents the contract and is skipped unless WP_CLI is loaded.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Placeholder for wp-env. Unit suite covers the same JSON keys.
 */
class CliTest extends TestCase {

	/**
	 * Skip when WordPress is not loaded (composer test:unit).
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'ABSPATH' ) || ! function_exists( 'add_filter' ) || ! class_exists( '\WP_CLI' ) ) {
			$this->markTestSkipped( 'Requires WordPress and WP-CLI (wp-env / Studio). See tests/integration-cli.sh.' );
		}
	}

	/**
	 * Status payload always has a targets array.
	 */
	public function test_status_has_targets_array() {
		$checker = new \WenPai\ChinaYes\Diagnostics\Checker();
		$payload = \WenPai\ChinaYes\Cli\StatusCommand::build_payload( $checker );
		$this->assertArrayHasKey( 'targets', $payload );
		$this->assertIsArray( $payload['targets'] );
	}
}
