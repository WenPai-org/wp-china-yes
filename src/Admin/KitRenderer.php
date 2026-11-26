<?php
/**
 * Server-rendered admin screens using wenpai-admin-ui kit (Route A).
 *
 * Keeps backend services and REST `/wpcy/v1/*` decoupled so the presentation
 * layer stays lightweight and swappable.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Diagnostics\ConflictDetector;
use WenPai\ChinaYes\Diagnostics\HealthAudit;
use WenPai\ChinaYes\Services\Catalog\PublicCatalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the 5 WPCY 4.0 admin screens, subviews, help, and onboarding wizard.
 */
final class KitRenderer {

	/**
	 * Render one admin screen.
	 *
	 * @param string               $page    Current `?page=` slug.
	 * @param array<string, mixed> $payload Bootstrap payload from `AdminModule::bootstrap_payload()`.
	 * @return void
	 */
	public static function render( string $page, array $payload ): void {
		if ( ! class_exists( '\Wenpai_Admin_Shell', false ) || ! class_exists( '\Wenpai_Admin_UI', false ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( '文派后台组件库尚未加载，请检查 lib/wenpai-admin-ui 目录是否完整。', 'wp-china-yes' ) . '</p></div>';
			return;
		}

		$settings = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : array();
		$caps     = isset( $payload['capabilities'] ) && is_array( $payload['capabilities'] ) ? $payload['capabilities'] : array();
		$links    = isset( $payload['links'] ) && is_array( $payload['links'] ) ? $payload['links'] : array();
		$version  = isset( $payload['pluginVersion'] ) ? (string) $payload['pluginVersion'] : '4.0.0';
		$locked   = ! empty( $caps['network_locked'] );
		$network  = ! empty( $caps['network_admin'] );

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
		if ( ( 'wpcy-onboarding' === $page || 'wizard' === $view ) && ! $locked && ! $network ) {
			self::render_wizard( $settings, $links, $version );
			return;
		}

		$tab_map = array(
			'wpcy'          => 'overview',
			'wpcy-connect'  => 'settings',
			'wpcy-features' => 'features',
			'wpcy-services' => 'services',
			'wpcy-licenses' => 'licenses',
			'wpcy-diagnose' => 'diagnose',
		);
		$current = $network ? 'settings' : ( 'help' === $view ? 'help' : ( $tab_map[ $page ] ?? 'overview' ) );
		$shell   = self::shell_args( $current, $version, $links, $network, $settings );

		\Wenpai_Admin_Shell::open( $shell );

		if ( 'help' === $current ) {
			self::render_help( $links );
		} elseif ( 'settings' === $current ) {
			self::render_settings( $settings, $locked, $network );
		} elseif ( 'features' === $current ) {
			self::render_features( $settings, $locked );
		} elseif ( 'services' === $current ) {
			self::render_services( $settings, $locked );
		} elseif ( 'licenses' === $current ) {
			self::render_licenses( $settings, $locked );
		} elseif ( 'diagnose' === $current ) {
			self::render_diagnose( $settings, $locked );
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
	 * Build an admin page URL for top-level WPCY menu pages.
	 *
	 * @param string                $slug    Admin page slug.
	 * @param bool                  $network Whether this is a network admin page.
	 * @param array<string, string> $extra   Optional query args.
	 * @return string
	 */
	private static function page_url( string $slug, bool $network = false, array $extra = array() ): string {
		$args = array_merge( array( 'page' => $slug ), $extra );
		if ( $network && function_exists( 'network_admin_url' ) && function_exists( 'add_query_arg' ) ) {
			$script = 'admin';
			return (string) add_query_arg( $args, network_admin_url( $script . '.php' ) );
		}
		if ( class_exists( '\Wenpai_Admin_Menu', false ) && function_exists( 'admin_url' ) && function_exists( 'add_query_arg' ) ) {
			return (string) \Wenpai_Admin_Menu::url( $slug, $extra );
		}
		$q = '?page=' . rawurlencode( $slug );
		foreach ( $extra as $k => $v ) {
			$q .= '&' . rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
		}
		return $q;
	}

	/**
	 * Resolve companion plugin admin URL without hardcoding admin entry filenames at call sites.
	 *
	 * Guards against double-prefixing when `$relative` is already a full `http(s)://` admin URL.
	 *
	 * @param string $relative Relative wp-admin path or full URL.
	 * @return string
	 */
	private static function wp_admin_link( string $relative ): string {
		if ( 0 === strpos( $relative, 'https://' ) || 0 === strpos( $relative, 'http://' ) ) {
			return $relative;
		}
		return function_exists( 'admin_url' ) ? (string) admin_url( $relative ) : $relative;
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
	 * Read effective WordPress.org mirror mode from settings.
	 *
	 * @param array<string, mixed> $conn Connectivity slice.
	 * @return string
	 */
	private static function conn_mirror( array $conn ): string {
		return (string) ( $conn['wordpress_org'] ?? $conn['wporg_mirror'] ?? 'auto' );
	}

	/**
	 * Read effective public assets scope from settings.
	 *
	 * @param array<string, mixed> $settings Full settings snapshot.
	 * @param array<string, mixed> $conn     Connectivity slice.
	 * @return string
	 */
	private static function conn_scope( array $settings, array $conn ): string {
		if ( isset( $conn['public_assets'] ) && is_array( $conn['public_assets'] ) && isset( $conn['public_assets']['scope'] ) ) {
			return (string) $conn['public_assets']['scope'];
		}
		if ( isset( $settings['public_assets'] ) && is_array( $settings['public_assets'] ) && isset( $settings['public_assets']['scope'] ) ) {
			return (string) $settings['public_assets']['scope'];
		}
		return 'both';
	}

	/**
	 * Build `Wenpai_Admin_Shell` args.
	 *
	 * @param string               $current  Active tab id.
	 * @param string               $version  Plugin version string.
	 * @param array<string, mixed> $links    External links.
	 * @param bool                 $network  Network admin context.
	 * @param array<string, mixed> $settings Settings snapshot (for white-label).
	 * @return array<string, mixed>
	 */
	private static function shell_args( string $current, string $version, array $links, bool $network, array $settings = array() ): array {
		$admin_url = static function ( string $slug, array $extra = array() ) use ( $network ): string {
			return self::page_url( $slug, $network, $extra );
		};

		$wl = WhiteLabel::resolve( $settings );

		if ( $network ) {
			$tabs = array(
				array(
					'id'        => 'settings',
					'label'     => __( '设置', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-connect' ),
					'icon_html' => self::kit_icon( 'settings' ),
				),
			);
		} else {
			$tabs = array(
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
					'id'        => 'features',
					'label'     => __( '功能', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-features' ),
					'icon_html' => self::kit_icon( 'equalizer' ),
				),
				array(
					'id'        => 'services',
					'label'     => __( '服务', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-services' ),
					'icon_html' => self::kit_icon( 'sparkle' ),
				),
			);
			if ( ! ( ! empty( $wl['enabled'] ) && ! empty( $wl['hide_links'] ) ) ) {
				$tabs[] = array(
					'id'        => 'licenses',
					'label'     => __( '授权', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-licenses' ),
					'icon_html' => self::kit_icon( 'shield-check' ),
				);
			}
			$tabs[] = array(
				'id'        => 'diagnose',
				'label'     => __( '诊断', 'wp-china-yes' ),
				'url'       => $admin_url( 'wpcy-diagnose' ),
				'icon_html' => self::kit_icon( 'tools' ),
			);
		}

		$support_url = isset( $links['help'] ) ? (string) $links['help'] : 'https://wpcy.com/go/support';
		$defaults    = array(
			'brand'        => __( '文派叶子', 'wp-china-yes' ),
			'plugin_name'  => __( '文派叶子', 'wp-china-yes' ),
			'help_url'     => $network ? $support_url : $admin_url( 'wpcy', array( 'view' => 'help' ) ),
			'feedback_url' => $support_url,
			'docs_url'     => $support_url,
		);
		$display     = function_exists( 'apply_filters' )
			? apply_filters( 'wenpai_plugin_display', $defaults, 'wp-china-yes' )
			: WhiteLabel::filter_display( $defaults, $settings );
		if ( ! is_array( $display ) ) {
			$display = $defaults;
		}
		$brand_name   = ! empty( $display['brand'] ) ? (string) $display['brand'] : $defaults['brand'];
		$help_url     = ! empty( $display['help_url'] ) ? (string) $display['help_url'] : $defaults['help_url'];
		$feedback_url = ! empty( $display['feedback_url'] ) ? (string) $display['feedback_url'] : $defaults['feedback_url'];
		$docs_url     = ! empty( $display['docs_url'] ) ? (string) $display['docs_url'] : $defaults['docs_url'];

		$mark_html = self::leaf_mark();
		if ( $wl['enabled'] && $wl['hide_logo'] ) {
			$mark_html = '';
		} elseif ( $wl['enabled'] && '' !== $wl['logo_url'] ) {
			$mark_html = '<img class="wpcy-mark-img" src="' . esc_url( $wl['logo_url'] ) . '" alt="' . esc_attr( $brand_name ) . '" width="22" height="22">';
		}

		$args = array(
			'slug'       => 'wpcy',
			'name'       => $brand_name,
			'mark_html'  => $mark_html,
			'home_url'   => $admin_url( $network ? 'wpcy-connect' : 'wpcy' ),
			'current'    => $current,
			'tabs'       => $tabs,
			'version'    => $brand_name . ' ' . $version,
			'main_class' => 'wenpai-measure-wide',
		);

		$show_links = ! ( $wl['enabled'] && $wl['hide_links'] ) || '' !== $wl['help_url'] || '' !== $wl['feedback_url'] || '' !== $wl['docs_url'];
		if ( $show_links ) {
			if ( ! ( $wl['enabled'] && $wl['hide_links'] && '' === $wl['help_url'] ) ) {
				$args['help_url']       = $help_url;
				$args['help_label']     = __( '帮助', 'wp-china-yes' );
				$args['help_icon_html'] = self::kit_icon( 'help' );
			}
			if ( ! ( $wl['enabled'] && $wl['hide_links'] && '' === $wl['feedback_url'] ) ) {
				$args['feedback_url']       = $feedback_url;
				$args['feedback_label']     = __( '反馈', 'wp-china-yes' );
				$args['feedback_icon_html'] = self::kit_icon( 'feedback' );
			}
			if ( ! ( $wl['enabled'] && $wl['hide_links'] && '' === $wl['docs_url'] ) ) {
				$args['doc_url']   = $docs_url;
				$args['doc_label'] = __( '文档与支持', 'wp-china-yes' );
			}
		}

		return $args;
	}

	/**
	 * Overview screen (`?page=wpcy`): stat_hub + 7d SVG trend & speed/quota split + tag_feed + ad_slot & icon_grid.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 */
	private static function render_overview( array $settings ): void {
		$profile       = self::profile_label( (string) ( $settings['profile'] ?? 'domestic' ) );
		$recovery      = ! empty( $settings['recovery_mode'] );
		$diag_url      = self::page_url( 'wpcy-diagnose' );
		$set_url       = self::page_url( 'wpcy-connect' );
		$feat_url      = self::page_url( 'wpcy-features' );
		$svc_url       = self::page_url( 'wpcy-services' );
		$news_set_url  = self::page_url( 'wpcy-features', false, array( 'module' => 'news' ) );
		$conn          = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror        = self::conn_mirror( $conn );
		$scope         = self::conn_scope( $settings, $conn );
		$avatar        = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$locale_follow = ! isset( $conn['admin_locale_follow'] ) || (bool) $conn['admin_locale_follow'];
		$noise_on      = 'on' === (string) ( $conn['heartbeat'] ?? 'off' ) || 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$news_enabled  = ! isset( $settings['admin']['news_enabled'] ) || (bool) $settings['admin']['news_enabled'];
		$wl            = WhiteLabel::resolve( $settings );
		$is_bound      = self::is_site_bound();
		$active_count  = ( 'off' !== $mirror ? 1 : 0 ) + ( 'off' !== $scope ? 1 : 0 ) + ( 'off' !== $avatar ? 1 : 0 ) + ( ( $locale_follow || $noise_on ) ? 1 : 0 );

		// Read cached server probe snapshot (render-safe local transient, zero remote HTTP).
		$cached_targets = array();
		$latencies      = array();
		$issue_count    = 0;
		if ( function_exists( 'get_transient' ) ) {
			$raw_diag = get_transient( 'wpcy_diagnostics_results' );
			if ( is_array( $raw_diag ) ) {
				foreach ( $raw_diag as $row ) {
					if ( is_array( $row ) && ! empty( $row['target'] ) ) {
						$cached_targets[ (string) $row['target'] ] = $row;
						if ( isset( $row['latency_ms'] ) && is_numeric( $row['latency_ms'] ) && (int) $row['latency_ms'] > 0 ) {
							$latencies[] = (int) $row['latency_ms'];
						}
						$res_code = isset( $row['result'] ) ? (string) $row['result'] : '';
						if ( 'fallback' === $res_code || 'down' === $res_code ) {
							++$issue_count;
						}
					}
				}
			}
		}

		$wporg_probe  = $cached_targets['api.wenpai.net'] ?? $cached_targets['downloads.wenpai.net'] ?? null;
		$pub_probe    = $cached_targets['cdnjs.admincdn.com'] ?? $cached_targets['jsd.admincdn.com'] ?? null;
		$ava_probe    = $cached_targets['cravatar.com'] ?? $cached_targets['cn.cravatar.com'] ?? null;
		$avg_latency  = array() !== $latencies ? (string) (int) round( array_sum( $latencies ) / count( $latencies ) ) : '—';
		$checked_text = __( '等待测速 · 自动回退', 'wp-china-yes' );
		if ( is_array( $wporg_probe ) && ! empty( $wporg_probe['checked_at'] ) && is_string( $wporg_probe['checked_at'] ) ) {
			$ts = strtotime( $wporg_probe['checked_at'] );
			if ( false !== $ts ) {
				$checked_text = sprintf( /* translators: %s: formatted check time */ __( '%s 更新 · 自动回退', 'wp-china-yes' ), gmdate( 'm-d H:i', $ts + 8 * 3600 ) );
			}
		}

		if ( $recovery ) {
			$lede = sprintf( /* translators: %s: site profile label */ __( '当前场景：%s · 安全恢复模式生效中', 'wp-china-yes' ), $profile );
		} elseif ( $issue_count > 0 ) {
			$lede = sprintf( /* translators: 1: site profile label, 2: fallback channel count */ __( '当前场景：%1$s · %2$d 项通道自动回退', 'wp-china-yes' ), $profile, $issue_count );
		} elseif ( $active_count > 0 ) {
			$lede = sprintf( /* translators: 1: site profile label, 2: active core channel count */ __( '当前场景：%1$s · %2$d 项核心通道正常', 'wp-china-yes' ), $profile, $active_count );
		} else {
			$lede = sprintf( /* translators: %s: site profile label */ __( '当前场景：%s · 当前使用默认直连', 'wp-china-yes' ), $profile );
		}

		$head_actions  = '<div class="wenpai-row wenpai-row--tight">';
		$head_actions .= \Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '调整设置', 'wp-china-yes' ),
				'href'    => $set_url,
				'variant' => 'secondary',
			)
		);
		$head_actions .= \Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '立即测速', 'wp-china-yes' ),
				'type'    => 'button',
				'variant' => $recovery ? 'secondary' : 'primary',
				'attrs'   => array( 'data-wpcy-overview-probe' => '1' ),
			)
		);
		$head_actions .= '</div>';

		$lede_html = '<span data-wpcy-overview-lede="1" data-wpcy-profile-label="' . esc_attr( $profile ) . '" data-wpcy-active-count="' . (int) $active_count . '" data-wpcy-recovery="' . ( $recovery ? '1' : '0' ) . '">' . esc_html( $lede ) . '</span>'
			. \Wenpai_Admin_UI::drawer_toggle_html(
				array(
					'target'     => 'wpcy-channel-drawer',
					'label'      => __( '通道详情', 'wp-china-yes' ),
					'open_label' => __( '收起通道', 'wp-china-yes' ),
				)
			);

		\Wenpai_Admin_Shell::page_head(
			__( '概览', 'wp-china-yes' ),
			'',
			$head_actions,
			$lede_html
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="overview">';
		echo '<div data-wpcy-migration-banner="1" hidden></div>';

		if ( $recovery ) {
			\Wenpai_Admin_UI::next(
				array(
					'state'   => 'warn',
					'icon'    => 'info',
					'title'   => __( '恢复模式生效中，镜像与静态加速已暂停', 'wp-china-yes' ),
					'desc'    => __( '当前使用 WordPress 默认直连；排查完成后可在诊断页恢复。', 'wp-china-yes' ),
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

		// Row 1: Silent-when-healthy 4-col Stat Hub + Anomaly Alert Strip + Expandable 4-col Channel Drawer.
		\Wenpai_Admin_UI::stat_hub(
			array(
				'drawer_id' => 'wpcy-channel-drawer',
				'attrs'     => array( 'data-wpcy-stats' => '1' ),
				'stats'     => array(
					array(
						'label' => __( '7 日加速请求', 'wp-china-yes' ),
						'value' => '0',
						'unit'  => __( '次', 'wp-china-yes' ),
					),
					array(
						'label' => __( '节点平均延迟', 'wp-china-yes' ),
						'value' => $avg_latency,
						'unit'  => 'ms',
					),
					array(
						'label' => __( '头像加速次数', 'wp-china-yes' ),
						'value' => '0',
						'unit'  => __( '次', 'wp-china-yes' ),
					),
					array(
						'label' => __( '已拦截慢外链', 'wp-china-yes' ),
						'value' => '0',
						'unit'  => __( '次', 'wp-china-yes' ),
					),
				),
				'alert'     => array(
					'visible'      => $issue_count > 0 && ! $recovery,
					'text'         => sprintf( /* translators: %d: fallback channel count */ __( '%d 项通道发生自动回退，系统正定期自动复检', 'wp-china-yes' ), max( 1, $issue_count ) ),
					'action_label' => __( '展开查看', 'wp-china-yes' ),
				),
				'channels'  => self::overview_channel_cells( $mirror, $scope, $avatar, $locale_follow, $noise_on, $wporg_probe, $pub_probe, $ava_probe ),
			)
		);

		// Row 2: Equal-height Split — Left 7d SVG Trend | Right Speed Tiles & Scrollable Service Quotas.
		echo '<div class="wenpai-split wenpai-split--equal">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title'             => __( '近 7 日加速趋势', 'wp-china-yes' ),
				'tile'              => false,
				'class'             => 'wenpai-card--equal',
				'head_control_html' => '<span class="meta" data-wpcy-trend-summary="1">' . esc_html__( '累计 0 次请求', 'wp-china-yes' ) . '</span>',
			)
		);
		echo '<div class="wenpai-card__body">';
		\Wenpai_Admin_UI::chart_frame(
			array(
				'attrs'      => array( 'data-wpcy-trend' => '1' ),
				'plot_html'  => self::empty_trend_svg(),
				'aria_label' => __( '近 7 日加速趋势', 'wp-china-yes' ),
			)
		);
		echo '</div>';
		\Wenpai_Admin_UI::card_foot(
			array(
				'start'        => true,
				'meta_html'    => '<span class="meta" data-wpcy-trend-legend="1">' . esc_html__( '等待首批加速请求 · 产生访问后按日汇总', 'wp-china-yes' ) . '</span>',
				'actions_html' => '<span class="meta">' . esc_html__( '按日汇总', 'wp-china-yes' ) . '</span>',
			)
		);
		\Wenpai_Admin_UI::card_close();

		$quota_foot_meta = $is_bound
			? __( '已关联文派云通行证 · 额度自动同步', 'wp-china-yes' )
			: __( '扩展插件额度随站点绑定同步', 'wp-china-yes' );
		$side_tabs       = \Wenpai_Admin_UI::view_tabs_html(
			array(
				'group'      => 'wpcy-side',
				'aria_label' => __( '节点测速与服务额度切换', 'wp-china-yes' ),
				'items'      => array(
					array(
						'id'         => 'speed',
						'label'      => __( '测速', 'wp-china-yes' ),
						'active'     => true,
						'card_title' => __( '核心节点测速', 'wp-china-yes' ),
						'foot_meta'  => $checked_text,
						'foot_label' => __( '完整诊断', 'wp-china-yes' ),
						'foot_href'  => $diag_url,
					),
					array(
						'id'         => 'quota',
						'label'      => __( '额度', 'wp-china-yes' ),
						'card_title' => __( '服务用量额度', 'wp-china-yes' ),
						'foot_meta'  => $quota_foot_meta,
						'foot_label' => __( '服务目录', 'wp-china-yes' ),
						'foot_href'  => $svc_url,
					),
				),
			)
		);

		\Wenpai_Admin_UI::card_open(
			array(
				'title'             => __( '核心节点测速', 'wp-china-yes' ),
				'tile'              => false,
				'class'             => 'wenpai-card--equal',
				'head_control_html' => $side_tabs,
			)
		);
		echo '<div class="wenpai-card__body">';
		\Wenpai_Admin_UI::view_panes(
			array(
				'group' => 'wpcy-side',
				'panes' => array(
					array(
						'id'     => 'speed',
						'active' => true,
						'html'   => \Wenpai_Admin_UI::metric_tiles_html(
							array(
								'attrs' => array( 'data-wpcy-latency' => '1' ),
								'items' => array(
									self::overview_metric_tile( 'wporg', __( 'WP.org 镜像', 'wp-china-yes' ), __( '免费', 'wp-china-yes' ), __( '国内镜像', 'wp-china-yes' ), $wporg_probe ),
									self::overview_metric_tile( 'cravatar', __( 'Cravatar 头像', 'wp-china-yes' ), __( '免费', 'wp-china-yes' ), __( '中国线路', 'wp-china-yes' ), $ava_probe ),
									self::overview_metric_tile( 'admincdn', 'adminCDN', '', __( '前后台加速', 'wp-china-yes' ), $pub_probe ),
									ProbeSnapshot::overview_browser_tile(),
								),
							)
						),
					),
					array(
						'id'   => 'quota',
						'html' => \Wenpai_Admin_UI::quota_list_html(
							array(
								'attrs' => array( 'data-wpcy-quotas' => '1' ),
								'items' => self::overview_quota_items( $svc_url, $settings, $is_bound ),
							)
						),
					),
				),
			)
		);
		echo '</div>';
		\Wenpai_Admin_UI::card_foot(
			array(
				'start'     => true,
				'meta_html' => '<span class="meta" data-wpcy-checked-at="1">' . esc_html( $checked_text ) . '</span>',
				'actions'   => array(
					array(
						'label'   => __( '完整诊断', 'wp-china-yes' ),
						'href'    => $diag_url,
						'variant' => 'ghost',
						'size'    => 'sm',
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';

		// Row 3: Micro-tag Activity Log & WPTee Community Feed (Zero-jump view_panes).
		$feed_head_ctl = '';
		if ( $news_enabled ) {
			$feed_head_ctl = '<a class="btn btn-ghost btn-sm" href="' . esc_url( $news_set_url ) . '" data-wpcy-news-manage="1" hidden>' . esc_html__( '管理订阅', 'wp-china-yes' ) . '</a>'
				. \Wenpai_Admin_UI::view_tabs_html(
					array(
						'group'      => 'wpcy-feed',
						'aria_label' => __( '运行记录与社区动态切换', 'wp-china-yes' ),
						'items'      => array(
							array(
								'id'         => 'events',
								'label'      => __( '运行记录', 'wp-china-yes' ),
								'active'     => true,
								'card_title' => __( '近期运行记录', 'wp-china-yes' ),
							),
							array(
								'id'         => 'news',
								'label'      => __( '茶馆动态', 'wp-china-yes' ),
								'card_title' => __( '文派茶馆动态', 'wp-china-yes' ),
							),
						),
					)
				);
		}

		\Wenpai_Admin_UI::card_open(
			array(
				'title'             => __( '近期运行记录', 'wp-china-yes' ),
				'tile'              => false,
				'attrs'             => array(
					'data-wpcy-feed-card'    => '1',
					'data-wpcy-news-enabled' => $news_enabled ? '1' : '0',
				),
				'head_control_html' => $feed_head_ctl,
			)
		);
		$event_rows     = array_merge(
			self::overview_suggestion_feed_items( self::active_suggestions( $settings ) ),
			array(
				array(
					'tag'   => __( '配置', 'wp-china-yes' ),
					'title' => sprintf( /* translators: %s: profile label */ __( '已按「%s」场景启用加速策略', 'wp-china-yes' ), $profile ),
				),
			)
		);
		$initial_events = \Wenpai_Admin_UI::tag_feed_html(
			array(
				'scrollable' => true,
				'items'      => $event_rows,
			)
		);
		if ( $news_enabled ) {
			$initial_news = \Wenpai_Admin_UI::tag_feed_html(
				array(
					'scrollable' => true,
					'items'      => array(
						array(
							'tag'   => __( '茶馆', 'wp-china-yes' ),
							'tone'  => 'accent',
							'title' => __( '正在同步文派茶馆动态（read.wptea.com）…', 'wp-china-yes' ),
						),
					),
				)
			);
			\Wenpai_Admin_UI::view_panes(
				array(
					'group' => 'wpcy-feed',
					'panes' => array(
						array(
							'id'     => 'events',
							'active' => true,
							'html'   => '<div data-wpcy-events="1">' . $initial_events . '</div>',
						),
						array(
							'id'   => 'news',
							'html' => '<div data-wpcy-news="1">' . $initial_news . '</div>',
						),
					),
				)
			);
		} else {
			echo '<div data-wpcy-events="1">' . $initial_events . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by tag_feed_html.
		}
		\Wenpai_Admin_UI::card_close();

		// Row 4: Reversed Split — Left 20rem Cloud / White-Label Agency Promo Slot (never closed on Overview) + Right 1fr 5x2 Quick Tools / WenPai Components Switcher.
		$ad_args   = self::overview_ad_slot_args( $wl, $svc_url );
		$split_r4  = null === $ad_args ? 'wenpai-split wenpai-split--reverse wenpai-split--equal is-single' : 'wenpai-split wenpai-split--reverse wenpai-split--equal';
		$hide_eco  = ! empty( $wl['enabled'] ) && ! empty( $wl['hide_links'] );
		$foot_acts = array();
		if ( ! $hide_eco ) {
			$foot_acts[] = array(
				'label'   => __( '文派生态目录', 'wp-china-yes' ),
				'href'    => $svc_url,
				'variant' => 'ghost',
				'size'    => 'sm',
			);
		}

		$tools_head_ctl = $hide_eco
			? '<span class="meta">' . esc_html__( '工具箱预留位', 'wp-china-yes' ) . '</span>'
			: \Wenpai_Admin_UI::view_tabs_html(
				array(
					'group'      => 'wpcy-tools',
					'aria_label' => __( '快捷工具与文派组件切换', 'wp-china-yes' ),
					'items'      => array(
						array(
							'id'         => 'quick',
							'label'      => __( '快捷工具', 'wp-china-yes' ),
							'active'     => true,
							'card_title' => __( '快捷工具', 'wp-china-yes' ),
							'foot_meta'  => __( '内建维护动作 · 工具箱预留位', 'wp-china-yes' ),
						),
						array(
							'id'         => 'suite',
							'label'      => __( '文派组件', 'wp-china-yes' ),
							'card_title' => __( '文派组件', 'wp-china-yes' ),
							'foot_meta'  => __( '已安装直达组件设置 · 未安装点击提示安装', 'wp-china-yes' ),
						),
					),
				)
			);

		echo '<div class="' . esc_attr( $split_r4 ) . '">';
		if ( null !== $ad_args ) {
			\Wenpai_Admin_UI::ad_slot( $ad_args );
		}

		\Wenpai_Admin_UI::card_open(
			array(
				'title'             => __( '快捷工具', 'wp-china-yes' ),
				'tile'              => false,
				'class'             => 'wenpai-card--equal',
				'head_control_html' => $tools_head_ctl,
			)
		);
		$quick_grid = \Wenpai_Admin_UI::icon_grid_html(
			array(
				'columns' => 5,
				'items'   => self::overview_quick_tools( $wl, $svc_url ),
			)
		);
		echo '<div class="wenpai-card__body">';
		if ( $hide_eco ) {
			echo $quick_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit icon_grid_html escapes output.
		} else {
			$suite_grid = \Wenpai_Admin_UI::icon_grid_html(
				array(
					'columns' => 5,
					'items'   => self::overview_suite_components( $settings ),
				)
			);
			\Wenpai_Admin_UI::view_panes(
				array(
					'group' => 'wpcy-tools',
					'panes' => array(
						array(
							'id'     => 'quick',
							'active' => true,
							'html'   => $quick_grid,
						),
						array(
							'id'   => 'suite',
							'html' => $suite_grid,
						),
					),
				)
			);
		}
		echo '</div>';
		\Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '内建维护动作 · 工具箱预留位', 'wp-china-yes' ),
				'actions' => $foot_acts,
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Build 4 channel cells for `Wenpai_Admin_UI::stat_hub()`.
	 *
	 * @param string                    $mirror        WP.org mirror mode.
	 * @param string                    $scope         Public assets scope.
	 * @param string                    $avatar        Avatar mode.
	 * @param bool                      $locale_follow Locale separation enabled.
	 * @param bool                      $noise_on      Heartbeat/feed noise reduction enabled.
	 * @param array<string, mixed>|null $wporg_probe   Cached WP.org probe.
	 * @param array<string, mixed>|null $pub_probe     Cached adminCDN probe.
	 * @param array<string, mixed>|null $ava_probe     Cached Cravatar probe.
	 * @return array<int, array<string, mixed>>
	 */
	private static function overview_channel_cells( string $mirror, string $scope, string $avatar, bool $locale_follow, bool $noise_on, ?array $wporg_probe, ?array $pub_probe, ?array $ava_probe ): array {
		$wporg_res  = is_array( $wporg_probe ) && isset( $wporg_probe['result'] ) ? (string) $wporg_probe['result'] : '';
		$pub_res    = is_array( $pub_probe ) && isset( $pub_probe['result'] ) ? (string) $pub_probe['result'] : '';
		$ava_res    = is_array( $ava_probe ) && isset( $ava_probe['result'] ) ? (string) $ava_probe['result'] : '';
		$wporg_warn = 'fallback' === $wporg_res || 'down' === $wporg_res;
		$pub_warn   = 'fallback' === $pub_res || 'down' === $pub_res;
		$ava_warn   = 'fallback' === $ava_res || 'down' === $ava_res;

		return array(
			array(
				'label'       => __( 'WP.org 更新镜像', 'wp-china-yes' ),
				'value'       => 'off' === $mirror ? __( '直连官方源', 'wp-china-yes' ) : ( $wporg_warn ? __( '探测波动 · 已自动回退', 'wp-china-yes' ) : __( 'WenPai.org 国内源（免费）', 'wp-china-yes' ) ),
				'tone'        => $wporg_warn ? 'warn' : '',
				'attrs'       => array( 'data-wpcy-channel' => 'wporg' ),
				'value_attrs' => array( 'data-wpcy-channel-val' => 'wporg' ),
			),
			array(
				'label'       => __( '公共静态资源', 'wp-china-yes' ),
				'value'       => 'off' === $scope ? __( '未启用', 'wp-china-yes' ) : ( $pub_warn ? __( '探测波动 · 已自动回退', 'wp-china-yes' ) : __( 'adminCDN 前后台加速', 'wp-china-yes' ) ),
				'tone'        => $pub_warn ? 'warn' : '',
				'attrs'       => array( 'data-wpcy-channel' => 'admincdn' ),
				'value_attrs' => array( 'data-wpcy-channel-val' => 'admincdn' ),
			),
			array(
				'label'       => __( 'Cravatar 头像', 'wp-china-yes' ),
				'value'       => ConflictDetector::is_companion_active( 'wpavatar' )
					? __( '文派头像协同接管', 'wp-china-yes' )
					: ( 'off' === $avatar ? __( '原始 Gravatar', 'wp-china-yes' ) : ( $ava_warn ? __( '探测波动 · 已自动回退', 'wp-china-yes' ) : __( '中国大陆节点', 'wp-china-yes' ) ) ),
				'tone'        => ( ! ConflictDetector::is_companion_active( 'wpavatar' ) && $ava_warn ) ? 'warn' : '',
				'attrs'       => array( 'data-wpcy-channel' => 'cravatar' ),
				'value_attrs' => ConflictDetector::is_companion_active( 'wpavatar' )
					? array(
						'data-wpcy-channel-val' => 'cravatar',
						'data-wpcy-takeover'    => '1',
					)
					: array( 'data-wpcy-channel-val' => 'cravatar' ),
			),
			array(
				'label' => __( '后台减噪与语言', 'wp-china-yes' ),
				'value' => ( $locale_follow && $noise_on ) ? __( '语言分离 · 通知减噪', 'wp-china-yes' ) : ( $locale_follow ? __( '前后台语言分离', 'wp-china-yes' ) : __( '默认设置', 'wp-china-yes' ) ),
			),
		);
	}

	/**
	 * Build one `Wenpai_Admin_UI::metric_tiles` item from a cached probe row.
	 *
	 * @param string                    $id      Tile key (`wporg`, `cravatar`, `admincdn`).
	 * @param string                    $label   Display label.
	 * @param string                    $badge   Optional free badge (`免费`).
	 * @param string                    $sub_ok  Healthy subtitle.
	 * @param array<string, mixed>|null $probe   Cached target row or null.
	 * @return array<string, mixed>
	 */
	private static function overview_metric_tile( string $id, string $label, string $badge, string $sub_ok, ?array $probe ): array {
		$ms   = is_array( $probe ) && isset( $probe['latency_ms'] ) && is_numeric( $probe['latency_ms'] ) ? (int) $probe['latency_ms'] : null;
		$res  = is_array( $probe ) && isset( $probe['result'] ) ? (string) $probe['result'] : '';
		$warn = 'fallback' === $res || 'down' === $res;
		$val  = null !== $ms ? (string) $ms : ( 'down' === $res ? __( '超时', 'wp-china-yes' ) : '—' );
		$unit = null !== $ms ? 'ms' : '';
		$sub  = $warn ? __( '已自动回退', 'wp-china-yes' ) : $sub_ok;

		return array(
			'label'       => $label,
			'badge'       => $badge,
			'value'       => $val,
			'unit'        => $unit,
			'sub'         => $sub,
			'tone'        => $warn ? 'warn' : '',
			'attrs'       => array( 'data-wpcy-tile' => $id ),
			'value_attrs' => array( 'data-wpcy-tile-val' => $id ),
			'sub_attrs'   => array( 'data-wpcy-tile-sub' => $id ),
		);
	}

	/**
	 * Build model-agnostic scrollable service quota items (cloud-overridable via `wpcy_catalog` transient
	 * and extensible by companion plugins via the `wpcy_quota_items` filter).
	 *
	 * @param string               $svc_url  Services directory URL.
	 * @param array<string, mixed> $settings Current settings snapshot.
	 * @param bool                 $is_bound Whether the site is currently bound to WenPai Cloud.
	 * @return array<int, array<string, mixed>>
	 */
	private static function overview_quota_items( string $svc_url, array $settings = array(), bool $is_bound = false ): array {
		$items = null;
		if ( function_exists( 'get_transient' ) ) {
			$catalog = get_transient( 'wpcy_catalog' );
			if ( is_array( $catalog ) && isset( $catalog['quotas'] ) && is_array( $catalog['quotas'] ) && array() !== $catalog['quotas'] ) {
				$items = $catalog['quotas'];
			}
		}
		if ( null === $items ) {
			$pub = PublicCatalog::cached();
			if ( isset( $pub['quotas'] ) && is_array( $pub['quotas'] ) && array() !== $pub['quotas'] ) {
				$items = $pub['quotas'];
			}
		}

		if ( null === $items ) {
			$items = array(
				array(
					'id'           => 'admincdn',
					'name'         => __( 'adminCDN 静态资源加速', 'wp-china-yes' ),
					'usage'        => __( '基础免费额度生效中', 'wp-china-yes' ),
					'percent'      => 24,
					'note'         => __( '超额自动切慢速通道或按需补充', 'wp-china-yes' ),
					'action_label' => __( '查看说明', 'wp-china-yes' ),
					'action_url'   => $svc_url,
				),
				array(
					'id'           => 'wpfonts',
					'name'         => __( '文风中文字体分片服务', 'wp-china-yes' ),
					'usage'        => __( '按扩展插件同步', 'wp-china-yes' ),
					'percent'      => 8,
					'note'         => __( '启用文风字体后在此汇总消耗', 'wp-china-yes' ),
					'action_label' => __( '浏览目录', 'wp-china-yes' ),
					'action_url'   => $svc_url,
				),
				array(
					'id'           => 'wpmind',
					'name'         => __( '心思 AI 接口服务', 'wp-china-yes' ),
					'usage'        => __( '按调用额度计量', 'wp-china-yes' ),
					'percent'      => 4,
					'note'         => __( '支持自有密钥或站点绑定额度', 'wp-china-yes' ),
					'action_label' => __( '服务目录', 'wp-china-yes' ),
					'action_url'   => $svc_url,
				),
				array(
					'id'    => 'motucloud',
					'name'  => __( '墨图图库与智储同步', 'wp-china-yes' ),
					'usage' => __( '当前不限额', 'wp-china-yes' ),
				),
				array(
					'id'    => 'cravatar',
					'name'  => __( 'Cravatar 头像与 WP.org 镜像', 'wp-china-yes' ),
					'usage' => __( '永久免费', 'wp-china-yes' ),
				),
			);
		}

		if ( function_exists( 'apply_filters' ) ) {
			$context  = array(
				'services_url' => $svc_url,
				'settings'     => $settings,
				'bound'        => $is_bound,
			);
			$filtered = apply_filters( 'wpcy_quota_items', $items, $context );
			if ( is_array( $filtered ) ) {
				$items = self::sanitize_quota_items( $filtered );
			}
		}

		return $items;
	}

	/**
	 * Normalize and sanitize quota items returned by `wpcy_quota_items` filters.
	 *
	 * @param array<int|string, mixed> $raw Raw quota rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_quota_items( array $raw ): array {
		$clean = array();
		foreach ( $raw as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$name = isset( $entry['name'] ) ? sanitize_text_field( (string) $entry['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$row = array(
				'name'  => $name,
				'usage' => isset( $entry['usage'] ) ? sanitize_text_field( (string) $entry['usage'] ) : '',
			);
			if ( isset( $entry['id'] ) && is_string( $entry['id'] ) && '' !== $entry['id'] ) {
				$row['id'] = sanitize_key( $entry['id'] );
			}
			if ( array_key_exists( 'percent', $entry ) && null !== $entry['percent'] && '' !== (string) $entry['percent'] && is_numeric( $entry['percent'] ) ) {
				$row['percent'] = max( 0, min( 100, (int) round( (float) $entry['percent'] ) ) );
			}
			if ( isset( $entry['tone'] ) && is_string( $entry['tone'] ) && '' !== $entry['tone'] ) {
				$row['tone'] = sanitize_key( $entry['tone'] );
			}
			if ( isset( $entry['note'] ) && is_string( $entry['note'] ) && '' !== $entry['note'] ) {
				$row['note'] = sanitize_text_field( $entry['note'] );
			}
			if ( isset( $entry['action_label'] ) && is_string( $entry['action_label'] ) && '' !== $entry['action_label'] ) {
				$row['action_label'] = sanitize_text_field( $entry['action_label'] );
			}
			if ( isset( $entry['action_url'] ) && is_string( $entry['action_url'] ) && '' !== $entry['action_url'] ) {
				$row['action_url'] = esc_url_raw( $entry['action_url'] );
			}
			$clean[] = $row;
		}
		return $clean;
	}

	/**
	 * Build `Wenpai_Admin_UI::ad_slot` args.
	 *
	 * Overview never renders a close button (`dismissible => false`):
	 * - Default (White-Label off): WenPai cloud-configurable spotlight carousel.
	 * - White-Label on: agencies either customize this card for their own client service/renewal/hosting
	 *   promotion, or explicitly turn on `promo_hidden` inside White-Label settings (`null` -> right card expands).
	 *
	 * @param array<string, mixed> $wl      Resolved WhiteLabel state.
	 * @param string               $svc_url Services URL.
	 * @return array<string, mixed>|null
	 */
	private static function overview_ad_slot_args( array $wl, string $svc_url ): ?array {
		if ( ! empty( $wl['enabled'] ) ) {
			if ( ! empty( $wl['promo_hidden'] ) ) {
				return null;
			}
			$agency_name = ! empty( $wl['brand_name'] ) ? (string) $wl['brand_name'] : __( '站点运维团队', 'wp-china-yes' );
			$support_url = ! empty( $wl['feedback_url'] ) ? (string) $wl['feedback_url'] : ( ! empty( $wl['help_url'] ) ? (string) $wl['help_url'] : '' );
			$cta_url     = ! empty( $wl['promo_cta_url'] ) ? (string) $wl['promo_cta_url'] : $support_url;
			$cta_label   = '' !== $cta_url
				? ( ! empty( $wl['promo_cta_label'] ) ? (string) $wl['promo_cta_label'] : __( '联系运维支持 ↗', 'wp-china-yes' ) )
				: '';
			$card_title  = ! empty( $wl['brand_name'] )
				? sprintf( /* translators: %s: agency brand name */ __( '%s · 专属服务', 'wp-china-yes' ), $agency_name )
				: __( '技术支持与维护', 'wp-china-yes' );
			$badge       = ! empty( $wl['promo_badge'] ) ? (string) $wl['promo_badge'] : __( '专属服务', 'wp-china-yes' );
			$headline    = ! empty( $wl['promo_title'] )
				? (string) $wl['promo_title']
				: sprintf( /* translators: %s: agency brand name */ __( '本站加速与日常维护由 %s 提供支持', 'wp-china-yes' ), $agency_name );
			$desc        = ! empty( $wl['promo_desc'] )
				? (string) $wl['promo_desc']
				: __( '日常核心更新、国内镜像加速与连通巡检均已配置就绪，如需续费、定制或运维协助可直接联系我们。', 'wp-china-yes' );

			return array(
				'dismissible' => false,
				'title'       => $card_title,
				'slides'      => array(
					array(
						'badge'      => $badge,
						'sub'        => $agency_name,
						'title'      => $headline,
						'desc'       => $desc,
						'pills'      => array( __( '工作时间响应', 'wp-china-yes' ), __( '专属支持', 'wp-china-yes' ) ),
						'foot_meta'  => $agency_name,
						'cta_label'  => $cta_label,
						'cta_url'    => $cta_url,
						'cta_target' => '_blank',
					),
				),
			);
		}

		if ( function_exists( 'get_transient' ) ) {
			$catalog = get_transient( 'wpcy_catalog' );
			if ( is_array( $catalog ) && isset( $catalog['promos'] ) && is_array( $catalog['promos'] ) && array() !== $catalog['promos'] ) {
				return array(
					'dismissible' => false,
					'title'       => __( '精选推荐', 'wp-china-yes' ),
					'slides'      => $catalog['promos'],
				);
			}
		}

		$pub = PublicCatalog::cached();
		if ( isset( $pub['promos'] ) && is_array( $pub['promos'] ) && array() !== $pub['promos'] ) {
			return array(
				'dismissible' => false,
				'title'       => __( '精选推荐', 'wp-china-yes' ),
				'slides'      => $pub['promos'],
			);
		}

		$wl_detail_url = self::page_url( 'wpcy-features', false, array( 'module' => 'whitelabel' ) );
		return array(
			'dismissible' => false,
			'title'       => __( '精选推荐', 'wp-china-yes' ),
			'slides'      => array(
				array(
					'badge'     => __( '商业授权', 'wp-china-yes' ),
					'sub'       => __( '建站代理商方案', 'wp-china-yes' ),
					'title'     => __( '面向客户交付站点？启用全套品牌白标', 'wp-china-yes' ),
					'desc'      => __( '替换为你的团队名称与工单入口；本推荐位可改为你的主机续费或运维服务入口，也可在白标设置中关闭。', 'wp-china-yes' ),
					'pills'     => array( __( '自有品牌交付', 'wp-china-yes' ), __( '代理商自用推荐位', 'wp-china-yes' ) ),
					'foot_meta' => __( '官方推荐 · 白标可自用', 'wp-china-yes' ),
					'cta_label' => __( '了解白标方案 →', 'wp-china-yes' ),
					'cta_url'   => $wl_detail_url,
				),
				array(
					'badge'     => __( '生态加速', 'wp-china-yes' ),
					'sub'       => __( '前端体验升级', 'wp-china-yes' ),
					'title'     => __( '文风商用中文字体 · 边缘动态分片加速', 'wp-china-yes' ),
					'desc'      => __( '精选免版权顾虑中文网页字库，国内多线按需切片，首屏不阻塞。', 'wp-china-yes' ),
					'pills'     => array( __( '免费商用字库', 'wp-china-yes' ), __( '边缘动态分片', 'wp-china-yes' ) ),
					'foot_meta' => __( '广告 · 云端可配', 'wp-china-yes' ),
					'cta_label' => __( '浏览生态目录 →', 'wp-china-yes' ),
					'cta_url'   => $svc_url,
				),
				array(
					'badge'     => __( '站点通行证', 'wp-china-yes' ),
					'sub'       => __( '免费跨站协同', 'wp-china-yes' ),
					'title'     => __( '关联文派云通行证，免费同步多插件初始额度', 'wp-china-yes' ),
					'desc'      => __( '一站式同步静态资源加速、中文字体与 AI 扩展额度，并开启节点异常回退通知。', 'wp-china-yes' ),
					'pills'     => array( __( '免费绑定', 'wp-china-yes' ), __( '跨站额度同步', 'wp-china-yes' ) ),
					'foot_meta' => __( '官方服务 · 免费开通', 'wp-china-yes' ),
					'cta_label' => __( '前往服务页绑定 →', 'wp-china-yes' ),
					'cta_url'   => $svc_url,
				),
			),
		);
	}

	/**
	 * Build 5x2 icon-box items for Overview quick actions & reserved WenPai Toolbox slots.
	 *
	 * @param array<string, mixed> $wl      Resolved WhiteLabel state.
	 * @param string               $svc_url Services URL.
	 * @return array<int, array<string, mixed>>
	 */
	private static function overview_quick_tools( array $wl, string $svc_url ): array {
		$maint_on    = MaintenanceModule::is_enabled();
		$maint_attrs = array(
			'data-wpcy-quick'       => 'toggle-maintenance',
			'data-wpcy-maint-state' => $maint_on ? '1' : '0',
		);
		if ( ! $maint_on ) {
			$maint_attrs['data-wenpai-confirm']         = __( '开启后，未登录的前台访客将看到 HTTP 503 维护提示；管理员登录、后台、REST API 与计划任务不受影响。确定现在开启？', 'wp-china-yes' );
			$maint_attrs['data-wenpai-confirm-title']   = __( '开启前台维护模式', 'wp-china-yes' );
			$maint_attrs['data-wenpai-confirm-ok']      = __( '立即开启', 'wp-china-yes' );
			$maint_attrs['data-wenpai-confirm-primary'] = '1';
		}

		$items = array(
			array(
				'label' => __( '刷新缓存', 'wp-china-yes' ),
				'icon'  => 'refresh',
				'attrs' => array( 'data-wpcy-quick' => 'flush-updates' ),
			),
			array(
				'label' => __( '重置回退', 'wp-china-yes' ),
				'icon'  => 'shield-check',
				'attrs' => array( 'data-wpcy-quick' => 'reset-fallback' ),
			),
			array(
				'label' => __( '可达自检', 'wp-china-yes' ),
				'icon'  => 'search',
				'attrs' => array( 'data-wpcy-quick' => 'scan-outbound' ),
			),
			array(
				'label' => __( '复制诊断', 'wp-china-yes' ),
				'icon'  => 'copy',
				'attrs' => array( 'data-wpcy-quick' => 'copy-report' ),
			),
			array(
				'label' => __( '清瞬态缓存', 'wp-china-yes' ),
				'icon'  => 'flash',
				'title' => __( '清理已过期的瞬态缓存并刷新对象缓存', 'wp-china-yes' ),
				'attrs' => array( 'data-wpcy-quick' => 'clear-transients' ),
			),
			array(
				'label' => $maint_on ? __( '维护中', 'wp-china-yes' ) : __( '维护模式', 'wp-china-yes' ),
				'icon'  => 'tools',
				'state' => $maint_on ? 'warn' : '',
				'title' => $maint_on
					? __( '前台维护模式已开启，点击关闭并恢复正常访问', 'wp-china-yes' )
					: __( '临时向前台访客展示维护提示，管理员后台不受影响', 'wp-china-yes' ),
				'attrs' => $maint_attrs,
			),
			array(
				'label' => __( '环境快照', 'wp-china-yes' ),
				'icon'  => 'server',
				'title' => __( '复制脱敏的 WordPress / PHP / 节点探测环境快照', 'wp-china-yes' ),
				'attrs' => array( 'data-wpcy-quick' => 'site-snapshot' ),
			),
			array(
				'label' => __( '国内化体检', 'wp-china-yes' ),
				'icon'  => 'shield-check',
				'title' => __( '查看国内化体检评分并一键修复优化项', 'wp-china-yes' ),
				'href'  => self::page_url( 'wpcy-diagnose' ) . '#wpcy-audit',
			),
		);

		$extra     = array();
		$raw_tools = array();
		if ( function_exists( 'get_transient' ) ) {
			$catalog = get_transient( 'wpcy_catalog' );
			if ( is_array( $catalog ) && isset( $catalog['toolbox'] ) && is_array( $catalog['toolbox'] ) && array() !== $catalog['toolbox'] ) {
				$raw_tools = $catalog['toolbox'];
			}
		}
		if ( array() === $raw_tools ) {
			$pub = PublicCatalog::cached();
			if ( isset( $pub['toolbox'] ) && is_array( $pub['toolbox'] ) ) {
				$raw_tools = $pub['toolbox'];
			}
		}
		foreach ( $raw_tools as $tool ) {
			if ( is_array( $tool ) && ! empty( $tool['label'] ) ) {
				$extra[] = array(
					'label' => sanitize_text_field( (string) $tool['label'] ),
					'icon'  => ! empty( $tool['icon'] ) ? sanitize_key( (string) $tool['icon'] ) : 'sparkle',
					'href'  => ! empty( $tool['href'] ) ? esc_url_raw( (string) $tool['href'] ) : '',
				);
			}
		}
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpcy_overview_toolbox_items', $extra );
			if ( is_array( $filtered ) ) {
				$extra = $filtered;
			}
		}

		$hide_eco  = ! empty( $wl['enabled'] ) && ! empty( $wl['hide_links'] );
		$pad_cap   = $hide_eco ? 10 : 9;
		$max_extra = max( 0, $pad_cap - count( $items ) );
		foreach ( array_slice( $extra, 0, $max_extra ) as $slot ) {
			if ( is_array( $slot ) && ! empty( $slot['label'] ) ) {
				$items[] = $slot;
			}
		}

		for ( $cur = count( $items ); $cur < $pad_cap; ++$cur ) {
			$items[] = array(
				'label'  => __( '工具预留', 'wp-china-yes' ),
				'icon'   => 'add',
				'dashed' => true,
				'attrs'  => array( 'data-wpcy-quick' => 'reserved-slot' ),
			);
		}
		if ( count( $items ) < 10 && ! $hide_eco ) {
			$items[] = array(
				'label'  => __( '生态目录', 'wp-china-yes' ),
				'icon'   => 'link',
				'dashed' => true,
				'href'   => $svc_url,
			);
		}

		return array_slice( $items, 0, 10 );
	}

	/**
	 * Build the 5x2 (10-item) WenPai companion plugin launcher grid (`文派组件`).
	 *
	 * Installed & active plugins navigate straight to their settings screen;
	 * inactive or uninstalled plugins prompt via the native kit dialog with one-click
	 * navigation to activate/install plus an optional product page link.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @return array<int, array<string, mixed>>
	 */
	private static function overview_suite_components( array $settings ): array {
		$catalog     = self::service_catalog();
		$reports     = self::service_status_reports( $settings );
		$can_install = ! function_exists( 'current_user_can' ) || current_user_can( 'install_plugins' );
		$can_act     = ! function_exists( 'current_user_can' ) || current_user_can( 'activate_plugins' );
		$defs        = array(
			array(
				'svc'   => 'cravatar',
				'label' => __( '文派头像', 'wp-china-yes' ),
				'icon'  => 'user',
			),
			array(
				'svc'   => 'windfonts',
				'label' => __( '文派字库', 'wp-china-yes' ),
				'icon'  => 'feather',
			),
			array(
				'svc'   => 'storage',
				'label' => __( '文派智储', 'wp-china-yes' ),
				'icon'  => 'server',
			),
			array(
				'svc'   => 'slug',
				'label' => __( '文派素格', 'wp-china-yes' ),
				'icon'  => 'equalizer',
			),
			array(
				'svc'   => 'ai',
				'label' => __( '文派心思', 'wp-china-yes' ),
				'icon'  => 'sparkle',
			),
			array(
				'svc'   => 'wporg',
				'label' => __( '文派云桥', 'wp-china-yes' ),
				'icon'  => 'cloud',
			),
			array(
				'svc'   => 'noise',
				'label' => __( '文派评论', 'wp-china-yes' ),
				'icon'  => 'feedback',
			),
			array(
				'svc'   => 'converter',
				'label' => __( '文派译词', 'wp-china-yes' ),
				'icon'  => 'map',
			),
			array(
				'svc'   => 'motucloud',
				'label' => __( '墨图图库', 'wp-china-yes' ),
				'icon'  => 'image',
			),
			array(
				'svc'   => 'compliance',
				'label' => __( '文派备案', 'wp-china-yes' ),
				'icon'  => 'shield-check',
			),
		);

		$items = array();
		foreach ( $defs as $def ) {
			$svc_id = $def['svc'];
			if ( ! isset( $catalog[ $svc_id ] ) ) {
				continue;
			}
			$spec   = $catalog[ $svc_id ];
			$report = isset( $reports[ $svc_id ] ) ? $reports[ $svc_id ] : array();
			$pstate = self::detect_companion_plugin( $spec['plugin_file'], $spec['plugin_slug'] );

			if ( $pstate['active'] ) {
				$settings_href = ! empty( $report['action_url'] )
					? $report['action_url']
					: ConflictDetector::resolve_companion_admin_url( $spec['plugin_slug'], $spec['plugin_file'] );
				$items[]       = array(
					'label' => $def['label'],
					'icon'  => $def['icon'],
					'state' => 'ok',
					'href'  => $settings_href,
					'title' => sprintf( /* translators: %s: companion plugin full name */ __( '%s · 已启用，点击进入设置', 'wp-china-yes' ), $spec['plugin_name'] ),
					'attrs' => array(
						'data-wpcy-suite-slug'  => $spec['plugin_slug'],
						'data-wpcy-suite-state' => 'active',
					),
				);
			} elseif ( $pstate['installed'] ) {
				if ( $can_act ) {
					$items[] = array(
						'label' => $def['label'],
						'icon'  => $def['icon'],
						'state' => 'warn',
						'title' => sprintf( /* translators: %s: companion plugin full name */ __( '%s · 已安装未启用，点击一键启用', 'wp-china-yes' ), $spec['plugin_name'] ),
						'attrs' => array(
							'data-wpcy-suite-slug'         => $spec['plugin_slug'],
							'data-wpcy-suite-state'        => 'installed',
							'data-wpcy-provision-slug'     => $spec['plugin_slug'],
							'data-wpcy-provision-name'     => $spec['plugin_name'],
							'data-wenpai-confirm-title'    => sprintf( /* translators: %s: companion plugin full name */ __( '启用%s', 'wp-china-yes' ), $spec['plugin_name'] ),
							'data-wenpai-confirm'          => $spec['plugin_pitch'],
							'data-wenpai-confirm-ok'       => __( '立即启用', 'wp-china-yes' ),
							'data-wenpai-confirm-cancel'   => __( '取消', 'wp-china-yes' ),
							'data-wenpai-confirm-primary'  => '1',
							'data-wenpai-confirm-more-label' => __( '组件主页', 'wp-china-yes' ),
							'data-wenpai-confirm-more-url' => $spec['plugin_url'],
						),
					);
				} else {
					$items[] = array(
						'label' => $def['label'],
						'icon'  => $def['icon'],
						'state' => 'warn',
						'href'  => self::page_url( 'wpcy-services', false, array( 'service' => $svc_id ) ),
						'title' => sprintf( /* translators: %s: companion plugin full name */ __( '%s · 已安装未启用', 'wp-china-yes' ), $spec['plugin_name'] ),
						'attrs' => array(
							'data-wpcy-suite-slug'  => $spec['plugin_slug'],
							'data-wpcy-suite-state' => 'installed',
						),
					);
				}
			} elseif ( $can_install && $can_act && ConflictDetector::is_companion_installable( $spec['plugin_slug'] ) ) {
				$items[] = array(
					'label' => $def['label'],
					'icon'  => $def['icon'],
					'title' => sprintf( /* translators: %s: companion plugin full name */ __( '%s · 未安装，点击一键安装并启用', 'wp-china-yes' ), $spec['plugin_name'] ),
					'attrs' => array(
						'data-wpcy-suite-slug'           => $spec['plugin_slug'],
						'data-wpcy-suite-state'          => 'missing',
						'data-wpcy-provision-slug'       => $spec['plugin_slug'],
						'data-wpcy-provision-name'       => $spec['plugin_name'],
						'data-wenpai-confirm-title'      => sprintf( /* translators: %s: companion plugin full name */ __( '安装%s', 'wp-china-yes' ), $spec['plugin_name'] ),
						'data-wenpai-confirm'            => $spec['plugin_pitch'],
						'data-wenpai-confirm-ok'         => __( '一键安装并启用', 'wp-china-yes' ),
						'data-wenpai-confirm-cancel'     => __( '取消', 'wp-china-yes' ),
						'data-wenpai-confirm-primary'    => '1',
						'data-wenpai-confirm-more-label' => __( '组件主页', 'wp-china-yes' ),
						'data-wenpai-confirm-more-url'   => $spec['plugin_url'],
					),
				);
			} else {
				$detail_href = self::page_url( 'wpcy-services', false, array( 'service' => $svc_id ) );
				$items[]     = array(
					'label' => $def['label'],
					'icon'  => $def['icon'],
					'href'  => $detail_href,
					'title' => sprintf( /* translators: %s: companion plugin full name */ __( '%s · 查看组件详情与获取方式', 'wp-china-yes' ), $spec['plugin_name'] ),
					'attrs' => array(
						'data-wpcy-suite-slug'           => $spec['plugin_slug'],
						'data-wpcy-suite-state'          => 'missing',
						'data-wenpai-confirm-title'      => $spec['plugin_name'],
						'data-wenpai-confirm'            => $spec['plugin_pitch'],
						'data-wenpai-confirm-ok'         => __( '查看详情', 'wp-china-yes' ),
						'data-wenpai-confirm-cancel'     => __( '取消', 'wp-china-yes' ),
						'data-wenpai-confirm-primary'    => '1',
						'data-wenpai-confirm-more-label' => __( '组件主页', 'wp-china-yes' ),
						'data-wenpai-confirm-more-url'   => $spec['plugin_url'],
					),
				);
			}
		}

		return $items;
	}

	/**
	 * Whether this site is currently bound to a WenPai Cloud Passport (`wpcy_site_identity`).
	 */
	private static function is_site_bound(): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		$identity = get_option( 'wpcy_site_identity', array() );
		return is_array( $identity )
			&& isset( $identity['binding'] )
			&& is_array( $identity['binding'] )
			&& 'bound' === (string) ( $identity['binding']['status'] ?? '' );
	}

	/**
	 * Compute active, un-dismissed actionable site suggestions merged into `近期运行记录`.
	 *
	 * Silent when everything is configured or dismissed — never emits filler "正常" rows.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @return list<array<string, string>>
	 */
	public static function active_suggestions( array $settings ): array {
		if ( ! empty( $settings['recovery_mode'] ) ) {
			return array();
		}
		$dismissed = array();
		if ( function_exists( 'get_option' ) ) {
			$raw_dis = get_option( 'wpcy_announcements_dismissed', array() );
			if ( is_array( $raw_dis ) ) {
				foreach ( $raw_dis as $did ) {
					if ( is_string( $did ) && '' !== $did ) {
						$dismissed[ $did ] = true;
					}
				}
			}
		}

		$profile  = (string) ( $settings['profile'] ?? 'domestic' );
		$conn     = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror   = self::conn_mirror( $conn );
		$scope    = self::conn_scope( $settings, $conn );
		$feeds    = (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$wl       = WhiteLabel::resolve( $settings );
		$diag_url = self::page_url( 'wpcy-diagnose' );
		$scan     = ConflictDetector::scan();
		$out      = array();

		foreach ( $scan['conflicts'] as $conf ) {
			$sug_id = 'sug.' . $conf['id'];
			if ( isset( $dismissed[ $sug_id ] ) ) {
				continue;
			}
			$out[] = array(
				'id'        => $sug_id,
				'tag'       => __( '冲突', 'wp-china-yes' ),
				'tone'      => 'bad' === $conf['level'] ? 'bad' : 'warn',
				'title'     => sprintf( /* translators: %s: conflicting plugin name */ __( '检测到「%s」同时启用，存在重复改写或失效节点冲突', 'wp-china-yes' ), $conf['name'] ),
				'fix_url'   => $diag_url,
				'fix_label' => __( '去排查', 'wp-china-yes' ),
			);
		}

		if ( 'domestic' === $profile && 'off' === $mirror && ! isset( $dismissed['sug.wporg'] ) ) {
			$out[] = array(
				'id'        => 'sug.wporg',
				'tag'       => __( '待处理', 'wp-china-yes' ),
				'tone'      => 'warn',
				'title'     => __( '核心与插件国内更新镜像当前已关闭', 'wp-china-yes' ),
				'fix_kind'  => 'wporg_mirror',
				'fix_label' => __( '一键开启', 'wp-china-yes' ),
			);
		}
		if ( 'domestic' === $profile && 'off' === $scope && ! isset( $dismissed['sug.assets'] ) ) {
			$out[] = array(
				'id'        => 'sug.assets',
				'tag'       => __( '待处理', 'wp-china-yes' ),
				'tone'      => 'warn',
				'title'     => __( '公共静态库与慢外链加速当前未启用', 'wp-china-yes' ),
				'fix_kind'  => 'public_assets',
				'fix_label' => __( '一键开启', 'wp-china-yes' ),
			);
		}
		if ( 'domestic' === $profile && 'block' !== $feeds && ! isset( $dismissed['sug.feeds'] ) ) {
			$out[] = array(
				'id'        => 'sug.feeds',
				'tag'       => __( '建议', 'wp-china-yes' ),
				'tone'      => 'warn',
				'title'     => __( 'WordPress 仪表盘默认慢速海外新闻源尚未替换', 'wp-china-yes' ),
				'fix_kind'  => 'dashboard_feeds',
				'fix_label' => __( '一键开启', 'wp-china-yes' ),
			);
		}
		if ( ! self::is_site_bound() && ! ( $wl['enabled'] && $wl['hide_links'] ) && ! isset( $dismissed['sug.passport'] ) ) {
			$out[] = array(
				'id'        => 'sug.passport',
				'tag'       => __( '待绑定', 'wp-china-yes' ),
				'tone'      => 'accent',
				'title'     => __( '尚未关联文派云通行证', 'wp-china-yes' ),
				'fix_kind'  => 'bind',
				'fix_label' => __( '绑定本站', 'wp-china-yes' ),
			);
		}

		return $out;
	}

	/**
	 * Convert `active_suggestions()` entries into SSR `Wenpai_Admin_UI::tag_feed` rows.
	 *
	 * @param list<array<string, string>> $suggestions Active suggestions.
	 * @return list<array<string, mixed>>
	 */
	private static function overview_suggestion_feed_items( array $suggestions ): array {
		$rows = array();
		foreach ( $suggestions as $sug ) {
			$id      = isset( $sug['id'] ) ? (string) $sug['id'] : '';
			$actions = '';
			if ( ! empty( $sug['fix_kind'] ) && 'bind' === $sug['fix_kind'] ) {
				$actions .= \Wenpai_Admin_UI::button_html(
					array(
						'label'   => $sug['fix_label'] ?? __( '绑定本站', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'secondary',
						'size'    => 'sm',
						'attrs'   => array( 'data-wpcy-action' => 'bind' ),
					)
				);
			} elseif ( ! empty( $sug['fix_kind'] ) ) {
				$actions .= \Wenpai_Admin_UI::button_html(
					array(
						'label'   => $sug['fix_label'] ?? __( '一键开启', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'secondary',
						'size'    => 'sm',
						'attrs'   => array(
							'data-wpcy-fix-sug' => $sug['fix_kind'],
							'data-wpcy-sug-id'  => $id,
						),
					)
				);
			} elseif ( ! empty( $sug['fix_url'] ) ) {
				$actions .= \Wenpai_Admin_UI::button_html(
					array(
						'label'   => $sug['fix_label'] ?? __( '去处理', 'wp-china-yes' ),
						'href'    => $sug['fix_url'],
						'variant' => 'secondary',
						'size'    => 'sm',
					)
				);
			}
			if ( '' !== $id ) {
				$actions .= \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '关闭', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'ghost',
						'size'    => 'sm',
						'attrs'   => array( 'data-wpcy-dismiss-sug' => $id ),
					)
				);
			}

			$rows[] = array(
				'tag'         => $sug['tag'] ?? __( '建议', 'wp-china-yes' ),
				'tone'        => $sug['tone'] ?? 'warn',
				'title'       => $sug['title'] ?? '',
				'note'        => $sug['note'] ?? '',
				'action_html' => $actions,
				'attrs'       => '' !== $id ? array( 'data-wpcy-sug-row' => $id ) : array(),
			);
		}
		return $rows;
	}

	/**
	 * Render the replacement fast domestic / agency news widget inside native WP Dashboard (`wp-admin/index.php`).
	 *
	 * @param array<string, mixed>              $settings Settings snapshot.
	 * @param list<array<string, string|mixed>> $items    Cached undismissed items from `AnnouncementsModule`.
	 */
	public static function render_dashboard_news_widget( array $settings, array $items ): void {
		if ( ! class_exists( '\Wenpai_Admin_UI', false ) ) {
			return;
		}
		$wl           = WhiteLabel::resolve( $settings );
		$overview_url = self::page_url( 'wpcy' );
		$news_set_url = self::page_url( 'wpcy-features', false, array( 'module' => 'news' ) );
		$feed_rows    = array();

		foreach ( array_slice( $items, 0, 5 ) as $item ) {
			$cat       = isset( $item['category'] ) ? (string) $item['category'] : '';
			$src_raw   = isset( $item['source'] ) ? (string) $item['source'] : '';
			$tone_raw  = isset( $item['tone'] ) ? (string) $item['tone'] : '';
			$tag       = 'release' === $cat
				? __( '版本', 'wp-china-yes' )
				: ( 'warn' === $tone_raw
					? __( '公告', 'wp-china-yes' )
					: ( 'wptea' === $src_raw
						? __( '茶馆', 'wp-china-yes' )
						: ( 'one' === $src_raw ? __( '官方', 'wp-china-yes' ) : __( '动态', 'wp-china-yes' ) ) ) );
			$tone      = 'warn' === $tone_raw ? 'warn' : ( ( 'release' === $cat || 'wptea' === $src_raw ) ? 'accent' : '' );
			$when      = '';
			$published = isset( $item['published_at'] ) ? (string) $item['published_at'] : ( isset( $item['date'] ) ? (string) $item['date'] : '' );
			if ( '' !== $published ) {
				$ts = strtotime( $published );
				if ( false !== $ts ) {
					$when = gmdate( 'm-d', $ts + 8 * 3600 );
				}
			}
			$feed_rows[] = array(
				'tag'    => $tag,
				'tone'   => $tone,
				'title'  => isset( $item['title'] ) ? (string) $item['title'] : ( isset( $item['message'] ) ? (string) $item['message'] : '' ),
				'url'    => isset( $item['url'] ) ? (string) $item['url'] : '',
				'target' => '_blank',
				'when'   => $when,
			);
		}

		if ( array() === $feed_rows ) {
			if ( $wl['enabled'] ) {
				$feed_rows[] = array(
					'tag'   => __( '公告', 'wp-china-yes' ),
					'title' => __( '当前没有新的站点维护通知', 'wp-china-yes' ),
					'note'  => __( '后台已自动屏蔽慢速海外默认事件源', 'wp-china-yes' ),
				);
			} else {
				$feed_rows[] = array(
					'tag'    => __( '社区', 'wp-china-yes' ),
					'tone'   => 'accent',
					'title'  => __( '文派茶馆 · WordPress 中文生态动态与版本维护公告', 'wp-china-yes' ),
					'url'    => 'https://wptea.com/',
					'target' => '_blank',
				);
				$feed_rows[] = array(
					'tag'   => __( '状态', 'wp-china-yes' ),
					'title' => __( '已替换默认慢速英文活动源，定期在后台静默同步最新中文动态', 'wp-china-yes' ),
				);
			}
		}

		echo '<div class="wenpai-app">';
		\Wenpai_Admin_UI::tag_feed( array( 'items' => $feed_rows ) );
		\Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => $wl['enabled'] ? __( '高速动态小工具', 'wp-china-yes' ) : __( '已替换默认慢速海外新闻源', 'wp-china-yes' ),
				'actions' => array(
					array(
						'label'   => __( '插件概览', 'wp-china-yes' ),
						'href'    => $overview_url,
						'variant' => 'ghost',
						'size'    => 'sm',
					),
					array(
						'label'   => __( '订阅设置', 'wp-china-yes' ),
						'href'    => $news_set_url,
						'variant' => 'ghost',
						'size'    => 'sm',
					),
				),
			)
		);
		echo '</div>';
	}

	/**
	 * Settings screen (`?page=wpcy-connect`): profile choice + core toggles + folded advanced rows.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Read-only subsite.
	 * @param bool                 $network  Network admin screen.
	 */
	private static function render_settings( array $settings, bool $locked, bool $network ): void {
		$profile     = (string) ( $settings['profile'] ?? 'domestic' );
		$conn        = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror      = self::conn_mirror( $conn );
		$avatar      = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat   = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds       = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$scope       = self::conn_scope( $settings, $conn );
		$icon_mode   = isset( $conn['icon_photos']['enabled'] ) ? (string) $conn['icon_photos']['enabled'] : 'off';
		$group_menus = ! empty( $settings['admin']['group_suite_menus'] );

		$lede = $locked
			? __( '当前站点继承网络设置，仅支持查看。', 'wp-china-yes' )
			: ( $network
				? __( '设置子站默认加速策略与覆盖权限。', 'wp-china-yes' )
				: __( '常用开关即时生效；切换场景或高级参数后点击右上角保存。', 'wp-china-yes' ) );

		$aside = '';
		if ( ! $locked ) {
			if ( ! $network ) {
				$aside .= \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '配置向导', 'wp-china-yes' ),
						'href'    => self::page_url( 'wpcy', false, array( 'view' => 'wizard' ) ),
						'variant' => 'secondary',
					)
				) . ' ';
			}
			$aside .= \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '保存设置', 'wp-china-yes' ),
					'type'    => 'submit',
					'variant' => 'primary',
					'form'    => 'wpcy-settings-form',
				)
			);
		}

		\Wenpai_Admin_Shell::page_head( __( '设置', 'wp-china-yes' ), $lede, $aside );

		echo '<div class="wenpai-stack" data-wpcy-screen="settings">';
		if ( $locked ) {
			\Wenpai_Admin_UI::notice(
				array(
					'tone' => 'info',
					'text' => __( '当前加速策略由网络管理员统一配置；如需单独调整，请在网络后台开启「允许子站覆盖」。', 'wp-china-yes' ),
				)
			);
		}

		echo '<form id="wpcy-settings-form" class="wenpai-stack" data-wpcy-network="' . ( $network ? '1' : '0' ) . '">';

		\Wenpai_Admin_UI::card_open();
		\Wenpai_Admin_UI::section_title(
			array(
				'title' => __( '站点场景', 'wp-china-yes' ),
			)
		);
		echo '<div class="choice-cards">';
		$scenes = self::scene_descriptions();
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

		\Wenpai_Admin_UI::card_open();
		\Wenpai_Admin_UI::section_title(
			array(
				'title' => __( '核心加速', 'wp-china-yes' ),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'cloud',
				'state'        => 'off' === $mirror ? '' : 'ok',
				'title'        => __( 'WordPress.org 镜像与中文语言包', 'wp-china-yes' ),
				'desc'         => __( '加速核心、插件、主题更新与简体中文语言包，异常自动回退。', 'wp-china-yes' ),
				'control_html' => self::toggle_control( 'wporg_enabled', 'off' !== $mirror, $locked ),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'flash',
				'state'        => 'off' === $scope ? '' : 'ok',
				'title'        => __( '公共前端库加速（adminCDN）', 'wp-china-yes' ),
				'desc'         => __( '加速 Google Fonts、CDNJS、jsDelivr 与 Emoji 静态资源。', 'wp-china-yes' ),
				'control_html' => self::toggle_control( 'public_assets_enabled', 'off' !== $scope, $locked ),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'image',
				'state'        => 'off' === $avatar ? '' : 'ok',
				'title'        => __( 'Cravatar 公共头像加速', 'wp-china-yes' ),
				'desc'         => __( '替代 Gravatar，提升评论区与后台头像加载速度。', 'wp-china-yes' ),
				'control_html' => self::toggle_control( 'avatar_enabled', 'off' !== $avatar, $locked ),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'sparkle',
				'state'        => 'off' === $icon_mode ? '' : 'ok',
				'title'        => __( '图标与免版权图库镜像', 'wp-china-yes' ),
				'desc'         => __( '加速编辑器矢量图标与免版权图库检索接口。', 'wp-china-yes' ),
				'control_html' => self::toggle_control( 'icon_photos_enabled', 'off' !== $icon_mode, $locked ),
			)
		);

		$adv_html  = self::field_row_select(
			'wporg_mirror',
			__( 'WordPress.org 下载源', 'wp-china-yes' ),
			'',
			array(
				'auto' => __( '国内镜像（异常自动回退）', 'wp-china-yes' ),
				'off'  => __( '直连 WordPress.org 官方源', 'wp-china-yes' ),
			),
			$mirror,
			$locked
		);
		$adv_html .= self::field_row_select(
			'public_assets_scope',
			__( '公共前端库生效范围', 'wp-china-yes' ),
			__( '跨境站建议选「仅后台」，避免改写海外访客前台资源。', 'wp-china-yes' ),
			array(
				'both'     => __( '后台与前台同时加速', 'wp-china-yes' ),
				'admin'    => __( '仅后台加速', 'wp-china-yes' ),
				'frontend' => __( '仅前台加速', 'wp-china-yes' ),
				'off'      => __( '未开启', 'wp-china-yes' ),
			),
			$scope,
			$locked
		);
		$adv_html .= self::field_row_select(
			'avatar_source',
			__( 'Cravatar 头像线路', 'wp-china-yes' ),
			'',
			array(
				'cravatar_cn'     => __( 'Cravatar 中国线路', 'wp-china-yes' ),
				'cravatar_global' => __( 'Cravatar 国际线路', 'wp-china-yes' ),
				'off'             => __( '未开启（使用原始 Gravatar）', 'wp-china-yes' ),
			),
			$avatar,
			$locked
		);
		$adv_html .= self::field_row_select(
			'icon_photos_mode',
			__( '图标与图库镜像范围', 'wp-china-yes' ),
			'',
			array(
				'on'    => __( '后台与前台同时加速', 'wp-china-yes' ),
				'admin' => __( '仅后台加速', 'wp-china-yes' ),
				'off'   => __( '未开启', 'wp-china-yes' ),
			),
			$icon_mode,
			$locked
		);
		$adv_html .= self::field_row_checkbox( 'heartbeat', __( '降低后台心跳频率', 'wp-china-yes' ), __( '停用仪表盘轮询，编辑器自动保存间隔调整为 60 秒', 'wp-china-yes' ), $heartbeat, $locked );
		$adv_html .= self::field_row_checkbox( 'dashboard_feeds', __( '屏蔽仪表盘外部资讯', 'wp-china-yes' ), __( '跳过后台首页对海外官方活动与英文新闻源的连接', 'wp-china-yes' ), $feeds, $locked );

		$summary = ( 'off' === $mirror ? __( '直连官方源', 'wp-china-yes' ) : __( '国内镜像', 'wp-china-yes' ) )
			. ' · ' . ( 'off' === $scope ? __( '前端库未开启', 'wp-china-yes' ) : __( '前端库已开启', 'wp-china-yes' ) )
			. ' · ' . ( $heartbeat ? __( '心跳已降频', 'wp-china-yes' ) : __( '默认心跳频率', 'wp-china-yes' ) );

		\Wenpai_Admin_UI::fold_list(
			array(
				'items' => array(
					array(
						'icon'      => 'settings',
						'title'     => __( '高级线路与后台参数', 'wp-china-yes' ),
						'desc'      => $summary,
						'body_html' => $adv_html,
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();

		if ( ! $network ) {
			$menu_name = WhiteLabel::menu_name( $settings );
			\Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '后台菜单整理', 'wp-china-yes' ),
					'tile'  => false,
				)
			);
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'equalizer',
					'state'        => $group_menus ? 'ok' : '',
					'title'        => sprintf( /* translators: %s: left sidebar menu name */ __( '将文派系列插件收纳到「%s」菜单下', 'wp-china-yes' ), $menu_name ),
					'desc'         => __( '仅在收纳了其他已启用插件时展开左侧子菜单，默认保持单入口。', 'wp-china-yes' ),
					'control_html' => self::toggle_control( 'admin_group_suite_menus', $group_menus, $locked ),
				)
			);
			\Wenpai_Admin_UI::card_close();
		}
		if ( ! $locked ) {
			\Wenpai_Admin_UI::savebar(
				array(
					'submit_label' => __( '保存设置', 'wp-china-yes' ),
				)
			);
		}
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Features screen (`?page=wpcy-features`): built-in governance & delivery modules.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are locked.
	 */
	private static function render_features( array $settings, bool $locked = false ): void {
		$module = isset( $_GET['module'] ) ? sanitize_key( wp_unslash( (string) $_GET['module'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only module routing.
		if ( 'locale' === $module ) {
			self::render_feature_locale( $settings, $locked );
			return;
		}
		if ( 'news' === $module ) {
			self::render_feature_news( $settings, $locked );
			return;
		}
		if ( 'outbound' === $module ) {
			self::render_outbound( $locked );
			return;
		}
		if ( 'noise' === $module ) {
			self::render_feature_noise( $settings, $locked );
			return;
		}
		if ( 'whitelabel' === $module ) {
			self::render_feature_whitelabel( $settings, $locked );
			return;
		}

		$conn          = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$locale_follow = ! isset( $conn['admin_locale_follow'] ) || (bool) $conn['admin_locale_follow'];
		$news_enabled  = ! isset( $settings['admin']['news_enabled'] ) || (bool) $settings['admin']['news_enabled'];
		$heartbeat     = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds         = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$notice_ctrl   = ! isset( $settings['modules']['notice_control'] ) || (bool) $settings['modules']['notice_control'];
		$hide_promo    = ! isset( $settings['admin']['hide_promo'] ) || (bool) $settings['admin']['hide_promo'];
		$noise_on      = $heartbeat || $feeds || $notice_ctrl || $hide_promo;
		$bl_raw        = isset( $settings['modules']['site_blocklist'] ) && is_array( $settings['modules']['site_blocklist'] ) ? $settings['modules']['site_blocklist'] : array();
		$bl_on         = ! empty( $bl_raw['enabled'] );
		$bl_count      = isset( $bl_raw['hosts'] ) && is_array( $bl_raw['hosts'] ) ? count( $bl_raw['hosts'] ) : 0;
		$wl            = WhiteLabel::resolve( $settings );

		\Wenpai_Admin_Shell::page_head(
			__( '功能', 'wp-china-yes' ),
			__( '内置本地治理与品牌交付模块，按需独立启用。', 'wp-china-yes' )
		);

		$modules = array(
			array(
				'id'     => 'locale',
				'icon'   => 'map',
				'title'  => __( '前后台语言分离', 'wp-china-yes' ),
				'desc'   => __( '前台保持访客语言，管理后台跟随当前管理员语言。', 'wp-china-yes' ),
				'active' => $locale_follow,
				'pill'   => $locale_follow ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' ),
				'metric' => $locale_follow ? __( '跟随管理员语言', 'wp-china-yes' ) : __( '前后台同一语言', 'wp-china-yes' ),
				'url'    => self::page_url( 'wpcy-features', false, array( 'module' => 'locale' ) ),
				'toggle' => 'admin_locale_follow',
			),
			array(
				'id'     => 'noise',
				'icon'   => 'settings',
				'title'  => __( '后台减噪与通知治理', 'wp-china-yes' ),
				'desc'   => __( '收纳非关键通知、隐藏营销横幅并减少后台慢请求。', 'wp-china-yes' ),
				'active' => $noise_on,
				'pill'   => $noise_on ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' ),
				'metric' => ( $notice_ctrl || $hide_promo )
					? __( '通知收纳已生效', 'wp-china-yes' )
					: __( '按需开启减噪', 'wp-china-yes' ),
				'url'    => self::page_url( 'wpcy-features', false, array( 'module' => 'noise' ) ),
			),
			array(
				'id'     => 'news',
				'icon'   => 'feedback',
				'title'  => __( '资讯与公告源', 'wp-china-yes' ),
				'desc'   => __( '订阅中文社区动态与维护通知，白标可换自有源。', 'wp-china-yes' ),
				'active' => $news_enabled,
				'pill'   => $news_enabled ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' ),
				'metric' => ( $wl['enabled'] && '' !== $wl['news_feed_url'] )
					? __( '白标自定义公告源', 'wp-china-yes' )
					: __( '默认源 read.wptea.com', 'wp-china-yes' ),
				'url'    => self::page_url( 'wpcy-features', false, array( 'module' => 'news' ) ),
				'toggle' => 'admin_news_enabled',
			),
			array(
				'id'     => 'outbound',
				'icon'   => 'info',
				'title'  => __( '外链治理', 'wp-china-yes' ),
				'desc'   => __( '按域名阻断慢请求，支持实时检测外部网址连接策略。', 'wp-china-yes' ),
				'active' => $bl_on,
				'pill'   => $bl_on ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' ),
				'metric' => $bl_count > 0
					? sprintf( /* translators: %d: host count */ __( '已设 %d 个拦截域名', 'wp-china-yes' ), $bl_count )
					: __( '核心白名单生效中', 'wp-china-yes' ),
				'url'    => self::page_url( 'wpcy-features', false, array( 'module' => 'outbound' ) ),
			),
			array(
				'id'     => 'whitelabel',
				'icon'   => 'sparkle',
				'title'  => __( '全套白标', 'wp-china-yes' ),
				'desc'   => __( '一次设置即可将全站文派插件统一替换为自有品牌与支持入口。', 'wp-china-yes' ),
				'active' => $wl['enabled'],
				'pill'   => ! $wl['entitled']
					? __( '需授权', 'wp-china-yes' )
					: ( $wl['locked'] ? __( '常量锁定', 'wp-china-yes' ) : ( $wl['enabled'] ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' ) ) ),
				'metric' => ! $wl['entitled']
					? __( '商业交付 · 需授权', 'wp-china-yes' )
					: ( $wl['enabled']
						? ( '' !== $wl['brand_name'] ? sprintf( /* translators: %s: brand name */ __( '当前品牌：%s', 'wp-china-yes' ), $wl['brand_name'] ) : __( '全套白标已生效', 'wp-china-yes' ) )
						: __( '已获授权，开启即生效', 'wp-china-yes' ) ),
				'url'    => self::page_url( 'wpcy-features', false, array( 'module' => 'whitelabel' ) ),
				'btn'    => ! $wl['entitled'] ? __( '了解与开通', 'wp-china-yes' ) : __( '配置', 'wp-china-yes' ),
			),
		);

		$active_mods   = array();
		$inactive_mods = array();
		foreach ( $modules as $mod ) {
			if ( ! empty( $mod['active'] ) ) {
				$active_mods[] = $mod;
			} else {
				$inactive_mods[] = $mod;
			}
		}

		echo '<div class="wenpai-stack" data-wpcy-screen="features">';

		if ( ! empty( $active_mods ) ) {
			$grid_items = array();
			foreach ( $active_mods as $mod ) {
				$ctl_html     = ! empty( $mod['toggle'] )
					? self::switch_control( (string) $mod['toggle'], true, $locked, array( 'data-wpcy-instant' => '1' ), false )
					: \Wenpai_Admin_UI::pill_html(
						array(
							'text'  => $mod['pill'],
							'state' => 'ok',
						)
					);
				$grid_items[] = array(
					'title'          => $mod['title'],
					'desc'           => $mod['desc'],
					'icon'           => $mod['icon'],
					'tone'           => 'ok',
					'control_html'   => $ctl_html,
					'status'         => $mod['metric'],
					'status_tone'    => 'ok',
					'action_label'   => ! empty( $mod['btn'] ) ? (string) $mod['btn'] : __( '配置', 'wp-china-yes' ),
					'action_url'     => $mod['url'],
					'action_variant' => 'ghost',
					'attrs'          => array( 'data-wpcy-core-card' => $mod['id'] ),
				);
			}
			\Wenpai_Admin_UI::module_grid( array( 'items' => $grid_items ) );
		}

		if ( ! empty( $inactive_mods ) ) {
			\Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '更多内置功能', 'wp-china-yes' ),
					'tile'  => false,
				)
			);
			foreach ( $inactive_mods as $mod ) {
				$is_gated = 'whitelabel' === $mod['id'] && ! $wl['entitled'];
				\Wenpai_Admin_UI::row(
					array(
						'icon'         => $mod['icon'],
						'title'        => $mod['title'],
						'desc'         => $mod['desc'],
						'pill'         => array(
							'text'  => $mod['pill'],
							'state' => 'mute',
						),
						'control_html' => \Wenpai_Admin_UI::button_html(
							array(
								'label'   => ! empty( $mod['btn'] ) ? (string) $mod['btn'] : __( '配置', 'wp-china-yes' ),
								'href'    => $mod['url'],
								'variant' => $is_gated ? 'secondary' : 'ghost',
								'size'    => 'sm',
							)
						),
					)
				);
			}
			\Wenpai_Admin_UI::card_close();
		}
		echo '</div>';
	}

	/**
	 * Feature module detail: News & Announcements Feed (`?page=wpcy-features&module=news`).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are locked.
	 */
	private static function render_feature_news( array $settings, bool $locked ): void {
		$back_url     = self::page_url( 'wpcy-features' );
		$news_enabled = ! isset( $settings['admin']['news_enabled'] ) || (bool) $settings['admin']['news_enabled'];
		$wl           = WhiteLabel::resolve( $settings );
		$wl_locked    = $locked || $wl['locked'] || ! $wl['entitled'];
		$wl_url       = self::page_url( 'wpcy-features', false, array( 'module' => 'whitelabel' ) );

		$aside_html = '';
		if ( ! $wl_locked ) {
			$aside_html = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '保存新闻源', 'wp-china-yes' ),
					'type'    => 'submit',
					'variant' => 'primary',
					'form'    => 'wpcy-settings-form',
				)
			);
		}

		\Wenpai_Admin_Shell::subpage_head(
			array(
				'title'      => __( '资讯与公告源', 'wp-china-yes' ),
				'back_url'   => $back_url,
				'back_label' => __( '返回功能列表', 'wp-china-yes' ),
				'lede'       => __( '管理概览页展示的社区动态与版本维护通知。', 'wp-china-yes' ),
				'aside_html' => $aside_html,
			)
		);
		echo '<div class="wenpai-stack" data-wpcy-screen="feature-news">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '文派茶馆与官方通知', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'feedback',
				'state'        => $news_enabled ? 'ok' : '',
				'title'        => __( '启用资讯与公告推送', 'wp-china-yes' ),
				'desc'         => __( '每日同步一次文派茶馆动态（read.wptea.com），默认显示 5 条，超出可下拉滑动查看。', 'wp-china-yes' ),
				'control_html' => self::toggle_control( 'admin_news_enabled', $news_enabled, $locked ),
			)
		);
		\Wenpai_Admin_UI::card_close();

		if ( ! $wl['entitled'] ) {
			\Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '自有品牌新闻源', 'wp-china-yes' ),
					'tile'  => false,
				)
			);
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'sparkle',
					'title'        => __( '自定义 HTTPS 公告接口', 'wp-china-yes' ),
					'desc'         => __( '开通白标授权后，可将默认资讯源替换为团队自有的客户公告地址。', 'wp-china-yes' ),
					'pill'         => array(
						'text'  => __( '需授权', 'wp-china-yes' ),
						'state' => 'mute',
					),
					'control_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '查看全套白标授权', 'wp-china-yes' ),
							'href'    => $wl_url,
							'variant' => 'secondary',
							'size'    => 'sm',
						)
					),
				)
			);
			\Wenpai_Admin_UI::card_close();
		} else {
			echo '<form id="wpcy-settings-form" class="wenpai-stack" data-wpcy-network="0">';
			$profile   = (string) ( $settings['profile'] ?? 'domestic' );
			$conn      = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
			$mirror    = self::conn_mirror( $conn );
			$avatar    = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
			$scope     = self::conn_scope( $settings, $conn );
			$heartbeat = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
			$feeds     = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
			echo '<input type="hidden" name="profile" value="' . esc_attr( $profile ) . '">';
			echo '<input type="hidden" name="wporg_mirror" value="' . esc_attr( $mirror ) . '">';
			echo '<input type="hidden" name="public_assets_scope" value="' . esc_attr( $scope ) . '">';
			echo '<input type="hidden" name="avatar_source" value="' . esc_attr( $avatar ) . '">';
			if ( $heartbeat ) {
				echo '<input type="hidden" name="heartbeat" value="1">';
			}
			if ( $feeds ) {
				echo '<input type="hidden" name="dashboard_feeds" value="1">';
			}
			if ( ! $wl_locked ) {
				echo '<input type="hidden" name="wl_enabled" value="' . ( $wl['enabled'] ? '1' : '0' ) . '">';
				echo '<input type="hidden" name="wl_brand_name" value="' . esc_attr( $wl['brand_name'] ) . '">';
				echo '<input type="hidden" name="wl_help_url" value="' . esc_attr( $wl['help_url'] ) . '">';
				echo '<input type="hidden" name="wl_feedback_url" value="' . esc_attr( $wl['feedback_url'] ) . '">';
				echo '<input type="hidden" name="wl_docs_url" value="' . esc_attr( $wl['docs_url'] ) . '">';
				echo '<input type="hidden" name="wl_logo_url" value="' . esc_attr( $wl['logo_url'] ) . '">';
				if ( $wl['hide_logo'] ) {
					echo '<input type="hidden" name="wl_hide_logo" value="1">';
				}
				if ( $wl['hide_links'] ) {
					echo '<input type="hidden" name="wl_hide_links" value="1">';
				}
				if ( $wl['promo_hidden'] ) {
					echo '<input type="hidden" name="wl_promo_hidden" value="1">';
				}
				echo '<input type="hidden" name="wl_promo_badge" value="' . esc_attr( $wl['promo_badge'] ) . '">';
				echo '<input type="hidden" name="wl_promo_title" value="' . esc_attr( $wl['promo_title'] ) . '">';
				echo '<input type="hidden" name="wl_promo_desc" value="' . esc_attr( $wl['promo_desc'] ) . '">';
				echo '<input type="hidden" name="wl_promo_cta_label" value="' . esc_attr( $wl['promo_cta_label'] ) . '">';
				echo '<input type="hidden" name="wl_promo_cta_url" value="' . esc_attr( $wl['promo_cta_url'] ) . '">';
			}

			\Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '自有品牌新闻源', 'wp-china-yes' ),
					'tile'  => false,
				)
			);
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside field_row_input.
			echo self::field_row_input(
				'wl_news_feed_url',
				'url',
				__( '自定义新闻源地址', 'wp-china-yes' ),
				__( '需返回包含标题、链接与摘要的 HTTPS 公告列表；留空保持默认源。', 'wp-china-yes' ),
				$wl['news_feed_url'],
				'https://example.com/wp-json/agency/v1/news',
				300,
				$wl_locked
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			\Wenpai_Admin_UI::card_close();
			if ( ! $wl_locked ) {
				\Wenpai_Admin_UI::savebar(
					array(
						'submit_label' => __( '保存新闻源', 'wp-china-yes' ),
					)
				);
			}
			echo '</form>';
		}
		echo '</div>';
	}

	/**
	 * Feature module detail: Admin/Frontend Locale Separation (`?page=wpcy-features&module=locale`).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are locked.
	 */
	private static function render_feature_locale( array $settings, bool $locked ): void {
		$back_url      = self::page_url( 'wpcy-features' );
		$conn          = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$locale_follow = ! isset( $conn['admin_locale_follow'] ) || (bool) $conn['admin_locale_follow'];
		$profile_url   = self::wp_admin_link( 'profile.php' );

		\Wenpai_Admin_Shell::subpage_head(
			array(
				'title'      => __( '前后台语言分离', 'wp-china-yes' ),
				'back_url'   => $back_url,
				'back_label' => __( '返回功能列表', 'wp-china-yes' ),
				'lede'       => __( '前台保持访客语言，管理后台按当前管理员偏好独立显示。', 'wp-china-yes' ),
			)
		);
		echo '<div class="wenpai-stack" data-wpcy-screen="feature-locale">';

		echo '<form id="wpcy-settings-form" class="wenpai-stack" data-wpcy-network="0">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '后台语言策略', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'map',
				'state'        => $locale_follow ? 'ok' : '',
				'title'        => __( '后台语言跟随当前管理员', 'wp-china-yes' ),
				'desc'         => __( '站点设为外语时，管理员在「个人资料」选择简体中文即可中文管理后台。', 'wp-china-yes' ),
				'control_html' => self::toggle_control( 'admin_locale_follow', $locale_follow, $locked ),
			)
		);
		\Wenpai_Admin_UI::card_foot(
			array(
				'start'   => true,
				'meta'    => __( '前台页面与搜索引擎语言标记保持站点原始设置。', 'wp-china-yes' ),
				'actions' => array(
					array(
						'label'   => __( '设置个人资料语言', 'wp-china-yes' ),
						'href'    => $profile_url,
						'variant' => 'secondary',
						'size'    => 'sm',
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Feature module detail: Admin Noise & Notice Governance (`?page=wpcy-features&module=noise`).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are locked.
	 */
	private static function render_feature_noise( array $settings, bool $locked ): void {
		$back_url    = self::page_url( 'wpcy-features' );
		$profile     = (string) ( $settings['profile'] ?? 'domestic' );
		$conn        = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror      = self::conn_mirror( $conn );
		$avatar      = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat   = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds       = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$scope       = self::conn_scope( $settings, $conn );
		$notice_ctrl = ! isset( $settings['modules']['notice_control'] ) || (bool) $settings['modules']['notice_control'];
		$hide_promo  = ! isset( $settings['admin']['hide_promo'] ) || (bool) $settings['admin']['hide_promo'];

		$aside_html = '';
		if ( ! $locked ) {
			$aside_html = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '保存设置', 'wp-china-yes' ),
					'type'    => 'submit',
					'variant' => 'primary',
					'form'    => 'wpcy-settings-form',
				)
			);
		}

		\Wenpai_Admin_Shell::subpage_head(
			array(
				'title'      => __( '后台减噪与通知治理', 'wp-china-yes' ),
				'back_url'   => $back_url,
				'back_label' => __( '返回功能列表', 'wp-china-yes' ),
				'lede'       => __( '收纳插件通知横幅，减少拖慢后台的轮询与外部请求。', 'wp-china-yes' ),
				'aside_html' => $aside_html,
			)
		);
		echo '<div class="wenpai-stack" data-wpcy-screen="feature-noise">';

		echo '<form id="wpcy-settings-form" class="wenpai-stack" data-wpcy-network="0">';
		echo '<input type="hidden" name="profile" value="' . esc_attr( $profile ) . '">';
		echo '<input type="hidden" name="wporg_mirror" value="' . esc_attr( $mirror ) . '">';
		echo '<input type="hidden" name="public_assets_scope" value="' . esc_attr( $scope ) . '">';
		echo '<input type="hidden" name="avatar_source" value="' . esc_attr( $avatar ) . '">';
		echo '<input type="hidden" name="noise_form" value="1">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '通知收纳与请求减负', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside field_row_* helpers.
		echo self::field_row_checkbox( 'notice_control', __( '后台通知集中收纳', 'wp-china-yes' ), __( '折叠页面顶部的非关键插件提示（保留系统与安全告警）', 'wp-china-yes' ), $notice_ctrl, $locked );
		echo self::field_row_checkbox( 'hide_promo', __( '隐藏第三方营销横幅', 'wp-china-yes' ), __( '过滤第三方插件常驻的付费广告条与打分弹窗', 'wp-china-yes' ), $hide_promo, $locked );
		echo self::field_row_checkbox( 'heartbeat', __( '降低后台心跳频率', 'wp-china-yes' ), __( '停用仪表盘轮询，编辑器自动保存间隔调整为 60 秒', 'wp-china-yes' ), $heartbeat, $locked );
		echo self::field_row_checkbox( 'dashboard_feeds', __( '屏蔽仪表盘外部资讯', 'wp-china-yes' ), __( '跳过后台首页对海外官方新闻与活动接口的同步请求', 'wp-china-yes' ), $feeds, $locked );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		\Wenpai_Admin_UI::card_close();
		if ( ! $locked ) {
			\Wenpai_Admin_UI::savebar(
				array(
					'submit_label' => __( '保存设置', 'wp-china-yes' ),
				)
			);
		}
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Feature module detail: Suite-wide White-Label (`?page=wpcy-features&module=whitelabel`).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are locked.
	 */
	private static function render_feature_whitelabel( array $settings, bool $locked ): void {
		$back_url  = self::page_url( 'wpcy-features' );
		$profile   = (string) ( $settings['profile'] ?? 'domestic' );
		$conn      = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror    = self::conn_mirror( $conn );
		$avatar    = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds     = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$scope     = self::conn_scope( $settings, $conn );
		WhiteLabel::maybe_refresh_license_cache();
		$wl        = WhiteLabel::resolve( $settings );
		$entitled  = ! empty( $wl['entitled'] );
		$wl_locked = $locked || $wl['locked'] || ! $entitled;

		$aside_html = '';
		if ( ! $wl_locked ) {
			$aside_html = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '保存白标设置', 'wp-china-yes' ),
					'type'    => 'submit',
					'variant' => 'primary',
					'form'    => 'wpcy-settings-form',
				)
			);
		}

		\Wenpai_Admin_Shell::subpage_head(
			array(
				'title'      => __( '全套白标', 'wp-china-yes' ),
				'back_url'   => $back_url,
				'back_label' => __( '返回功能列表', 'wp-china-yes' ),
				'lede'       => __( '将全站文派系列插件统一替换为自有品牌名称与支持入口。', 'wp-china-yes' ),
				'aside_html' => $aside_html,
			)
		);
		echo '<div class="wenpai-stack" data-wpcy-screen="feature-whitelabel">';
		if ( ! $entitled ) {
			$oem_url = 'https://wpcy.com/go/oem';
			if ( function_exists( 'get_option' ) ) {
				$ident = get_option( \WenPai\ChinaYes\Config\Schema::SITE_IDENTITY, array() );
				if ( is_array( $ident ) && ! empty( $ident['site_uuid'] ) && is_string( $ident['site_uuid'] ) ) {
					$oem_url = function_exists( 'add_query_arg' )
						? (string) add_query_arg( 'site_uuid', $ident['site_uuid'], $oem_url )
						: $oem_url . '?site_uuid=' . rawurlencode( $ident['site_uuid'] );
				}
			}
			\Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '开通商业白标授权（Agency / OEM）', 'wp-china-yes' ),
					'tile'  => false,
				)
			);
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'sparkle',
					'title'        => __( '当前站点尚未关联白标授权', 'wp-china-yes' ),
					'desc'         => __( '面向建站服务商与企业交付，支持 72 小时离线缓存保护。', 'wp-china-yes' ),
					'pill'         => array(
						'text'  => __( '未授权', 'wp-china-yes' ),
						'state' => 'mute',
					),
					'control_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '绑定本站同步授权', 'wp-china-yes' ),
							'type'    => 'button',
							'variant' => 'secondary',
							'size'    => 'sm',
							'attrs'   => array( 'data-wpcy-action' => 'bind' ),
						)
					) . ' ' . \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '前往开通授权', 'wp-china-yes' ),
							'href'    => $oem_url,
							'variant' => 'primary',
							'size'    => 'sm',
							'target'  => '_blank',
						)
					),
				)
			);
			\Wenpai_Admin_UI::card_close();
		} elseif ( $wl['locked'] ) {
			\Wenpai_Admin_UI::notice(
				array(
					'tone' => 'info',
					'text' => __( '当前白标参数已通过 wp-config.php 常量锁定，后台处于只读保护状态。', 'wp-china-yes' ),
				)
			);
		}
		echo '<form id="wpcy-settings-form" class="wenpai-stack" data-wpcy-network="0">';
		echo '<input type="hidden" name="profile" value="' . esc_attr( $profile ) . '">';
		echo '<input type="hidden" name="wporg_mirror" value="' . esc_attr( $mirror ) . '">';
		echo '<input type="hidden" name="public_assets_scope" value="' . esc_attr( $scope ) . '">';
		echo '<input type="hidden" name="avatar_source" value="' . esc_attr( $avatar ) . '">';
		if ( $heartbeat ) {
			echo '<input type="hidden" name="heartbeat" value="1">';
		}
		if ( $feeds ) {
			echo '<input type="hidden" name="dashboard_feeds" value="1">';
		}

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => $entitled ? __( '品牌署名与服务链接', 'wp-china-yes' ) : __( '白标定制项一览（开通授权后可编辑）', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'sparkle',
				'state'        => $wl['enabled'] ? 'ok' : '',
				'title'        => __( '启用全套白标模式', 'wp-china-yes' ),
				'desc'         => __( '向全站文派插件同步下方品牌设置，留空项保留默认，不影响更新通道。', 'wp-china-yes' ),
				'control_html' => self::switch_control( 'wl_enabled', $wl['enabled'], $wl_locked, array( 'data-wpcy-wl-toggle' => '1' ) ),
			)
		);
		echo '<div class="wenpai-stack--xs" data-wpcy-wl-fields="1"' . ( ( $wl['enabled'] || ! $entitled ) ? '' : ' hidden' ) . '>';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside field_row_* helpers.
		echo self::field_row_input( 'wl_brand_name', 'text', __( '品牌名称', 'wp-china-yes' ), __( '替换顶栏品牌名、左侧菜单名与插件列表署名；留空时左侧菜单默认显示「文派」。', 'wp-china-yes' ), $wl['brand_name'], __( '例如：某某数字建站', 'wp-china-yes' ), 64, $wl_locked );
		echo self::field_row_input( 'wl_help_url', 'url', __( '帮助中心地址', 'wp-china-yes' ), '', $wl['help_url'], 'https://example.com/help', 300, $wl_locked );
		echo self::field_row_input( 'wl_feedback_url', 'url', __( '工单与反馈地址', 'wp-china-yes' ), '', $wl['feedback_url'], 'https://example.com/support', 300, $wl_locked );
		echo self::field_row_input( 'wl_docs_url', 'url', __( '产品文档地址', 'wp-china-yes' ), '', $wl['docs_url'], 'https://example.com/docs', 300, $wl_locked );
		echo self::field_row_input( 'wl_news_feed_url', 'url', __( '自定义新闻源地址', 'wp-china-yes' ), __( '概览页 HTTPS 公告列表接口地址，留空保持默认源。', 'wp-china-yes' ), $wl['news_feed_url'], 'https://example.com/wp-json/agency/v1/news', 300, $wl_locked );
		echo self::field_row_input( 'wl_logo_url', 'url', __( '自定义图标地址', 'wp-china-yes' ), __( '建议使用 44×44 正方形透明背景 PNG 或 SVG。', 'wp-china-yes' ), $wl['logo_url'], 'https://example.com/logo.svg', 300, $wl_locked );
		echo self::field_row_checkbox( 'wl_hide_logo', __( '隐藏顶栏品牌图标', 'wp-china-yes' ), __( '仅显示纯文字品牌名称', 'wp-china-yes' ), $wl['hide_logo'], $wl_locked );
		echo self::field_row_checkbox( 'wl_hide_links', __( '隐藏未安装扩展与默认外链', 'wp-china-yes' ), __( '隐藏「服务」页未安装插件推荐及未自定义地址的默认链接', 'wp-china-yes' ), $wl['hide_links'], $wl_locked );
		echo self::field_row_checkbox( 'wl_promo_hidden', __( '在概览页隐藏左侧推荐卡片', 'wp-china-yes' ), __( '概览页默认不提供关闭按钮；勾选后隐藏左下推荐位，不勾选时可作为你的代理商品牌服务推荐位。', 'wp-china-yes' ), $wl['promo_hidden'], $wl_locked );
		echo '<div class="wenpai-stack--xs" data-wpcy-wl-promo-fields="1"' . ( $wl['promo_hidden'] ? ' hidden' : '' ) . '>';
		echo self::field_row_input( 'wl_promo_badge', 'text', __( '概览推荐位标签', 'wp-china-yes' ), __( '留空默认显示「专属服务」。', 'wp-china-yes' ), $wl['promo_badge'], __( '例如：年度运维', 'wp-china-yes' ), 32, $wl_locked );
		echo self::field_row_input( 'wl_promo_title', 'text', __( '概览推荐位标题', 'wp-china-yes' ), __( '面向客户展示你的主机续费、年度护航或定制开发信息；留空按品牌名自动生成。', 'wp-china-yes' ), $wl['promo_title'], __( '例如：云服务器续费与年度安全护航专享', 'wp-china-yes' ), 80, $wl_locked );
		echo self::field_row_input( 'wl_promo_desc', 'text', __( '概览推荐位说明', 'wp-china-yes' ), '', $wl['promo_desc'], __( '例如：含每日异地备份、SSL 续签与专属工程师支持。', 'wp-china-yes' ), 200, $wl_locked );
		echo self::field_row_input( 'wl_promo_cta_label', 'text', __( '推荐位按钮文字', 'wp-china-yes' ), '', $wl['promo_cta_label'], __( '例如：联系专属顾问 ↗', 'wp-china-yes' ), 32, $wl_locked );
		echo self::field_row_input( 'wl_promo_cta_url', 'url', __( '推荐位跳转地址', 'wp-china-yes' ), __( '留空时默认复用上方的工单或帮助中心地址。', 'wp-china-yes' ), $wl['promo_cta_url'], 'https://example.com/renew', 300, $wl_locked );
		echo '</div>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		\Wenpai_Admin_UI::fold_list(
			array(
				'items' => array(
					array(
						'icon'      => 'info',
						'title'     => __( '部署锁定说明（wp-config.php）', 'wp-china-yes' ),
						'desc'      => __( '交付客户站时可通过 PHP 常量固定白标参数，防止后台误改', 'wp-china-yes' ),
						'body_html' => '<p class="meta">' . esc_html__( '在 wp-config.php 中定义 WPCY_WHITELABEL 为 true 可锁定白标开关，并支持 WPCY_WHITELABEL_NAME、WPCY_WHITELABEL_NEWS_URL、WPCY_WHITELABEL_HIDE_PROMO 等常量覆盖对应字段。', 'wp-china-yes' ) . '</p>',
					),
				),
			)
		);
		echo '</div>';
		\Wenpai_Admin_UI::card_close();
		if ( ! $wl_locked ) {
			\Wenpai_Admin_UI::savebar(
				array(
					'submit_label' => __( '保存白标设置', 'wp-china-yes' ),
				)
			);
		}
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Services screen (`?page=wpcy-services`): 3-column active cloud/plugin cards + dynamic catalog.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are locked.
	 */
	private static function render_services( array $settings, bool $locked = false ): void {
		$raw_app = isset( $_GET['app'] ) ? (string) wp_unslash( $_GET['app'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by regex below.
		$app_id  = (string) preg_replace( '/[^a-zA-Z0-9_.-]/', '', $raw_app );
		if ( '' !== $app_id ) {
			AppsView::render_host( $app_id, self::page_url( 'wpcy-services' ) );
			return;
		}

		$service = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( (string) $_GET['service'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only detail routing.
		$catalog = self::service_catalog();
		$reports = self::service_status_reports( $settings );
		if ( '' !== $service && isset( $catalog[ $service ] ) ) {
			self::render_service_detail( $service, $catalog[ $service ], $settings, $locked, $reports[ $service ] ?? array() );
			return;
		}

		$profile   = (string) ( $settings['profile'] ?? 'domestic' );
		$conn      = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror    = self::conn_mirror( $conn );
		$scope     = self::conn_scope( $settings, $conn );
		$avatar    = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds     = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );

		$builtin_active = array(
			'wporg'    => 'off' !== $mirror,
			'admincdn' => 'off' !== $scope,
			'cravatar' => 'off' !== $avatar,
			'noise'    => $heartbeat || $feeds,
		);

		$scope_labels    = array(
			'both'     => __( '后台与前台同时加速', 'wp-china-yes' ),
			'admin'    => __( '仅后台加速', 'wp-china-yes' ),
			'frontend' => __( '仅前台加速', 'wp-china-yes' ),
			'off'      => __( '未开启', 'wp-china-yes' ),
		);
		$avatar_labels   = array(
			'cravatar_cn'     => __( 'Cravatar 中国线路', 'wp-china-yes' ),
			'cravatar_global' => __( 'Cravatar 国际线路', 'wp-china-yes' ),
			'off'             => __( '未开启（原始 Gravatar）', 'wp-china-yes' ),
		);
		$builtin_metrics = array(
			'wporg'    => 'off' === $mirror ? __( '直连 WordPress.org 官方源', 'wp-china-yes' ) : __( 'WenPai.org 镜像 · 异常自动回退', 'wp-china-yes' ),
			'admincdn' => $scope_labels[ $scope ] ?? $scope_labels['both'],
			'cravatar' => $avatar_labels[ $avatar ] ?? $avatar_labels['cravatar_cn'],
			'noise'    => ( $heartbeat || $feeds ) ? __( '心跳降频或外部源屏蔽已生效', 'wp-china-yes' ) : __( '保持 WordPress 默认行为', 'wp-china-yes' ),
		);

		$scene_recommended = array(
			'domestic'    => array( 'compliance', 'slug', 'windfonts' ),
			'crossborder' => array( 'converter', 'ai', 'storage' ),
			'mixed'       => array( 'ai', 'storage', 'converter' ),
		);
		$recommended_ids   = $scene_recommended[ $profile ] ?? $scene_recommended['domestic'];
		$profile_label     = self::profile_label( $profile );

		$plugin_states = array();
		foreach ( $catalog as $id => $spec ) {
			$plugin_states[ $id ] = self::detect_companion_plugin( $spec['plugin_file'], $spec['plugin_slug'] );
		}

		\Wenpai_Admin_Shell::page_head(
			__( '服务', 'wp-china-yes' ),
			__( '管理云端加速通道，并与已安装的文派独立插件协同。', 'wp-china-yes' ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '绑定本站', 'wp-china-yes' ),
					'variant' => 'primary',
					'attrs'   => array( 'data-wpcy-action' => 'bind' ),
				)
			)
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="services">';

		$svc_items = array();
		foreach ( $catalog as $id => $spec ) {
			$is_builtin   = 'builtin' === ( $spec['group'] ?? 'ext' );
			$plugin_state = $plugin_states[ $id ];
			if ( ! $is_builtin && ! $plugin_state['active'] ) {
				continue;
			}
			$report     = $reports[ $id ] ?? array();
			$detail_url = self::page_url( 'wpcy-services', false, array( 'service' => $id ) );
			$is_on      = $is_builtin ? ! empty( $builtin_active[ $id ] ) : true;
			$is_ok      = $plugin_state['active'] || $is_on;

			if ( ! empty( $report['pill'] ) ) {
				$pill_text  = (string) $report['pill'];
				$pill_state = ! empty( $report['state'] ) ? (string) $report['state'] : 'ok';
			} elseif ( $plugin_state['active'] ) {
				$pill_text  = __( '已接管', 'wp-china-yes' );
				$pill_state = 'ok';
			} else {
				$pill_text  = $is_on ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' );
				$pill_state = $is_on ? 'ok' : 'mute';
			}

			$metric_text = ! empty( $report['summary'] )
				? (string) $report['summary']
				: ( $plugin_state['active'] ? $spec['takeover_desc'] : ( $is_builtin ? ( $builtin_metrics[ $id ] ?? '' ) : $spec['takeover_desc'] ) );

			$ctl_html = ( ! $is_builtin || $plugin_state['active'] )
				? \Wenpai_Admin_UI::pill_html(
					array(
						'text'  => $pill_text,
						'state' => $pill_state,
					)
				)
				: self::switch_control( 'core_' . $id, $is_on, $locked, array( 'data-wpcy-service-toggle' => $id ), false );

			$card_action_url   = $detail_url;
			$card_action_label = $is_builtin ? __( '设置', 'wp-china-yes' ) : __( '管理', 'wp-china-yes' );
			if ( ! $is_builtin && $plugin_state['active'] ) {
				$card_action_url   = ! empty( $report['action_url'] )
					? $report['action_url']
					: ConflictDetector::resolve_companion_admin_url( $spec['plugin_slug'], $spec['plugin_file'] );
				$card_action_label = ! empty( $report['action_label'] ) ? $report['action_label'] : __( '打开设置', 'wp-china-yes' );
			}

			$svc_items[] = array(
				'title'          => $spec['title'],
				'desc'           => $spec['short_desc'],
				'icon'           => $spec['icon'],
				'tone'           => $is_ok ? 'accent' : 'mute',
				'control_html'   => $ctl_html,
				'status'         => $metric_text,
				'status_tone'    => $is_ok ? 'ok' : 'mute',
				'status_dot'     => true,
				'status_attrs'   => array( 'data-wpcy-core-metric-text' => $id ),
				'action_label'   => $card_action_label,
				'action_url'     => $card_action_url,
				'action_variant' => 'ghost',
				'attrs'          => array( 'data-wpcy-core-card' => $id ),
			);
		}
		\Wenpai_Admin_UI::module_grid( array( 'items' => $svc_items ) );

		$wl            = WhiteLabel::resolve( $settings );
		$hide_unlinked = $wl['enabled'] && $wl['hide_links'];
		$featured_ext  = array();
		$other_ext     = array();
		foreach ( $catalog as $id => $spec ) {
			if ( 'ext' !== ( $spec['group'] ?? 'ext' ) ) {
				continue;
			}
			$st = $plugin_states[ $id ];
			if ( $st['active'] ) {
				continue;
			}
			if ( $hide_unlinked && ! $st['installed'] ) {
				continue;
			}
			if ( $st['installed'] || in_array( $id, $recommended_ids, true ) ) {
				$featured_ext[ $id ] = $spec;
			} else {
				$other_ext[ $id ] = $spec;
			}
		}

		if ( ! empty( $featured_ext ) || ! empty( $other_ext ) ) {
			\Wenpai_Admin_UI::card_open(
				array(
					'title' => $hide_unlinked
						? __( '已安装待启用的生态插件', 'wp-china-yes' )
						: sprintf( /* translators: %s: site scene label */ __( '文派生态目录 · 适合「%s」', 'wp-china-yes' ), $profile_label ),
					'tile'  => false,
				)
			);
			foreach ( $featured_ext as $id => $spec ) {
				self::render_extension_row( $id, $spec, $plugin_states[ $id ], $reports[ $id ] ?? array() );
			}

			if ( ! empty( $other_ext ) ) {
				$cat_labels   = array(
					'all'     => __( '全部', 'wp-china-yes' ),
					'ai'      => __( 'AI 与出海', 'wp-china-yes' ),
					'media'   => __( '媒体与设计', 'wp-china-yes' ),
					'chinese' => __( '中文体验', 'wp-china-yes' ),
					'ops'     => __( '合规与运维', 'wp-china-yes' ),
				);
				$present_cats = array();
				$other_titles = array();
				foreach ( $other_ext as $other_spec ) {
					$other_titles[] = $other_spec['title'];
					$cat_key        = (string) ( $other_spec['category'] ?? 'ops' );
					if ( isset( $cat_labels[ $cat_key ] ) ) {
						$present_cats[ $cat_key ] = $cat_labels[ $cat_key ];
					}
				}

				ob_start();
				echo '<div data-wpcy-catalog-filter="1">';
				if ( count( $present_cats ) > 1 ) {
					echo '<div class="wpcy-chips wpcy-chips--flush">';
					echo '<button type="button" class="wpcy-chip is-active" data-wpcy-cat="all">' . esc_html( $cat_labels['all'] ) . '</button>';
					foreach ( $present_cats as $cat_key => $cat_label ) {
						echo '<button type="button" class="wpcy-chip" data-wpcy-cat="' . esc_attr( $cat_key ) . '">' . esc_html( $cat_label ) . '</button>';
					}
					echo '</div>';
				}
				foreach ( $other_ext as $id => $spec ) {
					$cat_key = (string) ( $spec['category'] ?? 'ops' );
					echo '<div data-wpcy-cat-item="' . esc_attr( $cat_key ) . '">';
					self::render_extension_row( $id, $spec, $plugin_states[ $id ], $reports[ $id ] ?? array() );
					echo '</div>';
				}
				echo '</div>';
				$other_html = (string) ob_get_clean();

				\Wenpai_Admin_UI::fold_list(
					array(
						'items' => array(
							array(
								'icon'      => 'sparkle',
								'title'     => sprintf( /* translators: %d: count of hidden extensions */ __( '浏览更多文派生态插件（%d 款）', 'wp-china-yes' ), count( $other_ext ) ),
								'desc'      => implode( ' / ', array_slice( $other_titles, 0, 5 ) ) . ( count( $other_titles ) > 5 ? ' …' : '' ),
								'body_html' => $other_html,
							),
						),
					)
				);
			}
			\Wenpai_Admin_UI::card_close();
		}

		if ( ! $hide_unlinked ) {
			self::render_scenario_bundles_card( $profile, $plugin_states );
		}

		if ( self::is_site_bound() ) {
			AppsView::render_services_card( $wl, self::page_url( 'wpcy-services' ) );
		} elseif ( ! $hide_unlinked ) {
			ob_start();
			AppsView::render_services_card( $wl, self::page_url( 'wpcy-services' ) );
			$apps_html = (string) ob_get_clean();

			\Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '云端微应用', 'wp-china-yes' ),
					'tile'  => false,
				)
			);
			\Wenpai_Admin_UI::fold_list(
				array(
					'items' => array(
						array(
							'icon'      => 'sparkle',
							'title'     => __( '轻便工具箱', 'wp-china-yes' ),
							'desc'      => __( '绑定文派云通行证后自动同步云端签名的运维与内容小工具', 'wp-china-yes' ),
							'body_html' => $apps_html,
						),
					),
				)
			);
			\Wenpai_Admin_UI::card_foot(
				array(
					'meta'    => __( '商业扩展授权与更新源已独立至顶栏「授权」页管理', 'wp-china-yes' ),
					'actions' => array(
						array(
							'label'   => __( '前往授权页 →', 'wp-china-yes' ),
							'href'    => self::page_url( 'wpcy-licenses' ),
							'variant' => 'ghost',
						),
					),
				)
			);
			\Wenpai_Admin_UI::card_close();
		}

		echo '</div>';
	}

	/**
	 * Render curated 1-click scenario starter bundles on the Services screen.
	 *
	 * Every plugin slug in these bundles is guaranteed to have a live downloadable
	 * release on WenPai Bridge (`updates.wenpai.net`).
	 *
	 * @param string                              $profile       Active site profile.
	 * @param array<string, array<string, mixed>> $plugin_states Detected plugin states keyed by catalog id.
	 */
	private static function render_scenario_bundles_card( string $profile, array $plugin_states ): void {
		$can_install = ! function_exists( 'current_user_can' ) || current_user_can( 'install_plugins' );
		$slug_labels = array(
			'wpavatar'             => __( '文派头像', 'wp-china-yes' ),
			'wpslug'               => __( '文派别名', 'wp-china-yes' ),
			'wpcomments'           => __( '文派评论', 'wp-china-yes' ),
			'wp-chinese-converter' => __( '译词简繁', 'wp-china-yes' ),
			'wpsss'                => __( '文派智储', 'wp-china-yes' ),
			'wpmind'               => __( '文派心思', 'wp-china-yes' ),
		);
		$slug_to_id  = array(
			'wpavatar'             => 'cravatar',
			'wpslug'               => 'slug',
			'wpcomments'           => 'comments',
			'wp-chinese-converter' => 'converter',
			'wpsss'                => 'storage',
			'wpmind'               => 'ai',
		);

		$bundles = array(
			array(
				'id'      => 'domestic',
				'match'   => 'domestic',
				'icon'    => 'home',
				'title'   => __( '国内企业与官网组合', 'wp-china-yes' ),
				'summary' => __( '头像本地化 + 拼音别名 + 评论增强', 'wp-china-yes' ),
				'slugs'   => array( 'wpavatar', 'wpslug', 'wpcomments' ),
			),
			array(
				'id'      => 'content',
				'match'   => 'mixed',
				'icon'    => 'sparkle',
				'title'   => __( '内容与社区组合', 'wp-china-yes' ),
				'summary' => __( '头像加速 + 拼音别名 + 简繁互转 + 心思 AI', 'wp-china-yes' ),
				'slugs'   => array( 'wpavatar', 'wpslug', 'wp-chinese-converter', 'wpmind' ),
			),
			array(
				'id'      => 'crossborder',
				'match'   => 'crossborder',
				'icon'    => 'cloud',
				'title'   => __( '外贸与跨境组合', 'wp-china-yes' ),
				'summary' => __( '对象存储加速 + 简繁多语 + 心思 AI', 'wp-china-yes' ),
				'slugs'   => array( 'wpsss', 'wp-chinese-converter', 'wpmind' ),
			),
		);

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '场景一键装机包', 'wp-china-yes' ),
				'tile'  => false,
				'attrs' => array( 'data-wpcy-bundles' => '1' ),
			)
		);

		foreach ( $bundles as $bundle ) {
			$missing = array();
			$active  = 0;
			$names   = array();
			foreach ( $bundle['slugs'] as $slug ) {
				$names[] = $slug_labels[ $slug ];
				$cat_id  = $slug_to_id[ $slug ];
				$is_act  = ! empty( $plugin_states[ $cat_id ]['active'] )
					|| ConflictDetector::is_companion_active( $slug );
				if ( $is_act ) {
					++$active;
				} else {
					$missing[] = $slug;
				}
			}

			$total     = count( $bundle['slugs'] );
			$is_match  = $profile === $bundle['match'];
			$all_ready = array() === $missing;

			if ( $all_ready ) {
				$pill_text  = __( '已全部启用', 'wp-china-yes' );
				$pill_state = 'ok';
			} elseif ( $is_match ) {
				$pill_text  = sprintf( /* translators: 1: active count, 2: total count */ __( '匹配当前场景 · %1$d/%2$d', 'wp-china-yes' ), $active, $total );
				$pill_state = '';
			} else {
				$pill_text  = sprintf( /* translators: 1: active count, 2: total count */ __( '已启用 %1$d/%2$d', 'wp-china-yes' ), $active, $total );
				$pill_state = 'mute';
			}

			$control_html = '';
			if ( ! $all_ready && $can_install ) {
				$control_html = \Wenpai_Admin_UI::button_html(
					array(
						'label'   => sprintf( /* translators: %d: remaining plugin count */ __( '一键安装剩余 %d 款', 'wp-china-yes' ), count( $missing ) ),
						'type'    => 'button',
						'variant' => $is_match ? 'primary' : 'secondary',
						'size'    => 'sm',
						'attrs'   => array(
							'data-wpcy-bundle-install' => implode( ',', $missing ),
							'data-wpcy-bundle-id'      => $bundle['id'],
						),
					)
				);
			}

			\Wenpai_Admin_UI::row(
				array(
					'icon'         => $bundle['icon'],
					'title'        => $bundle['title'],
					'desc'         => implode( ' · ', $names ) . ' — ' . $bundle['summary'],
					'pill'         => array(
						'text'  => $pill_text,
						'state' => $pill_state,
					),
					'control_html' => $control_html,
					'attrs'        => array( 'data-wpcy-bundle-row' => $bundle['id'] ),
				)
			);
		}

		\Wenpai_Admin_UI::card_close();
	}

	/**
	 * Dedicated Licenses & Commercial Update Providers screen (`?page=wpcy-licenses`).
	 *
	 * Consolidates Site Passport / WenPai Cloud rights, White-Label authorization,
	 * cross-plugin identity sharing, and commercial update sources (WeiXiaoduo Mall,
	 * WenPai Marketplace) in one focused top-level tab.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are network-locked.
	 */
	private static function render_licenses( array $settings, bool $locked = false ): void {
		$wl         = WhiteLabel::resolve( $settings );
		$is_bound   = self::is_site_bound();
		$hide_links = ! empty( $wl['enabled'] ) && ! empty( $wl['hide_links'] );
		$scan       = ConflictDetector::scan();
		$comp_count = count( $scan['synergies'] );
		$wl_url     = self::page_url( 'wpcy-features', false, array( 'module' => 'whitelabel' ) );
		$svc_url    = self::page_url( 'wpcy-services' );

		\Wenpai_Admin_Shell::page_head(
			__( '授权', 'wp-china-yes' ),
			__( '管理站点通行证、全套品牌白标与商业扩展更新源。', 'wp-china-yes' ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => $is_bound ? __( '已绑定 · 解除绑定', 'wp-china-yes' ) : __( '绑定本站', 'wp-china-yes' ),
					'variant' => $is_bound ? 'secondary' : 'primary',
					'attrs'   => array(
						'data-wpcy-action' => 'bind',
						'data-wpcy-bound'  => $is_bound ? '1' : '0',
					),
				)
			)
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="licenses">';

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '站点通行证与文派云权益', 'wp-china-yes' ),
				'tile'  => false,
				'attrs' => array( 'data-wpcy-licenses-passport' => '1' ),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'user',
				'title'        => __( '文派云通行证', 'wp-china-yes' ),
				'desc'         => __( '绑定一次即可同步全站云额度与授权状态，已装文派组件自动复用。', 'wp-china-yes' ),
				'pill'         => array(
					'text'  => $is_bound ? __( '已绑定', 'wp-china-yes' ) : __( '未绑定', 'wp-china-yes' ),
					'state' => $is_bound ? 'ok' : 'mute',
				),
				'control_html' => ! $hide_links
					? \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '控制台 ↗', 'wp-china-yes' ),
							'href'    => 'https://wenpai.net/my-account/my-sites/',
							'target'  => '_blank',
							'variant' => 'ghost',
							'size'    => 'sm',
						)
					)
					: '',
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'shield-check',
				'title'        => __( '全套品牌白标', 'wp-china-yes' ),
				'desc'         => __( '面向客户交付站点，一次授权覆盖全站文派组件品牌与推荐位。', 'wp-china-yes' ),
				'pill'         => array(
					'text'  => ! empty( $wl['entitled'] ) ? __( '已授权', 'wp-china-yes' ) : __( '未开通', 'wp-china-yes' ),
					'state' => ! empty( $wl['entitled'] ) ? 'ok' : 'mute',
				),
				'control_html' => \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '配置', 'wp-china-yes' ),
						'href'    => $wl_url,
						'variant' => 'ghost',
						'size'    => 'sm',
					)
				),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'link',
				'title'        => __( '生态组件联动', 'wp-china-yes' ),
				'desc'         => __( '向文派心思、文派智储、文派头像等已启用插件共享站点身份与云额度。', 'wp-china-yes' ),
				'pill'         => array(
					'text'  => $comp_count > 0
						? sprintf( /* translators: %d: active WenPai companion plugin count */ __( '%d 款已联动', 'wp-china-yes' ), $comp_count )
						: __( '暂未启用伴生插件', 'wp-china-yes' ),
					'state' => $comp_count > 0 ? 'ok' : 'mute',
				),
				'control_html' => \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '查看组件', 'wp-china-yes' ),
						'href'    => $svc_url,
						'variant' => 'ghost',
						'size'    => 'sm',
					)
				),
			)
		);
		\Wenpai_Admin_UI::card_close();

		ProvidersView::render_card( $wl, $locked );

		echo '</div>';
	}

	/**
	 * Dedicated Outbound Governance screen (`?page=wpcy-features&module=outbound`).
	 *
	 * @param bool $locked Whether subsite settings are locked.
	 */
	private static function render_outbound( bool $locked = false ): void {
		$dis_attr = $locked ? ' disabled' : '';

		\Wenpai_Admin_Shell::subpage_head(
			array(
				'title'      => __( '外链治理', 'wp-china-yes' ),
				'back_url'   => self::page_url( 'wpcy-features' ),
				'back_label' => __( '返回功能列表', 'wp-china-yes' ),
				'lede'       => __( '按域名屏蔽慢请求，并实时检测外部网址连接策略。', 'wp-china-yes' ),
			)
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="outbound">';

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '自定义外链拦截表', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		echo '<div data-wpcy-blocklist="1">';
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'map',
				'title'        => __( '启用自定义外链拦截表', 'wp-china-yes' ),
				'desc'         => __( '阻断命中列表的出站请求（最多 20 条，支付与验证核心域名始终放行）。', 'wp-china-yes' ),
				'control_html' => self::switch_control( 'bl_enabled', false, $locked, array( 'data-wpcy-bl-enabled' => '1' ) ),
			)
		);
		echo '<div class="wpcy-inline-bar wpcy-inline-bar--spaced">';
		echo '<input type="text" data-wpcy-bl-host="1" placeholder="' . esc_attr__( '输入域名，例如 stats.wp.com', 'wp-china-yes' ) . '"' . $dis_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string.
		echo '<select data-wpcy-bl-match="1"' . $dis_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string.
		echo '<option value="exact">' . esc_html__( '精确匹配', 'wp-china-yes' ) . '</option>';
		echo '<option value="suffix">' . esc_html__( '含子域名', 'wp-china-yes' ) . '</option>';
		echo '</select>';
		echo '<input type="text" data-wpcy-bl-note="1" placeholder="' . esc_attr__( '备注说明（可选）', 'wp-china-yes' ) . '"' . $dis_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string.
		$add_btn = \Wenpai_Admin_UI::button_html(
			array(
				'label'    => __( '添加域名', 'wp-china-yes' ),
				'type'     => 'button',
				'variant'  => 'primary',
				'disabled' => $locked,
				'attrs'    => array( 'data-wpcy-bl-add' => '1' ),
			)
		);
		echo $add_btn; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit button helper escapes output.
		echo '</div>';
		if ( ! $locked ) {
			echo '<div class="wpcy-chips">';
			echo '<span>' . esc_html__( '常见慢源快填：', 'wp-china-yes' ) . '</span>';
			echo '<button type="button" class="wpcy-chip" data-wpcy-bl-preset="stats.wp.com" data-wpcy-bl-preset-note="' . esc_attr__( 'WP.com 统计连接', 'wp-china-yes' ) . '">stats.wp.com</button>';
			echo '<button type="button" class="wpcy-chip" data-wpcy-bl-preset="pixel.wp.com" data-wpcy-bl-preset-note="' . esc_attr__( 'WP.com 像素连接', 'wp-china-yes' ) . '">pixel.wp.com</button>';
			echo '<button type="button" class="wpcy-chip" data-wpcy-bl-preset="jetpack.wordpress.com" data-wpcy-bl-preset-note="' . esc_attr__( 'Jetpack 远程连接', 'wp-china-yes' ) . '">jetpack.wordpress.com</button>';
			echo '</div>';
		}
		echo '<ul class="wpcy-host-list" data-wpcy-bl-list="1"></ul>';
		echo '</div>';
		\Wenpai_Admin_UI::card_close();

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '出站网址策略检测', 'wp-china-yes' ),
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
		echo $test_btn; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit button helper escapes output.
		echo '</div>';
		echo '<div class="wpcy-chips">';
		echo '<span>' . esc_html__( '示例网址：', 'wp-china-yes' ) . '</span>';
		echo '<button type="button" class="wpcy-chip" data-wpcy-url-sample="https://api.wordpress.org/core/version-check/1.7/">api.wordpress.org</button>';
		echo '<button type="button" class="wpcy-chip" data-wpcy-url-sample="https://fonts.googleapis.com/css2?family=Inter">fonts.googleapis.com</button>';
		echo '<button type="button" class="wpcy-chip" data-wpcy-url-sample="https://stats.wp.com/e-2026.js">stats.wp.com</button>';
		echo '</div>';
		echo '<div class="wpcy-test-result" data-wpcy-url-output="1" hidden></div>';
		echo '</div>';
		\Wenpai_Admin_UI::card_close();

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '保护规则与出站记录', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		\Wenpai_Admin_UI::status_list(
			array(
				'attrs' => array( 'data-wpcy-outbound-layers' => '1' ),
				'items' => array(
					array(
						'label' => __( '核心业务保护白名单', 'wp-china-yes' ),
						'value' => __( '正在读取…', 'wp-china-yes' ),
					),
					array(
						'label' => __( '内置连接治理规则', 'wp-china-yes' ),
						'value' => __( '正在读取…', 'wp-china-yes' ),
					),
					array(
						'label' => __( '已知后台慢源过滤', 'wp-china-yes' ),
						'value' => __( '正在读取…', 'wp-china-yes' ),
					),
				),
			)
		);
		\Wenpai_Admin_UI::fold_list(
			array(
				'items' => array(
					array(
						'icon'      => 'info',
						'title'     => __( '近期外部连接记录', 'wp-china-yes' ),
						'desc'      => __( '展开查看最近触发记录的外部主机域名与请求次数（仅记录主机名与频次）', 'wp-china-yes' ),
						'body_html' => '<div data-wpcy-outbound-log="1"><p class="meta">' . esc_html__( '正在读取近期记录…', 'wp-china-yes' ) . '</p></div>',
					),
				),
			)
		);
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

		$row_desc    = ! empty( $report['summary'] ) ? (string) $report['summary'] : $spec['short_desc'];
		$can_install = ! function_exists( 'current_user_can' ) || current_user_can( 'install_plugins' );
		$can_act     = ! function_exists( 'current_user_can' ) || current_user_can( 'activate_plugins' );

		if ( $plugin_state['active'] ) {
			$settings_url = ! empty( $report['action_url'] )
				? $report['action_url']
				: ConflictDetector::resolve_companion_admin_url( $spec['plugin_slug'], $spec['plugin_file'] );
			$action_ctl   = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '打开设置', 'wp-china-yes' ),
					'href'    => $settings_url,
					'variant' => 'secondary',
					'size'    => 'sm',
				)
			);
		} elseif ( $plugin_state['installed'] && $can_act ) {
			$action_ctl = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '详情', 'wp-china-yes' ),
					'href'    => $detail_url,
					'variant' => 'ghost',
					'size'    => 'sm',
				)
			) . ' ' . \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '立即启用', 'wp-china-yes' ),
					'type'    => 'button',
					'variant' => 'secondary',
					'size'    => 'sm',
					'attrs'   => array(
						'data-wpcy-provision-slug' => $spec['plugin_slug'],
						'data-wpcy-provision-name' => $spec['plugin_name'],
						'data-wpcy-suite-state'    => 'installed',
					),
				)
			);
		} elseif ( ! $plugin_state['installed'] && $can_install && $can_act && ConflictDetector::is_companion_installable( $spec['plugin_slug'] ) ) {
			$action_ctl = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '详情', 'wp-china-yes' ),
					'href'    => $detail_url,
					'variant' => 'ghost',
					'size'    => 'sm',
				)
			) . ' ' . \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '一键安装', 'wp-china-yes' ),
					'type'    => 'button',
					'variant' => 'secondary',
					'size'    => 'sm',
					'attrs'   => array(
						'data-wpcy-provision-slug' => $spec['plugin_slug'],
						'data-wpcy-provision-name' => $spec['plugin_name'],
						'data-wpcy-suite-state'    => 'missing',
					),
				)
			);
		} else {
			$action_ctl = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '查看详情', 'wp-china-yes' ),
					'href'    => $detail_url,
					'variant' => 'ghost',
					'size'    => 'sm',
				)
			);
		}

		\Wenpai_Admin_UI::row(
			array(
				'icon'         => $spec['icon'],
				'state'        => $row_state,
				'title'        => $spec['title'],
				'desc'         => $row_desc,
				'pill'         => $pill,
				'control_html' => $action_ctl,
			)
		);
	}

	/**
	 * Collect live status reports from companion WenPai standalone plugins.
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
		$adm_script = 'admin';
		$adm_entry  = $adm_script . '.php?page=';
		$opt_entry  = 'options-general.php?page=';

		$catalog = array(
			'wporg'      => array(
				'group'         => 'builtin',
				'category'      => 'ops',
				'title'         => __( 'WP.org 镜像', 'wp-china-yes' ),
				'icon'          => 'cloud',
				'short_desc'    => __( '核心、主题、插件与中文语言包镜像 · WenPai.org', 'wp-china-yes' ),
				'desc'          => __( '为 WordPress 核心、插件、主题更新与中文翻译包提供国内高速镜像及自动故障回退。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派云桥（WPBridge）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpbridge',
				'plugin_file'   => 'wpbridge/wpbridge.php',
				'plugin_admin'  => $adm_entry . 'wpbridge',
				'plugin_url'    => 'https://wpcy.com/bridge',
				'plugin_pitch'  => __( '在后台直接浏览与安装文派生态插件与主题目录，并管理商业版更新通道。', 'wp-china-yes' ),
				'takeover_desc' => __( '生态目录浏览与自定义更新通道已由文派云桥（WPBridge）协同管理。', 'wp-china-yes' ),
			),
			'admincdn'   => array(
				'group'         => 'builtin',
				'category'      => 'ops',
				'title'         => __( '公共前端库', 'wp-china-yes' ),
				'icon'          => 'flash',
				'short_desc'    => __( 'Google Fonts、CDNJS、jsDelivr 与核心静态加速 · adminCDN', 'wp-china-yes' ),
				'desc'          => __( '由萌芽加速（adminCDN）提供常用开源前端库与静态资源的国内分发加速。', 'wp-china-yes' ),
				'plugin_name'   => __( '萌芽静态加速（WPStatic）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpstatic',
				'plugin_file'   => 'wpstatic/wpstatic.php',
				'plugin_admin'  => $opt_entry . 'wpstatic',
				'plugin_url'    => 'https://wpcy.com/static',
				'plugin_pitch'  => __( '按版本精细托管 WordPress 核心静态资源，并自定义公共库白名单规则。', 'wp-china-yes' ),
				'takeover_desc' => __( '核心静态资源托管与公共库白名单策略已由萌芽静态加速（WPStatic）接管。', 'wp-china-yes' ),
			),
			'cravatar'   => array(
				'group'         => 'builtin',
				'category'      => 'media',
				'title'         => __( 'Cravatar 头像', 'wp-china-yes' ),
				'icon'          => 'image',
				'short_desc'    => __( 'Gravatar 国内替代与全球双线分发 · 初认 Cravatar', 'wp-china-yes' ),
				'desc'          => __( '由初认（Cravatar）提供国内外双线头像加速，消除头像破图与跨海等待。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派头像（WPAvatar）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpavatar',
				'plugin_file'   => 'wpavatar/wpavatar.php',
				'plugin_admin'  => $opt_entry . 'wpavatar-settings',
				'plugin_url'    => 'https://wpcy.com/avatar',
				'plugin_pitch'  => __( '开启用户本地上传头像、中文首字头像生成、默认矢量头像与本地缓存管理。', 'wp-china-yes' ),
				'takeover_desc' => __( '本地头像上传、首字头像生成与缓存策略已由文派头像（WPAvatar）接管。', 'wp-china-yes' ),
			),
			'noise'      => array(
				'group'         => 'ext',
				'category'      => 'ops',
				'title'         => __( '评论治理', 'wp-china-yes' ),
				'icon'          => 'feedback',
				'short_desc'    => __( '评论审核工作流、常用回复模板与垃圾评论拦截 · WPComments', 'wp-china-yes' ),
				'desc'          => __( '为站点评论区提供高效的审核流转、快捷回复模板与垃圾信息过滤。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派评论（WPComments）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpcomments',
				'plugin_file'   => 'wpcomments/wpcomments.php',
				'plugin_admin'  => $opt_entry . 'wpcomments-settings',
				'plugin_url'    => 'https://wpcy.com/comments',
				'plugin_pitch'  => __( '提供常用回复短语、评论者身份标识与自动化垃圾评论拦截能力。', 'wp-china-yes' ),
				'takeover_desc' => __( '评论审核与常用回复工作流已由文派评论（WPComments）接管。', 'wp-china-yes' ),
			),
			'ai'         => array(
				'group'         => 'ext',
				'category'      => 'ai',
				'title'         => __( 'AI 智能与 GEO', 'wp-china-yes' ),
				'icon'          => 'sparkle',
				'short_desc'    => __( '多模型 AI 网关路由、写作辅助与生成式搜索优化 · WPMind', 'wp-china-yes' ),
				'desc'          => __( '统一接入国内外主流大模型接口，提供故障自动切换与生成式引擎优化（GEO）。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派心思（WPMind）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpmind',
				'plugin_file'   => 'wpmind/wpmind.php',
				'plugin_admin'  => $adm_entry . 'wpmind',
				'plugin_url'    => 'https://wpcy.com/mind',
				'plugin_pitch'  => __( '统一管理 DeepSeek、通义千问、OpenAI 等多服务商路由，内置 llms.txt 生成与内容辅助。', 'wp-china-yes' ),
				'takeover_desc' => __( '多模型 AI 网关与 GEO 生成式搜索优化已由文派心思（WPMind）接管。', 'wp-china-yes' ),
			),
			'storage'    => array(
				'group'         => 'ext',
				'category'      => 'media',
				'title'         => __( '对象存储', 'wp-china-yes' ),
				'icon'          => 'server',
				'short_desc'    => __( '媒体库附件自动同步至存储桶与自定义 CDN · WPSSS', 'wp-china-yes' ),
				'desc'          => __( '将本站上传的图片与媒体文件自动同步至阿里云 OSS、腾讯云 COS、华为云 OBS 或 S3 兼容存储桶。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派智储（WPSSS）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpsss',
				'plugin_file'   => 'wpsss/wpsss.php',
				'plugin_admin'  => $opt_entry . 'wpsss',
				'plugin_url'    => 'https://wpcy.com/wpsss',
				'plugin_pitch'  => __( '连接主流云厂商存储桶与自定义加速域名，支持历史媒体补传与连接自检。', 'wp-china-yes' ),
				'takeover_desc' => __( '媒体文件同步至存储桶与自定义加速域名已由文派智储（WPSSS）接管。', 'wp-china-yes' ),
			),
			'compliance' => array(
				'group'         => 'ext',
				'category'      => 'ops',
				'title'         => __( '政策合规', 'wp-china-yes' ),
				'icon'          => 'info',
				'short_desc'    => __( '工信部 ICP 备案号与公安联网备案规范展示 · WPICP', 'wp-china-yes' ),
				'desc'          => __( '在中国大陆运营站点页脚规范展示 ICP 备案号、公安网安备案号与经营许可信息。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派备案（WPICP）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpicp',
				'plugin_file'   => 'wpicp/wpicp.php',
				'plugin_admin'  => $opt_entry . 'wpicp',
				'plugin_url'    => 'https://wpcy.com/icp',
				'plugin_pitch'  => __( '在站点页脚规范输出工信部 ICP 备案、公安联网备案标识及增值电信许可证信息。', 'wp-china-yes' ),
				'takeover_desc' => __( '前台页脚备案标识与合规公示已由文派备案（WPICP）接管。', 'wp-china-yes' ),
			),
			'windfonts'  => array(
				'group'         => 'ext',
				'category'      => 'media',
				'title'         => __( '中文字体', 'wp-china-yes' ),
				'icon'          => 'feather',
				'short_desc'    => __( '云端 WOFF2 中文动态子集字体库与古腾堡集成 · Windfonts', 'wp-china-yes' ),
				'desc'          => __( '通过文风字体（Windfonts）按需加载轻量中文子集切片，兼顾中文排版品质与首屏速度。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派字库（WPFonts）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpfonts',
				'plugin_file'   => 'wpfonts/wpfonts.php',
				'plugin_admin'  => $opt_entry . 'wpfonts',
				'plugin_url'    => 'https://wpcy.com/webfonts',
				'plugin_pitch'  => __( '检索免费商用中文字体，接入区块编辑器字体库并按标题、正文精细指定字族。', 'wp-china-yes' ),
				'takeover_desc' => __( '中文字库选择、区块字体库与样式绑定已由文派字库（WPFonts）接管。', 'wp-china-yes' ),
			),
			'motucloud'  => array(
				'group'         => 'ext',
				'category'      => 'media',
				'title'         => __( '图标与图库', 'wp-china-yes' ),
				'icon'          => 'image',
				'short_desc'    => __( '墨图爱可矢量图标与免版权高清图片库 · WPPhoto', 'wp-china-yes' ),
				'desc'          => __( '集成墨图云集（MotuCloud）开源矢量图标与免版权图库，由墨图图库插件统一承载。', 'wp-china-yes' ),
				'plugin_name'   => __( '墨图图库（WPPhoto）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpphoto',
				'plugin_file'   => 'wpphoto/wpphoto.php',
				'plugin_admin'  => $adm_entry . 'wpphoto',
				'plugin_url'    => 'https://wpcy.com/photo',
				'plugin_pitch'  => __( '在区块编辑器与媒体库内直接检索、插入墨图爱可 SVG 图标与免版权高清配图。', 'wp-china-yes' ),
				'takeover_desc' => __( '编辑器矢量图标选择与免版权图片检索已由墨图图库（WPPhoto）接管。', 'wp-china-yes' ),
			),
			'slug'       => array(
				'group'         => 'ext',
				'category'      => 'chinese',
				'title'         => __( '中文体验优化', 'wp-china-yes' ),
				'icon'          => 'equalizer',
				'short_desc'    => __( '中文标题自动转拼音或英文固定链接别名 · WPSlug', 'wp-china-yes' ),
				'desc'          => __( '自动将文章、分类法与上传媒体的中文名称转换为干净的拼音或英文别名。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派素格（WPSlug）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpslug',
				'plugin_file'   => 'wpslug/wpslug.php',
				'plugin_admin'  => $opt_entry . 'wpslug',
				'plugin_url'    => 'https://wpcy.com/slug',
				'plugin_pitch'  => __( '支持本地拼音、多语种翻译与音译别名生成，内置历史文章批量转换与自定义词表。', 'wp-china-yes' ),
				'takeover_desc' => __( '中文标题转别名与上传文件名规范化已由文派素格（WPSlug）接管。', 'wp-china-yes' ),
			),
			'converter'  => array(
				'group'         => 'ext',
				'category'      => 'chinese',
				'title'         => __( '中文简繁转换', 'wp-china-yes' ),
				'icon'          => 'map',
				'short_desc'    => __( '大陆、港澳台及海外华人多地区简繁词汇转换 · WPCC', 'wp-china-yes' ),
				'desc'          => __( '面向跨地区华语访客提供准确的中文简繁转换、地域词汇适配与搜索引擎多地区标记。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派译词（WPCC）', 'wp-china-yes' ),
				'plugin_slug'   => 'wp-chinese-converter',
				'plugin_file'   => 'wp-chinese-converter/wp-chinese-converter.php',
				'plugin_admin'  => $opt_entry . 'wp-chinese-converter',
				'plugin_url'    => 'https://wpcy.com/chinese-converter',
				'plugin_pitch'  => __( '基于专业词汇表支持 6 种中文地区变体实时转换、访客语言自动识别与 hreflang 标记。', 'wp-china-yes' ),
				'takeover_desc' => __( '前台多地区中文简繁转换与词汇适配已由文派译词（WPCC）接管。', 'wp-china-yes' ),
			),
		);

		if ( function_exists( 'get_transient' ) ) {
			$remote = get_transient( 'wpcy_catalog' );
			if ( ! is_array( $remote ) ) {
				$remote = get_transient( 'wpcy_catalog_stale' );
			}
			if ( is_array( $remote ) ) {
				$items = isset( $remote['services'] ) && is_array( $remote['services'] ) ? $remote['services'] : $remote;
				foreach ( $items as $row ) {
					if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['title'] ) ) {
						continue;
					}
					$rid = sanitize_key( (string) $row['id'] );
					if ( '' === $rid || isset( $catalog[ $rid ] ) ) {
						continue;
					}
					$slug            = ! empty( $row['plugin_slug'] ) ? sanitize_key( (string) $row['plugin_slug'] ) : $rid;
					$catalog[ $rid ] = array(
						'group'         => 'ext',
						'category'      => ! empty( $row['category'] ) ? sanitize_key( (string) $row['category'] ) : 'ops',
						'title'         => sanitize_text_field( (string) $row['title'] ),
						'icon'          => ! empty( $row['icon'] ) ? sanitize_key( (string) $row['icon'] ) : 'sparkle',
						'short_desc'    => ! empty( $row['short_desc'] ) ? sanitize_text_field( (string) $row['short_desc'] ) : sanitize_text_field( (string) ( $row['desc'] ?? '' ) ),
						'desc'          => sanitize_text_field( (string) ( $row['desc'] ?? $row['short_desc'] ?? '' ) ),
						'plugin_name'   => sanitize_text_field( (string) ( $row['plugin_name'] ?? $row['title'] ) ),
						'plugin_slug'   => $slug,
						'plugin_file'   => ! empty( $row['plugin_file'] ) ? sanitize_text_field( (string) $row['plugin_file'] ) : $slug . '/' . $slug . '.php',
						'plugin_admin'  => ! empty( $row['plugin_admin'] ) ? sanitize_text_field( (string) $row['plugin_admin'] ) : $adm_entry . $slug,
						'plugin_url'    => ! empty( $row['plugin_url'] ) ? esc_url_raw( (string) $row['plugin_url'] ) : 'https://wpcy.com/' . $slug,
						'plugin_pitch'  => sanitize_text_field( (string) ( $row['plugin_pitch'] ?? $row['desc'] ?? '' ) ),
						'takeover_desc' => sanitize_text_field( (string) ( $row['takeover_desc'] ?? __( '已由对应文派独立插件协同接管。', 'wp-china-yes' ) ) ),
					);
				}
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpcy_service_catalog', $catalog );
			if ( is_array( $filtered ) ) {
				return $filtered;
			}
		}

		return $catalog;
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
		$mirror       = self::conn_mirror( $conn );
		$avatar       = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat    = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds        = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$scope        = self::conn_scope( $settings, $conn );
		$profile      = (string) ( $settings['profile'] ?? 'domestic' );

		$saveable   = in_array( $id, array( 'wporg', 'admincdn', 'cravatar' ), true );
		$aside_html = '';
		if ( $saveable && ! $locked ) {
			$aside_html = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '保存此项设置', 'wp-china-yes' ),
					'type'    => 'submit',
					'variant' => 'primary',
					'form'    => 'wpcy-settings-form',
				)
			);
		}

		\Wenpai_Admin_Shell::subpage_head(
			array(
				'title'      => $spec['title'],
				'back_url'   => $back_url,
				'back_label' => __( '返回服务列表', 'wp-china-yes' ),
				'lede'       => $spec['desc'],
				'aside_html' => $aside_html,
			)
		);
		echo '<div class="wenpai-stack" data-wpcy-screen="service-detail">';
		if ( 'cravatar' === $id && $plugin_state['active'] ) {
			\Wenpai_Admin_UI::notice(
				array(
					'tone' => 'info',
					'text' => __( '头像加速与本地头像能力当前已由文派头像（WPAvatar）协同接管，下方基础线路设置仅在停用文派头像后生效。', 'wp-china-yes' ),
				)
			);
		}
		if ( $saveable ) {
			echo '<form id="wpcy-settings-form" class="wenpai-stack" data-wpcy-network="0">';
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
			if ( $heartbeat ) {
				echo '<input type="hidden" name="heartbeat" value="1">';
			}
			if ( $feeds ) {
				echo '<input type="hidden" name="dashboard_feeds" value="1">';
			}

			\Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '通道参数', 'wp-china-yes' ),
					'tile'  => false,
				)
			);

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside field_row_* helpers.
			if ( 'wporg' === $id ) {
				echo self::field_row_select(
					'wporg_mirror',
					__( 'WordPress.org 下载源', 'wp-china-yes' ),
					__( '控制核心、插件、主题与中文翻译包的下载方式；国内镜像不可达时自动切换至官方源。', 'wp-china-yes' ),
					array(
						'auto' => __( '国内镜像（异常自动回退）', 'wp-china-yes' ),
						'off'  => __( '直连 WordPress.org 官方源', 'wp-china-yes' ),
					),
					$mirror,
					$locked
				);
				\Wenpai_Admin_UI::row(
					array(
						'icon'  => 'map',
						'state' => 'off' === $mirror ? '' : 'ok',
						'title' => __( '中文语言包下载与翻译更新', 'wp-china-yes' ),
						'desc'  => __( '安装或升级核心、插件与主题时，自动从文派国内镜像同步简体中文语言包。', 'wp-china-yes' ),
						'pill'  => array(
							'text'  => 'off' === $mirror ? __( '直连官方源', 'wp-china-yes' ) : __( '已开启', 'wp-china-yes' ),
							'state' => 'off' === $mirror ? 'mute' : 'ok',
						),
					)
				);
				\Wenpai_Admin_UI::row(
					array(
						'icon'  => 'image',
						'state' => 'off' === $mirror ? '' : 'ok',
						'title' => __( '后台插件与主题商店预览图加速', 'wp-china-yes' ),
						'desc'  => __( '后台在线浏览插件图标、横幅与主题截图时自动走国内静态镜像（ts / ps.wenpai.net）。', 'wp-china-yes' ),
						'pill'  => array(
							'text'  => 'off' === $mirror ? __( '未开启', 'wp-china-yes' ) : __( '已开启', 'wp-china-yes' ),
							'state' => 'off' === $mirror ? 'mute' : 'ok',
						),
					)
				);
			} elseif ( 'cravatar' === $id ) {
				echo self::field_row_select(
					'avatar_source',
					__( 'Cravatar 头像加速线路', 'wp-china-yes' ),
					__( '按访客分布选择中国线路或国际线路，关闭后恢复请求原始 Gravatar。', 'wp-china-yes' ),
					array(
						'cravatar_cn'     => __( 'Cravatar 中国线路', 'wp-china-yes' ),
						'cravatar_global' => __( 'Cravatar 国际线路', 'wp-china-yes' ),
						'off'             => __( '未开启（使用原始 Gravatar）', 'wp-china-yes' ),
					),
					$avatar,
					$locked
				);
			} elseif ( 'admincdn' === $id ) {
				echo self::field_row_select(
					'public_assets_scope',
					__( '公共前端库生效范围', 'wp-china-yes' ),
					__( '加速 Google Fonts、Google AJAX、CDNJS、jsDelivr 与 Emoji 静态文件。', 'wp-china-yes' ),
					array(
						'both'     => __( '后台与前台同时加速（国内站推荐）', 'wp-china-yes' ),
						'admin'    => __( '仅后台加速（跨境站推荐）', 'wp-china-yes' ),
						'frontend' => __( '仅前台加速', 'wp-china-yes' ),
						'off'      => __( '未开启', 'wp-china-yes' ),
					),
					$scope,
					$locked
				);
			}
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			\Wenpai_Admin_UI::card_close();
			if ( ! $locked ) {
				\Wenpai_Admin_UI::savebar(
					array(
						'submit_label' => __( '保存此项设置', 'wp-china-yes' ),
					)
				);
			}
			echo '</form>';
		}

		if ( 'admincdn' === $id ) {
			ManifestStatusCard::render();
		}

		$plugin_url = ! empty( $spec['plugin_url'] ) ? $spec['plugin_url'] : 'https://wpcy.com/';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => $saveable ? __( '进阶能力与独立插件协同', 'wp-china-yes' ) : __( '独立插件协同', 'wp-china-yes' ),
				'tile'  => false,
			)
		);

		$can_install = ! function_exists( 'current_user_can' ) || current_user_can( 'install_plugins' );
		$can_act     = ! function_exists( 'current_user_can' ) || current_user_can( 'activate_plugins' );
		if ( $plugin_state['active'] ) {
			$ver_label    = '' !== $plugin_state['version'] ? ' v' . $plugin_state['version'] : '';
			$admin_link   = ! empty( $report['action_url'] )
				? $report['action_url']
				: ConflictDetector::resolve_companion_admin_url( $spec['plugin_slug'], $spec['plugin_file'] );
			$action_label = ! empty( $report['action_label'] ) ? $report['action_label'] : __( '打开插件设置', 'wp-china-yes' );
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
							'size'    => 'sm',
						)
					),
				)
			);
		} elseif ( $plugin_state['installed'] ) {
			$plugins_url = self::wp_admin_link( 'plugins.php' );
			$act_btn     = $can_act
				? \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '立即启用插件', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'primary',
						'size'    => 'sm',
						'attrs'   => array(
							'data-wpcy-provision-slug' => $spec['plugin_slug'],
							'data-wpcy-provision-name' => $spec['plugin_name'],
						),
					)
				)
				: \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '前往插件列表', 'wp-china-yes' ),
						'href'    => $plugins_url,
						'variant' => 'secondary',
						'size'    => 'sm',
					)
				);
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
					'control_html' => $act_btn,
				)
			);
		} elseif ( $can_install && $can_act && ConflictDetector::is_companion_installable( $spec['plugin_slug'] ) ) {
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
							'size'    => 'sm',
							'target'  => '_blank',
						)
					) . ' ' . \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '一键安装并启用', 'wp-china-yes' ),
							'type'    => 'button',
							'variant' => 'primary',
							'size'    => 'sm',
							'attrs'   => array(
								'data-wpcy-provision-slug' => $spec['plugin_slug'],
								'data-wpcy-provision-name' => $spec['plugin_name'],
							),
						)
					),
				)
			);
		} else {
			$upload_base = self::wp_admin_link( 'plugin-install.php' );
			$upload_url  = function_exists( 'add_query_arg' )
				? (string) add_query_arg( array( 'tab' => 'upload' ), $upload_base )
				: $upload_base;
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'download',
					'title'        => $spec['plugin_name'],
					'desc'         => $spec['plugin_pitch'],
					'pill'         => array(
						'text'  => __( '未安装', 'wp-china-yes' ),
						'state' => 'mute',
					),
					'control_html' => ( $can_install
						? \Wenpai_Admin_UI::button_html(
							array(
								'label'   => __( '上传安装包', 'wp-china-yes' ),
								'href'    => $upload_url,
								'variant' => 'ghost',
								'size'    => 'sm',
							)
						) . ' '
						: '' )
						. \Wenpai_Admin_UI::button_html(
							array(
								'label'   => __( '前往官网获取 ↗', 'wp-china-yes' ),
								'href'    => $plugin_url,
								'variant' => 'secondary',
								'size'    => 'sm',
								'target'  => '_blank',
							)
						),
				)
			);
		}
		\Wenpai_Admin_UI::card_close();
		echo '</div>';
	}

	/**
	 * Built-in Help screen (`?page=wpcy&view=help`).
	 *
	 * @param array<string, mixed> $links External links.
	 */
	private static function render_help( array $links ): void {
		$support_url = isset( $links['help'] ) ? (string) $links['help'] : 'https://wpcy.com/go/support';
		\Wenpai_Admin_Shell::page_head(
			__( '帮助与支持', 'wp-china-yes' ),
			__( '了解站点场景选择、功能与服务边界，以及常见网络连接问题的排查方法。', 'wp-china-yes' ),
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
						'title'       => __( '站点场景与自动回退', 'wp-china-yes' ),
						'icon'        => 'cloud',
						'desc'        => __( '如何按服务器与访客位置选择场景，以及镜像异常时的保护机制。', 'wp-china-yes' ),
						'collapsible' => true,
						'open'        => true,
						'steps'       => array(
							array(
								'title' => __( '国内站、跨境站与混合站该如何选择？', 'wp-china-yes' ),
								'body'  => __( '服务器和主要访客都在中国大陆时选「国内站」，前后台均走国内高速节点；面向海外买家的外贸或出海站点选「跨境站」，前台保持原始海外连接以确保支付与物流顺畅，仅加速国内运营团队使用的后台；国内外访客兼顾的站点选「混合站」，可按需在高级线路里分别设定。', 'wp-china-yes' ),
							),
							array(
								'title' => __( '国内镜像临时波动时会阻碍插件或系统更新吗？', 'wp-china-yes' ),
								'body'  => __( '不会。文派叶子内置连通性健康巡检，一旦检测到镜像响应超时或异常，会自动将下载请求回退至 WordPress.org 官方源，并在概览页运行记录中留存说明。', 'wp-china-yes' ),
							),
						),
					),
					array(
						'title'       => __( '「功能」与「服务」的分工', 'wp-china-yes' ),
						'icon'        => 'sparkle',
						'desc'        => __( '文派叶子内置能力与文派生态独立插件如何协同工作。', 'wp-china-yes' ),
						'collapsible' => true,
						'open'        => true,
						'steps'       => array(
							array(
								'title' => __( '「功能」与「服务」两个页面有什么区别？', 'wp-china-yes' ),
								'body'  => __( '「功能」汇聚文派叶子自身携带的本地治理模块（前后台语言分离、后台减噪、资讯与公告源、外链治理与全套白标）；「服务」负责管理云端加速通道，并与文派智储、文派心思、文派素格、文派头像、文派字库等独立插件联动。', 'wp-china-yes' ),
							),
							array(
								'title' => __( '安装独立插件后会与文派叶子的基础加速冲突吗？', 'wp-china-yes' ),
								'body'  => __( '不会。文派叶子提供底层网络通道与连通性检测，启用对应独立插件后，「服务」页会自动将其置顶显示为「已接管」，由独立插件接手细粒度业务配置。', 'wp-china-yes' ),
							),
						),
					),
					array(
						'title'       => __( '全套白标与交付锁定', 'wp-china-yes' ),
						'icon'        => 'settings',
						'desc'        => __( '如何为客户站点统一替换品牌名称并锁定后台配置。', 'wp-china-yes' ),
						'collapsible' => true,
						'open'        => false,
						'steps'       => array(
							array(
								'title' => __( '全套白标会修改插件源码或影响后续升级吗？', 'wp-china-yes' ),
								'body'  => __( '不会。全套白标通过统一的显示接口替换全站文派系列插件的品牌名、帮助链接、文档链接与插件列表署名，插件目录名与在线更新通道保持完好；交付客户站时还可在 wp-config.php 中定义 WPCY_WHITELABEL 常量固定配置。', 'wp-china-yes' ),
							),
						),
					),
				),
				'links'  => array(
					array(
						'label' => __( '文派产品文档中心', 'wp-china-yes' ),
						'href'  => 'https://wpcy.com/document',
					),
					array(
						'label' => __( '技术支持与问题反馈', 'wp-china-yes' ),
						'href'  => $support_url,
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
	}

	/**
	 * Diagnose screen (`?page=wpcy-diagnose`): China-localization audit + Server vs Browser 2-column comparison + recovery mode.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are network-locked.
	 */
	private static function render_diagnose( array $settings, bool $locked = false ): void {
		$recovery = ! empty( $settings['recovery_mode'] );
		$probes   = array();
		if ( function_exists( 'get_transient' ) ) {
			$raw_probes = get_transient( Checker::STORE_KEY );
			if ( is_array( $raw_probes ) ) {
				$probes = $raw_probes;
			}
		}
		$audit       = HealthAudit::evaluate( $settings, $probes );
		$audit_score = $audit['score'];
		$audit_tone  = $audit['status'];
		$fix_count   = $audit['fixable_count'];
		$score_pill  = sprintf(
			/* translators: 1: numeric score out of 100, 2: status text */
			__( '%1$d 分 · %2$s', 'wp-china-yes' ),
			$audit_score,
			'ok' === $audit_tone
				? __( '已就绪', 'wp-china-yes' )
				: ( $fix_count > 0 ? __( '可一键优化', 'wp-china-yes' ) : __( '需留意', 'wp-china-yes' ) )
		);

		\Wenpai_Admin_Shell::page_head(
			__( '诊断', 'wp-china-yes' ),
			__( '一键体检国内化状态，并对比服务器出站与当前浏览器的连通延迟。', 'wp-china-yes' ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '开始检测', 'wp-china-yes' ),
					'variant' => 'primary',
					'attrs'   => array( 'data-wpcy-action' => 'diagnose' ),
				)
			)
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="diagnose">';

		$audit_head_actions  = '<div class="wenpai-row wenpai-row--tight">';
		$audit_head_actions .= \Wenpai_Admin_UI::pill_html(
			array(
				'text'  => $score_pill,
				'state' => $audit_tone,
				'attrs' => array( 'data-wpcy-audit-score' => '1' ),
			)
		);
		if ( ! $locked ) {
			$audit_head_actions .= \Wenpai_Admin_UI::button_html(
				array(
					'label'    => $fix_count > 0
						? sprintf( /* translators: %d: fixable count */ __( '一键修复（%d 项）', 'wp-china-yes' ), $fix_count )
						: __( '已全部优化', 'wp-china-yes' ),
					'type'     => 'button',
					'variant'  => $fix_count > 0 ? 'primary' : 'secondary',
					'size'     => 'sm',
					'disabled' => 0 === $fix_count,
					'attrs'    => array( 'data-wpcy-audit-fix' => '1' ),
				)
			);
		}
		$audit_head_actions .= '</div>';

		\Wenpai_Admin_UI::card_open(
			array(
				'title'             => __( '国内化体检与一键修复', 'wp-china-yes' ),
				'tile'              => false,
				'head_control_html' => $audit_head_actions,
				'attrs'             => array(
					'id'                   => 'wpcy-audit',
					'data-wpcy-audit-card' => '1',
				),
			)
		);
		foreach ( $audit['items'] as $item ) {
			$row_control = '';
			if ( 'conflicts' === $item['id'] && 'ok' !== $item['status'] && function_exists( 'admin_url' ) ) {
				$row_control = \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '插件管理', 'wp-china-yes' ),
						'href'    => admin_url( 'plugins.php' ),
						'variant' => 'ghost',
						'size'    => 'sm',
					)
				);
			}
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => $item['icon'],
					'title'        => $item['label'],
					'desc'         => $item['detail'],
					'pill'         => array(
						'text'  => $item['pill'],
						'state' => $item['status'],
					),
					'control_html' => $row_control,
					'attrs'        => array( 'data-wpcy-audit-item' => $item['id'] ),
				)
			);
		}
		\Wenpai_Admin_UI::card_foot(
			array(
				'meta'    => $fix_count > 0
					? sprintf( /* translators: %d: count of auto-fixable items */ __( '发现 %d 项可自动优化的国内化配置，一键修复不影响第三方插件。', 'wp-china-yes' ), $fix_count )
					: __( '国内化核心配置已就绪，修复动作始终可逆。', 'wp-china-yes' ),
				'attrs'   => array( 'data-wpcy-audit-summary' => '1' ),
				'actions' => array(
					array(
						'label'   => __( '重新体检', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'ghost',
						'attrs'   => array( 'data-wpcy-audit-refresh' => '1' ),
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '<div class="wenpai-grid-2">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '服务器出站连通性', 'wp-china-yes' ),
				'icon'  => 'server',
			)
		);
		\Wenpai_Admin_UI::status_list(
			array(
				'attrs' => array( 'data-wpcy-diag-server' => '1' ),
				'items' => array(
					array(
						'label' => __( 'WP.org 国内镜像接口', 'wp-china-yes' ),
						'value' => __( '等待检测', 'wp-china-yes' ),
					),
					array(
						'label' => __( '安装包国内下载源', 'wp-china-yes' ),
						'value' => __( '等待检测', 'wp-china-yes' ),
					),
					array(
						'label' => __( 'Cravatar 头像服务', 'wp-china-yes' ),
						'value' => __( '等待检测', 'wp-china-yes' ),
					),
					array(
						'label' => __( 'adminCDN 公共前端库', 'wp-china-yes' ),
						'value' => __( '等待检测', 'wp-china-yes' ),
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '当前浏览器连通性', 'wp-china-yes' ),
				'icon'  => 'eye',
			)
		);
		\Wenpai_Admin_UI::status_list(
			array(
				'attrs' => array( 'data-wpcy-diag-browser' => '1' ),
				'items' => ProbeSnapshot::diagnose_browser_items(),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';

		$scan = ConflictDetector::scan();
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '组件互联与冲突检测', 'wp-china-yes' ),
				'tile'  => false,
				'attrs' => array( 'data-wpcy-conflict-card' => '1' ),
			)
		);
		self::render_conflict_and_synergy_rows( $scan );
		\Wenpai_Admin_UI::card_close();

		ManifestStatusCard::render();

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '安全恢复模式', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'tools',
				'state'        => $recovery ? 'ok' : 'bad',
				'title'        => $recovery ? __( '恢复正常加速', 'wp-china-yes' ) : __( '临时暂停全部加速规则', 'wp-china-yes' ),
				'desc'         => $recovery
					? __( '退出恢复模式并重新应用已保存的镜像与加速设置。', 'wp-china-yes' )
					: __( '切回 WordPress 默认直连以排查冲突，原有配置完整保留。', 'wp-china-yes' ),
				'control_html' => \Wenpai_Admin_UI::button_html(
					array(
						'label'   => $recovery ? __( '恢复正常运行', 'wp-china-yes' ) : __( '进入恢复模式', 'wp-china-yes' ),
						'variant' => $recovery ? 'secondary' : 'danger',
						'size'    => 'sm',
						'attrs'   => $recovery
							? array( 'data-wpcy-action' => 'recovery-off' )
							: array(
								'data-wpcy-action'    => 'recovery-on',
								'data-wenpai-confirm' => __( '确认暂停全部镜像与静态资源加速并切回默认直连吗？原有设置不会丢失。', 'wp-china-yes' ),
							),
					)
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';
	}

	/**
	 * Render conflict & WenPai companion synergy rows inside Diagnose or Onboarding Wizard.
	 *
	 * @param array<string, mixed> $scan Scan snapshot from `ConflictDetector::scan()`.
	 * @phpstan-param array{
	 *     conflicts: list<array{id: string, slug: string, file: string, name: string, level: string, reason: string}>,
	 *     synergies: list<array{slug: string, file: string, name: string, admin: string, url: string, synergy: string}>
	 * } $scan
	 */
	private static function render_conflict_and_synergy_rows( array $scan ): void {
		$plugins_base = self::wp_admin_link( 'plugins.php' );
		if ( array() === $scan['conflicts'] ) {
			\Wenpai_Admin_UI::row(
				array(
					'icon'  => 'shield-check',
					'state' => 'ok',
					'title' => __( '未检测到同类改写或失效镜像冲突', 'wp-china-yes' ),
					'desc'  => __( '当前站点未同时启用重复的镜像、字体或头像替换插件；与文派系列组件自动协同让渡。', 'wp-china-yes' ),
					'pill'  => array(
						'text'  => __( '环境正常', 'wp-china-yes' ),
						'state' => 'ok',
					),
				)
			);
		} else {
			foreach ( $scan['conflicts'] as $conf ) {
				$state      = 'bad' === $conf['level'] ? 'bad' : 'warn';
				$manage_url = function_exists( 'add_query_arg' )
					? (string) add_query_arg( array( 's' => $conf['slug'] ), $plugins_base )
					: $plugins_base;
				\Wenpai_Admin_UI::row(
					array(
						'icon'         => 'warning',
						'state'        => $state,
						'title'        => $conf['name'],
						'desc'         => $conf['reason'],
						'pill'         => array(
							'text'  => 'bad' === $state ? __( '失效节点冲突', 'wp-china-yes' ) : __( '同类重复改写', 'wp-china-yes' ),
							'state' => $state,
						),
						'control_html' => \Wenpai_Admin_UI::button_html(
							array(
								'label'   => __( '管理插件', 'wp-china-yes' ),
								'href'    => $manage_url,
								'variant' => 'secondary',
								'size'    => 'sm',
							)
						),
					)
				);
			}
		}

		foreach ( $scan['synergies'] as $syn ) {
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'sparkle',
					'state'        => 'ok',
					'title'        => $syn['name'],
					'desc'         => $syn['synergy'],
					'pill'         => array(
						'text'  => __( '协同接管', 'wp-china-yes' ),
						'state' => 'ok',
					),
					'control_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '组件设置', 'wp-china-yes' ),
							'href'    => self::wp_admin_link( $syn['admin'] ),
							'variant' => 'ghost',
							'size'    => 'sm',
						)
					),
				)
			);
		}
	}

	/**
	 * Onboarding wizard screen (`?page=wpcy-onboarding` or `?page=wpcy&view=wizard`).
	 *
	 * 3-step interactive setup:
	 * 1. Choose site scene (`选择站点场景`)
	 * 2. Environment & conflict check (`环境与冲突检测`)
	 * 3. Free starter component bundle (`装机组件推荐`)
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param array<string, mixed> $links    Links map.
	 * @param string               $version  Plugin version string.
	 */
	private static function render_wizard( array $settings, array $links, string $version = '4.0.0' ): void {
		$profile                  = (string) ( $settings['profile'] ?? 'domestic' );
		$wl                       = WhiteLabel::resolve( $settings );
		$hide_eco                 = ! empty( $wl['enabled'] ) && ! empty( $wl['hide_links'] );
		$home_url                 = self::page_url( 'wpcy' );
		$scan                     = ConflictDetector::scan();
		$shell                    = self::shell_args( '', $version, $links, false, $settings );
		$shell['tabs']            = array();
		$shell['head_right_html'] = \Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '退出向导', 'wp-china-yes' ),
				'href'    => $home_url,
				'variant' => 'ghost',
			)
		);

		$wiz_steps = $hide_eco
			? array(
				__( '选择站点场景', 'wp-china-yes' ),
				__( '环境与冲突检测', 'wp-china-yes' ),
			)
			: array(
				__( '选择站点场景', 'wp-china-yes' ),
				__( '环境与冲突检测', 'wp-china-yes' ),
				__( '装机组件推荐', 'wp-china-yes' ),
			);

		\Wenpai_Admin_Shell::open( $shell );
		\Wenpai_Admin_UI::wizard_open(
			array(
				'steps'   => $wiz_steps,
				'current' => 0,
				'attrs'   => array( 'data-wpcy-wizard-shell' => '1' ),
			)
		);
		echo '<form id="wpcy-wizard-form" class="wenpai-stack" data-wpcy-screen="wizard" data-wpcy-home="' . esc_url( $home_url ) . '">';

		// Step 0: Site Scene Selection.
		echo '<div data-wpcy-wiz-step="0">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title'   => __( '欢迎使用文派叶子', 'wp-china-yes' ),
				'sub'     => __( '请选择最符合本站服务器与访客分布的场景，系统将自动启用对应的推荐加速策略。', 'wp-china-yes' ),
				'heading' => 1,
				'tile'    => false,
			)
		);
		echo '<div class="choice-cards">';
		$scenes = self::scene_descriptions();
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
						'label'   => __( '跳过向导', 'wp-china-yes' ),
						'href'    => $home_url,
						'variant' => 'ghost',
					),
					array(
						'label'   => __( '下一步：冲突检测', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'primary',
						'attrs'   => array( 'data-wpcy-wiz-goto' => '1' ),
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';

		// Step 1: Environment & Conflict Scan.
		echo '<div data-wpcy-wiz-step="1" hidden>';
		\Wenpai_Admin_UI::card_open(
			array(
				'title'   => __( '环境与冲突检测', 'wp-china-yes' ),
				'sub'     => __( '自动核查已启用的同类改写插件与文派系列组件协同状态，避免多插件叠加冲突。', 'wp-china-yes' ),
				'heading' => 1,
				'tile'    => false,
			)
		);
		self::render_conflict_and_synergy_rows( $scan );
		\Wenpai_Admin_UI::card_foot(
			array(
				'actions' => array(
					array(
						'label'   => __( '上一步', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'ghost',
						'attrs'   => array( 'data-wpcy-wiz-goto' => '0' ),
					),
					$hide_eco
						? array(
							'label'   => __( '应用方案并进入概览', 'wp-china-yes' ),
							'type'    => 'submit',
							'variant' => 'primary',
						)
						: array(
							'label'   => __( '下一步：装机推荐', 'wp-china-yes' ),
							'type'    => 'button',
							'variant' => 'primary',
							'attrs'   => array( 'data-wpcy-wiz-goto' => '2' ),
						),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';

		// Step 2: Free Starter Bundle Recommendations.
		if ( ! $hide_eco ) {
			echo '<div data-wpcy-wiz-step="2" hidden>';
			\Wenpai_Admin_UI::card_open(
				array(
					'title'   => __( '装机组件推荐', 'wp-china-yes' ),
					'sub'     => __( '精选常用免费组件，启用后与文派叶子自动协同接管；按需开启开关即可一键安装或启用。', 'wp-china-yes' ),
					'heading' => 1,
					'tile'    => false,
				)
			);
			$starters = ConflictDetector::wizard_starter_items( $profile );
			foreach ( $starters as $starter ) {
				$slug   = $starter['slug'];
				$pstate = self::detect_companion_plugin( $starter['file'], $slug );
				if ( $pstate['active'] ) {
					\Wenpai_Admin_UI::row(
						array(
							'icon'         => $starter['icon'],
							'state'        => 'ok',
							'title'        => $starter['name'],
							'desc'         => $starter['short_desc'],
							'pill'         => array(
								'text'  => __( '已启用', 'wp-china-yes' ),
								'state' => 'ok',
							),
							'control_html' => self::switch_control( 'suite_done_' . $slug, true, true, array(), false ),
						)
					);
				} else {
					$rec_dom   = in_array( $slug, array( 'wpavatar', 'wpslug', 'wpbridge' ), true ) ? '1' : '0';
					$rec_cross = in_array( $slug, array( 'wpslug', 'wp-chinese-converter' ), true ) ? '1' : '0';
					$rec_mixed = in_array( $slug, array( 'wpavatar', 'wpslug', 'wpbridge' ), true ) ? '1' : '0';
					$checked   = $pstate['installed'] || $starter['recommended'];
					$pill      = $pstate['installed']
						? array(
							'text'  => __( '已安装待启用', 'wp-china-yes' ),
							'state' => 'warn',
						)
						: ( $starter['recommended']
							? array(
								'text'  => __( '场景推荐', 'wp-china-yes' ),
								'state' => 'ok',
							)
							: array(
								'text'  => __( '按需选装', 'wp-china-yes' ),
								'state' => 'mute',
							) );
					\Wenpai_Admin_UI::row(
						array(
							'icon'         => $starter['icon'],
							'state'        => $pstate['installed'] ? 'warn' : '',
							'title'        => $starter['name'],
							'desc'         => $starter['short_desc'],
							'pill'         => $pill,
							'attrs'        => array( 'data-wpcy-starter-row' => $slug ),
							'control_html' => self::switch_control(
								'suite_slug',
								$checked,
								false,
								array(
									'data-wpcy-starter-slug' => $slug,
									'data-wpcy-installed' => $pstate['installed'] ? '1' : '0',
									'data-wpcy-rec-domestic' => $rec_dom,
									'data-wpcy-rec-crossborder' => $rec_cross,
									'data-wpcy-rec-mixed' => $rec_mixed,
								),
								false
							),
						)
					);
				}
			}
			\Wenpai_Admin_UI::card_foot(
				array(
					'start'     => true,
					'meta_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '上一步', 'wp-china-yes' ),
							'type'    => 'button',
							'variant' => 'ghost',
							'attrs'   => array( 'data-wpcy-wiz-goto' => '1' ),
						)
					),
					'actions'   => array(
						array(
							'label'   => __( '仅保存加速配置', 'wp-china-yes' ),
							'type'    => 'button',
							'variant' => 'secondary',
							'attrs'   => array( 'data-wpcy-wiz-skip-suite' => '1' ),
						),
						array(
							'label'   => __( '应用方案并配置组件', 'wp-china-yes' ),
							'type'    => 'submit',
							'variant' => 'primary',
							'attrs'   => array( 'data-wpcy-wiz-submit' => '1' ),
						),
					),
				)
			);
			\Wenpai_Admin_UI::card_close();
			echo '</div>';
		}

		echo '</form>';
		\Wenpai_Admin_UI::wizard_close();
		\Wenpai_Admin_Shell::close( $shell );
	}

	/**
	 * Shared scene title & description pairs for Settings and Onboarding Wizard.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function scene_descriptions(): array {
		return array(
			'domestic'    => array(
				__( '国内站', 'wp-china-yes' ),
				__( '服务器与访客均在大陆，前后台全量启用国内加速。', 'wp-china-yes' ),
			),
			'crossborder' => array(
				__( '跨境站', 'wp-china-yes' ),
				__( '海外访客保持直连保障支付顺畅，仅加速国内运营后台。', 'wp-china-yes' ),
			),
			'mixed'       => array(
				__( '混合站', 'wp-china-yes' ),
				__( '兼顾海内外访客，默认加速更新与后台，可按需细分线路。', 'wp-china-yes' ),
			),
		);
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
			'inbound'     => __( '跨境入境站', 'wp-china-yes' ),
			'mixed'       => __( '混合站', 'wp-china-yes' ),
		);
		return $map[ $profile ] ?? $profile;
	}

	/**
	 * Toggle switch HTML using kit switch markup (instant save).
	 *
	 * @param string $name     Input name.
	 * @param bool   $checked  Whether checked.
	 * @param bool   $disabled Whether disabled.
	 * @return string
	 */
	private static function toggle_control( string $name, bool $checked, bool $disabled ): string {
		return self::switch_control( $name, $checked, $disabled, array( 'data-wpcy-instant' => '1' ) );
	}

	/**
	 * Generic kit pill switch HTML with optional data attributes.
	 *
	 * @param string                $name       Input name.
	 * @param bool                  $checked    Whether checked.
	 * @param bool                  $disabled   Whether disabled.
	 * @param array<string, string> $attrs      Extra input attributes.
	 * @param bool                  $show_label Whether to render visible label text instead of screen-reader text.
	 * @return string
	 */
	private static function switch_control( string $name, bool $checked, bool $disabled, array $attrs = array(), bool $show_label = true ): string {
		$dis   = $disabled ? ' disabled' : '';
		$chk   = $checked ? ' checked' : '';
		$txt   = $checked ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' );
		$extra = '';
		foreach ( $attrs as $k => $v ) {
			$extra .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		$label = $show_label
			? '<span class="wenpai-switch__label">' . esc_html( $txt ) . '</span>'
			: '<span class="screen-reader-text">' . esc_html( $txt ) . '</span>';
		return '<span class="wenpai-switch">'
			. '<input type="checkbox" class="wenpai-switch__input" name="' . esc_attr( $name ) . '" value="1"' . $extra . $chk . $dis . '>'
			. '<span class="wenpai-switch__track" aria-hidden="true"></span>'
			. $label
			. '</span>';
	}

	/**
	 * Two-column text/url input field row (`.wenpai-field.field.wenpai-field--row`).
	 *
	 * @param string $name        Input name.
	 * @param string $type        Input type (`text` or `url`).
	 * @param string $label       Field label.
	 * @param string $desc        Field description.
	 * @param string $value       Current value.
	 * @param string $placeholder Placeholder text.
	 * @param int    $max_length  Max length.
	 * @param bool   $disabled    Whether disabled.
	 * @return string
	 */
	private static function field_row_input( string $name, string $type, string $label, string $desc, string $value, string $placeholder, int $max_length, bool $disabled ): string {
		$id  = 'wpcy-f-' . $name;
		$dis = $disabled ? ' disabled' : '';
		$cls = 'wenpai-field field wenpai-field--row' . ( $disabled ? ' is-disabled' : '' );
		$h   = '<div class="' . $cls . '">';
		$h  .= '<div class="field-label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>';
		$h  .= '<div class="field-ctl"><input id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" maxlength="' . (int) $max_length . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $dis . '>';
		if ( '' !== $desc ) {
			$h .= '<p class="wenpai-field__desc">' . esc_html( $desc ) . '</p>';
		}
		$h .= '</div></div>';
		return $h;
	}

	/**
	 * Two-column select field row (`.field`).
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
		$cls = 'wenpai-field field wenpai-field--row' . ( $disabled ? ' is-disabled' : '' );
		$h   = '<div class="' . $cls . '">';
		$h  .= '<div class="field-label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>';
		$h  .= '<div class="field-ctl"><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $dis . '>';
		foreach ( $options as $val => $text ) {
			$sel = $current === $val ? ' selected' : '';
			$h  .= '<option value="' . esc_attr( $val ) . '"' . $sel . '>' . esc_html( $text ) . '</option>';
		}
		$h .= '</select>';
		if ( '' !== $desc ) {
			$h .= '<p class="wenpai-field__desc">' . esc_html( $desc ) . '</p>';
		}
		$h .= '</div></div>';
		return $h;
	}

	/**
	 * Two-column checkbox field row (`.field`).
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
		$cls = 'wenpai-field field wenpai-field--row' . ( $disabled ? ' is-disabled' : '' );
		$h   = '<div class="' . $cls . '">';
		$h  .= '<div class="field-label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>';
		$h  .= '<div class="field-ctl"><label class="opt"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . $chk . $dis . '><span>' . esc_html( $desc ) . '</span></label></div></div>';
		return $h;
	}

	/**
	 * Initial 7-day SVG chart frame using kit CSS tokens (updated by JS once `/stats` loads).
	 */
	private static function empty_trend_svg(): string {
		$labels  = array( '6天前', '5天前', '4天前', '3天前', '前天', '昨天', '今天' );
		$circles = '';
		$texts   = '';
		for ( $i = 0; $i < 7; $i++ ) {
			$x        = (int) round( 44 + $i * ( ( 612 - 44 ) / 6 ) );
			$circles .= '<circle cx="' . $x . '" cy="148" r="3.5" fill="var(--wenpai-accent)"/>';
			$texts   .= '<text x="' . $x . '" y="168" text-anchor="middle">' . esc_html( $labels[ $i ] ) . '</text>';
		}
		return '<svg data-wenpai-art viewBox="0 0 640 180" width="100%" height="180" role="img" aria-label="' . esc_attr__( '近 7 日加速趋势', 'wp-china-yes' ) . '">'
			. '<g stroke="var(--wenpai-line-soft)" stroke-width="1">'
			. '<line x1="44" y1="26" x2="612" y2="26"/><line x1="44" y1="87" x2="612" y2="87"/><line x1="44" y1="148" x2="612" y2="148"/>'
			. '</g>'
			. '<polyline fill="none" stroke="var(--wenpai-accent)" stroke-width="2" points="44,148 612,148"/>'
			. $circles
			. '<g fill="var(--wenpai-ink-3)" font-size="12">'
			. '<text x="36" y="30" text-anchor="end">10</text>'
			. '<text x="36" y="91" text-anchor="end">5</text>'
			. '<text x="36" y="152" text-anchor="end">0</text>'
			. $texts
			. '</g></svg>';
	}
}
