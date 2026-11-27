<?php
/**
 * WPCY Settings & Network Settings screen (`?page=wpcy-connect`).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

use WenPai\ChinaYes\Admin\WhiteLabel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPCY Settings & Network Settings screen (`?page=wpcy-connect`).
 */
final class SettingsView {

	/**
	 * Settings screen (`?page=wpcy-connect`): profile choice + core toggles + folded advanced rows.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Read-only subsite.
	 * @param bool                 $network  Network admin screen.
	 */
	public static function render( array $settings, bool $locked, bool $network ): void {
		$profile     = (string) ( $settings['profile'] ?? 'domestic' );
		$conn        = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror      = KitUi::conn_mirror( $conn );
		$avatar      = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat   = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds       = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$scope       = KitUi::conn_scope( $settings, $conn );
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
						'href'    => KitUi::page_url( 'wpcy', false, array( 'view' => 'wizard' ) ),
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
		$scenes = KitUi::scene_descriptions();
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
				'control_html' => KitUi::toggle_control( 'wporg_enabled', 'off' !== $mirror, $locked ),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'flash',
				'state'        => 'off' === $scope ? '' : 'ok',
				'title'        => __( '公共前端库加速（adminCDN）', 'wp-china-yes' ),
				'desc'         => __( '加速 Google Fonts、CDNJS、jsDelivr 与 Emoji 静态资源。', 'wp-china-yes' ),
				'control_html' => KitUi::toggle_control( 'public_assets_enabled', 'off' !== $scope, $locked ),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'image',
				'state'        => 'off' === $avatar ? '' : 'ok',
				'title'        => __( 'Cravatar 公共头像加速', 'wp-china-yes' ),
				'desc'         => __( '替代 Gravatar，提升评论区与后台头像加载速度。', 'wp-china-yes' ),
				'control_html' => KitUi::toggle_control( 'avatar_enabled', 'off' !== $avatar, $locked ),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'sparkle',
				'state'        => 'off' === $icon_mode ? '' : 'ok',
				'title'        => __( '图标与免版权图库镜像', 'wp-china-yes' ),
				'desc'         => __( '加速编辑器矢量图标与免版权图库检索接口。', 'wp-china-yes' ),
				'control_html' => KitUi::toggle_control( 'icon_photos_enabled', 'off' !== $icon_mode, $locked ),
			)
		);

		$adv_html  = KitUi::field_row_select(
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
		$adv_html .= KitUi::field_row_select(
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
		$adv_html .= KitUi::field_row_select(
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
		$adv_html .= KitUi::field_row_select(
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
		$adv_html .= KitUi::field_row_checkbox( 'heartbeat', __( '降低后台心跳频率', 'wp-china-yes' ), __( '停用仪表盘轮询，编辑器自动保存间隔调整为 60 秒', 'wp-china-yes' ), $heartbeat, $locked );
		$adv_html .= KitUi::field_row_checkbox( 'dashboard_feeds', __( '屏蔽仪表盘外部资讯', 'wp-china-yes' ), __( '跳过后台首页对海外官方活动与英文新闻源的连接', 'wp-china-yes' ), $feeds, $locked );

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
					'control_html' => KitUi::toggle_control( 'admin_group_suite_menus', $group_menus, $locked ),
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
}
