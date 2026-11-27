<?php
/**
 * WPCY Overview admin screen (`?page=wpcy`).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

use WenPai\ChinaYes\Admin\ProbeSnapshot;
use WenPai\ChinaYes\Admin\WhiteLabel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPCY Overview admin screen (`?page=wpcy`).
 */
final class OverviewView {

	/**
	 * Overview screen (`?page=wpcy`): stat_hub + 7d SVG trend & speed/quota split + tag_feed + ad_slot & icon_grid.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 */
	public static function render( array $settings ): void {
		$profile       = KitUi::profile_label( (string) ( $settings['profile'] ?? 'domestic' ) );
		$recovery      = ! empty( $settings['recovery_mode'] );
		$diag_url      = KitUi::page_url( 'wpcy-diagnose' );
		$set_url       = KitUi::page_url( 'wpcy-connect' );
		$feat_url      = KitUi::page_url( 'wpcy-features' );
		$svc_url       = KitUi::page_url( 'wpcy-services' );
		$news_set_url  = KitUi::page_url( 'wpcy-features', false, array( 'module' => 'news' ) );
		$conn          = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror        = KitUi::conn_mirror( $conn );
		$scope         = KitUi::conn_scope( $settings, $conn );
		$avatar        = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$locale_follow = ! isset( $conn['admin_locale_follow'] ) || (bool) $conn['admin_locale_follow'];
		$noise_on      = 'on' === (string) ( $conn['heartbeat'] ?? 'off' ) || 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$news_enabled  = ! isset( $settings['admin']['news_enabled'] ) || (bool) $settings['admin']['news_enabled'];
		$wl            = WhiteLabel::resolve( $settings );
		$is_bound      = KitUi::is_site_bound();
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
				'channels'  => OverviewWidgets::overview_channel_cells( $mirror, $scope, $avatar, $locale_follow, $noise_on, $wporg_probe, $pub_probe, $ava_probe ),
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
				'plot_html'  => KitUi::empty_trend_svg(),
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
									OverviewWidgets::overview_metric_tile( 'wporg', __( 'WP.org 镜像', 'wp-china-yes' ), __( '免费', 'wp-china-yes' ), __( '国内镜像', 'wp-china-yes' ), $wporg_probe ),
									OverviewWidgets::overview_metric_tile( 'cravatar', __( 'Cravatar 头像', 'wp-china-yes' ), __( '免费', 'wp-china-yes' ), __( '中国线路', 'wp-china-yes' ), $ava_probe ),
									OverviewWidgets::overview_metric_tile( 'admincdn', 'adminCDN', '', __( '前后台加速', 'wp-china-yes' ), $pub_probe ),
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
								'items' => OverviewWidgets::overview_quota_items( $svc_url, $settings, $is_bound ),
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
			OverviewSuggestions::suggestion_feed_items( OverviewSuggestions::active_suggestions( $settings ) ),
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
		$ad_args   = OverviewWidgets::overview_ad_slot_args( $wl, $svc_url );
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
				'items'   => OverviewWidgets::overview_quick_tools( $wl, $svc_url ),
			)
		);
		echo '<div class="wenpai-card__body">';
		if ( $hide_eco ) {
			echo $quick_grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit icon_grid_html escapes output.
		} else {
			$suite_grid = \Wenpai_Admin_UI::icon_grid_html(
				array(
					'columns' => 5,
					'items'   => OverviewWidgets::overview_suite_components( $settings ),
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
}
