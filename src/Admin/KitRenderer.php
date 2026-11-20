<?php
/**
 * Server-rendered admin pages using wenpai-admin-ui kit v0.11.1 (Route A, Plan 2).
 *
 * Keeps all backend business logic and REST /wpcy/v1/* untouched so the UI
 * shell remains thin and swappable.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the 5 wpcy 4.0 admin screens via Wenpai_Admin_Shell / Wenpai_Admin_UI.
 */
final class KitRenderer {

	/**
	 * Render one admin screen.
	 *
	 * @param string               $page    Current ?page= slug.
	 * @param array<string, mixed> $payload Bootstrap payload from AdminModule::bootstrap_payload().
	 * @return void
	 */
	public static function render( string $page, array $payload ): void {
		if ( ! class_exists( '\Wenpai_Admin_Shell', false ) || ! class_exists( '\Wenpai_Admin_UI', false ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( '文派后台组件库未加载，请检查 lib/wenpai-admin-ui。', 'wp-china-yes' ) . '</p></div>';
			return;
		}

		$settings = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : array();
		$caps     = isset( $payload['capabilities'] ) && is_array( $payload['capabilities'] ) ? $payload['capabilities'] : array();
		$links    = isset( $payload['links'] ) && is_array( $payload['links'] ) ? $payload['links'] : array();
		$version  = isset( $payload['pluginVersion'] ) ? (string) $payload['pluginVersion'] : '4.0.0';
		$locked   = ! empty( $caps['network_locked'] );
		$network  = ! empty( $caps['network_admin'] );

		if ( 'wpcy-onboarding' === $page && ! $locked && ! $network ) {
			self::render_wizard( $settings, $links );
			return;
		}

		$tab_map = array(
			'wpcy'          => 'overview',
			'wpcy-connect'  => 'settings',
			'wpcy-services' => 'services',
			'wpcy-diagnose' => 'diagnose',
		);
		$current = $network ? 'settings' : ( $tab_map[ $page ] ?? 'overview' );
		$shell   = self::shell_args( $current, $version, $links, $network );

		\Wenpai_Admin_Shell::open( $shell );

		if ( 'settings' === $current ) {
			self::render_settings( $settings, $locked, $network );
		} elseif ( 'services' === $current ) {
			self::render_services( $settings );
		} elseif ( 'diagnose' === $current ) {
			self::render_diagnose( $settings );
		} else {
			self::render_overview( $settings );
		}

		\Wenpai_Admin_Shell::close( $shell );
	}

	/**
	 * Monochrome leaf SVG brand mark.
	 */
	private static function leaf_mark(): string {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>';
	}

	/**
	 * Build an admin page URL via Wenpai_Admin_Menu::url when available.
	 */
	private static function page_url( string $slug, bool $network = false ): string {
		if ( ! $network && class_exists( '\Wenpai_Admin_Menu', false ) ) {
			return (string) \Wenpai_Admin_Menu::url( $slug );
		}
		if ( $network && function_exists( 'network_admin_url' ) && function_exists( 'add_query_arg' ) ) {
			return (string) add_query_arg( array( 'page' => $slug ), network_admin_url( 'admin.php' ) );
		}
		return '?page=' . rawurlencode( $slug );
	}

	/**
	 * Build Wenpai_Admin_Shell args.
	 *
	 * @param string               $current Active tab id.
	 * @param string               $version Plugin version string.
	 * @param array<string, mixed> $links   External links.
	 * @param bool                 $network Network admin context.
	 * @return array<string, mixed>
	 */
	private static function shell_args( string $current, string $version, array $links, bool $network ): array {
		$admin_url = static function ( string $slug ) use ( $network ): string {
			return self::page_url( $slug, $network );
		};

		$tabs = $network
			? array(
				array( 'id' => 'settings', 'label' => __( '设置', 'wp-china-yes' ), 'url' => $admin_url( 'wpcy-connect' ) ),
			)
			: array(
				array( 'id' => 'overview', 'label' => __( '概览', 'wp-china-yes' ), 'url' => $admin_url( 'wpcy' ) ),
				array( 'id' => 'settings', 'label' => __( '设置', 'wp-china-yes' ), 'url' => $admin_url( 'wpcy-connect' ) ),
				array( 'id' => 'services', 'label' => __( '服务', 'wp-china-yes' ), 'url' => $admin_url( 'wpcy-services' ) ),
				array( 'id' => 'diagnose', 'label' => __( '诊断', 'wp-china-yes' ), 'url' => $admin_url( 'wpcy-diagnose' ) ),
			);

		return array(
			'name'      => __( '文派叶子', 'wp-china-yes' ),
			'mark_html' => self::leaf_mark(),
			'home_url'  => $admin_url( $network ? 'wpcy-connect' : 'wpcy' ),
			'current'   => $current,
			'tabs'      => $tabs,
			'version'   => sprintf( /* translators: %s: version */ __( '文派叶子 %s', 'wp-china-yes' ), $version ),
			'doc_url'   => isset( $links['help'] ) ? (string) $links['help'] : 'https://wpcy.com/go/support',
			'doc_label' => __( '帮助', 'wp-china-yes' ),
		);
	}

	/**
	 * Overview screen (Plan 2: statbar + 7d SVG trend & latency split + 4 channels + folded log).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 */
	private static function render_overview( array $settings ): void {
		$profile  = self::profile_label( (string) ( $settings['profile'] ?? 'domestic' ) );
		$recovery = ! empty( $settings['recovery_mode'] );
		$conn     = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror   = (string) ( $conn['wporg_mirror'] ?? 'auto' );
		$avatar   = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$assets   = isset( $settings['public_assets']['scope'] ) ? (string) $settings['public_assets']['scope'] : 'both';
		$diag_url = self::page_url( 'wpcy-diagnose' );
		$set_url  = self::page_url( 'wpcy-connect' );

		\Wenpai_Admin_Shell::page_head(
			__( '概览', 'wp-china-yes' ),
			sprintf( /* translators: %s: site profile label */ __( '当前场景：%s。一屏查看加速效果、节点延迟与核心通道状态。', 'wp-china-yes' ), $profile ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '立即诊断', 'wp-china-yes' ),
					'href'    => $diag_url,
					'variant' => 'primary',
				)
			)
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="overview">';

		if ( $recovery ) {
			\Wenpai_Admin_UI::next(
				array(
					'state'   => 'warn',
					'icon'    => 'info',
					'title'   => __( '当前处于安全恢复模式，全部加速代理已暂停', 'wp-china-yes' ),
					'desc'    => __( '站点已切回 WordPress 原始直连。排查完成后可在诊断页恢复正常运行。', 'wp-china-yes' ),
					'actions' => array(
						array( 'label' => __( '前往诊断页', 'wp-china-yes' ), 'href' => $diag_url, 'variant' => 'primary' ),
					),
				)
			);
		}

		// Top 4-cell statbar (hydrated asynchronously by wpcy-admin.js from /wpcy/v1/stats).
		\Wenpai_Admin_UI::card_open();
		\Wenpai_Admin_UI::statbar(
			array(
				'attrs' => array( 'data-wpcy-stats' => '1' ),
				'items' => array(
					array( 'label' => __( '7 日总加速', 'wp-china-yes' ), 'value' => '—', 'unit' => __( '次', 'wp-china-yes' ) ),
					array( 'label' => __( '镜像平均延迟', 'wp-china-yes' ), 'value' => '—', 'unit' => 'ms' ),
					array( 'label' => __( '头像代理', 'wp-china-yes' ), 'value' => '—', 'unit' => __( '次', 'wp-china-yes' ) ),
					array( 'label' => __( '拦截外部慢请求', 'wp-china-yes' ), 'value' => '—', 'unit' => __( '次', 'wp-china-yes' ) ),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();

		// Main-side split: 7-day SVG trend + latency meter.
		echo '<div class="wenpai-split">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '近 7 日加速趋势', 'wp-china-yes' ),
				'desc'  => __( '每日代理请求总数（更新、前端资源与头像）', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		\Wenpai_Admin_UI::chart_frame(
			array(
				'attrs'      => array( 'data-wpcy-trend' => '1' ),
				'plot_html'  => self::empty_trend_svg(),
				'aria_label' => __( '近 7 日加速趋势', 'wp-china-yes' ),
			)
		);
		\Wenpai_Admin_UI::card_close();

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '节点延迟对比', 'wp-china-yes' ),
				'desc'  => __( '越短越快（来自最近一次探测）', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		\Wenpai_Admin_UI::meter(
			array(
				'attrs' => array( 'data-wpcy-latency' => '1' ),
				'items' => array(
					array( 'name' => __( 'WP.org 国内镜像', 'wp-china-yes' ), 'value' => '—', 'percent' => 15 ),
					array( 'name' => __( '公共前端库 CDN', 'wp-china-yes' ), 'value' => '—', 'percent' => 15 ),
					array( 'name' => __( 'Cravatar 节点', 'wp-china-yes' ), 'value' => '—', 'percent' => 15 ),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';

		// Core 4 channels + folded events log.
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '核心通道状态', 'wp-china-yes' ),
				'icon'  => 'cloud',
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'  => 'cloud',
				'state' => 'off' === $mirror ? '' : 'ok',
				'title' => __( 'WordPress.org 更新与安装', 'wp-china-yes' ),
				'desc'  => 'off' === $mirror ? __( '直连 WordPress.org 官方源', 'wp-china-yes' ) : __( '优先国内镜像，异常自动回退官方源', 'wp-china-yes' ),
				'pill'  => array(
					'text'  => 'off' === $mirror ? __( '直连', 'wp-china-yes' ) : __( '国内镜像', 'wp-china-yes' ),
					'state' => 'off' === $mirror ? 'mute' : 'ok',
				),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'  => 'flash',
				'state' => 'off' === $assets ? '' : 'ok',
				'title' => __( '常用前端资源', 'wp-china-yes' ),
				'desc'  => __( 'Google Fonts、CDNJS、jsDelivr 与 Emoji 加速', 'wp-china-yes' ),
				'pill'  => array(
					'text'  => 'off' === $assets ? __( '已关闭', 'wp-china-yes' ) : __( '已开启', 'wp-china-yes' ),
					'state' => 'off' === $assets ? 'mute' : 'ok',
				),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'  => 'image',
				'state' => 'off' === $avatar ? '' : 'ok',
				'title' => __( 'Cravatar 头像', 'wp-china-yes' ),
				'desc'  => __( '替代 Gravatar，解决头像空白与慢加载', 'wp-china-yes' ),
				'pill'  => array(
					'text'  => 'off' === $avatar ? __( '已关闭', 'wp-china-yes' ) : __( '已开启', 'wp-china-yes' ),
					'state' => 'off' === $avatar ? 'mute' : 'ok',
				),
			)
		);
		\Wenpai_Admin_UI::fold_list(
			array(
				'items' => array(
					array(
						'title'     => __( '最近事件记录', 'wp-china-yes' ),
						'desc'      => __( '展开查看最近加速与巡检记录', 'wp-china-yes' ),
						'body_html' => '<div data-wpcy-events="1"><p class="meta">' . esc_html__( '正在加载最近记录…', 'wp-china-yes' ) . '</p></div>',
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'note'    => __( '按当前站点场景自动配好，一般无需频繁调整。', 'wp-china-yes' ),
				'actions' => array(
					array( 'label' => __( '调整设置', 'wp-china-yes' ), 'href' => $set_url, 'variant' => 'secondary' ),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';
	}

	/**
	 * Settings screen (Profile choice + core toggles + folded two-column advanced settings).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Read-only subsite (CO-06).
	 * @param bool                 $network  Network admin screen.
	 */
	private static function render_settings( array $settings, bool $locked, bool $network ): void {
		$profile   = (string) ( $settings['profile'] ?? 'domestic' );
		$conn      = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror    = (string) ( $conn['wporg_mirror'] ?? 'auto' );
		$avatar    = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat = 'on' === (string) ( $conn['heartbeat'] ?? 'on' );
		$feeds     = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'block' );
		$scope     = isset( $settings['public_assets']['scope'] ) ? (string) $settings['public_assets']['scope'] : 'both';

		$lede = $locked
			? __( '已由网络设定 · 本站不能单独修改', 'wp-china-yes' )
			: ( $network
				? __( '网络策略：默认应用于全网子站，可按需开放子站覆盖。', 'wp-china-yes' )
				: __( '核心开关即时生效；站点场景与下方高级线路点击「保存设置」统一生效。', 'wp-china-yes' ) );

		$aside = $locked
			? ''
			: \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '保存设置', 'wp-china-yes' ),
					'type'    => 'submit',
					'variant' => 'primary',
					'form'    => 'wpcy-settings-form',
				)
			);

		\Wenpai_Admin_Shell::page_head( __( '设置', 'wp-china-yes' ), $lede, $aside );

		echo '<div class="wenpai-stack" data-wpcy-screen="settings">';
		if ( $locked ) {
			\Wenpai_Admin_UI::notice(
				array(
					'tone' => 'info',
					'text' => __( '这些设置由网络管理员统一设定。需要本站单独调整时，请联系网络管理员在网络后台开放「允许子站覆盖」。', 'wp-china-yes' ),
				)
			);
		}

		$dis_attr = $locked ? ' disabled' : '';
		echo '<form id="wpcy-settings-form" class="wenpai-stack" data-wpcy-network="' . ( $network ? '1' : '0' ) . '">';

		// Scene choice card.
		\Wenpai_Admin_UI::card_open();
		\Wenpai_Admin_UI::section_title(
			array(
				'title' => __( '站点场景', 'wp-china-yes' ),
				'desc'  => __( '选择最符合本站访客分布的场景，将自动匹配默认加速策略。', 'wp-china-yes' ),
			)
		);
		echo '<div class="choice-cards">';
		$scenes = array(
			'domestic'    => array( __( '国内站', 'wp-china-yes' ), __( '站点和访客都在中国大陆，默认走国内镜像与 Cravatar 国内节点。', 'wp-china-yes' ) ),
			'crossborder' => array( __( '跨境站', 'wp-china-yes' ), __( '访客主要在海外，后台走加速，前台保持直连。', 'wp-china-yes' ) ),
			'mixed'       => array( __( '混合站', 'wp-china-yes' ), __( '国内外都有访客，按后台与前台分别选择线路。', 'wp-china-yes' ) ),
		);
		foreach ( $scenes as $key => $info ) {
			$checked = $profile === $key ? ' checked' : '';
			echo '<label class="choice"><input type="radio" name="profile" value="' . esc_attr( $key ) . '"' . $checked . $dis_attr . '><span><strong>' . esc_html( $info[0] ) . '</strong><small>' . esc_html( $info[1] ) . '</small></span></label>';
		}
		echo '</div>';
		\Wenpai_Admin_UI::card_close();

		// Core acceleration + folded advanced two-column settings.
		\Wenpai_Admin_UI::card_open();
		\Wenpai_Admin_UI::section_title(
			array(
				'title' => __( '核心加速', 'wp-china-yes' ),
				'desc'  => __( '常用项开关即时生效；细节线路收在下方折叠区。', 'wp-china-yes' ),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'  => 'cloud',
				'state' => 'off' === $mirror ? '' : 'ok',
				'title' => __( 'WordPress 更新与安装', 'wp-china-yes' ),
				'desc'  => __( '按场景自动选择国内镜像或官方直连，可在下方高级线路中覆盖。', 'wp-china-yes' ),
				'pill'  => array(
					'text'  => 'off' === $mirror ? __( '直连官方源', 'wp-china-yes' ) : __( '国内镜像', 'wp-china-yes' ),
					'state' => 'off' === $mirror ? 'mute' : 'ok',
				),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'flash',
				'state'        => 'off' === $scope ? '' : 'ok',
				'title'        => __( '加速常用前端资源', 'wp-china-yes' ),
				'desc'         => __( 'Google Fonts、CDNJS、jsDelivr 与 Emoji 静态资源走更快节点。', 'wp-china-yes' ),
				'control_html' => self::toggle_control( 'public_assets_enabled', 'off' !== $scope, $locked ),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'image',
				'state'        => 'off' === $avatar ? '' : 'ok',
				'title'        => __( '头像走 Cravatar', 'wp-china-yes' ),
				'desc'         => __( '替代 Gravatar，解决头像空白与慢加载。', 'wp-china-yes' ),
				'control_html' => self::toggle_control( 'avatar_enabled', 'off' !== $avatar, $locked ),
			)
		);

		$adv_html  = self::field_row_select(
			'wporg_mirror',
			__( 'WordPress.org 源', 'wp-china-yes' ),
			__( '优先国内镜像，上游不可用时自动回退官方源。', 'wp-china-yes' ),
			array(
				'auto' => __( '国内镜像（自动回退）', 'wp-china-yes' ),
				'off'  => __( '直连 WordPress.org', 'wp-china-yes' ),
			),
			$mirror,
			$locked
		);
		$adv_html .= self::field_row_select(
			'public_assets_scope',
			__( '公共前端库范围', 'wp-china-yes' ),
			__( '控制前端公共静态库加速的生效位置。', 'wp-china-yes' ),
			array(
				'both'     => __( '后台与前台', 'wp-china-yes' ),
				'admin'    => __( '仅后台', 'wp-china-yes' ),
				'frontend' => __( '仅前台', 'wp-china-yes' ),
				'off'      => __( '关闭', 'wp-china-yes' ),
			),
			$scope,
			$locked
		);
		$adv_html .= self::field_row_select(
			'avatar_source',
			__( '头像线路', 'wp-china-yes' ),
			__( '可按访客地区选择中国线路或国际线路。', 'wp-china-yes' ),
			array(
				'cravatar_cn'     => __( 'Cravatar 中国线路（国内节点）', 'wp-china-yes' ),
				'cravatar_global' => __( 'Cravatar 国际线路（全球节点）', 'wp-china-yes' ),
				'off'             => __( '关闭（保留 Gravatar）', 'wp-china-yes' ),
			),
			$avatar,
			$locked
		);
		$adv_html .= self::field_row_checkbox( 'heartbeat', __( '减少后台心跳', 'wp-china-yes' ), __( '仪表盘停止轮询，编辑器降为 60 秒一次。', 'wp-china-yes' ), $heartbeat, $locked );
		$adv_html .= self::field_row_checkbox( 'dashboard_feeds', __( '不加载仪表盘外部内容', 'wp-china-yes' ), __( '屏蔽 WordPress 新闻、活动等出站请求。', 'wp-china-yes' ), $feeds, $locked );

		$summary = ( 'off' === $mirror ? __( '直连官方源', 'wp-china-yes' ) : __( '国内镜像', 'wp-china-yes' ) )
			. ' · ' . ( 'off' === $scope ? __( '前端加速关', 'wp-china-yes' ) : __( '前端加速开', 'wp-china-yes' ) )
			. ' · ' . ( $heartbeat ? __( '已优化心跳', 'wp-china-yes' ) : __( '默认心跳', 'wp-china-yes' ) );

		\Wenpai_Admin_UI::fold_list(
			array(
				'items' => array(
					array(
						'title'     => __( '高级线路与后台体验', 'wp-china-yes' ),
						'desc'      => $summary,
						'body_html' => $adv_html,
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Services screen (Plan 2: 3-column service card grid).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 */
	private static function render_services( array $settings ): void {
		unset( $settings );
		\Wenpai_Admin_Shell::page_head(
			__( '服务', 'wp-china-yes' ),
			__( '基础加速免费自带、无需绑定；扩展服务按需开通。', 'wp-china-yes' ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '绑定本站', 'wp-china-yes' ),
					'variant' => 'primary',
					'attrs'   => array( 'data-wpcy-action' => 'bind' ),
				)
			)
		);

		echo '<div class="wenpai-grid-3" data-wpcy-screen="services">';
		$cards = array(
			array( __( 'WP.org 国内镜像', 'wp-china-yes' ), 'cloud', __( '核心、主题、插件更新与简体中文翻译包自动走国内镜像，上游异常自动回退。', 'wp-china-yes' ), __( '免费 · 无需绑定', 'wp-china-yes' ), __( '运行中', 'wp-china-yes' ), true ),
			array( __( 'Cravatar 公共头像', 'wp-china-yes' ), 'image', __( 'Gravatar 的中国替代服务，支持按地区选择国内或国际线路。', 'wp-china-yes' ), __( '免费 · 无需绑定', 'wp-china-yes' ), __( '运行中', 'wp-china-yes' ), true ),
			array( __( 'Windfonts 中文字体', 'wp-china-yes' ), 'feather', __( '免费商用中文字体按需子集化分发，降低首屏体积，无需自建托管。', 'wp-china-yes' ), __( '绑定后显示', 'wp-china-yes' ), __( '去绑定', 'wp-china-yes' ), false ),
			array( __( '公共前端库加速', 'wp-china-yes' ), 'flash', __( 'Google Fonts、CDNJS、jsDelivr 与 Emoji 静态资源自动切换至更快节点。', 'wp-china-yes' ), __( '免费 · 无需绑定', 'wp-china-yes' ), __( '运行中', 'wp-china-yes' ), true ),
			array( __( '后台减噪优化', 'wp-china-yes' ), 'settings', __( '降低 Heartbeat 轮询频率，屏蔽仪表盘外部慢请求，缩短后台等待时间。', 'wp-china-yes' ), __( '免费 · 无需绑定', 'wp-china-yes' ), __( '已开启', 'wp-china-yes' ), true ),
			array( __( '云桥统一连接', 'wp-china-yes' ), 'plug', __( '一次绑定本站即可同步文派生态插件的更新通道与扩展能力。', 'wp-china-yes' ), __( '可选扩展', 'wp-china-yes' ), __( '绑定本站', 'wp-china-yes' ), false ),
		);
		foreach ( $cards as $card ) {
			\Wenpai_Admin_UI::card_open( array( 'title' => $card[0], 'icon' => $card[1] ) );
			echo '<p class="meta">' . esc_html( $card[2] ) . '</p>';
			\Wenpai_Admin_UI::card_foot(
				array(
					'start'   => true,
					'meta'    => $card[3],
					'actions' => array(
						array(
							'label'    => $card[4],
							'variant'  => $card[5] ? 'ghost' : 'secondary',
							'disabled' => $card[5],
							'attrs'    => $card[5] ? array() : array( 'data-wpcy-action' => 'bind' ),
						),
					),
				)
			);
			\Wenpai_Admin_UI::card_close();
		}
		echo '</div>';
	}

	/**
	 * Diagnose screen (Plan 2: Server vs Browser 2-column comparison + recovery mode).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 */
	private static function render_diagnose( array $settings ): void {
		$recovery = ! empty( $settings['recovery_mode'] );
		\Wenpai_Admin_Shell::page_head(
			__( '诊断', 'wp-china-yes' ),
			__( '左右对照服务器与浏览器两端的连通性，一眼定位瓶颈。', 'wp-china-yes' ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '并发重测', 'wp-china-yes' ),
					'variant' => 'primary',
					'attrs'   => array( 'data-wpcy-action' => 'diagnose' ),
				)
			)
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="diagnose">';
		echo '<div class="wenpai-grid-2">';
		\Wenpai_Admin_UI::card_open( array( 'title' => __( '服务器到各源', 'wp-china-yes' ), 'icon' => 'server' ) );
		\Wenpai_Admin_UI::status_list(
			array(
				'attrs' => array( 'data-wpcy-diag-server' => '1' ),
				'items' => array(
					array( 'label' => __( 'WP.org 国内镜像', 'wp-china-yes' ), 'value' => __( '待检测', 'wp-china-yes' ) ),
					array( 'label' => __( 'WP.org 官方源', 'wp-china-yes' ), 'value' => __( '待检测', 'wp-china-yes' ) ),
					array( 'label' => __( 'Cravatar API', 'wp-china-yes' ), 'value' => __( '待检测', 'wp-china-yes' ) ),
					array( 'label' => __( '公共前端库源', 'wp-china-yes' ), 'value' => __( '待检测', 'wp-china-yes' ) ),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();

		\Wenpai_Admin_UI::card_open( array( 'title' => __( '浏览器到各源', 'wp-china-yes' ), 'icon' => 'eye' ) );
		\Wenpai_Admin_UI::status_list(
			array(
				'attrs' => array( 'data-wpcy-diag-browser' => '1' ),
				'items' => array(
					array( 'label' => __( 'Cravatar 头像节点', 'wp-china-yes' ), 'value' => __( '待检测', 'wp-china-yes' ) ),
					array( 'label' => __( '公共前端库 CDN', 'wp-china-yes' ), 'value' => __( '待检测', 'wp-china-yes' ) ),
					array( 'label' => __( 'Windfonts 字体源', 'wp-china-yes' ), 'value' => __( '待检测', 'wp-china-yes' ) ),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '紧急恢复', 'wp-china-yes' ),
				'desc'  => __( '遇到资源加载异常时，可一键暂停全部代理规则并保留当前设置。', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		\Wenpai_Admin_UI::task(
			array(
				'title'       => $recovery ? __( '退出恢复模式', 'wp-china-yes' ) : __( '暂停全部加速规则', 'wp-china-yes' ),
				'desc'        => $recovery
					? __( '重新启用已保存的镜像与前端资源加速规则。', 'wp-china-yes' )
					: __( '立即切回 WordPress 原始直连，可随时在此恢复。', 'wp-china-yes' ),
				'action_html' => \Wenpai_Admin_UI::button_html(
					array(
						'label'   => $recovery ? __( '恢复正常运行', 'wp-china-yes' ) : __( '进入恢复模式', 'wp-china-yes' ),
						'variant' => $recovery ? 'secondary' : 'danger',
						'attrs'   => $recovery
							? array( 'data-wpcy-action' => 'recovery-off' )
							: array(
								'data-wpcy-action'    => 'recovery-on',
								'data-wenpai-confirm' => __( '将立即暂停所有镜像与资源代理并切回直连，确定继续？', 'wp-china-yes' ),
							),
					)
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';
	}

	/**
	 * Onboarding wizard screen.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param array<string, mixed> $links    Links map.
	 */
	private static function render_wizard( array $settings, array $links ): void {
		unset( $links );
		$profile  = (string) ( $settings['profile'] ?? 'domestic' );
		$home_url = self::page_url( 'wpcy' );

		\Wenpai_Admin_UI::wizard_open(
			array(
				'name'      => __( '文派叶子', 'wp-china-yes' ),
				'mark_html' => self::leaf_mark(),
				'title'     => __( '首次配置', 'wp-china-yes' ),
				'desc'      => __( '选定站点场景后，自动套用最合适的默认加速方案，30 秒完成。', 'wp-china-yes' ),
				'exit_url'  => $home_url,
			)
		);
		\Wenpai_Admin_UI::steps(
			array(
				'current' => 1,
				'items'   => array(
					array( 'title' => __( '选站点场景', 'wp-china-yes' ), 'desc' => __( '国内 / 跨境 / 混合', 'wp-china-yes' ) ),
					array( 'title' => __( '应用默认方案', 'wp-china-yes' ), 'desc' => __( '自动匹配镜像与头像', 'wp-china-yes' ) ),
					array( 'title' => __( '进入概览', 'wp-china-yes' ), 'desc' => __( '随时可在设置调整', 'wp-china-yes' ) ),
				),
			)
		);
		echo '<form id="wpcy-wizard-form" data-wpcy-screen="wizard" data-wpcy-home="' . esc_url( $home_url ) . '">';
		\Wenpai_Admin_UI::card_open();
		echo '<div class="choice-cards">';
		$scenes = array(
			'domestic'    => array( __( '国内站', 'wp-china-yes' ), __( '站点和访客都在中国大陆，默认走国内镜像与 Cravatar 国内节点。', 'wp-china-yes' ) ),
			'crossborder' => array( __( '跨境站', 'wp-china-yes' ), __( '访客主要在海外，后台走加速，前台保持直连。', 'wp-china-yes' ) ),
			'mixed'       => array( __( '混合站', 'wp-china-yes' ), __( '国内外都有访客，按后台与前台分别选择线路。', 'wp-china-yes' ) ),
		);
		foreach ( $scenes as $key => $info ) {
			$checked = $profile === $key ? ' checked' : '';
			echo '<label class="choice"><input type="radio" name="profile" value="' . esc_attr( $key ) . '"' . $checked . '><span><strong>' . esc_html( $info[0] ) . '</strong><small>' . esc_html( $info[1] ) . '</small></span></label>';
		}
		echo '</div>';
		\Wenpai_Admin_UI::card_foot(
			array(
				'actions' => array(
					array( 'label' => __( '跳过', 'wp-china-yes' ), 'href' => $home_url, 'variant' => 'ghost' ),
					array( 'label' => __( '应用并进入概览', 'wp-china-yes' ), 'type' => 'submit', 'variant' => 'primary' ),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</form>';
		\Wenpai_Admin_UI::wizard_close();
	}

	/**
	 * Localized label for site profile.
	 */
	private static function profile_label( string $profile ): string {
		$map = array(
			'domestic'    => __( '国内站', 'wp-china-yes' ),
			'crossborder' => __( '跨境站', 'wp-china-yes' ),
			'mixed'       => __( '混合站', 'wp-china-yes' ),
		);
		return $map[ $profile ] ?? $profile;
	}

	/**
	 * Toggle switch HTML using kit switch markup.
	 */
	private static function toggle_control( string $name, bool $checked, bool $disabled ): string {
		$dis = $disabled ? ' disabled' : '';
		$chk = $checked ? ' checked' : '';
		$txt = $checked ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' );
		return '<span class="wenpai-switch">'
			. '<input type="checkbox" class="wenpai-switch__input" name="' . esc_attr( $name ) . '" value="1" data-wpcy-instant="1"' . $chk . $dis . '>'
			. '<span class="wenpai-switch__track" aria-hidden="true"></span>'
			. '<span class="wenpai-switch__label">' . esc_html( $txt ) . '</span>'
			. '</span>';
	}

	/**
	 * Two-column select field row (.field).
	 *
	 * @param array<string, string> $options Value => label map.
	 */
	private static function field_row_select( string $name, string $label, string $desc, array $options, string $current, bool $disabled ): string {
		$id  = 'wpcy-f-' . $name;
		$dis = $disabled ? ' disabled' : '';
		$h   = '<div class="wenpai-field field wenpai-field--row">';
		$h  .= '<div class="field-label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>';
		$h  .= '<div class="field-ctl"><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $dis . '>';
		foreach ( $options as $val => $text ) {
			$sel = $current === $val ? ' selected' : '';
			$h  .= '<option value="' . esc_attr( $val ) . '"' . $sel . '>' . esc_html( $text ) . '</option>';
		}
		$h .= '</select><p class="wenpai-field__desc">' . esc_html( $desc ) . '</p></div></div>';
		return $h;
	}

	/**
	 * Two-column checkbox field row (.field).
	 */
	private static function field_row_checkbox( string $name, string $label, string $desc, bool $checked, bool $disabled ): string {
		$id  = 'wpcy-f-' . $name;
		$dis = $disabled ? ' disabled' : '';
		$chk = $checked ? ' checked' : '';
		$h   = '<div class="wenpai-field field wenpai-field--row">';
		$h  .= '<div class="field-label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>';
		$h  .= '<div class="field-ctl"><label class="opt"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . $chk . $dis . '><span>' . esc_html( $desc ) . '</span></label></div></div>';
		return $h;
	}

	/**
	 * Initial SVG chart frame using kit CSS tokens (updated by JS once /stats loads).
	 */
	private static function empty_trend_svg(): string {
		return '<svg data-wenpai-art viewBox="0 0 640 180" width="100%" height="180" role="img" aria-label="' . esc_attr__( '近 7 日加速趋势', 'wp-china-yes' ) . '">'
			. '<g stroke="var(--wenpai-line)" stroke-width="1">'
			. '<line x1="36" y1="24" x2="620" y2="24"/><line x1="36" y1="74" x2="620" y2="74"/>'
			. '<line x1="36" y1="124" x2="620" y2="124"/><line x1="36" y1="164" x2="620" y2="164"/>'
			. '</g>'
			. '<text x="320" y="98" text-anchor="middle" fill="var(--wenpai-ink-3)" font-size="13">' . esc_html__( '加载近 7 日统计中…', 'wp-china-yes' ) . '</text>'
			. '</svg>';
	}
}
