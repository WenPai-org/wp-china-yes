<?php
/**
 * Unit tests for ProvidersView card rendering and source vocabulary guardrails.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\ProvidersView;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Verifies ProvidersView::render_card() in normal, locked, and White-Label modes,
 * plus inline-style and forbidden-vocabulary checks across PHP + JS sources.
 */
class ProvidersViewTest extends TestCase {

	/**
	 * Load vendored wenpai-admin-ui helpers once and reset test state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AdminStore::reset();
		self::ensure_kit_loaded();
	}

	/**
	 * Load minimal WP escaping helpers + vendored wenpai-admin-ui classes for unit process.
	 *
	 * @return void
	 */
	private static function ensure_kit_loaded(): void {
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
	 * Capture HTML output of ProvidersView::render_card().
	 *
	 * @param array<string, mixed> $wl     White-Label state.
	 * @param bool                 $locked Network-locked flag.
	 * @return string
	 */
	private function render_card( array $wl = array(), bool $locked = false ): string {
		ob_start();
		ProvidersView::render_card( $wl, $locked );
		return (string) ob_get_clean();
	}

	/**
	 * Normal unlocked mode renders both preset rows, collapsible connect panel, and zero inline styles.
	 *
	 * @return void
	 */
	public function test_normal_mode_renders_presets_and_collapsible_panel(): void {
		$html = $this->render_card(
			array(
				'enabled'    => false,
				'hide_links' => false,
			),
			false
		);

		$this->assertStringContainsString( '商业扩展授权与更新源', $html );
		$this->assertStringContainsString( 'data-wpcy-providers-card="1"', $html );
		$this->assertStringContainsString( 'data-wpcy-locked="0"', $html );
		$this->assertStringContainsString( '薇晓朵商城', $html );
		$this->assertStringContainsString( '文派集市', $html );
		$this->assertStringContainsString( '未连接', $html );
		$this->assertStringContainsString( '配置连接', $html );
		$this->assertStringContainsString( '购买邮箱', $html );
		$this->assertStringContainsString( '授权密钥', $html );
		$this->assertStringContainsString( '验证并连接', $html );
		$this->assertStringContainsString( '检查更新 ↗', $html );

		foreach ( array( 'weixiaoduo-mall', 'wenpai-marketplace' ) as $provider_id ) {
			$this->assertStringContainsString( 'data-wpcy-prov-actions="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-toggle="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-panel="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-status="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-form="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-email="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-key="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-connect="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-connected="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-test="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-products-btn="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-updates="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-disconnect="' . $provider_id . '"', $html );
			$this->assertStringContainsString( 'data-wpcy-prov-products="' . $provider_id . '"', $html );
		}

		$this->assertStringNotContainsString( '即将开放', $html );
		$this->assertStringNotContainsString( 'style="', $html );
		$this->assertStringNotContainsString( ' disabled', $html );
	}

	/**
	 * Network-locked subsite mode marks card locked and disables toggle, inputs, and write actions.
	 *
	 * @return void
	 */
	public function test_locked_subsite_mode_disables_controls(): void {
		$html = $this->render_card( array(), true );

		$this->assertStringContainsString( 'data-wpcy-locked="1"', $html );
		foreach ( array( 'weixiaoduo-mall', 'wenpai-marketplace' ) as $provider_id ) {
			$this->assertMatchesRegularExpression( '/<button[^>]*data-wpcy-prov-toggle="' . $provider_id . '"[^>]*disabled|<button[^>]*disabled[^>]*data-wpcy-prov-toggle="' . $provider_id . '"/', $html );
			$this->assertMatchesRegularExpression( '/<input[^>]*data-wpcy-prov-email="' . $provider_id . '"[^>]*disabled/', $html );
			$this->assertMatchesRegularExpression( '/<input[^>]*data-wpcy-prov-key="' . $provider_id . '"[^>]*disabled/', $html );
		}
		$this->assertStringContainsString( 'wenpai-field field wenpai-field--row is-disabled', $html );
		$this->assertStringNotContainsString( 'style="', $html );
	}

	/**
	 * White-Label enabled with hide_links => true suppresses the entire card cleanly.
	 *
	 * @return void
	 */
	public function test_whitelabel_hide_links_suppresses_card(): void {
		$suppressed = $this->render_card(
			array(
				'enabled'    => true,
				'hide_links' => true,
			),
			false
		);
		$this->assertSame( '', $suppressed );

		// When enabled => true but hide_links => false, card still renders.
		$visible = $this->render_card(
			array(
				'enabled'    => true,
				'hide_links' => false,
			),
			false
		);
		$this->assertStringContainsString( 'data-wpcy-providers-card="1"', $visible );
	}

	/**
	 * Source files contain zero inline style attributes and zero forbidden terms.
	 *
	 * @return void
	 */
	public function test_sources_have_zero_inline_styles_and_zero_forbidden_words(): void {
		$root     = dirname( __DIR__, 3 );
		$php_file = $root . '/src/Admin/ProvidersView.php';
		$js_file  = $root . '/assets/js/wpcy-providers.js';

		$this->assertFileExists( $php_file );
		$this->assertFileExists( $js_file );

		$php_src = (string) file_get_contents( $php_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading local source file.
		$js_src  = (string) file_get_contents( $js_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading local source file.

		$this->assertStringNotContainsString( 'style="', $php_src );
		$this->assertStringNotContainsString( 'style="', $js_src );

		$pattern = '/遥测|匿名数据|opt-in|entitlement|套餐|\bPro\b/u';
		$this->assertDoesNotMatchRegularExpression( $pattern, $php_src );
		$this->assertDoesNotMatchRegularExpression( $pattern, $js_src );
	}
}
