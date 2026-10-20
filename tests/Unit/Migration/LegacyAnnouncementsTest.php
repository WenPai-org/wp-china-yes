<?php
/**
 * 3.x legacy option with an announcements key: not carried into 4.0.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Migration\LegacyReader;
use WenPai\ChinaYes\Migration\Mappers;
use WenPai\ChinaYes\Migration\Runner;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-option-stubs.php';

/**
 * Mappers / Runner never emit the removed root.
 */
class LegacyAnnouncementsTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
	}

	/**
	 * Mappers::map_site ignores the key and the document lacks it.
	 */
	public function test_mappers_ignore_announcements() {
		$report = ( new Mappers() )->map_site( $this->legacy() );

		$this->assertContains( 'announcements', $report->ignored() );
		$this->assertNotContains( 'announcements', $report->kept() );
		$this->assertArrayNotHasKey( 'announcements', $report->settings() );
		$this->assertSame( 'off', $report->settings()['connectivity']['wordpress_org'] );
	}

	/**
	 * Single site: Runner::execute writes wpcy_settings without it.
	 */
	public function test_runner_execute_single_site_drops_announcements() {
		OptionStore::$options[ LegacyReader::OPTION ] = $this->legacy();

		$report = ( new Runner() )->execute();

		$this->assertContains( 'announcements', $report->ignored() );
		$stored = OptionStore::$options[ Schema::SETTINGS ] ?? null;
		$this->assertIsArray( $stored );
		$this->assertArrayNotHasKey( 'announcements', $stored );
	}

	/**
	 * Single site: an existing 4.0 document with the legacy root is replaced
	 * by migration output without it.
	 */
	public function test_runner_execute_replaces_stored_legacy_root() {
		OptionStore::$options[ LegacyReader::OPTION ] = $this->legacy();
		$existing                                     = Defaults::settings();
		$existing['announcements']                    = array( 'dismissed' => array( 'old' ) );
		OptionStore::$options[ Schema::SETTINGS ]     = $existing;

		( new Runner() )->execute();

		$this->assertArrayNotHasKey( 'announcements', OptionStore::$options[ Schema::SETTINGS ] );
	}

	/**
	 * Multisite: Runner::execute writes the network document without it.
	 */
	public function test_runner_execute_multisite_drops_announcements() {
		OptionStore::$multisite                            = true;
		OptionStore::$site_options[ LegacyReader::OPTION ] = $this->legacy();

		$report = ( new Runner() )->execute();

		$this->assertContains( 'announcements', $report->ignored() );
		$stored = OptionStore::$site_options[ Schema::NETWORK_SETTINGS ] ?? null;
		$this->assertIsArray( $stored );
		$this->assertArrayNotHasKey( 'announcements', $stored );
	}

	/**
	 * Synthetic 3.x `wp_china_yes` carrying an announcements key.
	 *
	 * @return array<string, mixed>
	 */
	private function legacy(): array {
		return array(
			'store'         => 'off',
			'announcements' => array( 'dismissed' => array( 'a1' ) ),
		);
	}
}
