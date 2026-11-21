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
use WenPai\ChinaYes\Diagnostics\ConflictDetector;
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
		$this->assertStringNotContainsString( 'data-wenpai-ad-dismiss', $overview );
		$this->assertStringContainsString( '快捷工具', $overview );
		$this->assertStringContainsString( '文派组件', $overview );
		$this->assertStringContainsString( 'wenpai-icon-grid--5', $overview );
		$this->assertStringNotContainsString( 'wenpai-icon-box__badge', $overview );
		$this->assertStringContainsString( 'data-wpcy-suite-slug="wpavatar"', $overview );
		$this->assertStringContainsString( 'data-wpcy-suite-slug="wpfonts"', $overview );
		$this->assertStringContainsString( '工具箱预留位', $overview );
		$this->assertStringContainsString( '工具预留', $overview );
		$this->assertStringContainsString( 'data-wpcy-sug-row="sug.feeds"', $overview );
		$this->assertStringContainsString( 'data-wpcy-sug-row="sug.passport"', $overview );
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
		$this->assertStringContainsString( '组件互联与冲突检测', $diagnose );
		$this->assertStringContainsString( '未检测到同类改写或失效镜像冲突', $diagnose );

		$wizard = $this->render_page( 'wpcy-onboarding' );
		$this->assertStringContainsString( 'data-wpcy-screen="wizard"', $wizard );
		$this->assertStringContainsString( '环境与冲突检测', $wizard );
		$this->assertStringContainsString( '装机组件推荐', $wizard );
		$this->assertStringContainsString( 'data-wpcy-starter-slug="wpavatar"', $wizard );
		$this->assertStringNotContainsString( 'style="', $wizard );

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

	/**
	 * White-Label agency promo slot customization, promo_hidden expansion, dismissed suggestions,
	 * and native WP Dashboard news widget all render cleanly.
	 */
	public function test_whitelabel_agency_promo_slot_suggestions_and_dashboard_widget(): void {
		set_transient(
			EntitlementsModule::TRANSIENT_FRESH,
			array(
				array(
					'service' => 'oem',
					'status'  => 'active',
				),
			),
			3600
		);

		// 1. Agency customizes Overview promo slot for their own brand services (zero close button).
		$agency_promo = $this->render_page(
			'wpcy',
			array(
				'connectivity' => array(
					'dashboard_feeds' => 'block',
				),
				'admin'        => array(
					'whitelabel' => array(
						'enabled'         => true,
						'brand_name'      => '星云建站',
						'hide_links'      => true,
						'promo_hidden'    => false,
						'promo_badge'     => '年度运维',
						'promo_title'     => '云服务器续费立减 20%',
						'promo_desc'      => '含每日异地备份与专属工程师响应。',
						'promo_cta_label' => '联系专属顾问 ↗',
						'promo_cta_url'   => 'https://nebula.example.com/renew',
					),
				),
			)
		);
		$this->assertStringContainsString( '星云建站 · 专属服务', $agency_promo );
		$this->assertStringContainsString( '云服务器续费立减 20%', $agency_promo );
		$this->assertStringContainsString( 'https://nebula.example.com/renew', $agency_promo );
		$this->assertStringNotContainsString( 'data-wenpai-ad-dismiss', $agency_promo );
		$this->assertStringNotContainsString( 'data-wpcy-sug-row=', $agency_promo );

		// 2. Agency turns on promo_hidden in White-Label settings -> ad_slot omitted, right card spans full width.
		$hidden_promo = $this->render_page(
			'wpcy',
			array(
				'admin' => array(
					'whitelabel' => array(
						'enabled'      => true,
						'brand_name'   => '星云建站',
						'promo_hidden' => true,
					),
				),
			)
		);
		$this->assertStringNotContainsString( 'data-wenpai-ad-slot', $hidden_promo );
		$this->assertStringContainsString( 'wenpai-split--reverse wenpai-split--equal is-single', $hidden_promo );

		// 3. Dismissed suggestion IDs in wpcy_announcements_dismissed are suppressed on SSR.
		update_option( 'wpcy_announcements_dismissed', array( 'sug.feeds', 'sug.passport' ) );
		$dismissed_overview = $this->render_page( 'wpcy' );
		$this->assertStringNotContainsString( 'data-wpcy-sug-row="sug.feeds"', $dismissed_overview );
		$this->assertStringNotContainsString( 'data-wpcy-sug-row="sug.passport"', $dismissed_overview );

		// 4. Native WP Dashboard replacement news widget renders cleanly.
		ob_start();
		KitRenderer::render_dashboard_news_widget(
			Defaults::settings(),
			array(
				array(
					'id'           => 'rel-681',
					'category'     => 'release',
					'title'        => 'WordPress 6.8.1 中文维护包说明',
					'url'          => 'https://wptea.com/rel-681',
					'published_at' => '2026-11-20T10:00:00Z',
				),
			)
		);
		$widget_html = (string) ob_get_clean();
		$this->assertStringContainsString( 'WordPress 6.8.1 中文维护包说明', $widget_html );
		$this->assertStringContainsString( '已替换默认慢速海外新闻源', $widget_html );
		$this->assertStringNotContainsString( 'style="', $widget_html );
	}

	/**
	 * ConflictDetector surfaces overlapping plugins in Overview, Diagnose, and Wizard while pairing companions.
	 */
	public function test_conflict_detector_and_companion_synergy_across_screens(): void {
		update_option(
			'active_plugins',
			array(
				'useso-take-over-google/useso-take-over-google.php',
				'wpslug/wpslug.php',
				'wp-pinyin-slug/wp-pinyin-slug.php',
				'wpavatar/wpavatar.php',
			)
		);

		$overview = $this->render_page( 'wpcy' );
		$this->assertStringContainsString( 'data-wpcy-sug-row="sug.conflict.useso-take-over-google"', $overview );
		$this->assertStringContainsString( 'data-wpcy-sug-row="sug.conflict.wp-pinyin-slug"', $overview );
		$this->assertStringContainsString( '文派头像协同接管', $overview );

		$diagnose = $this->render_page( 'wpcy-diagnose' );
		$this->assertStringContainsString( '360 前端公共库替换（Useso）', $diagnose );
		$this->assertStringContainsString( '失效节点冲突', $diagnose );
		$this->assertStringContainsString( 'WP Pinyin Slug', $diagnose );
		$this->assertStringContainsString( '文派头像（WPAvatar）', $diagnose );
		$this->assertStringContainsString( '协同接管', $diagnose );

		$wizard = $this->render_page( 'wpcy', array(), array( 'view' => 'wizard' ) );
		$this->assertStringContainsString( 'data-wpcy-screen="wizard"', $wizard );
		$this->assertStringContainsString( '360 前端公共库替换（Useso）', $wizard );
		$this->assertStringContainsString( '装机组件推荐', $wizard );
	}

	/**
	 * Companion menu resolver maps alias slugs (`wpavatar` -> `wpavatar-settings`), honors live
	 * `$_parent_pages`, and guarantees every Wizard Step 3 starter item is online-installable.
	 */
	public function test_companion_menu_resolution_and_starter_installability(): void {
		$this->assertStringContainsString(
			'options-general.php?page=wpavatar-settings',
			ConflictDetector::resolve_companion_admin_url( 'wpavatar' )
		);
		$this->assertStringContainsString(
			'options-general.php?page=wpcomments-settings',
			ConflictDetector::resolve_companion_admin_url( 'wpcomments' )
		);

		$prev_parents = $GLOBALS['_parent_pages'] ?? null;
		try {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- testing live WP menu table lookup.
			$GLOBALS['_parent_pages'] = array(
				'wpavatar-settings' => 'options-general.php',
				'wpsss'             => '',
			);
			$this->assertStringContainsString(
				'options-general.php?page=wpavatar-settings',
				ConflictDetector::resolve_live_url_for_page_slug( 'wpavatar' )
			);
			$this->assertStringContainsString(
				'?page=wpsss',
				ConflictDetector::resolve_live_url_for_page_slug( 'wpsss' )
			);
		} finally {
			if ( null === $prev_parents ) {
				unset( $GLOBALS['_parent_pages'] );
			} else {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring test state.
				$GLOBALS['_parent_pages'] = $prev_parents;
			}
		}

		foreach ( ConflictDetector::wizard_starter_items( 'domestic' ) as $starter ) {
			$this->assertTrue(
				ConflictDetector::is_companion_installable( $starter['slug'] ),
				'Wizard starter slug must have a public installable package: ' . $starter['slug']
			);
		}
	}
}
