<?php
/**
 * Preset ids and 404 for unknown.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Providers\Presets;

require_once __DIR__ . '/wp-providers-stubs.php';

/**
 * Two frozen ids; unknown get() is null.
 */
class PresetsTest extends TestCase {

	/**
	 * Enum is weixiaoduo-mall and wenpai-marketplace.
	 */
	public function test_ids_are_the_two_presets() {
		$this->assertSame(
			array( 'weixiaoduo-mall', 'wenpai-marketplace' ),
			Presets::ids()
		);
	}

	/**
	 * Mall is available with a frozen origin.
	 */
	public function test_weixiaoduo_mall_is_available() {
		$row = Presets::get( 'weixiaoduo-mall' );
		$this->assertIsArray( $row );
		$this->assertSame( 'weixiaoduo-mall', $row['id'] );
		$this->assertSame( '薇晓朵商城', $row['name'] );
		$this->assertSame( 'available', $row['status'] );
		$this->assertSame( 'https://mall.weixiaoduo.com', $row['api_url'] );
	}

	/**
	 * Marketplace is coming_soon with empty api_url.
	 */
	public function test_wenpai_marketplace_is_coming_soon() {
		$row = Presets::get( 'wenpai-marketplace' );
		$this->assertIsArray( $row );
		$this->assertSame( 'wenpai-marketplace', $row['id'] );
		$this->assertSame( '文派集市', $row['name'] );
		$this->assertSame( 'coming_soon', $row['status'] );
		$this->assertSame( '', $row['api_url'] );
	}

	/**
	 * Unknown id is null (REST maps this to 404).
	 */
	public function test_unknown_id_is_null() {
		$this->assertNull( Presets::get( 'custom-bridge-api' ) );
		$this->assertNull( Presets::get( '' ) );
	}
}
