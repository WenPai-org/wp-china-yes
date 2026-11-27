<?php
/**
 * Actionable site suggestions and native WP Dashboard news widget renderer.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

use WenPai\ChinaYes\Admin\WhiteLabel;
use WenPai\ChinaYes\Diagnostics\ConflictDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes Overview suggestions and renders the native WP Dashboard replacement news widget.
 */
final class OverviewSuggestions {

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
		$mirror   = KitUi::conn_mirror( $conn );
		$scope    = KitUi::conn_scope( $settings, $conn );
		$feeds    = (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$wl       = WhiteLabel::resolve( $settings );
		$diag_url = KitUi::page_url( 'wpcy-diagnose' );
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
		if ( ! KitUi::is_site_bound() && ! ( $wl['enabled'] && $wl['hide_links'] ) && ! isset( $dismissed['sug.passport'] ) ) {
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
	public static function suggestion_feed_items( array $suggestions ): array {
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
		$overview_url = KitUi::page_url( 'wpcy' );
		$news_set_url = KitUi::page_url( 'wpcy-features', false, array( 'module' => 'news' ) );
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
}
