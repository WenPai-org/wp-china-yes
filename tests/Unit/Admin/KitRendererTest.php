<?php
/**
 * Server-rendered 5-tab KitRenderer smoke & contract tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\KitRenderer;
use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;

require_once __DIR__ . '/wp-admin-stubs.php';

/**
 * Verifies all 5 tabs, module subviews, dynamic catalog merge, help, and wizard render cleanly.
 */
class KitRendererTest extends TestCase {

	/**
	 * Load kit PHP helpers once and reset state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AdminStore::reset();
		OptionStore::reset();
		$_GET = array();

		self::ensure_kit_loaded();
	}

	/**
	 * Load minimal WP escaping helpers + vendored wenpai-admin-ui classes for unit process.
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
	 * Render helper returning HTML output.
	 *
	 * @param string               $page     Page slug.
	 * @param array<string, mixed> $settings Optional settings override.
	 * @param array<string, mixed> $query    Optional $_GET override.
	 * @return string
	 */
	private function render_page( string $page, array $settings = array(), array $query = array() ): string {
		$_GET    = $query;
		$merged  = array_replace_recursive( Defaults::settings(), $settings );
		$payload = array(
			'pluginVersion' => '4.0.0',
			'settings'      => $merged,
			'capabilities'  => array(
				'network_admin'  => false,
				'network_locked' => false,
			),
			'links'         => array(
				'help' => 'https://wpcy.com/go/support',
			),
		);
		ob_start();
		KitRenderer::render( $page, $payload );
		return (string) ob_get_clean();
	}

	/**
	 * All 5 primary tabs, wizard, and built-in help render expected twin layout and zero inline styles.
	 */
	public function test_five_tabs_wizard_and_help_render_cleanly(): void {
		$overview = $this->render_page( 'wpcy' );
		$this->assertStringContainsString( 'data-wpcy-screen="overview"', $overview );
		$this->assertStringContainsString( '通道详情', $overview );
		$this->assertStringContainsString( '调整设置', $overview );
		$this->assertStringContainsString( '立即测速', $overview );
		$this->assertStringContainsString( '近 7 日加速趋势', $overview );
		$this->assertStringContainsString( '核心节点测速', $overview );
		$this->assertStringContainsString( '服务用量额度', $overview );
		$this->assertStringContainsString( '近期运行记录', $overview );
		$this->assertStringContainsString( '茶馆动态', $overview );
		$this->assertStringContainsString( '精选推荐', $overview );
		$this->assertStringContainsString( '快捷工具', $overview );
		$this->assertStringContainsString( '站点建议', $overview );
		$this->assertStringContainsString( 'wpcy-features', $overview );
		$overview_no_meter = (string) preg_replace( '/<i style="inline-size:[0-9]+%"><\/i>/', '', $overview );
		$this->assertStringNotContainsString( 'style="', $overview_no_meter );

		$settings = $this->render_page( 'wpcy-connect', array( 'profile' => 'crossborder' ) );
		$this->assertStringContainsString( 'data-wpcy-screen="settings"', $settings );
		$this->assertStringContainsString( 'wenpai-measure-wide', $settings );
		$this->assertStringContainsString( 'WordPress.org 镜像与中文语言包', $settings );
		$this->assertStringContainsString( 'admin_group_suite_menus', $settings );
		$this->assertStringNotContainsString( 'style="', $settings );

		$features = $this->render_page( 'wpcy-features' );
		$this->assertStringContainsString( 'data-wpcy-screen="features"', $features );
		$this->assertStringContainsString( 'wenpai-module-grid', $features );
		$this->assertStringContainsString( 'btn-ghost', $features );
		$this->assertStringContainsString( '前后台语言分离', $features );
		$this->assertStringContainsString( '资讯与公告源', $features );
		$this->assertStringContainsString( '了解与开通', $features );
		$this->assertStringNotContainsString( 'style="', $features );

		// Dynamic remote catalog merge on Services screen.
		set_transient(
			'wpcy_catalog',
			array(
				'services' => array(
					array(
						'id'          => 'wpbackup',
						'category'    => 'ops',
						'title'       => '云盾备份',
						'short_desc'  => '整站增量备份与异地容灾 · WPBackup',
						'plugin_name' => '文派备份（WPBackup）',
						'plugin_slug' => 'wpbackup',
					),
				),
			),
			3600
		);
		$services = $this->render_page( 'wpcy-services' );
		$this->assertStringContainsString( 'data-wpcy-screen="services"', $services );
		$this->assertStringContainsString( 'wenpai-module-grid', $services );
		$this->assertStringContainsString( '云盾备份', $services );
		$this->assertStringContainsString( 'https://wpcy.com/', $services );
		$this->assertStringNotContainsString( 'style="', $services );

		$diagnose = $this->render_page( 'wpcy-diagnose' );
		$this->assertStringContainsString( 'data-wpcy-screen="diagnose"', $diagnose );

		$wizard = $this->render_page( 'wpcy-onboarding' );
		$this->assertStringContainsString( 'data-wpcy-screen="wizard"', $wizard );

		$help = $this->render_page( 'wpcy', array(), array( 'view' => 'help' ) );
		$this->assertStringContainsString( '帮助与支持', $help );
	}

	/**
	 * Feature detail subviews and paid White-Label gate vs unlocked state render accurately.
	 */
	public function test_feature_subviews_and_whitelabel_gate_states(): void {
		foreach ( array( 'locale', 'noise', 'news', 'outbound' ) as $mod ) {
			$html = $this->render_page( 'wpcy-features', array(), array( 'module' => $mod ) );
			$this->assertNotEmpty( $html );
			$this->assertStringNotContainsString( 'style="', $html );
		}

		// Unentitled white-label view shows locked gate card and disabled inputs.
		$gated = $this->render_page( 'wpcy-features', array(), array( 'module' => 'whitelabel' ) );
		$this->assertStringContainsString( '开通商业白标授权', $gated );
		$this->assertStringContainsString( 'https://wpcy.com/go/oem', $gated );

		// Entitled white-label view unlocks save button and custom news feed input (wrapped transient format).
		set_transient(
			EntitlementsModule::TRANSIENT_FRESH,
			array(
				'fetched_at'   => '2026-11-21T00:00:00Z',
				'entitlements' => array(
					array(
						'service' => 'oem',
						'status'  => 'active',
					),
				),
			),
			3600
		);
		$unlocked = $this->render_page(
			'wpcy-features',
			array(
				'admin' => array(
					'whitelabel' => array(
						'enabled'       => true,
						'brand_name'    => '星云建站',
						'news_feed_url' => 'https://nebula.example.com/news.json',
					),
				),
			),
			array( 'module' => 'whitelabel' )
		);
		$this->assertStringContainsString( '保存白标设置', $unlocked );
		$this->assertStringContainsString( '星云建站', $unlocked );
		$this->assertStringNotContainsString( '开通商业白标授权', $unlocked );
	}

	/**
	 * Overview SSR hydrates cached server probe results and switches cleanly in recovery mode.
	 */
	public function test_overview_hydrates_cached_probes_and_recovery_mode(): void {
		set_transient(
			'wpcy_diagnostics_results',
			array(
				array(
					'target'     => 'api.wenpai.net',
					'result'     => 'ok',
					'latency_ms' => 38,
					'checked_at' => '2026-11-21T03:30:00Z',
				),
				array(
					'target'     => 'cdnjs.admincdn.com',
					'result'     => 'ok',
					'latency_ms' => 24,
					'checked_at' => '2026-11-21T03:30:00Z',
				),
				array(
					'target'     => 'cn.cravatar.com',
					'result'     => 'fallback',
					'latency_ms' => 112,
					'checked_at' => '2026-11-21T03:30:00Z',
				),
			),
			3600
		);

		$hydrated = $this->render_page( 'wpcy' );
		$this->assertStringContainsString( '38<small>ms</small>', $hydrated );
		$this->assertStringContainsString( '24<small>ms</small>', $hydrated );
		$this->assertStringContainsString( '112<small>ms</small>', $hydrated );
		$this->assertStringContainsString( '58 <small>ms</small>', $hydrated );
		$this->assertStringContainsString( '11-21 11:30 更新', $hydrated );
		$this->assertStringContainsString( '1 项通道发生自动回退', $hydrated );

		$recovery = $this->render_page( 'wpcy', array( 'recovery_mode' => true ) );
		$this->assertStringContainsString( '恢复模式生效中，镜像与静态加速已暂停', $recovery );
		$recovery_no_meter = (string) preg_replace( '/<i style="inline-size:[0-9]+%"><\/i>/', '', $recovery );
		$this->assertStringNotContainsString( 'style="', $recovery_no_meter );
	}
}
