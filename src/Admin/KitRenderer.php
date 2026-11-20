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
			self::render_wizard( $settings, $links, $version );
			return;
		}

		$tab_map = array(
			'wpcy'          => 'overview',
			'wpcy-connect'  => 'settings',
			'wpcy-services' => 'services',
			'wpcy-diagnose' => 'diagnose',
		);
		$view    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
		$current = $network ? 'settings' : ( 'help' === $view ? 'help' : ( $tab_map[ $page ] ?? 'overview' ) );
		$shell   = self::shell_args( $current, $version, $links, $network );

		\Wenpai_Admin_Shell::open( $shell );

		if ( 'help' === $current ) {
			self::render_help( $links );
		} elseif ( 'settings' === $current ) {
			self::render_settings( $settings, $locked, $network );
		} elseif ( 'services' === $current ) {
			self::render_services( $settings, $locked );
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
	 * Build an admin page URL for top-level wpcy menu pages.
	 *
	 * @param string                $slug    Admin page slug.
	 * @param bool                  $network Whether this is a network admin page.
	 * @param array<string, string> $extra   Optional query args.
	 * @return string
	 */
	private static function page_url( string $slug, bool $network = false, array $extra = array() ): string {
		$entry = 'admin.php';
		$args  = array_merge( array( 'page' => $slug ), $extra );
		if ( $network && function_exists( 'network_admin_url' ) && function_exists( 'add_query_arg' ) ) {
			return (string) add_query_arg( $args, network_admin_url( $entry ) );
		}
		if ( function_exists( 'admin_url' ) && function_exists( 'add_query_arg' ) ) {
			return (string) add_query_arg( $args, admin_url( $entry ) );
		}
		$q = '?page=' . rawurlencode( $slug );
		foreach ( $extra as $k => $v ) {
			$q .= '&' . rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
		}
		return $q;
	}

	/**
	 * Render kit SVG icon when available.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	private static function kit_icon( string $name ): string {
		if ( class_exists( '\Wenpai_Admin_Icons', false ) ) {
			return (string) \Wenpai_Admin_Icons::svg( $name );
		}
		return '';
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
		$admin_url = static function ( string $slug, array $extra = array() ) use ( $network ): string {
			return self::page_url( $slug, $network, $extra );
		};

		$tabs = $network
			? array(
				array(
					'id'        => 'settings',
					'label'     => __( '设置', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-connect' ),
					'icon_html' => self::kit_icon( 'settings' ),
				),
			)
			: array(
				array(
					'id'        => 'overview',
					'label'     => __( '概览', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy' ),
					'icon_html' => self::kit_icon( 'overview' ),
				),
				array(
					'id'        => 'settings',
					'label'     => __( '设置', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-connect' ),
					'icon_html' => self::kit_icon( 'settings' ),
				),
				array(
					'id'        => 'services',
					'label'     => __( '服务', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-services' ),
					'icon_html' => self::kit_icon( 'sparkle' ),
				),
				array(
					'id'        => 'diagnose',
					'label'     => __( '诊断', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-diagnose' ),
					'icon_html' => self::kit_icon( 'tools' ),
				),
			);

		$support_url = isset( $links['help'] ) ? (string) $links['help'] : 'https://wpcy.com/go/support';

		return array(
			'slug'               => 'wpcy',
			'name'               => __( '文派叶子', 'wp-china-yes' ),
			'mark_html'          => self::leaf_mark(),
			'home_url'           => $admin_url( $network ? 'wpcy-connect' : 'wpcy' ),
			'current'            => $current,
			'tabs'               => $tabs,
			'help_url'           => $network ? $support_url : $admin_url( 'wpcy', array( 'view' => 'help' ) ),
			'help_label'         => __( '帮助', 'wp-china-yes' ),
			'help_icon_html'     => self::kit_icon( 'help' ),
			'feedback_url'       => $support_url,
			'feedback_label'     => __( '反馈', 'wp-china-yes' ),
			'feedback_icon_html' => self::kit_icon( 'feedback' ),
			'version'            => sprintf( /* translators: %s: version */ __( '文派叶子 %s', 'wp-china-yes' ), $version ),
			'doc_url'            => $support_url,
			'doc_label'          => __( '文档与支持', 'wp-china-yes' ),
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
						array(
							'label'   => __( '前往诊断页', 'wp-china-yes' ),
							'href'    => $diag_url,
							'variant' => 'primary',
						),
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
					array(
						'label' => __( '7 日总加速', 'wp-china-yes' ),
						'value' => '—',
						'unit'  => __( '次', 'wp-china-yes' ),
					),
					array(
						'label' => __( '镜像平均延迟', 'wp-china-yes' ),
						'value' => '—',
						'unit'  => 'ms',
					),
					array(
						'label' => __( '头像代理', 'wp-china-yes' ),
						'value' => '—',
						'unit'  => __( '次', 'wp-china-yes' ),
					),
					array(
						'label' => __( '拦截外部慢请求', 'wp-china-yes' ),
						'value' => '—',
						'unit'  => __( '次', 'wp-china-yes' ),
					),
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
					array(
						'name'    => __( 'WP.org 国内镜像', 'wp-china-yes' ),
						'value'   => '—',
						'percent' => 15,
					),
					array(
						'name'    => __( '公共前端库 CDN', 'wp-china-yes' ),
						'value'   => '—',
						'percent' => 15,
					),
					array(
						'name'    => __( 'Cravatar 节点', 'wp-china-yes' ),
						'value'   => '—',
						'percent' => 15,
					),
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
						'icon'      => 'info',
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
				'meta'    => __( '按当前站点场景自动配好，一般无需频繁调整。', 'wp-china-yes' ),
				'actions' => array(
					array(
						'label'   => __( '调整设置', 'wp-china-yes' ),
						'href'    => $set_url,
						'variant' => 'secondary',
					),
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
			$is_on = $profile === $key;
			$cls   = $is_on ? 'choice is-on' : 'choice';
			echo '<label class="' . esc_attr( $cls ) . '"><input type="radio" name="profile" value="' . esc_attr( $key ) . '"';
			checked( $is_on );
			disabled( $locked );
			echo '><span><span class="t">' . esc_html( $info[0] ) . '</span><span class="d">' . esc_html( $info[1] ) . '</span></span></label>';
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

		$blocklist_html = '<div data-wpcy-blocklist="1">'
			. '<div class="wpcy-inline-bar" style="margin-bottom:10px">'
			. '<label class="wenpai-switch">'
			. '<input type="checkbox" data-wpcy-bl-enabled="1"' . $dis_attr . '>'
			. '<span class="track" aria-hidden="true"></span>'
			. '<span class="wenpai-switch__label">' . esc_html__( '启用自定义外链拦截表（最多 20 条，受保护核心域名不可拦截）', 'wp-china-yes' ) . '</span>'
			. '</label>'
			. '</div>'
			. '<div class="wpcy-inline-bar">'
			. '<input type="text" data-wpcy-bl-host="1" placeholder="' . esc_attr__( '输入域名，例如 telemetry.example.com', 'wp-china-yes' ) . '"' . $dis_attr . '>'
			. '<select data-wpcy-bl-match="1"' . $dis_attr . '>'
			. '<option value="exact">' . esc_html__( '精确匹配', 'wp-china-yes' ) . '</option>'
			. '<option value="suffix">' . esc_html__( '含子域名', 'wp-china-yes' ) . '</option>'
			. '</select>'
			. '<input type="text" data-wpcy-bl-note="1" placeholder="' . esc_attr__( '备注（可选）', 'wp-china-yes' ) . '"' . $dis_attr . '>'
			. \Wenpai_Admin_UI::button_html(
				array(
					'label'    => __( '添加域名', 'wp-china-yes' ),
					'type'     => 'button',
					'variant'  => 'secondary',
					'size'     => 'sm',
					'disabled' => $locked,
					'attrs'    => array( 'data-wpcy-bl-add' => '1' ),
				)
			)
			. '</div>'
			. '<ul class="wpcy-host-list" data-wpcy-bl-list="1"></ul>'
			. '</div>';

		\Wenpai_Admin_UI::fold_list(
			array(
				'items' => array(
					array(
						'icon'      => 'settings',
						'title'     => __( '高级线路与后台体验', 'wp-china-yes' ),
						'desc'      => $summary,
						'body_html' => $adv_html,
					),
					array(
						'icon'      => 'info',
						'title'     => __( '自定义外链拦截表', 'wp-china-yes' ),
						'desc'      => __( '按域名屏蔽拖慢后台的第三方出站请求，与核心白名单自动隔离。', 'wp-china-yes' ),
						'body_html' => $blocklist_html,
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Services screen (Plan 2: 3-column service card grid + per-service detail view).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are locked.
	 */
	private static function render_services( array $settings, bool $locked = false ): void {
		$service = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( (string) $_GET['service'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only detail routing.
		$catalog = self::service_catalog();
		$reports = self::service_status_reports( $settings );
		if ( '' !== $service && isset( $catalog[ $service ] ) ) {
			self::render_service_detail( $service, $catalog[ $service ], $settings, $locked, $reports[ $service ] ?? array() );
			return;
		}

		$profile   = (string) ( $settings['profile'] ?? 'domestic' );
		$conn      = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror    = (string) ( $conn['wporg_mirror'] ?? 'auto' );
		$scope     = (string) ( $settings['public_assets']['scope'] ?? 'both' );
		$avatar    = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat = 'on' === (string) ( $conn['heartbeat'] ?? 'on' );
		$feeds     = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'block' );

		$builtin_active = array(
			'wporg'    => 'off' !== $mirror,
			'admincdn' => 'off' !== $scope,
			'cravatar' => 'off' !== $avatar,
			'noise'    => $heartbeat || $feeds,
		);

		$scope_labels    = array(
			'both'     => __( '前台与后台全量加速', 'wp-china-yes' ),
			'admin'    => __( '仅加速 WordPress 后台', 'wp-china-yes' ),
			'frontend' => __( '仅加速站点前台', 'wp-china-yes' ),
			'off'      => __( '当前未开启', 'wp-china-yes' ),
		);
		$avatar_labels   = array(
			'cravatar_cn'     => __( 'Cravatar 中国节点', 'wp-china-yes' ),
			'cravatar_global' => __( 'Cravatar 全球节点', 'wp-china-yes' ),
			'off'             => __( '使用默认 Gravatar', 'wp-china-yes' ),
		);
		$builtin_metrics = array(
			'wporg'    => 'off' === $mirror ? __( '直连 WordPress.org 官方源', 'wp-china-yes' ) : __( 'WenPai.org 镜像 · 异常自动回退', 'wp-china-yes' ),
			'admincdn' => $scope_labels[ $scope ] ?? $scope_labels['both'],
			'cravatar' => $avatar_labels[ $avatar ] ?? $avatar_labels['cravatar_cn'],
			'noise'    => ( $heartbeat || $feeds ) ? __( '已启用心跳降频或外部源屏蔽', 'wp-china-yes' ) : __( '默认保持原样，可按需开启', 'wp-china-yes' ),
		);

		$scene_recommended = array(
			'domestic'    => array( 'compliance', 'slug', 'windfonts' ),
			'crossborder' => array( 'converter', 'ai', 'storage' ),
			'mixed'       => array( 'ai', 'storage', 'converter' ),
		);
		$recommended_ids   = $scene_recommended[ $profile ] ?? $scene_recommended['domestic'];

		$profile_labels = array(
			'domestic'    => __( '国内站', 'wp-china-yes' ),
			'crossborder' => __( '跨境站', 'wp-china-yes' ),
			'mixed'       => __( '混合站', 'wp-china-yes' ),
		);
		$profile_label  = $profile_labels[ $profile ] ?? $profile_labels['domestic'];

		$plugin_states = array();
		foreach ( $catalog as $id => $spec ) {
			$plugin_states[ $id ] = self::detect_companion_plugin( $spec['plugin_file'], $spec['plugin_slug'] );
		}

		\Wenpai_Admin_Shell::page_head(
			__( '服务', 'wp-china-yes' ),
			__( '文派叶子内置 4 项基础加速，并按站点环境智能推荐可联动的文派独立插件。', 'wp-china-yes' ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '绑定本站', 'wp-china-yes' ),
					'variant' => 'primary',
					'attrs'   => array( 'data-wpcy-action' => 'bind' ),
				)
			)
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="services">';

		// Section 1: 2x2 featured grid for the 4 built-in core services.
		echo '<div class="wpcy-core-grid">';
		foreach ( $catalog as $id => $spec ) {
			if ( 'builtin' !== ( $spec['group'] ?? 'ext' ) ) {
				continue;
			}
			$plugin_state = $plugin_states[ $id ];
			$report       = isset( $reports[ $id ] ) && is_array( $reports[ $id ] ) ? $reports[ $id ] : array();
			$detail_url   = self::page_url( 'wpcy-services', false, array( 'service' => $id ) );
			$is_on        = ! empty( $builtin_active[ $id ] );
			$is_ok        = $plugin_state['active'] || $is_on;

			if ( ! empty( $report['pill'] ) ) {
				$pill_text  = (string) $report['pill'];
				$pill_state = ! empty( $report['state'] ) ? (string) $report['state'] : 'ok';
			} elseif ( $plugin_state['active'] ) {
				$pill_text  = __( '已接管', 'wp-china-yes' );
				$pill_state = 'ok';
			} else {
				$pill_text  = $is_on ? __( '已开启', 'wp-china-yes' ) : __( '已关闭', 'wp-china-yes' );
				$pill_state = $is_on ? 'ok' : 'mute';
			}

			$metric_text = ! empty( $report['summary'] )
				? (string) $report['summary']
				: ( $builtin_metrics[ $id ] ?? '' );

			echo '<div class="wpcy-core-card">';
			echo '<div class="wpcy-core-card__top">';
			echo '<span class="wpcy-core-card__icon' . ( $is_ok ? ' is-ok' : '' ) . '">';
			echo \Wenpai_Admin_Icons::svg( $spec['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized kit SVG helper.
			echo '</span>';
			echo '<div class="wpcy-core-card__head">';
			echo '<h3 class="wpcy-core-card__title">' . esc_html( $spec['title'] ) . '</h3>';
			echo '<p class="wpcy-core-card__desc">' . esc_html( $spec['short_desc'] ) . '</p>';
			echo '</div>';
			echo '<span class="pill ' . esc_attr( $pill_state ) . '"><span class="dot" aria-hidden="true"></span>' . esc_html( $pill_text ) . '</span>';
			echo '</div>';
			echo '<div class="wpcy-core-card__foot">';
			echo '<span class="wpcy-core-card__metric">' . esc_html( $metric_text ) . '</span>';
			$btn_html = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '设置', 'wp-china-yes' ),
					'href'    => $detail_url,
					'variant' => 'secondary',
					'size'    => 'sm',
				)
			);
			echo $btn_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit button helper escapes attributes and label.
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';

		// Partition extension services into featured (installed/active OR recommended for current site scene) vs collapsed rest.
		$featured_ext = array();
		$other_ext    = array();
		foreach ( $catalog as $id => $spec ) {
			if ( 'ext' !== ( $spec['group'] ?? 'ext' ) ) {
				continue;
			}
			$st = $plugin_states[ $id ];
			if ( $st['active'] || $st['installed'] || in_array( $id, $recommended_ids, true ) ) {
				$featured_ext[ $id ] = $spec;
			} else {
				$other_ext[ $id ] = $spec;
			}
		}

		// Section 2: Scene-tailored recommended & active extensions (visible by default).
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => sprintf( /* translators: %s: site scene label */ __( '适合当前「%s」的扩展', 'wp-china-yes' ), $profile_label ),
				'sub'   => __( '根据本站场景精选；已安装的独立插件会自动置顶并同步运行状态。', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		foreach ( $featured_ext as $id => $spec ) {
			self::render_extension_row( $id, $spec, $plugin_states[ $id ], $reports[ $id ] ?? array() );
		}

		// Collapsed fold for remaining ecosystem extensions.
		if ( ! empty( $other_ext ) ) {
			$other_titles = array();
			foreach ( $other_ext as $other_spec ) {
				$other_titles[] = $other_spec['title'];
			}
			ob_start();
			foreach ( $other_ext as $id => $spec ) {
				self::render_extension_row( $id, $spec, $plugin_states[ $id ], $reports[ $id ] ?? array() );
			}
			$other_html = (string) ob_get_clean();

			\Wenpai_Admin_UI::fold_list(
				array(
					'items' => array(
						array(
							'icon'      => 'sparkle',
							'title'     => sprintf( /* translators: %d: count of hidden extensions */ __( '更多生态扩展（%d 项）', 'wp-china-yes' ), count( $other_ext ) ),
							'desc'      => implode( ' / ', $other_titles ),
							'body_html' => $other_html,
						),
					),
				)
			);
		}
		\Wenpai_Admin_UI::card_close();
		echo '</div>';
	}

	/**
	 * Render one ecosystem extension row inside the Services card.
	 *
	 * @param string                                                $id           Service id.
	 * @param array<string, string>                                 $spec         Service catalog entry.
	 * @param array{installed: bool, active: bool, version: string} $plugin_state Companion plugin state.
	 * @param array<string, string>                                 $report       Optional status report.
	 */
	private static function render_extension_row( string $id, array $spec, array $plugin_state, array $report ): void {
		$detail_url = self::page_url( 'wpcy-services', false, array( 'service' => $id ) );

		if ( ! empty( $report['pill'] ) ) {
			$pill      = array(
				'text'  => (string) $report['pill'],
				'state' => ! empty( $report['state'] ) ? (string) $report['state'] : 'ok',
			);
			$row_state = 'ok' === $pill['state'] ? 'ok' : '';
		} elseif ( $plugin_state['active'] ) {
			$pill      = array(
				'text'  => __( '已接管', 'wp-china-yes' ),
				'state' => 'ok',
			);
			$row_state = 'ok';
		} elseif ( $plugin_state['installed'] ) {
			$pill      = array(
				'text'  => __( '未启用', 'wp-china-yes' ),
				'state' => 'warn',
			);
			$row_state = 'warn';
		} else {
			$pill      = array(
				'text'  => __( '未安装', 'wp-china-yes' ),
				'state' => 'mute',
			);
			$row_state = '';
		}

		$row_desc = ! empty( $report['summary'] ) ? (string) $report['summary'] : $spec['short_desc'];
		$btn_text = $plugin_state['active'] ? __( '管理', 'wp-china-yes' ) : __( '详情', 'wp-china-yes' );

		\Wenpai_Admin_UI::row(
			array(
				'icon'         => $spec['icon'],
				'state'        => $row_state,
				'title'        => $spec['title'],
				'desc'         => $row_desc,
				'pill'         => $pill,
				'control_html' => \Wenpai_Admin_UI::button_html(
					array(
						'label'   => $btn_text,
						'href'    => $detail_url,
						'variant' => 'secondary',
						'size'    => 'sm',
					)
				),
			)
		);
	}

	/**
	 * Collect live status reports from companion WenPai standalone plugins.
	 *
	 * Companion plugins may hook `wpcy_service_status` and return an entry keyed by service id
	 * (`wporg`, `admincdn`, `cravatar`, `noise`, `ai`, `storage`, `compliance`, `windfonts`,
	 * `motucloud`, `slug`, `converter`) with optional `summary`, `pill`, `state`, `action_label`,
	 * and `action_url` strings.
	 *
	 * @param array<string, mixed> $settings Current settings snapshot.
	 * @return array<string, array<string, string>>
	 */
	private static function service_status_reports( array $settings ): array {
		if ( ! function_exists( 'apply_filters' ) ) {
			return array();
		}
		$raw = apply_filters( 'wpcy_service_status', array(), $settings );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$clean = array();
		foreach ( $raw as $key => $entry ) {
			if ( ! is_string( $key ) || ! is_array( $entry ) ) {
				continue;
			}
			$clean[ sanitize_key( $key ) ] = array(
				'summary'      => isset( $entry['summary'] ) ? sanitize_text_field( (string) $entry['summary'] ) : '',
				'pill'         => isset( $entry['pill'] ) ? sanitize_text_field( (string) $entry['pill'] ) : '',
				'state'        => isset( $entry['state'] ) ? sanitize_key( (string) $entry['state'] ) : 'ok',
				'action_label' => isset( $entry['action_label'] ) ? sanitize_text_field( (string) $entry['action_label'] ) : '',
				'action_url'   => isset( $entry['action_url'] ) ? esc_url_raw( (string) $entry['action_url'] ) : '',
			);
		}
		return $clean;
	}

	/**
	 * Service catalog metadata and companion standalone plugin definitions.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function service_catalog(): array {
		return array(
			'wporg'      => array(
				'group'         => 'builtin',
				'title'         => __( 'WP.org 镜像', 'wp-china-yes' ),
				'icon'          => 'cloud',
				'short_desc'    => __( '核心、主题、插件与翻译镜像 · WenPai.org', 'wp-china-yes' ),
				'desc'          => __( '核心、主题、插件与翻译包走 WenPai.org 国内镜像，含图标与截图代理。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派云桥 (WPBridge)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpbridge',
				'plugin_file'   => 'wpbridge/wpbridge.php',
				'plugin_admin'  => 'admin.php?page=wpbridge',
				'plugin_url'    => 'https://wpcy.com/bridge',
				'plugin_pitch'  => __( '在后台直接检索文派生态插件与主题目录，并管理自定义更新源。', 'wp-china-yes' ),
				'takeover_desc' => __( '生态目录检索与自定义更新通道已由文派云桥 (WPBridge) 联动接管。', 'wp-china-yes' ),
			),
			'admincdn'   => array(
				'group'         => 'builtin',
				'title'         => __( '公共前端库', 'wp-china-yes' ),
				'icon'          => 'flash',
				'short_desc'    => __( '公共前端库与核心静态加速 · adminCDN', 'wp-china-yes' ),
				'desc'          => __( '由 adminCDN 提供公共前端库与 WordPress 核心静态资源国内加速。', 'wp-china-yes' ),
				'plugin_name'   => __( '萌芽静态加速 (WPStatic)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpstatic',
				'plugin_file'   => 'wpstatic/wpstatic.php',
				'plugin_admin'  => 'options-general.php?page=wpstatic',
				'plugin_url'    => 'https://wpcy.com/static',
				'plugin_pitch'  => __( '将 wp-admin / wp-includes 核心 CSS/JS 按版本钉死至 wpstatic.admincdn.com 并细粒度管理白名单。', 'wp-china-yes' ),
				'takeover_desc' => __( '核心静态资源版本钉死与白名单策略已由 WPStatic 联动接管。', 'wp-china-yes' ),
			),
			'cravatar'   => array(
				'group'         => 'builtin',
				'title'         => __( 'Cravatar 头像', 'wp-china-yes' ),
				'icon'          => 'image',
				'short_desc'    => __( 'Gravatar 国内替代，双线加速 · Cravatar', 'wp-china-yes' ),
				'desc'          => __( '替代 Gravatar，支持国内外双线路由，解决评论与后台头像空白。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派头像 (WPAvatar)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpavatar',
				'plugin_file'   => 'wpavatar/wpavatar.php',
				'plugin_admin'  => 'options-general.php?page=wpavatar',
				'plugin_url'    => 'https://wpcy.com/avatar',
				'plugin_pitch'  => __( '解锁本地上传头像、中文首字生成头像、SVG 默认头像与本地缓存预热。', 'wp-china-yes' ),
				'takeover_desc' => __( '高级头像生成、本地上传与缓存策略已由文派头像 (WPAvatar) 联动接管。', 'wp-china-yes' ),
			),
			'noise'      => array(
				'group'         => 'builtin',
				'title'         => __( '后台减噪', 'wp-china-yes' ),
				'icon'          => 'settings',
				'short_desc'    => __( '心跳降频与屏蔽外部慢源 · WPComments', 'wp-china-yes' ),
				'desc'          => __( '降低后台心跳频率、屏蔽仪表盘外部慢请求，并可联动评论治理。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派评论 (WPComments)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpcomments',
				'plugin_file'   => 'wpcomments/wpcomments.php',
				'plugin_admin'  => 'options-general.php?page=wpcomments',
				'plugin_url'    => 'https://wpcy.com/comments',
				'plugin_pitch'  => __( '提供评论审核工作流、常用回复模板与反垃圾评论治理。', 'wp-china-yes' ),
				'takeover_desc' => __( '评论审核与常用回复工作流已由文派评论 (WPComments) 联动接管。', 'wp-china-yes' ),
			),
			'ai'         => array(
				'group'         => 'ext',
				'title'         => __( 'AI 智能', 'wp-china-yes' ),
				'icon'          => 'sparkle',
				'short_desc'    => __( '多模型 AI 网关与 GEO 引擎优化 · WPMind', 'wp-china-yes' ),
				'desc'          => __( '接入 DeepSeek、通义千问、OpenAI 等多模型 AI 网关与 GEO 优化。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派心思 (WPMind)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpmind',
				'plugin_file'   => 'wpmind/wpmind.php',
				'plugin_admin'  => 'admin.php?page=wpmind',
				'plugin_url'    => 'https://wpcy.com/mind',
				'plugin_pitch'  => __( '提供多服务商故障自动切换路由、GEO 结构化优化、AI 写作辅助与用量统计。', 'wp-china-yes' ),
				'takeover_desc' => __( '多模型 AI 路由、GEO 优化与智能工作流已由文派心思 (WPMind) 联动接管。', 'wp-china-yes' ),
			),
			'storage'    => array(
				'group'         => 'ext',
				'title'         => __( '对象存储', 'wp-china-yes' ),
				'icon'          => 'server',
				'short_desc'    => __( '媒体库自动同步至 OSS / COS / S3 · WPSSS', 'wp-china-yes' ),
				'desc'          => __( '将本站媒体库与静态附件自动卸载至阿里云 OSS、腾讯云 COS 或 S3 兼容存储。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派智储 (WPSSS)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpsss',
				'plugin_file'   => 'wpsss/wpsss.php',
				'plugin_admin'  => 'options-general.php?page=wpsss',
				'plugin_url'    => 'https://wpcy.com/wpsss',
				'plugin_pitch'  => __( '一键配置主流对象存储桶、自定义 CDN 域名、媒体同步队列与体检工具。', 'wp-china-yes' ),
				'takeover_desc' => __( '媒体库对象存储卸载与 CDN 域名改写已由文派智储 (WPSSS) 联动接管。', 'wp-china-yes' ),
			),
			'compliance' => array(
				'group'         => 'ext',
				'title'         => __( '政策合规', 'wp-china-yes' ),
				'icon'          => 'info',
				'short_desc'    => __( 'ICP 与公安联网备案标识公示 · WPICP', 'wp-china-yes' ),
				'desc'          => __( '规范展示工信部 ICP 备案号、公安网安备案标识与合规信息。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派备案 (WPICP)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpicp',
				'plugin_file'   => 'wpicp/wpicp.php',
				'plugin_admin'  => 'options-general.php?page=wpicp',
				'plugin_url'    => 'https://wpcy.com/icp',
				'plugin_pitch'  => __( '在前台页脚自动展示 ICP 备案号、公安备案图标与许可证，支持状态巡检。', 'wp-china-yes' ),
				'takeover_desc' => __( '前台 ICP 与公安备案合规展示已由文派备案 (WPICP) 联动接管。', 'wp-china-yes' ),
			),
			'windfonts'  => array(
				'group'         => 'ext',
				'title'         => __( '中文字体', 'wp-china-yes' ),
				'icon'          => 'feather',
				'short_desc'    => __( '云端 WOFF2 动态子集字体 · Windfonts', 'wp-china-yes' ),
				'desc'          => __( '由 Windfonts 提供云端中文字体动态子集切片与国内高速分发。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派字库 (WPFonts)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpfonts',
				'plugin_file'   => 'wpfonts/wpfonts.php',
				'plugin_admin'  => 'admin.php?page=wpfonts',
				'plugin_url'    => 'https://wpcy.com/webfonts',
				'plugin_pitch'  => __( '浏览免费商用字库，按标题/正文选择器绑定字体并集成古腾堡编辑器字体库。', 'wp-china-yes' ),
				'takeover_desc' => __( '字库浏览、CSS 选择器绑定与编辑器集成已由文派字库 (WPFonts) 联动接管。', 'wp-china-yes' ),
			),
			'motucloud'  => array(
				'group'         => 'ext',
				'title'         => __( '图标与图库', 'wp-china-yes' ),
				'icon'          => 'image',
				'short_desc'    => __( 'MotuCloud 图标与免版权图库镜像 · WPPhoto', 'wp-china-yes' ),
				'desc'          => __( '由 MotuCloud（墨图云集）提供 SVG 图标与免版权图库国内镜像，由 WPPhoto 统一承载。', 'wp-china-yes' ),
				'plugin_name'   => __( '墨图图库 (WPPhoto)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpphoto',
				'plugin_file'   => 'wpphoto/wpphoto.php',
				'plugin_admin'  => 'admin.php?page=wpphoto',
				'plugin_url'    => 'https://wpcy.com/photo',
				'plugin_pitch'  => __( '在古腾堡编辑器与媒体库中直接搜索并插入墨图爱可 SVG 图标与免版权高清图片。', 'wp-china-yes' ),
				'takeover_desc' => __( '编辑器图标选择器与免版权图库已由墨图图库 (WPPhoto) 联动接管。', 'wp-china-yes' ),
			),
			'slug'       => array(
				'group'         => 'ext',
				'title'         => __( '中文体验优化', 'wp-china-yes' ),
				'icon'          => 'equalizer',
				'short_desc'    => __( '中文标题转拼音/英文别名与媒体净化 · WPSlug', 'wp-china-yes' ),
				'desc'          => __( '自动将文章、分类与媒体中文名转换为拼音或英文固定链接别名。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派素格 (WPSlug)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpslug',
				'plugin_file'   => 'wpslug/wpslug.php',
				'plugin_admin'  => 'options-general.php?page=wpslug',
				'plugin_url'    => 'https://wpcy.com/slug',
				'plugin_pitch'  => __( '支持拼音/翻译模式、批量转换历史别名、自定义词典与中文文件名净化。', 'wp-china-yes' ),
				'takeover_desc' => __( '中文固定链接别名转换与词典规则已由文派素格 (WPSlug) 联动接管。', 'wp-china-yes' ),
			),
			'converter'  => array(
				'group'         => 'ext',
				'title'         => __( '中文简繁转换', 'wp-china-yes' ),
				'icon'          => 'map',
				'short_desc'    => __( '多地区中文简繁自动转换与词汇适配 · WPCC', 'wp-china-yes' ),
				'desc'          => __( '面向大陆、港澳台与海外华人访客的中文简繁转换与多语 SEO。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派译词 (WPCC)', 'wp-china-yes' ),
				'plugin_slug'   => 'wp-chinese-converter',
				'plugin_file'   => 'wp-chinese-converter/wp-chinese-converter.php',
				'plugin_admin'  => 'options-general.php?page=wp-chinese-converter',
				'plugin_url'    => 'https://wpcy.com/chinese-converter',
				'plugin_pitch'  => __( '基于 OpenCC 词汇表支持 6 种中文变体转换、浏览器语言识别与 hreflang SEO。', 'wp-china-yes' ),
				'takeover_desc' => __( '前台多地区简繁转换与固定链接适配已由文派译词 (WPCC) 联动接管。', 'wp-china-yes' ),
			),
		);
	}

	/**
	 * Detect whether a companion standalone plugin is installed and active.
	 *
	 * @param string $plugin_file Relative plugin file path.
	 * @param string $plugin_slug Plugin directory slug.
	 * @return array{installed: bool, active: bool, version: string}
	 */
	private static function detect_companion_plugin( string $plugin_file, string $plugin_slug ): array {
		$installed = false;
		$active    = false;
		$version   = '';

		if ( function_exists( 'get_plugins' ) ) {
			$all = get_plugins();
			if ( isset( $all[ $plugin_file ] ) ) {
				$installed = true;
				$version   = isset( $all[ $plugin_file ]['Version'] ) ? (string) $all[ $plugin_file ]['Version'] : '';
			} else {
				foreach ( $all as $file => $data ) {
					if ( 0 === strpos( (string) $file, $plugin_slug . '/' ) ) {
						$installed   = true;
						$plugin_file = (string) $file;
						$version     = isset( $data['Version'] ) ? (string) $data['Version'] : '';
						break;
					}
				}
			}
		}

		if ( $installed && function_exists( 'is_plugin_active' ) ) {
			$active = is_plugin_active( $plugin_file );
		} elseif ( $installed && function_exists( 'get_option' ) ) {
			$act    = (array) get_option( 'active_plugins', array() );
			$active = in_array( $plugin_file, $act, true );
		}

		return array(
			'installed' => $installed,
			'active'    => $active,
			'version'   => $version,
		);
	}

	/**
	 * Render individual service settings + companion standalone plugin linkage page.
	 *
	 * @param string                $id       Service id.
	 * @param array<string, string> $spec     Service catalog entry.
	 * @param array<string, mixed>  $settings Settings snapshot.
	 * @param bool                  $locked   Whether subsite settings are locked.
	 * @param array<string, string> $report   Optional live status report from `wpcy_service_status`.
	 */
	private static function render_service_detail( string $id, array $spec, array $settings, bool $locked, array $report = array() ): void {
		$back_url     = self::page_url( 'wpcy-services' );
		$plugin_state = self::detect_companion_plugin( $spec['plugin_file'], $spec['plugin_slug'] );
		$conn         = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror       = (string) ( $conn['wporg_mirror'] ?? 'auto' );
		$avatar       = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat    = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds        = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$scope        = isset( $settings['public_assets']['scope'] ) ? (string) $settings['public_assets']['scope'] : 'both';
		$profile      = (string) ( $settings['profile'] ?? 'domestic' );

		$head_actions = \Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '返回服务列表', 'wp-china-yes' ),
				'href'    => $back_url,
				'variant' => 'ghost',
			)
		);
		$saveable     = in_array( $id, array( 'wporg', 'admincdn', 'cravatar', 'noise' ), true );
		if ( $saveable && ! $locked ) {
			$head_actions .= ' ' . \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '保存此项设置', 'wp-china-yes' ),
					'type'    => 'submit',
					'variant' => 'primary',
					'form'    => 'wpcy-settings-form',
				)
			);
		}

		\Wenpai_Admin_Shell::page_head( $spec['title'], $spec['desc'], $head_actions );

		echo '<div class="wenpai-stack" data-wpcy-screen="service-detail">';
		if ( $saveable ) {
			echo '<form id="wpcy-settings-form" class="wenpai-stack" data-wpcy-network="0">';
			// Hidden baseline inputs so form submission preserves full settings state.
			echo '<input type="hidden" name="profile" value="' . esc_attr( $profile ) . '">';
			if ( 'wporg' !== $id ) {
				echo '<input type="hidden" name="wporg_mirror" value="' . esc_attr( $mirror ) . '">';
			}
			if ( 'admincdn' !== $id ) {
				echo '<input type="hidden" name="public_assets_scope" value="' . esc_attr( $scope ) . '">';
			}
			if ( 'cravatar' !== $id ) {
				echo '<input type="hidden" name="avatar_source" value="' . esc_attr( $avatar ) . '">';
			}
			if ( 'noise' !== $id ) {
				if ( $heartbeat ) {
					echo '<input type="hidden" name="heartbeat" value="1">';
				}
				if ( $feeds ) {
					echo '<input type="hidden" name="dashboard_feeds" value="1">';
				}
			}

			// Card 1: Built-in wpcy settings for this service.
			\Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '基础设置', 'wp-china-yes' ),
					'tile'  => false,
				)
			);

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside field_row_* helpers.
			if ( 'wporg' === $id ) {
				echo self::field_row_select(
					'wporg_mirror',
					__( 'WordPress.org 镜像策略', 'wp-china-yes' ),
					__( '国内镜像异常时自动回退官方源。', 'wp-china-yes' ),
					array(
						'auto' => __( '国内镜像（自动回退官方源）', 'wp-china-yes' ),
						'off'  => __( '直连 WordPress.org 官方源', 'wp-china-yes' ),
					),
					$mirror,
					$locked
				);
			} elseif ( 'cravatar' === $id ) {
				echo self::field_row_select(
					'avatar_source',
					__( 'Cravatar 头像加速线路', 'wp-china-yes' ),
					__( '替代 Gravatar，支持 WebP 与国内外双线。', 'wp-china-yes' ),
					array(
						'cravatar_cn'     => __( 'Cravatar 中国线路（国内节点）', 'wp-china-yes' ),
						'cravatar_global' => __( 'Cravatar 国际线路（全球节点）', 'wp-china-yes' ),
						'off'             => __( '关闭（保留 Gravatar 原始地址）', 'wp-china-yes' ),
					),
					$avatar,
					$locked
				);
			} elseif ( 'admincdn' === $id ) {
				echo self::field_row_select(
					'public_assets_scope',
					__( 'adminCDN 公共前端库加速范围', 'wp-china-yes' ),
					__( '加速 Google Fonts、CDNJS、jsDelivr 与 Emoji。', 'wp-china-yes' ),
					array(
						'both'     => __( '后台与前台同时加速（推荐）', 'wp-china-yes' ),
						'admin'    => __( '仅加速 WordPress 后台', 'wp-china-yes' ),
						'frontend' => __( '仅加速站点前台', 'wp-china-yes' ),
						'off'      => __( '关闭公共前端库加速', 'wp-china-yes' ),
					),
					$scope,
					$locked
				);
			} elseif ( 'noise' === $id ) {
				echo self::field_row_checkbox( 'heartbeat', __( '减少后台心跳 (Heartbeat)', 'wp-china-yes' ), __( '仪表盘停止轮询，编辑器降频为 60 秒一次。', 'wp-china-yes' ), $heartbeat, $locked );
				echo self::field_row_checkbox( 'dashboard_feeds', __( '屏蔽仪表盘外部慢请求', 'wp-china-yes' ), __( '阻止仪表盘加载官方英文新闻与活动源。', 'wp-china-yes' ), $feeds, $locked );
			}
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			\Wenpai_Admin_UI::card_close();
			echo '</form>';
		}

		// Companion standalone plugin linkage card (Install prompt vs Active takeover).
		$plugin_url = ! empty( $spec['plugin_url'] ) ? $spec['plugin_url'] : 'https://wpcy.com/';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => $saveable ? __( '高级扩展联动', 'wp-china-yes' ) : __( '独立插件联动', 'wp-china-yes' ),
				'sub'   => $plugin_state['active']
					? __( '独立插件已启用，文派叶子已自动与其建立联动。', 'wp-china-yes' )
					: __( '安装对应独立插件以开启完整设置与高级功能。', 'wp-china-yes' ),
				'tile'  => false,
			)
		);

		if ( $plugin_state['active'] ) {
			$ver_label    = '' !== $plugin_state['version'] ? ' v' . $plugin_state['version'] : '';
			$admin_link   = ! empty( $report['action_url'] )
				? $report['action_url']
				: ( function_exists( 'admin_url' ) ? admin_url( $spec['plugin_admin'] ) : $spec['plugin_admin'] );
			$action_label = ! empty( $report['action_label'] ) ? $report['action_label'] : __( '打开高级设置', 'wp-china-yes' );
			$active_desc  = ! empty( $report['summary'] ) ? $report['summary'] : $spec['takeover_desc'];
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'sparkle',
					'state'        => 'ok',
					'title'        => $spec['plugin_name'] . $ver_label,
					'desc'         => $active_desc,
					'pill'         => array(
						'text'  => ! empty( $report['pill'] ) ? $report['pill'] : __( '已安装并接管', 'wp-china-yes' ),
						'state' => ! empty( $report['state'] ) ? $report['state'] : 'ok',
					),
					'control_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => $action_label,
							'href'    => $admin_link,
							'variant' => 'primary',
						)
					),
				)
			);
		} elseif ( $plugin_state['installed'] ) {
			$plugins_url = function_exists( 'admin_url' ) ? admin_url( 'plugins.php' ) : 'plugins.php';
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'plug',
					'state'        => 'warn',
					'title'        => $spec['plugin_name'],
					'desc'         => $spec['plugin_pitch'],
					'pill'         => array(
						'text'  => __( '已安装未启用', 'wp-china-yes' ),
						'state' => 'warn',
					),
					'control_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '前往插件页启用', 'wp-china-yes' ),
							'href'    => $plugins_url,
							'variant' => 'primary',
						)
					),
				)
			);
		} else {
			$install_url = function_exists( 'admin_url' )
				? add_query_arg(
					array(
						's'    => $spec['plugin_slug'],
						'tab'  => 'search',
						'type' => 'term',
					),
					admin_url( 'plugin-install.php' )
				)
				: 'plugin-install.php';
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'download',
					'title'        => $spec['plugin_name'],
					'desc'         => $spec['plugin_pitch'],
					'pill'         => array(
						'text'  => __( '未安装', 'wp-china-yes' ),
						'state' => 'mute',
					),
					'control_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '查看插件页', 'wp-china-yes' ),
							'href'    => $plugin_url,
							'variant' => 'ghost',
							'attrs'   => array(
								'target' => '_blank',
								'rel'    => 'noopener noreferrer',
							),
						)
					) . ' ' . \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '安装独立插件', 'wp-china-yes' ),
							'href'    => $install_url,
							'variant' => 'secondary',
						)
					),
				)
			);
		}
		\Wenpai_Admin_UI::card_close();
		echo '</div>';
	}

	/**
	 * Built-in Help screen (?page=wpcy&view=help) triggered by top-right Help button.
	 *
	 * @param array<string, mixed> $links External links.
	 */
	private static function render_help( array $links ): void {
		$support_url = isset( $links['help'] ) ? (string) $links['help'] : 'https://wpcy.com/go/support';
		\Wenpai_Admin_Shell::page_head(
			__( '帮助与文档', 'wp-china-yes' ),
			__( '常见问题解答、文派独立插件联动说明与故障排查指南。', 'wp-china-yes' ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '前往诊断页', 'wp-china-yes' ),
					'href'    => self::page_url( 'wpcy-diagnose' ),
					'variant' => 'secondary',
				)
			)
		);

		\Wenpai_Admin_UI::card_open();
		\Wenpai_Admin_UI::help(
			array(
				'groups' => array(
					array(
						'title'       => __( '场景与加速原理', 'wp-china-yes' ),
						'icon'        => 'cloud',
						'desc'        => __( '国内站、跨境站与混合站如何选择，以及镜像回退机制。', 'wp-china-yes' ),
						'collapsible' => true,
						'open'        => true,
						'steps'       => array(
							array(
								'title' => __( '三种站点场景有什么区别？', 'wp-china-yes' ),
								'body'  => __( '「国内站」同时加速后台与前台访客；「跨境站」仅加速国内管理员后台，前台保持海外直连；「混合站」允许分别指定前后台头像与公共库线路。', 'wp-china-yes' ),
							),
							array(
								'title' => __( '国内镜像偶发不可用时会影响更新吗？', 'wp-china-yes' ),
								'body'  => __( '不会。文派叶子 4.0 内置健康探针，当国内镜像超时或返回非 200 时会自动回退到 WordPress.org 官方源，并在概览页提示回退状态。', 'wp-china-yes' ),
							),
						),
					),
					array(
						'title'       => __( '独立插件联动机制', 'wp-china-yes' ),
						'icon'        => 'sparkle',
						'desc'        => __( '文派叶子与文派头像、文风字体、文派智储、文派心思、文派别名的协同分工。', 'wp-china-yes' ),
						'collapsible' => true,
						'open'        => true,
						'steps'       => array(
							array(
								'title' => __( '安装了独立插件（如文派头像 WPAvatar）后会和文派叶子冲突吗？', 'wp-china-yes' ),
								'body'  => __( '不仅不会冲突，还会自动协同：文派叶子负责底层节点加速与连通性巡检，检测到独立插件启用后，会在「服务」详情页显示「已安装并接管」，由独立插件提供完整的高级自定义设置。', 'wp-china-yes' ),
							),
							array(
								'title' => __( '没安装独立插件时可以正常使用基础加速吗？', 'wp-china-yes' ),
								'body'  => __( '完全可以。文派叶子自带开箱即用的 WP.org 镜像、Cravatar 头像替换、前端公共库加速与后台减噪；只有需要高级自定义（如本地上传头像、全量字库绑定、OSS 媒体上云、AI 路由）时才需按引导安装对应独立插件。', 'wp-china-yes' ),
							),
						),
					),
				),
				'links'  => array(
					array(
						'label' => __( '官方文档中心', 'wp-china-yes' ),
						'href'  => 'https://wpcy.com/document',
					),
					array(
						'label' => __( '社区支持与反馈', 'wp-china-yes' ),
						'href'  => $support_url,
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
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
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '服务器到各源', 'wp-china-yes' ),
				'icon'  => 'server',
			)
		);
		\Wenpai_Admin_UI::status_list(
			array(
				'attrs' => array( 'data-wpcy-diag-server' => '1' ),
				'items' => array(
					array(
						'label' => __( 'WP.org 国内镜像', 'wp-china-yes' ),
						'value' => __( '待检测', 'wp-china-yes' ),
					),
					array(
						'label' => __( 'WP.org 官方源', 'wp-china-yes' ),
						'value' => __( '待检测', 'wp-china-yes' ),
					),
					array(
						'label' => __( 'Cravatar API', 'wp-china-yes' ),
						'value' => __( '待检测', 'wp-china-yes' ),
					),
					array(
						'label' => __( '公共前端库源', 'wp-china-yes' ),
						'value' => __( '待检测', 'wp-china-yes' ),
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '浏览器到各源', 'wp-china-yes' ),
				'icon'  => 'eye',
			)
		);
		\Wenpai_Admin_UI::status_list(
			array(
				'attrs' => array( 'data-wpcy-diag-browser' => '1' ),
				'items' => array(
					array(
						'label' => __( 'Cravatar 头像节点', 'wp-china-yes' ),
						'value' => __( '待检测', 'wp-china-yes' ),
					),
					array(
						'label' => __( '公共前端库 CDN', 'wp-china-yes' ),
						'value' => __( '待检测', 'wp-china-yes' ),
					),
					array(
						'label' => __( 'Windfonts 字体源', 'wp-china-yes' ),
						'value' => __( '待检测', 'wp-china-yes' ),
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '出站 URL 路由检测', 'wp-china-yes' ),
				'sub'   => __( '输入任意外部网址，实时查看文派叶子对其的放行、镜像改写或拦截策略。', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		echo '<div data-wpcy-url-tester="1">';
		echo '<div class="wpcy-inline-bar">';
		echo '<input type="url" data-wpcy-url-input="1" placeholder="https://api.wordpress.org/core/version-check/1.7/" aria-label="' . esc_attr__( '待检测网址', 'wp-china-yes' ) . '">';
		$test_btn = \Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '检测策略', 'wp-china-yes' ),
				'type'    => 'button',
				'variant' => 'secondary',
				'attrs'   => array( 'data-wpcy-url-submit' => '1' ),
			)
		);
		echo $test_btn; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit button helper escapes attributes and label.
		echo '</div>';
		echo '<div class="wpcy-test-result" data-wpcy-url-output="1" hidden></div>';
		echo '</div>';
		\Wenpai_Admin_UI::card_close();

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '紧急恢复', 'wp-china-yes' ),
				'sub'   => __( '遇到资源加载异常时，可一键暂停全部代理规则并保留当前设置。', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'tools',
				'state'        => $recovery ? 'ok' : 'bad',
				'title'        => $recovery ? __( '退出恢复模式', 'wp-china-yes' ) : __( '暂停全部加速规则', 'wp-china-yes' ),
				'desc'         => $recovery
					? __( '重新启用已保存的镜像与前端资源加速规则。', 'wp-china-yes' )
					: __( '立即切回 WordPress 原始直连，可随时在此恢复。', 'wp-china-yes' ),
				'control_html' => \Wenpai_Admin_UI::button_html(
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
	 * @param string               $version  Plugin version string.
	 */
	private static function render_wizard( array $settings, array $links, string $version = '4.0.0' ): void {
		$profile                  = (string) ( $settings['profile'] ?? 'domestic' );
		$home_url                 = self::page_url( 'wpcy' );
		$shell                    = self::shell_args( '', $version, $links, false );
		$shell['tabs']            = array();
		$shell['head_right_html'] = \Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '退出向导', 'wp-china-yes' ),
				'href'    => $home_url,
				'variant' => 'ghost',
			)
		);

		\Wenpai_Admin_Shell::open( $shell );
		\Wenpai_Admin_UI::wizard_open(
			array(
				'steps'   => array(
					__( '选站点场景', 'wp-china-yes' ),
					__( '应用默认方案', 'wp-china-yes' ),
					__( '进入概览', 'wp-china-yes' ),
				),
				'current' => 0,
			)
		);
		echo '<form id="wpcy-wizard-form" data-wpcy-screen="wizard" data-wpcy-home="' . esc_url( $home_url ) . '">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title'   => __( '首次配置', 'wp-china-yes' ),
				'sub'     => __( '选定站点场景后，自动套用最合适的默认加速方案，30 秒完成。', 'wp-china-yes' ),
				'heading' => 1,
				'tile'    => false,
			)
		);
		echo '<div class="choice-cards">';
		$scenes = array(
			'domestic'    => array( __( '国内站', 'wp-china-yes' ), __( '站点和访客都在中国大陆，默认走国内镜像与 Cravatar 国内节点。', 'wp-china-yes' ) ),
			'crossborder' => array( __( '跨境站', 'wp-china-yes' ), __( '访客主要在海外，后台走加速，前台保持直连。', 'wp-china-yes' ) ),
			'mixed'       => array( __( '混合站', 'wp-china-yes' ), __( '国内外都有访客，按后台与前台分别选择线路。', 'wp-china-yes' ) ),
		);
		foreach ( $scenes as $key => $info ) {
			$is_on = $profile === $key;
			$cls   = $is_on ? 'choice is-on' : 'choice';
			echo '<label class="' . esc_attr( $cls ) . '"><input type="radio" name="profile" value="' . esc_attr( $key ) . '"';
			checked( $is_on );
			echo '><span><span class="t">' . esc_html( $info[0] ) . '</span><span class="d">' . esc_html( $info[1] ) . '</span></span></label>';
		}
		echo '</div>';
		\Wenpai_Admin_UI::card_foot(
			array(
				'actions' => array(
					array(
						'label'   => __( '跳过', 'wp-china-yes' ),
						'href'    => $home_url,
						'variant' => 'ghost',
					),
					array(
						'label'   => __( '应用并进入概览', 'wp-china-yes' ),
						'type'    => 'submit',
						'variant' => 'primary',
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</form>';
		\Wenpai_Admin_UI::wizard_close();
		\Wenpai_Admin_Shell::close( $shell );
	}

	/**
	 * Localized label for site profile.
	 *
	 * @param string $profile Profile slug.
	 * @return string
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
	 *
	 * @param string $name     Input name.
	 * @param bool   $checked  Whether checked.
	 * @param bool   $disabled Whether disabled.
	 * @return string
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
	 * @param string                $name     Input name.
	 * @param string                $label    Field label.
	 * @param string                $desc     Field description.
	 * @param array<string, string> $options  Value => label map.
	 * @param string                $current  Current value.
	 * @param bool                  $disabled Whether disabled.
	 * @return string
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
	 *
	 * @param string $name     Input name.
	 * @param string $label    Field label.
	 * @param string $desc     Checkbox label text.
	 * @param bool   $checked  Whether checked.
	 * @param bool   $disabled Whether disabled.
	 * @return string
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
