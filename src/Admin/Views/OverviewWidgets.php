<?php
/**
 * Overview tiles, quota summary, suggestions feed, quick tools, and WP Dashboard widget.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

use WenPai\ChinaYes\Admin\MaintenanceModule;
use WenPai\ChinaYes\Admin\WhiteLabel;
use WenPai\ChinaYes\Diagnostics\ConflictDetector;
use WenPai\ChinaYes\Services\Catalog\PublicCatalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overview tiles, quota summary, suggestions feed, quick tools, and WP Dashboard widget.
 */
final class OverviewWidgets {

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
	public static function overview_channel_cells( string $mirror, string $scope, string $avatar, bool $locale_follow, bool $noise_on, ?array $wporg_probe, ?array $pub_probe, ?array $ava_probe ): array {
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
	public static function overview_metric_tile( string $id, string $label, string $badge, string $sub_ok, ?array $probe ): array {
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
	public static function overview_quota_items( string $svc_url, array $settings = array(), bool $is_bound = false ): array {
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
	public static function overview_ad_slot_args( array $wl, string $svc_url ): ?array {
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

		$wl_detail_url = KitUi::page_url( 'wpcy-features', false, array( 'module' => 'whitelabel' ) );
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
	public static function overview_quick_tools( array $wl, string $svc_url ): array {
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
				'href'  => KitUi::page_url( 'wpcy-diagnose' ) . '#wpcy-audit',
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
	public static function overview_suite_components( array $settings ): array {
		$catalog     = ServiceCatalog::catalog();
		$reports     = ServiceCatalog::status_reports( $settings );
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
			$pstate = ServiceCatalog::detect_companion_plugin( $spec['plugin_file'], $spec['plugin_slug'] );

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
						'href'  => KitUi::page_url( 'wpcy-services', false, array( 'service' => $svc_id ) ),
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
				$detail_href = KitUi::page_url( 'wpcy-services', false, array( 'service' => $svc_id ) );
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
}
