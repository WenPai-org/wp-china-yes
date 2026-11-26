<?php
/**
 * Tests for W7 admin signed manifest status card rendering and copy/style constraints.
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\ManifestStatusCard;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Connectivity\PublicAssets\Manifest;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Verifies W7 card fields, refresh control, zero inline styles, and zero forbidden copy.
 */
class ManifestStatusCardTest extends TestCase {

	/**
	 * Load admin kit helpers once and reset stores.
	 */
	protected function setUp(): void {
		parent::setUp();
		AdminStore::reset();
		OptionStore::reset();

		$kit_root = dirname( __DIR__, 3 ) . '/lib/wenpai-admin-ui';
		if ( ! function_exists( '\esc_html__' ) ) {
			eval( // phpcs:ignore Squiz.PHP.Eval.Discouraged -- isolated global function shims in unit suite.
				'function esc_html__($s){return htmlspecialchars((string)$s,ENT_QUOTES,"UTF-8");}
				function esc_attr__($s){return htmlspecialchars((string)$s,ENT_QUOTES,"UTF-8");}
				function esc_url($s){return htmlspecialchars((string)$s,ENT_QUOTES,"UTF-8");}
				function esc_url_raw($s){return (string)$s;}
				function esc_textarea($s){return htmlspecialchars((string)$s,ENT_QUOTES,"UTF-8");}
				function absint($v){return abs((int)$v);}
				function wp_parse_args($a,$d=array()){return array_merge((array)$d,(array)$a);}
				function wp_strip_all_tags($s){return trim(strip_tags((string)$s));}
				function sanitize_text_field($s){return trim(strip_tags((string)$s));}
				function wp_unslash($s){return $s;}
				function wp_kses($s){return (string)$s;}
				function wp_kses_post($s){return (string)$s;}
				function wp_kses_allowed_html(){return array();}
				function checked($a,$b=true,$e=true){$r=(bool)$a===(bool)$b?" checked=\"checked\"":"";if($e){echo $r;}return $r;}
				function selected($a,$b,$e=true){$r=(string)$a===(string)$b?" selected=\"selected\"":"";if($e){echo $r;}return $r;}
				function disabled($a,$b=true,$e=true){$r=(bool)$a===(bool)$b?" disabled=\"disabled\"":"";if($e){echo $r;}return $r;}'
			);
		}
		foreach ( array( 'icons', 'menu', 'ui', 'action', 'settings', 'tools', 'shell' ) as $part ) {
			require_once $kit_root . '/php/class-wenpai-admin-' . $part . '.php';
		}
	}

	/**
	 * Status card displays version, keyId, source, switch_reason, and refresh button with no inline styles or forbidden words.
	 */
	public function test_renders_manifest_fields_cleanly_without_inline_styles_or_forbidden_copy(): void {
		$now      = time();
		$state    = array(
			'active_source' => 'primary',
			'source_url'    => Manifest::PRIMARY_URL,
			'version'       => 2026112601,
			'issued_at'     => '2026-11-26T08:00:00Z',
			'key_id'        => 'admincdn-test-2026-11',
			'stored_at'     => $now - 180,
			'switch_reason' => 'class_down:npm',
			'last_error'    => '',
			'manifest'      => array(
				'killSwitch' => false,
				'classes'    => array(),
			),
		);
		$manifest = new Manifest(
			new Repository(),
			null,
			null,
			null,
			static function () use ( $state ) {
				return $state;
			}
		);

		$html = ManifestStatusCard::render_html( $manifest, true );

		$this->assertStringContainsString( 'data-wpcy-manifest-card="1"', $html );
		$this->assertStringContainsString( '#2026112601', $html );
		$this->assertStringContainsString( 'admincdn-test-2026-11', $html );
		$this->assertStringContainsString( 'class_down:npm', $html );
		$this->assertStringContainsString( 'data-wpcy-action="manifest-refresh"', $html );
		$this->assertStringContainsString( 'data-wpcy-action="client-probe-toggle"', $html );
		$this->assertStringContainsString( '帮助文派判断镜像可用性', $html );
		$this->assertStringContainsString( 'checked="checked"', $html );
		$this->assertSame( 0, preg_match( '/\bstyle\s*=/i', $html ) );
		$this->assertSame( 0, preg_match( '/遥测|匿名数据|opt-in|entitlement|套餐|\bPro\b/', $html ) );

		$html_off = ManifestStatusCard::render_html( $manifest, false );
		$this->assertStringContainsString( 'data-wpcy-action="client-probe-toggle"', $html_off );
		$this->assertStringNotContainsString( 'checked="checked"', $html_off );
	}
}
