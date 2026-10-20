<?php
/**
 * Legacy announcements root is not written back by recovery or site PUT.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Rest\DocumentWriter;
use WenPai\ChinaYes\Rest\RecoveryActions;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-rest-stubs.php';
require_once __DIR__ . '/wp-rest-action-stub.php';

/**
 * Stored legacy documents lose announcements on the next save.
 */
class LegacyAnnouncementsWriteBackTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		OptionStore::reset();
		RestError::reset();
	}

	/**
	 * Single site: a recovery action on a legacy wpcy_settings clears the root.
	 */
	public function test_recovery_action_clears_legacy_root_single_site() {
		OptionStore::$options[ Schema::SETTINGS ] = $this->legacy( Defaults::settings() );

		$this->assertTrue( ( new RecoveryActions( new Repository() ) )->apply( RecoveryActions::DISABLE_REWRITES ) );

		$stored = get_option( Schema::SETTINGS );
		$this->assertIsArray( $stored );
		$this->assertTrue( $stored['recovery_mode'] );
		$this->assertArrayNotHasKey( 'announcements', $stored );
	}

	/**
	 * Multisite: a recovery action on legacy site overrides clears the root.
	 */
	public function test_recovery_action_clears_legacy_root_in_site_overrides() {
		$this->multisite( false );
		OptionStore::$options[ Schema::SITE_OVERRIDES ] = $this->legacy( array( 'schema_version' => Schema::VERSION ) );

		$this->assertTrue( ( new RecoveryActions( new Repository() ) )->apply( RecoveryActions::EXIT ) );

		$overrides = get_option( Schema::SITE_OVERRIDES );
		$this->assertIsArray( $overrides );
		$this->assertFalse( $overrides['recovery_mode'] );
		$this->assertArrayNotHasKey( 'announcements', $overrides );
	}

	/**
	 * Unlocked subsite: legacy overrides with announcements lose the root after put_site.
	 */
	public function test_put_site_drops_legacy_root_from_site_overrides() {
		$this->multisite( true );
		$legacy                 = $this->legacy( array( 'schema_version' => Schema::VERSION ) );
		$legacy['connectivity'] = array( 'heartbeat' => 'off' );
		OptionStore::$options[ Schema::SITE_OVERRIDES ] = $legacy;

		$result = $this->writer()->put_site( array( 'connectivity' => array( 'heartbeat' => 'on' ) ) );

		$this->assertTrue( $result );
		$overrides = get_option( Schema::SITE_OVERRIDES );
		$this->assertIsArray( $overrides );
		$this->assertSame( 'on', $overrides['connectivity']['heartbeat'] );
		$this->assertArrayNotHasKey( 'announcements', $overrides );
	}

	/**
	 * Locked subsite: recovery_mode put_site still rewrites overrides without the root.
	 */
	public function test_locked_put_site_drops_legacy_root_from_site_overrides() {
		$this->multisite( false );
		OptionStore::$options[ Schema::SITE_OVERRIDES ] = $this->legacy( array( 'schema_version' => Schema::VERSION ) );

		$result = $this->writer()->put_site( array( 'recovery_mode' => true ) );

		$this->assertTrue( $result );
		$overrides = get_option( Schema::SITE_OVERRIDES );
		$this->assertIsArray( $overrides );
		$this->assertTrue( $overrides['recovery_mode'] );
		$this->assertArrayNotHasKey( 'announcements', $overrides );
	}

	/**
	 * Add the removed announcements root to a document.
	 *
	 * @param array<string, mixed> $doc Document.
	 * @return array<string, mixed>
	 */
	private function legacy( array $doc ): array {
		$doc['announcements'] = array( 'dismissed' => array( 'a1' ) );
		return $doc;
	}

	/**
	 * Switch to multisite with a stored network document.
	 *
	 * @param bool $allow allow_site_override.
	 * @return void
	 */
	private function multisite( bool $allow ): void {
		OptionStore::$multisite         = true;
		$network                        = Defaults::network_settings();
		$network['allow_site_override'] = $allow;
		OptionStore::$site_options[ Schema::NETWORK_SETTINGS ] = $network;
	}

	/**
	 * Writer under test.
	 *
	 * @return DocumentWriter
	 */
	private function writer(): DocumentWriter {
		return new DocumentWriter( new Repository() );
	}
}
