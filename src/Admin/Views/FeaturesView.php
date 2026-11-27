<?php
/**
 * WPCY Features screen and feature module detail views (`?page=wpcy-features`).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

use WenPai\ChinaYes\Admin\NoticeControl\NoticeCenter;
use WenPai\ChinaYes\Admin\NoticeControl\PromoRules;
use WenPai\ChinaYes\Admin\WhiteLabel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPCY Features screen and feature module detail views (`?page=wpcy-features`).
 */
final class FeaturesView {

	/**
	 * Features screen (`?page=wpcy-features`): built-in governance & delivery modules.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are locked.
	 */
	public static function render( array $settings, bool $locked = false ): void {
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
			OutboundView::render( $locked );
			return;
		}
		if ( 'noise' === $module ) {
			self::render_feature_noise( $settings, $locked );
			return;
		}
		if ( 'whitelabel' === $module ) {
			WhiteLabelView::render( $settings, $locked );
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
				'url'    => KitUi::page_url( 'wpcy-features', false, array( 'module' => 'locale' ) ),
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
				'url'    => KitUi::page_url( 'wpcy-features', false, array( 'module' => 'noise' ) ),
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
				'url'    => KitUi::page_url( 'wpcy-features', false, array( 'module' => 'news' ) ),
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
				'url'    => KitUi::page_url( 'wpcy-features', false, array( 'module' => 'outbound' ) ),
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
				'url'    => KitUi::page_url( 'wpcy-features', false, array( 'module' => 'whitelabel' ) ),
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
					? KitUi::switch_control( (string) $mod['toggle'], true, $locked, array( 'data-wpcy-instant' => '1' ), false )
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
		$back_url     = KitUi::page_url( 'wpcy-features' );
		$news_enabled = ! isset( $settings['admin']['news_enabled'] ) || (bool) $settings['admin']['news_enabled'];
		$wl           = WhiteLabel::resolve( $settings );
		$wl_locked    = $locked || $wl['locked'] || ! $wl['entitled'];
		$wl_url       = KitUi::page_url( 'wpcy-features', false, array( 'module' => 'whitelabel' ) );

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
				'control_html' => KitUi::toggle_control( 'admin_news_enabled', $news_enabled, $locked ),
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
			$mirror    = KitUi::conn_mirror( $conn );
			$avatar    = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
			$scope     = KitUi::conn_scope( $settings, $conn );
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
			echo KitUi::field_row_input(
				'wl_news_feed_url',
				'url',
				__( '自定义新闻源地址', 'wp-china-yes' ),
				__( '需返回包含标题、链接与摘要的 HTTPS 公告列表；留空保持默认源。', 'wp-china-yes' ),
				$wl['news_feed_url'],
				'https://wenpai.org/wp-json/agency/v1/news',
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
		$back_url      = KitUi::page_url( 'wpcy-features' );
		$conn          = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$locale_follow = ! isset( $conn['admin_locale_follow'] ) || (bool) $conn['admin_locale_follow'];
		$profile_url   = KitUi::wp_admin_link( 'profile.php' );

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
				'control_html' => KitUi::toggle_control( 'admin_locale_follow', $locale_follow, $locked ),
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
		$back_url    = KitUi::page_url( 'wpcy-features' );
		$profile     = (string) ( $settings['profile'] ?? 'domestic' );
		$conn        = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror      = KitUi::conn_mirror( $conn );
		$avatar      = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat   = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds       = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$scope       = KitUi::conn_scope( $settings, $conn );
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
		$promo_count = count( PromoRules::active_selectors() );
		$native_hub  = NoticeCenter::has_native_notification_center();
		$drawer_desc = $native_hub
			? __( '已检测到新版 WordPress 原生通知中心，叶子自动退让不重复收纳', 'wp-china-yes' )
			: __( '将第三方插件的非关键提示收纳到顶部工具栏「通知」抽屉，始终保留系统更新、错误与安全告警，并自动兼容新版 WordPress 通知中心', 'wp-china-yes' );
		$promo_desc  = sprintf(
			/* translators: %d: count of curated built-in promo rules */
			__( '使用内置精选规则（当前 %d 条）隐藏主流插件常驻升级广告条与评分横幅，零远程拉取、零额外请求', 'wp-china-yes' ),
			$promo_count
		);

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside field_row_* helpers.
		echo KitUi::field_row_checkbox( 'notice_control', __( '后台通知集中收纳', 'wp-china-yes' ), $drawer_desc, $notice_ctrl, $locked );
		echo KitUi::field_row_checkbox( 'hide_promo', __( '隐藏第三方营销横幅', 'wp-china-yes' ), $promo_desc, $hide_promo, $locked );
		echo KitUi::field_row_checkbox( 'heartbeat', __( '降低后台心跳频率', 'wp-china-yes' ), __( '停用仪表盘轮询，编辑器自动保存间隔调整为 60 秒', 'wp-china-yes' ), $heartbeat, $locked );
		echo KitUi::field_row_checkbox( 'dashboard_feeds', __( '屏蔽仪表盘外部资讯', 'wp-china-yes' ), __( '跳过后台首页对海外官方新闻与活动接口的同步请求', 'wp-china-yes' ), $feeds, $locked );
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
}
