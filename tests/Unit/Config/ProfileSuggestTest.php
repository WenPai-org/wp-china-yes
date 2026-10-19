<?php
/**
 * D4 profile suggestion rules. Response never includes IP.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\ProfileSuggest;

/**
 * Four D4 classes plus geo failure.
 */
class ProfileSuggestTest extends TestCase {

	/**
	 * Server CN → domestic regardless of admin hints.
	 */
	public function test_server_cn_suggests_domestic() {
		$engine = new ProfileSuggest(
			static function () {
				return array( 'country' => 'CN' );
			}
		);
		$out    = $engine->suggest( 'en-US', 'America/New_York' );

		$this->assertSame( 'domestic', $out['suggestion'] );
		$this->assertSame( 'CN', $out['signals']['server_country'] );
		$this->assertArrayNotHasKey( 'ip', $out );
		$this->assertArrayNotHasKey( 'ip', $out['signals'] );
	}

	/**
	 * Server abroad + admin zh-CN → crossborder.
	 */
	public function test_abroad_and_admin_zh_cn_suggests_crossborder() {
		$engine = new ProfileSuggest(
			static function () {
				return array( 'country' => 'US' );
			}
		);
		$out    = $engine->suggest( 'zh-CN', 'America/Los_Angeles' );

		$this->assertSame( 'crossborder', $out['suggestion'] );
		$this->assertSame( 'US', $out['signals']['server_country'] );
		$this->assertSame( 'zh-CN', $out['signals']['admin_locale_hint'] );
	}

	/**
	 * Server abroad + admin Asia/Shanghai → crossborder.
	 */
	public function test_abroad_and_admin_shanghai_suggests_crossborder() {
		$engine = new ProfileSuggest(
			static function () {
				return array( 'country' => 'DE' );
			}
		);
		$out    = $engine->suggest( 'en-US', 'Asia/Shanghai' );

		$this->assertSame( 'crossborder', $out['suggestion'] );
	}

	/**
	 * Server abroad + admin not inland → null. Does not suggest mixed.
	 */
	public function test_abroad_and_foreign_admin_suggests_null() {
		$engine = new ProfileSuggest(
			static function () {
				return array( 'country' => 'US' );
			}
		);
		$out    = $engine->suggest( 'en-US', 'America/New_York' );

		$this->assertNull( $out['suggestion'] );
	}

	/**
	 * Geo failure → suggestion null, server_country null.
	 */
	public function test_geo_failure_suggests_null() {
		$engine = new ProfileSuggest(
			static function () {
				return null;
			}
		);
		$out    = $engine->suggest( 'zh-CN', 'Asia/Shanghai' );

		$this->assertNull( $out['suggestion'] );
		$this->assertNull( $out['signals']['server_country'] );
		$this->assertArrayNotHasKey( 'ip', $out );
		$this->assertArrayNotHasKey( 'ip', $out['signals'] );
	}

	/**
	 * HK is not CN.
	 */
	public function test_hong_kong_is_not_domestic() {
		$engine = new ProfileSuggest(
			static function () {
				return array( 'country' => 'HK' );
			}
		);
		$out    = $engine->suggest( 'zh-CN', 'Asia/Shanghai' );

		$this->assertSame( 'crossborder', $out['suggestion'] );
		$this->assertSame( 'HK', $out['signals']['server_country'] );
	}

	/**
	 * Underscore locale zh_CN counts as inland admin.
	 */
	public function test_zh_cn_underscore_is_inland() {
		$engine = new ProfileSuggest(
			static function () {
				return array( 'country' => 'FR' );
			}
		);
		$out    = $engine->suggest( 'zh_CN', null );

		$this->assertSame( 'crossborder', $out['suggestion'] );
	}

	/**
	 * Locale / timezone longer than 64 characters are dropped from signals.
	 */
	public function test_locale_and_timezone_over_64_are_null() {
		$engine = new ProfileSuggest(
			static function () {
				return array( 'country' => 'US' );
			}
		);
		$long   = str_repeat( 'a', 65 );
		$out    = $engine->suggest( $long, $long );

		$this->assertNull( $out['signals']['admin_locale_hint'] );
		$this->assertNull( $out['signals']['admin_tz_hint'] );
		$this->assertNull( $out['suggestion'] );
	}
}
