<?php
/**
 * Unit tests for AppsView SSR markup, White-Label suppression, and source hygiene.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\AppsView;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Verifies AppsView::render_host(), AppsView::render_services_card(), and zero-inline-style / forbidden-copy guards.
 */
class AppsViewTest extends TestCase {

	/**
	 * Load kit PHP helpers once before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AdminStore::reset();
		self::ensure_kit_loaded();
	}

	/**
	 * Load minimal WP escaping helpers and vendored wenpai-admin-ui classes for unit suite.
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
	 * Host screen renders breadcrumb back link, card container, polite status region, stage, and zero inline styles.
	 *
	 * @return void
	 */
	public function test_render_host_outputs_expected_kit_markup_without_inline_styles(): void {
		ob_start();
		AppsView::render_host( 'slug-helper', 'admin.php?page=wpcy-services' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'wenpai-subhead', $html );
		$this->assertStringContainsString( '返回服务列表', $html );
		$this->assertStringContainsString( 'admin.php?page=wpcy-services', $html );
		$this->assertStringContainsString( '轻便小工具', $html );
		$this->assertStringContainsString( '在隔离沙箱中运行文派生态轻便工具。', $html );
		$this->assertStringContainsString( 'class="wenpai-stack"', $html );
		$this->assertStringContainsString( 'data-wpcy-screen="app-host"', $html );
		$this->assertStringContainsString( 'data-wpcy-app-id="slug-helper"', $html );
		$this->assertStringContainsString( 'data-wpcy-app-card="1"', $html );
		$this->assertStringContainsString( 'role="status" aria-live="polite" class="meta" data-wpcy-app-status="1"', $html );
		$this->assertStringContainsString( '正在校验签名目录与运行环境…', $html );
		$this->assertStringContainsString( '<div data-wpcy-app-stage="1"></div>', $html );
		$this->assertStringNotContainsString( 'style="', $html );
	}

	/**
	 * Services card renders accessible section, polite live region, and grid mount when White-Label allows links.
	 *
	 * @return void
	 */
	public function test_render_services_card_outputs_accessible_section_and_respects_whitelabel(): void {
		ob_start();
		AppsView::render_services_card(
			array(
				'enabled'    => false,
				'hide_links' => true,
			),
			'admin.php?page=wpcy-services'
		);
		$default_html = (string) ob_get_clean();

		$this->assertStringContainsString( 'aria-labelledby="wpcy-apps-heading"', $default_html );
		$this->assertStringContainsString( 'data-wpcy-apps-section="1"', $default_html );
		$this->assertStringContainsString( 'data-wpcy-services-url="admin.php?page=wpcy-services"', $default_html );
		$this->assertStringContainsString( '轻便工具箱', $default_html );
		$this->assertStringContainsString( 'id="wpcy-apps-heading" role="status" aria-live="polite" class="meta" data-wpcy-apps-live="1"', $default_html );
		$this->assertStringContainsString( '绑定站点后自动同步文派云端签名的轻便运维与内容小工具，并在概览页快捷工具区点亮入口。', $default_html );
		$this->assertStringContainsString( '<div data-wpcy-apps-grid="1"></div>', $default_html );
		$this->assertStringNotContainsString( 'style="', $default_html );

		// White-Label enabled without hide_links still renders the card.
		ob_start();
		AppsView::render_services_card(
			array(
				'enabled'    => true,
				'hide_links' => false,
			),
			'admin.php?page=wpcy-services'
		);
		$wl_open_html = (string) ob_get_clean();
		$this->assertStringContainsString( 'data-wpcy-apps-section="1"', $wl_open_html );

		// White-Label enabled with hide_links => true suppresses output completely.
		ob_start();
		AppsView::render_services_card(
			array(
				'enabled'    => true,
				'hide_links' => true,
			),
			'admin.php?page=wpcy-services'
		);
		$suppressed = (string) ob_get_clean();
		$this->assertSame( '', $suppressed );
	}

	/**
	 * Static source scan across AppsView.php and wpcy-apps.js enforces zero inline styles,
	 * strict iframe sandbox tokens, and zero forbidden words.
	 *
	 * @return void
	 */
	public function test_source_files_contain_no_inline_styles_and_no_forbidden_words(): void {
		$root      = dirname( __DIR__, 3 );
		$php_path  = $root . '/src/Admin/AppsView.php';
		$js_path   = $root . '/assets/js/wpcy-apps.js';
		$php_src   = (string) file_get_contents( $php_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file read.
		$js_src    = (string) file_get_contents( $js_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source file read.
		$forbidden = '/遥测|匿名数据|opt-in|entitlement|套餐|\bPro\b/u';

		$this->assertNotEmpty( $php_src );
		$this->assertNotEmpty( $js_src );

		$this->assertStringNotContainsString( 'style="', $php_src );
		$this->assertStringNotContainsString( 'style="', $js_src );
		$this->assertDoesNotMatchRegularExpression( '/\.style\b/', $js_src );

		$this->assertDoesNotMatchRegularExpression( $forbidden, $php_src );
		$this->assertDoesNotMatchRegularExpression( $forbidden, $js_src );

		$this->assertStringContainsString( 'allow-scripts allow-forms', $js_src );
		$this->assertStringNotContainsString( 'allow-same-origin', $js_src );
		$this->assertStringContainsString( 'strict-origin', $js_src );
		$this->assertStringContainsString( 'wpcy-app-iframe', $js_src );
		$this->assertStringContainsString( 'wpcy_apps_session_invalid', $js_src );
	}
}
