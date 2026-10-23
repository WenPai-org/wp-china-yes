<?php
/**
 * Admin rule feeds: the default verifier rejects test-key signatures without crashing.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\Announcements\AnnouncementsModule;
use WenPai\ChinaYes\Admin\ElementHide\ElementHideModule;
use WenPai\ChinaYes\Admin\NoticeControl\NoticeControlModule;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Support\LegacyFixtureKey;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Element hide, notice control and announcements fail closed in non-test mode.
 */
class TrustAnchorAdminTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AdminStore::reset();
		OptionStore::reset();
		RestError::reset();
	}

	/**
	 * Element hide: test-key-signed rules are rejected by default, accepted in test mode.
	 */
	public function test_element_hide_rejects_test_key_rules_by_default() {
		$source = $this->fixture( 'element-hide/sample.json' );
		$module = new ElementHideModule( new Repository(), $source );
		$this->assertNull( $module->refresh() );
		$this->assertNull( $module->cached_document() );

		$test_mode = new ElementHideModule( new Repository(), $source, null, null, LegacyFixtureKey::verifier() );
		$this->assertIsArray( $test_mode->refresh() );
	}

	/**
	 * Notice control: test-key-signed rules are rejected by default, accepted in test mode.
	 */
	public function test_notice_control_rejects_test_key_rules_by_default() {
		$source = $this->fixture( 'notice-rules/sample.json' );
		$module = new NoticeControlModule( new Repository(), $source );
		$this->assertNull( $module->refresh() );
		$this->assertNull( $module->cached_document() );

		$test_mode = new NoticeControlModule( new Repository(), $source, null, null, LegacyFixtureKey::verifier() );
		$this->assertIsArray( $test_mode->refresh() );
	}

	/**
	 * Announcements: test-key-signed feed is rejected by default, accepted in test mode.
	 */
	public function test_announcements_reject_test_key_feed_by_default() {
		$source = $this->fixture( 'announcements/sample.json' );
		$module = new AnnouncementsModule( new Repository(), $source );
		$this->assertNull( $module->refresh() );
		$payload = $module->payload();
		$this->assertNull( $payload['generated_at'] );
		$this->assertSame( array(), $payload['items'] );

		$test_mode = new AnnouncementsModule( new Repository(), $source, null, LegacyFixtureKey::verifier() );
		$this->assertIsArray( $test_mode->refresh() );
	}

	/**
	 * Absolute fixture path.
	 *
	 * @param string $name Path under tests/fixtures/.
	 */
	private function fixture( string $name ): string {
		return dirname( __DIR__, 2 ) . '/fixtures/' . $name;
	}
}
