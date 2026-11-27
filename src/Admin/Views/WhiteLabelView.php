<?php
/**
 * Suite-wide White-Label feature detail view (`?page=wpcy-features&module=whitelabel`).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

use WenPai\ChinaYes\Admin\WhiteLabel;
use WenPai\ChinaYes\Config\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the White-Label configuration screen.
 */
final class WhiteLabelView {

	/**
	 * Render Suite-wide White-Label view (`?page=wpcy-features&module=whitelabel`).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are locked.
	 */
	public static function render( array $settings, bool $locked ): void {
		$back_url  = KitUi::page_url( 'wpcy-features' );
		$profile   = (string) ( $settings['profile'] ?? 'domestic' );
		$conn      = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror    = KitUi::conn_mirror( $conn );
		$avatar    = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds     = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$scope     = KitUi::conn_scope( $settings, $conn );
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
				$ident = get_option( Schema::SITE_IDENTITY, array() );
				if ( is_array( $ident ) && ! empty( $ident['site_uuid'] ) && is_string( $ident['site_uuid'] ) ) {
					$oem_url = function_exists( 'add_query_arg' )
						? (string) add_query_arg( 'site_uuid', $ident['site_uuid'], $oem_url )
						: $oem_url . '?site_uuid=' . rawurlencode( $ident['site_uuid'] );
				}
			}
			$is_bound = KitUi::is_site_bound();
			$wl_ctas  = '';
			if ( ! $is_bound ) {
				$wl_ctas .= \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '绑定本站同步授权', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'secondary',
						'size'    => 'sm',
						'attrs'   => array( 'data-wpcy-action' => 'bind' ),
					)
				) . ' ';
			}
			$wl_ctas .= \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '前往开通授权', 'wp-china-yes' ),
					'href'    => $oem_url,
					'variant' => 'primary',
					'size'    => 'sm',
					'target'  => '_blank',
				)
			);
			\Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '商业白标授权', 'wp-china-yes' ),
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
					'control_html' => $wl_ctas,
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
				'control_html' => KitUi::switch_control( 'wl_enabled', $wl['enabled'], $wl_locked, array( 'data-wpcy-wl-toggle' => '1' ) ),
			)
		);
		echo '<div class="wenpai-stack--xs" data-wpcy-wl-fields="1"' . ( ( $wl['enabled'] || ! $entitled ) ? '' : ' hidden' ) . '>';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside field_row_* helpers.
		echo KitUi::field_row_input( 'wl_brand_name', 'text', __( '品牌名称', 'wp-china-yes' ), __( '替换顶栏品牌名、左侧菜单名与插件列表署名；留空时左侧菜单默认显示「文派」。', 'wp-china-yes' ), $wl['brand_name'], __( '例如：某某数字建站', 'wp-china-yes' ), 64, $wl_locked );
		echo KitUi::field_row_input( 'wl_help_url', 'url', __( '帮助中心地址', 'wp-china-yes' ), '', $wl['help_url'], 'https://docs.wenpai.org/help', 300, $wl_locked );
		echo KitUi::field_row_input( 'wl_feedback_url', 'url', __( '工单与反馈地址', 'wp-china-yes' ), '', $wl['feedback_url'], 'https://docs.wenpai.org/support', 300, $wl_locked );
		echo KitUi::field_row_input( 'wl_docs_url', 'url', __( '产品文档地址', 'wp-china-yes' ), '', $wl['docs_url'], 'https://docs.wenpai.org/docs', 300, $wl_locked );
		echo KitUi::field_row_input( 'wl_news_feed_url', 'url', __( '自定义新闻源地址', 'wp-china-yes' ), __( '概览页 HTTPS 公告列表接口地址，留空保持默认源。', 'wp-china-yes' ), $wl['news_feed_url'], 'https://wenpai.org/wp-json/agency/v1/news', 300, $wl_locked );
		echo KitUi::field_row_input( 'wl_logo_url', 'url', __( '自定义图标地址', 'wp-china-yes' ), __( '建议使用 44×44 正方形透明背景 PNG 或 SVG。', 'wp-china-yes' ), $wl['logo_url'], 'https://wenpai.org/logo.svg', 300, $wl_locked );
		echo KitUi::field_row_checkbox( 'wl_hide_logo', __( '隐藏顶栏品牌图标', 'wp-china-yes' ), __( '仅显示纯文字品牌名称', 'wp-china-yes' ), $wl['hide_logo'], $wl_locked );
		echo KitUi::field_row_checkbox( 'wl_hide_links', __( '隐藏未安装扩展与默认外链', 'wp-china-yes' ), __( '隐藏「服务」页未安装插件推荐及未自定义地址的默认链接', 'wp-china-yes' ), $wl['hide_links'], $wl_locked );
		echo KitUi::field_row_checkbox( 'wl_promo_hidden', __( '在概览页隐藏左侧推荐卡片', 'wp-china-yes' ), __( '概览页默认不提供关闭按钮；勾选后隐藏左下推荐位，不勾选时可作为你的代理商品牌服务推荐位。', 'wp-china-yes' ), $wl['promo_hidden'], $wl_locked );
		echo '<div class="wenpai-stack--xs" data-wpcy-wl-promo-fields="1"' . ( $wl['promo_hidden'] ? ' hidden' : '' ) . '>';
		echo KitUi::field_row_input( 'wl_promo_badge', 'text', __( '概览推荐位标签', 'wp-china-yes' ), __( '留空默认显示「专属服务」。', 'wp-china-yes' ), $wl['promo_badge'], __( '例如：年度运维', 'wp-china-yes' ), 32, $wl_locked );
		echo KitUi::field_row_input( 'wl_promo_title', 'text', __( '概览推荐位标题', 'wp-china-yes' ), __( '面向客户展示你的主机续费、年度护航或定制开发信息；留空按品牌名自动生成。', 'wp-china-yes' ), $wl['promo_title'], __( '例如：云服务器续费与年度安全护航专享', 'wp-china-yes' ), 80, $wl_locked );
		echo KitUi::field_row_input( 'wl_promo_desc', 'text', __( '概览推荐位说明', 'wp-china-yes' ), '', $wl['promo_desc'], __( '例如：含每日异地备份、SSL 续签与专属工程师支持。', 'wp-china-yes' ), 200, $wl_locked );
		echo KitUi::field_row_input( 'wl_promo_cta_label', 'text', __( '推荐位按钮文字', 'wp-china-yes' ), '', $wl['promo_cta_label'], __( '例如：联系专属顾问 ↗', 'wp-china-yes' ), 32, $wl_locked );
		echo KitUi::field_row_input( 'wl_promo_cta_url', 'url', __( '推荐位跳转地址', 'wp-china-yes' ), __( '留空时默认复用上方的工单或帮助中心地址。', 'wp-china-yes' ), $wl['promo_cta_url'], 'https://wenpai.org/renew', 300, $wl_locked );
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
}
