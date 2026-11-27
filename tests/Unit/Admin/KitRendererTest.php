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
use WenPai\ChinaYes\Admin\WhiteLabel;
use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Config\Repository;
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
		$this->assertStringContainsString( '商业白标授权', $gated );
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
		$this->assertStringNotContainsString( '商业白标授权', $unlocked );
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

	/**
	 * Overview Quick Tools grid includes the three built-in M0 tools plus two reserved slots.
	 */
	public function test_overview_renders_built_in_quick_tools_and_maintenance_badge(): void {
		\WenPai\ChinaYes\Admin\MaintenanceModule::set_enabled( false );
		$html = $this->render_page( 'wpcy' );
		$this->assertStringContainsString( 'data-wpcy-quick="clear-transients"', $html );
		$this->assertStringContainsString( '清瞬态缓存', $html );
		$this->assertStringContainsString( 'data-wpcy-quick="toggle-maintenance"', $html );
		$this->assertStringContainsString( '维护模式', $html );
		$this->assertStringContainsString( 'data-wpcy-quick="site-snapshot"', $html );
		$this->assertStringContainsString( '环境快照', $html );
		$this->assertStringContainsString( '国内化体检', $html );
		$this->assertSame( 1, substr_count( $html, 'data-wpcy-quick="reserved-slot"' ) );

		\WenPai\ChinaYes\Admin\MaintenanceModule::set_enabled( true );
		$active_html = $this->render_page( 'wpcy' );
		$this->assertStringContainsString( '维护中', $active_html );
		$this->assertStringContainsString( 'wenpai-icon-box__dot is-warn', $active_html );
		\WenPai\ChinaYes\Admin\MaintenanceModule::set_enabled( false );
	}

	/**
	 * Overview applies `wpcy_quota_items` so companion plugins (e.g. WPMind) can render live balances,
	 * and Onboarding Wizard Step 2 includes WPMind in the starter list.
	 */
	public function test_overview_applies_wpcy_quota_items_filter_and_wizard_includes_wpmind(): void {
		$captured_context = null;
		$saw_wpmind_id    = false;

		add_filter(
			'wpcy_quota_items',
			static function ( $items, $context ) use ( &$captured_context, &$saw_wpmind_id ) {
				$captured_context = $context;
				if ( ! is_array( $items ) ) {
					$items = array();
				}
				foreach ( $items as $index => $row ) {
					if ( is_array( $row ) && 'wpmind' === ( $row['id'] ?? '' ) ) {
						$saw_wpmind_id   = true;
						$items[ $index ] = array(
							'id'           => 'wpmind',
							'name'         => '心思 AI 接口服务',
							'usage'        => '剩余 4,500 心思点数',
							'percent'      => 10,
							'note'         => '本月已用 500 / 5,000 心思点数',
							'action_label' => '管理算力',
							'action_url'   => 'admin.php?page=wpmind&tab=cloud',
						);
					}
				}
				$items[] = array(
					'id'      => 'custom_quota',
					'name'    => '扩展额度示例',
					'usage'   => '不限量',
					'percent' => null,
				);
				$items[] = 'invalid-row';
				$items[] = array( 'name' => '' );
				return $items;
			},
			10,
			2
		);

		$overview = $this->render_page( 'wpcy' );
		$this->assertTrue( $saw_wpmind_id );
		$this->assertIsArray( $captured_context );
		$this->assertArrayHasKey( 'services_url', $captured_context );
		$this->assertArrayHasKey( 'settings', $captured_context );
		$this->assertArrayHasKey( 'bound', $captured_context );
		$this->assertStringContainsString( '剩余 4,500 心思点数', $overview );
		$this->assertStringContainsString( '本月已用 500 / 5,000 心思点数', $overview );
		$this->assertStringContainsString( '管理算力', $overview );
		$this->assertStringContainsString( '扩展额度示例', $overview );
		$this->assertStringNotContainsString( '支持自有密钥或站点绑定额度', $overview );

		// Filter still runs even when remote wpcy_catalog transient supplies quotas.
		set_transient(
			'wpcy_catalog',
			array(
				'quotas' => array(
					array(
						'id'    => 'wpmind',
						'name'  => '心思 AI 接口服务',
						'usage' => '云端占位文案',
					),
				),
			),
			3600
		);
		$overview_with_catalog = $this->render_page( 'wpcy' );
		$this->assertStringContainsString( '剩余 4,500 心思点数', $overview_with_catalog );
		$this->assertStringNotContainsString( '云端占位文案', $overview_with_catalog );

		$wizard = $this->render_page( 'wpcy-onboarding' );
		$this->assertStringContainsString( 'data-wpcy-starter-slug="wpmind"', $wizard );
		$this->assertStringContainsString( '文派心思（WPMind）', $wizard );
	}

	/**
	 * Commercial licenses & update providers live in their own top-level tab (`wpcy-licenses`)
	 * without duplicating `ProvidersView` inside `wpcy-services`.
	 */
	public function test_licenses_tab_renders_passport_and_providers_without_duplicating_on_services(): void {
		$licenses = $this->render_page( 'wpcy-licenses' );
		$this->assertStringContainsString( 'data-wpcy-screen="licenses"', $licenses );
		$this->assertStringContainsString( '站点通行证与文派云权益', $licenses );
		$this->assertStringContainsString( 'data-wpcy-licenses-passport="1"', $licenses );
		$this->assertStringContainsString( '商业扩展授权与更新源', $licenses );
		$this->assertStringContainsString( 'data-wpcy-providers-card="1"', $licenses );
		$this->assertStringContainsString( '薇晓朵商城', $licenses );
		$this->assertStringContainsString( '文派集市', $licenses );
		$this->assertStringContainsString( 'page=wpcy-licenses', $licenses );
		$this->assertStringNotContainsString( 'style="', $licenses );

		$services = $this->render_page( 'wpcy-services' );
		$this->assertStringNotContainsString( 'data-wpcy-providers-card="1"', $services );
		$this->assertStringContainsString( 'page=wpcy-licenses', $services );
		$this->assertStringContainsString( 'data-wpcy-bundles="1"', $services );
		$this->assertStringContainsString( '场景一键装机包', $services );
		$this->assertStringContainsString( '国内企业与官网组合', $services );
		$this->assertStringContainsString( '内容与社区组合', $services );
		$this->assertStringContainsString( '外贸与跨境组合', $services );

		$diagnose = $this->render_page( 'wpcy-diagnose' );
		$this->assertStringContainsString( 'data-wpcy-audit-card="1"', $diagnose );
		$this->assertStringContainsString( '国内化体检与一键修复', $diagnose );
		$this->assertStringContainsString( 'data-wpcy-audit-fix="1"', $diagnose );
		$this->assertStringNotContainsString( 'style="', $diagnose );
	}

	/**
	 * Cold cache on a bound site's WhiteLabel screen triggers one guarded refresh and unlocks immediately,
	 * while `WhiteLabel::is_entitled()` itself stays strictly non-blocking.
	 */
	public function test_whitelabel_cold_cache_guarded_refresh_unlocks_bound_site(): void {
		$repo                 = new Repository();
		$identity             = $repo->get_identity();
		$binding              = is_array( $identity['binding'] ) ? $identity['binding'] : array();
		$binding['status']    = 'bound';
		$binding['site_hash'] = 'site_hash_wl_cold_01';
		$identity['binding']  = $binding;
		$repo->set_identity( $identity );
		$repo->set_credential( 'cred_wl_cold_test' );

		add_filter(
			'wpcy_services_api',
			static function () {
				return 'https://mock.wpcy.test/v1';
			}
		);

		// Direct `WhiteLabel::is_entitled()` never performs outbound HTTP on a cold cache.
		$this->assertFalse( WhiteLabel::is_entitled() );
		$this->assertCount( 0, AdminStore::$http );

		AdminStore::$http_responses[] = array(
			'code' => 200,
			'body' => wp_json_encode(
				array(
					'entitlements' => array(
						array(
							'id'      => 'wpcy-oem-cold-1',
							'service' => 'oem',
							'tier'    => 'agency',
							'status'  => 'active',
							'quota'   => array(),
						),
					),
				)
			),
		);

		$unlocked = $this->render_page( 'wpcy-features', array(), array( 'module' => 'whitelabel' ) );
		$this->assertCount( 1, AdminStore::$http );
		$this->assertStringContainsString( '/v1/sites/site_hash_wl_cold_01/entitlements', AdminStore::$http[0]['url'] );
		$this->assertStringContainsString( '保存白标设置', $unlocked );
		$this->assertStringNotContainsString( '商业白标授权', $unlocked );
		$this->assertDoesNotMatchRegularExpression( '/遥测|匿名数据|opt-in|entitlement|套餐|\bPro\b/u', $unlocked );

		// Second render hits warm fresh cache with zero additional HTTP calls.
		$this->render_page( 'wpcy-features', array(), array( 'module' => 'whitelabel' ) );
		$this->assertCount( 1, AdminStore::$http );
	}

	/**
	 * Failed cold-cache refresh on WhiteLabel view records short negative backoff and does not hammer upstream.
	 */
	public function test_whitelabel_cold_cache_failure_sets_backoff_and_avoids_hammering(): void {
		$repo                 = new Repository();
		$identity             = $repo->get_identity();
		$binding              = is_array( $identity['binding'] ) ? $identity['binding'] : array();
		$binding['status']    = 'bound';
		$binding['site_hash'] = 'site_hash_wl_fail_01';
		$identity['binding']  = $binding;
		$repo->set_identity( $identity );
		$repo->set_credential( 'cred_wl_fail_test' );

		add_filter(
			'wpcy_services_api',
			static function () {
				return 'https://mock.wpcy.test/v1';
			}
		);

		// Empty response queue makes wp_remote_get return WP_Error.
		$first = $this->render_page( 'wpcy-features', array(), array( 'module' => 'whitelabel' ) );
		$this->assertCount( 1, AdminStore::$http );
		$this->assertStringContainsString( '商业白标授权', $first );
		$this->assertNotEmpty( get_transient( EntitlementsModule::TRANSIENT_BACKOFF ) );

		// Repeated page load during backoff skips outbound HTTP completely.
		$second = $this->render_page( 'wpcy-features', array(), array( 'module' => 'whitelabel' ) );
		$this->assertCount( 1, AdminStore::$http );
		$this->assertStringContainsString( '商业白标授权', $second );
	}
}
