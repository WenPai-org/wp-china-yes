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

		\Wenpai_Admin_UI::fold_list(
			array(
				'items' => array(
					array(
						'icon'      => 'settings',
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
	 * Services screen (Plan 2: 3-column service card grid + per-service detail view).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are locked.
	 */
	private static function render_services( array $settings, bool $locked = false ): void {
		$service = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( (string) $_GET['service'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only detail routing.
		$catalog = self::service_catalog();
		if ( '' !== $service && isset( $catalog[ $service ] ) ) {
			self::render_service_detail( $service, $catalog[ $service ], $settings, $locked );
			return;
		}

		\Wenpai_Admin_Shell::page_head(
			__( '服务', 'wp-china-yes' ),
			__( '文派叶子作为生态底座提供基础连接；每项服务均设有专属配置页，并与对应文派独立插件联动接管高级功能。', 'wp-china-yes' ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '绑定本站', 'wp-china-yes' ),
					'variant' => 'primary',
					'attrs'   => array( 'data-wpcy-action' => 'bind' ),
				)
			)
		);

		$groups = array(
			'core'       => array(
				'title' => __( '核心云服务与静态加速', 'wp-china-yes' ),
				'desc'  => __( '由 WenPai.org、adminCDN、Cravatar、Windfonts、MotuCloud 与文派智储提供底层节点支撑。', 'wp-china-yes' ),
			),
			'governance' => array(
				'title' => __( 'AI 智能、政策合规与中文体验', 'wp-china-yes' ),
				'desc'  => __( '涵盖 AI 网关 (wpcy.com/mind)、备案合规 (wpcy.com/icp)、中文体验优化 (wpcy.com/slug)、简繁转换 (wpcy.com/chinese-converter) 与后台减噪。', 'wp-china-yes' ),
			),
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="services">';
		foreach ( $groups as $group_key => $group_meta ) {
			echo '<div>';
			\Wenpai_Admin_UI::section_title(
				array(
					'title' => $group_meta['title'],
					'desc'  => $group_meta['desc'],
				)
			);
			echo '</div>';
			echo '<div class="wenpai-grid-3">';
			foreach ( $catalog as $id => $spec ) {
				if ( ( $spec['group'] ?? 'core' ) !== $group_key ) {
					continue;
				}
				$plugin_state = self::detect_companion_plugin( $spec['plugin_file'], $spec['plugin_slug'] );
				$meta_text    = $plugin_state['active']
					? sprintf( /* translators: %s: companion plugin name */ __( '已接管：%s', 'wp-china-yes' ), $spec['plugin_name'] )
					: $spec['meta'];
				$detail_url   = self::page_url( 'wpcy-services', false, array( 'service' => $id ) );

				\Wenpai_Admin_UI::card_open(
					array(
						'title' => $spec['title'],
						'icon'  => $spec['icon'],
					)
				);
				echo '<p class="meta">' . esc_html( $spec['desc'] ) . '</p>';
				\Wenpai_Admin_UI::card_foot(
					array(
						'start'   => true,
						'meta'    => $meta_text,
						'actions' => array(
							array(
								'label'   => $plugin_state['active'] ? __( '已接管 · 配置', 'wp-china-yes' ) : __( '设置与联动', 'wp-china-yes' ),
								'href'    => $detail_url,
								'variant' => 'secondary',
							),
						),
					)
				);
				\Wenpai_Admin_UI::card_close();
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Service catalog metadata and companion standalone plugin definitions.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function service_catalog(): array {
		return array(
			'wporg'      => array(
				'group'         => 'core',
				'title'         => __( 'WP.org 镜像与云桥更新', 'wp-china-yes' ),
				'icon'          => 'cloud',
				'desc'          => __( '核心、主题、插件与翻译包走 WenPai.org 国内镜像，含图标与截图代理。', 'wp-china-yes' ),
				'meta'          => __( 'WenPai.org · 联动 WPBridge', 'wp-china-yes' ),
				'plugin_name'   => __( '文派云桥 (WPBridge)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpbridge',
				'plugin_file'   => 'wpbridge/wpbridge.php',
				'plugin_admin'  => 'admin.php?page=wpbridge',
				'plugin_url'    => 'https://wpcy.com/bridge',
				'plugin_pitch'  => __( '安装文派云桥 (WPBridge) 可在后台直接检索并一键安装文派生态目录中的全部插件与主题，管理自定义更新源。', 'wp-china-yes' ),
				'takeover_desc' => __( '文派叶子负责官方源底层镜像与回退，生态目录检索与自定义更新通道已由文派云桥 (WPBridge) 联动接管。', 'wp-china-yes' ),
			),
			'admincdn'   => array(
				'group'         => 'core',
				'title'         => __( '公共前端库与核心静态', 'wp-china-yes' ),
				'icon'          => 'flash',
				'desc'          => __( '由 adminCDN 提供 Google Fonts、CDNJS、jsDelivr 与 WP 核心静态加速。', 'wp-china-yes' ),
				'meta'          => __( 'adminCDN · 联动 WPStatic', 'wp-china-yes' ),
				'plugin_name'   => __( '萌芽静态加速 (WPStatic)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpstatic',
				'plugin_file'   => 'wpstatic/wpstatic.php',
				'plugin_admin'  => 'options-general.php?page=wpstatic',
				'plugin_url'    => 'https://wpcy.com/static',
				'plugin_pitch'  => __( '安装 WPStatic（及文派表情 wpcy.com/emoji）可将 wp-admin / wp-includes 核心 CSS/JS 按版本钉死至 wpstatic.admincdn.com，并精细管理公共库白名单。', 'wp-china-yes' ),
				'takeover_desc' => __( 'adminCDN 公共前端库与 WordPress 核心静态资源钉死策略已由 WPStatic 联动接管。', 'wp-china-yes' ),
			),
			'cravatar'   => array(
				'group'         => 'core',
				'title'         => __( 'Cravatar 公共头像', 'wp-china-yes' ),
				'icon'          => 'image',
				'desc'          => __( 'Gravatar 的中国替代服务，支持国内外双线路由，彻底解决评论与后台头像空白。', 'wp-china-yes' ),
				'meta'          => __( 'Cravatar · 联动 WPAvatar', 'wp-china-yes' ),
				'plugin_name'   => __( '文派头像 (WPAvatar)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpavatar',
				'plugin_file'   => 'wpavatar/wpavatar.php',
				'plugin_admin'  => 'options-general.php?page=wpavatar',
				'plugin_url'    => 'https://wpcy.com/avatar',
				'plugin_pitch'  => __( '安装文派头像 (wpcy.com/avatar) 可解锁用户本地上传头像、中文首字生成头像、SVG 默认头像与本地头像缓存预热等高级自定义功能。', 'wp-china-yes' ),
				'takeover_desc' => __( '文派叶子提供底层 Cravatar 节点路由与巡检，高级头像生成与缓存策略已由文派头像 (WPAvatar) 联动接管。', 'wp-china-yes' ),
			),
			'windfonts'  => array(
				'group'         => 'core',
				'title'         => __( 'Windfonts 中文字体', 'wp-china-yes' ),
				'icon'          => 'feather',
				'desc'          => __( '面向中文网页的云端 WOFF2 动态子集字体服务，大幅降低中文字体首屏体积。', 'wp-china-yes' ),
				'meta'          => __( 'Windfonts · 联动 WPFonts', 'wp-china-yes' ),
				'plugin_name'   => __( '文派字库 (WPFonts)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpfonts',
				'plugin_file'   => 'wpfonts/wpfonts.php',
				'plugin_admin'  => 'admin.php?page=wpfonts',
				'plugin_url'    => 'https://wpcy.com/webfonts',
				'plugin_pitch'  => __( '安装文派字库 (wpcy.com/webfonts) 可浏览完整免费商用字库、按标题/正文/代码块 CSS 选择器精细绑定字体，并集成古腾堡编辑器字体库。', 'wp-china-yes' ),
				'takeover_desc' => __( '文派叶子提供云桥授权与节点测速，字体选择与排版规则已由文派字库 (WPFonts) 联动接管。', 'wp-china-yes' ),
			),
			'motucloud'  => array(
				'group'         => 'core',
				'title'         => __( '图标与免版权图库', 'wp-china-yes' ),
				'icon'          => 'sparkle',
				'desc'          => __( '由 MotuCloud（墨图云集）提供 WP 核心图标接口与媒体库免版权图库国内镜像。', 'wp-china-yes' ),
				'meta'          => __( 'MotuCloud · 联动 WPIcon', 'wp-china-yes' ),
				'plugin_name'   => __( '墨图云集 (WPIcon / WPPhoto)', 'wp-china-yes' ),
				'plugin_slug'   => 'motucloud',
				'plugin_file'   => 'motucloud/motucloud.php',
				'plugin_admin'  => 'admin.php?page=motucloud',
				'plugin_url'    => 'https://wpcy.com/icons',
				'plugin_pitch'  => __( '安装墨图云集插件（WPIcon / WPPhoto 整合包）可在古腾堡编辑器与媒体库中直接浏览、检索并插入墨图爱可 SVG 图标与免版权高清图片。', 'wp-china-yes' ),
				'takeover_desc' => __( '文派叶子负责 MotuCloud 镜像通道，编辑器图标选择器与免版权图库已由墨图云集插件联动接管。', 'wp-china-yes' ),
			),
			'storage'    => array(
				'group'         => 'core',
				'title'         => __( '对象存储与媒体分流', 'wp-china-yes' ),
				'icon'          => 'server',
				'desc'          => __( '将本站媒体库与静态附件自动卸载至阿里云 OSS、腾讯云 COS 或 S3 兼容存储。', 'wp-china-yes' ),
				'meta'          => __( '媒体上云 · 联动 WPSSS', 'wp-china-yes' ),
				'plugin_name'   => __( '文派智储 (WPSSS)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpsss',
				'plugin_file'   => 'wpsss/wpsss.php',
				'plugin_admin'  => 'options-general.php?page=wpsss',
				'plugin_url'    => 'https://wpcy.com/wpsss',
				'plugin_pitch'  => __( '安装文派智储 (wpcy.com/wpsss) 可一键配置国内外主流对象存储桶、自定义 CDN 域名、媒体同步队列与体检工具。', 'wp-china-yes' ),
				'takeover_desc' => __( '本站媒体库对象存储卸载与 CDN 域名改写已由文派智储 (WPSSS) 联动接管。', 'wp-china-yes' ),
			),
			'ai'         => array(
				'group'         => 'governance',
				'title'         => __( 'AI 智能与 GEO 优化', 'wp-china-yes' ),
				'icon'          => 'sparkle',
				'desc'          => __( '为站点接入 DeepSeek、通义千问、OpenAI 等多模型 AI 网关与 GEO 引擎优化。', 'wp-china-yes' ),
				'meta'          => __( 'wpcy.com/mind · 联动 WPMind', 'wp-china-yes' ),
				'plugin_name'   => __( '文派心思 (WPMind)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpmind',
				'plugin_file'   => 'wpmind/wpmind.php',
				'plugin_admin'  => 'admin.php?page=wpmind',
				'plugin_url'    => 'https://wpcy.com/mind',
				'plugin_pitch'  => __( '安装文派心思 (wpcy.com/mind) 可启用多服务商 AI 故障自动切换路由、GEO 结构化数据优化、AI 辅助写作与用量统计面板。', 'wp-china-yes' ),
				'takeover_desc' => __( '多模型 AI 服务商路由、GEO 优化与智能工作流已由文派心思 (WPMind) 联动接管。', 'wp-china-yes' ),
			),
			'compliance' => array(
				'group'         => 'governance',
				'title'         => __( '政策合规与网站备案', 'wp-china-yes' ),
				'icon'          => 'info',
				'desc'          => __( '管理中国站点 ICP 备案号、公安联网备案标识公示，以及海外慢外链合规拦截。', 'wp-china-yes' ),
				'meta'          => __( 'wpcy.com/icp · 联动 WPICP', 'wp-china-yes' ),
				'plugin_name'   => __( '文派备案 (WPICP)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpicp',
				'plugin_file'   => 'wpicp/wpicp.php',
				'plugin_admin'  => 'options-general.php?page=wpicp',
				'plugin_url'    => 'https://wpcy.com/icp',
				'plugin_pitch'  => __( '安装文派备案 (wpcy.com/icp) 可在前台页脚规范展示工信部 ICP 备案号、公安网安备案图标与增值电信许可证，支持备案状态巡检。', 'wp-china-yes' ),
				'takeover_desc' => __( '文派叶子负责底层出站数据驻留与慢外链拦截，前台 ICP / 公安备案合规展示已由文派备案 (WPICP) 联动接管。', 'wp-china-yes' ),
			),
			'slug'       => array(
				'group'         => 'governance',
				'title'         => __( '中文体验优化', 'wp-china-yes' ),
				'icon'          => 'equalizer',
				'desc'          => __( '中文标题自动转拼音或英文固定链接别名、媒体文件名净化与中文排版体验优化。', 'wp-china-yes' ),
				'meta'          => __( 'wpcy.com/slug · 联动 WPSlug', 'wp-china-yes' ),
				'plugin_name'   => __( '文派素格 (WPSlug)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpslug',
				'plugin_file'   => 'wpslug/wpslug.php',
				'plugin_admin'  => 'options-general.php?page=wpslug',
				'plugin_url'    => 'https://wpcy.com/slug',
				'plugin_pitch'  => __( '安装文派素格 (wpcy.com/slug) 可自动将文章、分类与媒体中文名转换为拼音或英文别名，支持批量转换与自定义词典。', 'wp-china-yes' ),
				'takeover_desc' => __( '文派叶子提供后台语言跟随等中文环境基线，别名自动转换与词典规则已由文派素格 (WPSlug) 联动接管。', 'wp-china-yes' ),
			),
			'converter'  => array(
				'group'         => 'governance',
				'title'         => __( '中文简繁转换', 'wp-china-yes' ),
				'icon'          => 'map',
				'desc'          => __( '面向大陆、港澳台与海外华人访客的中文简繁自动转换、地区词汇适配与多语 SEO。', 'wp-china-yes' ),
				'meta'          => __( 'wpcy.com/chinese-converter · 联动 WPCC', 'wp-china-yes' ),
				'plugin_name'   => __( '文派译词 (WPCC)', 'wp-china-yes' ),
				'plugin_slug'   => 'wp-chinese-converter',
				'plugin_file'   => 'wp-chinese-converter/wp-chinese-converter.php',
				'plugin_admin'  => 'options-general.php?page=wp-chinese-converter',
				'plugin_url'    => 'https://wpcy.com/chinese-converter',
				'plugin_pitch'  => __( '安装文派译词 (wpcy.com/chinese-converter) 可基于 OpenCC 词汇表实现大陆简、台湾正体、香港繁等 6 种中文变体转换与 hreflang SEO。', 'wp-china-yes' ),
				'takeover_desc' => __( '前台多地区中文简繁转换、浏览器语言识别与固定链接适配已由文派译词 (WPCC) 联动接管。', 'wp-china-yes' ),
			),
			'noise'      => array(
				'group'         => 'governance',
				'title'         => __( '后台减噪与通知治理', 'wp-china-yes' ),
				'icon'          => 'settings',
				'desc'          => __( 'Heartbeat 降频、屏蔽仪表盘外部慢源、后台第三方推销横幅收纳与评论审核治理。', 'wp-china-yes' ),
				'meta'          => __( 'wpcy.com/comments · 联动 WPComments', 'wp-china-yes' ),
				'plugin_name'   => __( '文派评论 (WPComments)', 'wp-china-yes' ),
				'plugin_slug'   => 'wpcomments',
				'plugin_file'   => 'wpcomments/wpcomments.php',
				'plugin_admin'  => 'options-general.php?page=wpcomments',
				'plugin_url'    => 'https://wpcy.com/comments',
				'plugin_pitch'  => __( '文派叶子内置后台通知降噪与心跳优化；安装文派评论 (wpcy.com/comments) 可进一步接管评论审核、常用回复与反垃圾治理。', 'wp-china-yes' ),
				'takeover_desc' => __( '文派叶子负责后台通知降噪与慢请求拦截，评论区审核与常用回复工作流已由文派评论 (WPComments) 联动接管。', 'wp-china-yes' ),
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
	 */
	private static function render_service_detail( string $id, array $spec, array $settings, bool $locked ): void {
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
				'title' => sprintf( /* translators: %s: service title */ __( '%s · 叶子内置基础设置', 'wp-china-yes' ), $spec['title'] ),
				'icon'  => $spec['icon'],
			)
		);

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside field_row_* helpers.
		if ( 'wporg' === $id ) {
			echo self::field_row_select(
				'wporg_mirror',
				__( 'WordPress.org 镜像策略', 'wp-china-yes' ),
				__( '控制核心、插件、主题与语言包的更新下载源；国内镜像异常时自动无感回退官方源。', 'wp-china-yes' ),
				array(
					'auto' => __( '国内镜像（自动回退官方源）', 'wp-china-yes' ),
					'off'  => __( '直连 WordPress.org 官方源', 'wp-china-yes' ),
				),
				$mirror,
				$locked
			);
			\Wenpai_Admin_UI::row(
				array(
					'icon'  => 'image',
					'state' => 'off' === $mirror ? '' : 'ok',
					'title' => __( '插件图标与对照图代理 (ts / ps.wenpai.net)', 'wp-china-yes' ),
					'desc'  => __( '后台插件安装页与更新页的 w.org 静态图标、横幅自动跟随镜像策略加速。', 'wp-china-yes' ),
					'pill'  => array(
						'text'  => 'off' === $mirror ? __( '已关闭', 'wp-china-yes' ) : __( '自动跟随', 'wp-china-yes' ),
						'state' => 'off' === $mirror ? 'mute' : 'ok',
					),
				)
			);
		} elseif ( 'cravatar' === $id ) {
			echo self::field_row_select(
				'avatar_source',
				__( 'Cravatar 头像加速线路', 'wp-china-yes' ),
				__( '替代 Gravatar，支持 WebP 与超高并发分发，彻底消除后台评论区与前台头像空白。', 'wp-china-yes' ),
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
				__( '将 Google Fonts、Google AJAX、CDNJS、jsDelivr 与 WordPress Emoji 改写至 *.admincdn.com 国内高速节点。', 'wp-china-yes' ),
				array(
					'both'     => __( '后台与前台同时加速（推荐）', 'wp-china-yes' ),
					'admin'    => __( '仅加速 WordPress 后台', 'wp-china-yes' ),
					'frontend' => __( '仅加速站点前台', 'wp-china-yes' ),
					'off'      => __( '关闭公共前端库加速', 'wp-china-yes' ),
				),
				$scope,
				$locked
			);
			\Wenpai_Admin_UI::row(
				array(
					'icon'  => 'server',
					'state' => 'ok',
					'title' => __( 'WordPress 核心静态库 (wpstatic.admincdn.com)', 'wp-china-yes' ),
					'desc'  => __( '安装 WPStatic 插件后可将 wp-admin / wp-includes 核心 CSS/JS 按版本号钉死至 adminCDN 边缘节点。', 'wp-china-yes' ),
					'pill'  => array(
						'text'  => $plugin_state['active'] ? __( '已钉死加速', 'wp-china-yes' ) : __( '待安装 WPStatic', 'wp-china-yes' ),
						'state' => $plugin_state['active'] ? 'ok' : 'mute',
					),
				)
			);
		} elseif ( 'noise' === $id ) {
			echo self::field_row_checkbox( 'heartbeat', __( '减少后台心跳 (Heartbeat)', 'wp-china-yes' ), __( '仪表盘停止轮询，文章编辑器降频为 60 秒一次，显著降低 CPU 与无效请求。', 'wp-china-yes' ), $heartbeat, $locked );
			echo self::field_row_checkbox( 'dashboard_feeds', __( '屏蔽仪表盘外部慢请求', 'wp-china-yes' ), __( '阻止仪表盘加载 WordPress 官方英文新闻与活动源，加快后台首页打开速度。', 'wp-china-yes' ), $feeds, $locked );
		} elseif ( 'windfonts' === $id ) {
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			\Wenpai_Admin_UI::row(
				array(
					'icon'  => 'feather',
					'state' => $plugin_state['active'] ? 'ok' : '',
					'title' => __( 'Windfonts 云端中文子集字体引擎', 'wp-china-yes' ),
					'desc'  => __( '按页面实际渲染字符动态切分 WOFF2 子集包，中文字体首屏体积从数 MB 降至数十 KB。', 'wp-china-yes' ),
					'pill'  => array(
						'text'  => $plugin_state['active'] ? __( '由文派字库接管', 'wp-china-yes' ) : __( '就绪', 'wp-china-yes' ),
						'state' => 'ok',
					),
				)
			);
		} elseif ( 'motucloud' === $id ) {
			\Wenpai_Admin_UI::row(
				array(
					'icon'  => 'sparkle',
					'state' => 'ok',
					'title' => __( 'MotuCloud 墨图云集图标与图库镜像通道', 'wp-china-yes' ),
					'desc'  => __( '为 WordPress 核心图标接口与媒体库免版权图片搜索提供国内高速镜像（icons.motucloud.com / motucloud.com）。', 'wp-china-yes' ),
					'pill'  => array(
						'text'  => $plugin_state['active'] ? __( '已接管', 'wp-china-yes' ) : __( '基础通道就绪', 'wp-china-yes' ),
						'state' => 'ok',
					),
				)
			);
		} elseif ( 'storage' === $id ) {
			\Wenpai_Admin_UI::row(
				array(
					'icon'  => 'server',
					'state' => $plugin_state['active'] ? 'ok' : '',
					'title' => __( '媒体库与自产静态对象存储分流', 'wp-china-yes' ),
					'desc'  => __( '文派叶子负责官方源与公共前端库加速；本站上传的图片、音视频与附件由文派智储 (WPSSS) 负责同步上云。', 'wp-china-yes' ),
					'pill'  => array(
						'text'  => $plugin_state['active'] ? __( '已由文派智储接管', 'wp-china-yes' ) : __( '本地存储', 'wp-china-yes' ),
						'state' => $plugin_state['active'] ? 'ok' : 'mute',
					),
				)
			);
		} elseif ( 'ai' === $id ) {
			\Wenpai_Admin_UI::row(
				array(
					'icon'  => 'sparkle',
					'state' => $plugin_state['active'] ? 'ok' : '',
					'title' => __( '文派 AI 网关与 GEO 引擎底座', 'wp-china-yes' ),
					'desc'  => __( '文派叶子提供站点身份绑定与出站网络加速；多模型 AI 端点配置、故障自动切换与 GEO 优化由文派心思 (wpcy.com/mind) 提供。', 'wp-china-yes' ),
					'pill'  => array(
						'text'  => $plugin_state['active'] ? __( '已由文派心思接管', 'wp-china-yes' ) : __( '待启用 WPMind', 'wp-china-yes' ),
						'state' => $plugin_state['active'] ? 'ok' : 'mute',
					),
				)
			);
		} elseif ( 'slug' === $id ) {
			\Wenpai_Admin_UI::row(
				array(
					'icon'  => 'equalizer',
					'state' => 'ok',
					'title' => __( '中文站点基础体验与后台语言跟随', 'wp-china-yes' ),
					'desc'  => __( '文派叶子负责后台语言跟随等中文环境基线；文章/分类/媒体中文自动转拼音或英文别名由文派素格 (wpcy.com/slug) 负责。', 'wp-china-yes' ),
					'pill'  => array(
						'text'  => $plugin_state['active'] ? __( '已由文派素格接管', 'wp-china-yes' ) : __( '基础基线生效中', 'wp-china-yes' ),
						'state' => 'ok',
					),
				)
			);
		} elseif ( 'converter' === $id ) {
			\Wenpai_Admin_UI::row(
				array(
					'icon'  => 'map',
					'state' => $plugin_state['active'] ? 'ok' : '',
					'title' => __( '中文简繁转换与多地区词汇适配', 'wp-china-yes' ),
					'desc'  => __( '基于 OpenCC 词汇表实现大陆简、台湾正体、香港繁等 6 种中文变体转换与 hreflang SEO，由文派译词 (wpcy.com/chinese-converter) 提供。', 'wp-china-yes' ),
					'pill'  => array(
						'text'  => $plugin_state['active'] ? __( '已由文派译词接管', 'wp-china-yes' ) : __( '待启用 WPCC', 'wp-china-yes' ),
						'state' => $plugin_state['active'] ? 'ok' : 'mute',
					),
				)
			);
		} else {
			\Wenpai_Admin_UI::row(
				array(
					'icon'  => 'info',
					'state' => 'ok',
					'title' => __( '出站数据驻留与慢外链合规防护', 'wp-china-yes' ),
					'desc'  => __( '自动拦截拖慢后台的不可达海外遥测与外链请求；前台 ICP 备案号与公安联网备案公示由文派备案 (wpcy.com/icp) 负责。', 'wp-china-yes' ),
					'pill'  => array(
						'text'  => __( '防护生效中', 'wp-china-yes' ),
						'state' => 'ok',
					),
				)
			);
		}
		\Wenpai_Admin_UI::card_close();
		echo '</form>';

		// Card 2: Companion standalone plugin linkage card (Install prompt vs Active takeover).
		$plugin_url = ! empty( $spec['plugin_url'] ) ? $spec['plugin_url'] : 'https://wpcy.com/';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => sprintf( /* translators: %s: companion plugin name */ __( '独立插件联动 · %s', 'wp-china-yes' ), $spec['plugin_name'] ),
				'sub'   => $plugin_state['active']
					? __( '已检测到独立插件处于激活状态，文派叶子已自动与其建立底层加速与高级配置联动。', 'wp-china-yes' )
					: __( '文派叶子负责提供开箱即用的基础加速；安装对应独立插件可解锁完整自定义与高级功能。', 'wp-china-yes' ),
				'tile'  => false,
			)
		);

		if ( $plugin_state['active'] ) {
			$ver_label  = '' !== $plugin_state['version'] ? ' v' . $plugin_state['version'] : '';
			$admin_link = function_exists( 'admin_url' ) ? admin_url( $spec['plugin_admin'] ) : $spec['plugin_admin'];
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'sparkle',
					'state'        => 'ok',
					'title'        => $spec['plugin_name'] . $ver_label,
					'desc'         => $spec['takeover_desc'],
					'pill'         => array(
						'text'  => __( '已安装并接管', 'wp-china-yes' ),
						'state' => 'ok',
					),
					'control_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '打开高级设置', 'wp-china-yes' ),
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
