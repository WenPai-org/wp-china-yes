<?php
/**
 * Commercial extension update providers card view for the Services screen.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

use WenPai\ChinaYes\Providers\Presets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the commercial update source card on the Services screen.
 */
final class ProvidersView {

	/**
	 * Render the providers management card unless suppressed by White-Label settings.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $wl     Resolved White-Label settings.
	 * @param bool                 $locked Whether subsite settings are network-locked.
	 * @return void
	 */
	public static function render_card( array $wl, bool $locked ): void {
		if ( ! empty( $wl['enabled'] ) && ! empty( $wl['hide_links'] ) ) {
			return;
		}
		if ( ! class_exists( '\Wenpai_Admin_UI', false ) ) {
			return;
		}

		$mall_id   = Presets::WEIXIAODUO_MALL;
		$market_id = Presets::WENPAI_MARKETPLACE;
		$field_cls = 'wenpai-field field wenpai-field--row' . ( $locked ? ' is-disabled' : '' );
		$dis_attr  = $locked ? ' disabled' : '';
		$email_id  = 'wpcy-prov-email-' . $mall_id;
		$key_id    = 'wpcy-prov-key-' . $mall_id;

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '商业扩展授权与更新源', 'wp-china-yes' ),
				'tile'  => false,
				'attrs' => array(
					'data-wpcy-providers-card' => '1',
					'data-wpcy-locked'         => $locked ? '1' : '0',
				),
			)
		);

		$toggle_btn = \Wenpai_Admin_UI::button_html(
			array(
				'label'    => __( '配置连接', 'wp-china-yes' ),
				'type'     => 'button',
				'variant'  => 'secondary',
				'class'    => 'btn-sm',
				'disabled' => $locked,
				'attrs'    => array(
					'data-wpcy-prov-toggle' => $mall_id,
					'aria-expanded'         => 'false',
				),
			)
		);

		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'plug',
				'title'        => __( '薇晓朵商城', 'wp-china-yes' ),
				'desc'         => __( '连接商城授权密钥后，已购商业主题与插件可通过国内更新通道自动接收升级。', 'wp-china-yes' ),
				'pill'         => array(
					'text'  => __( '未连接', 'wp-china-yes' ),
					'state' => 'mute',
				),
				'control_html' => '<span data-wpcy-prov-actions="' . esc_attr( $mall_id ) . '">' . $toggle_btn . '</span>',
				'attrs'        => array(
					'data-wpcy-prov-row' => $mall_id,
				),
			)
		);

		echo '<div class="wenpai-stack--xs" data-wpcy-prov-panel="' . esc_attr( $mall_id ) . '" hidden>';
		echo '<p class="meta" data-wpcy-prov-status="' . esc_attr( $mall_id ) . '"></p>';

		echo '<div class="wenpai-stack--xs" data-wpcy-prov-form="' . esc_attr( $mall_id ) . '">';
		echo '<div class="' . esc_attr( $field_cls ) . '">';
		echo '<div class="field-label"><label for="' . esc_attr( $email_id ) . '">' . esc_html__( '购买邮箱', 'wp-china-yes' ) . '</label></div>';
		echo '<div class="field-ctl"><input id="' . esc_attr( $email_id ) . '" type="email" autocomplete="email" placeholder="name@example.com" data-wpcy-prov-email="' . esc_attr( $mall_id ) . '"' . $dis_attr . '></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static disabled attribute.
		echo '</div>';

		echo '<div class="' . esc_attr( $field_cls ) . '">';
		echo '<div class="field-label"><label for="' . esc_attr( $key_id ) . '">' . esc_html__( '授权密钥', 'wp-china-yes' ) . '</label></div>';
		echo '<div class="field-ctl"><input id="' . esc_attr( $key_id ) . '" type="password" autocomplete="off" placeholder="' . esc_attr__( '输入商城订单授权密钥', 'wp-china-yes' ) . '" data-wpcy-prov-key="' . esc_attr( $mall_id ) . '"' . $dis_attr . '></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static disabled attribute.
		echo '</div>';

		echo '<div class="wenpai-row wenpai-row--tight">';
		\Wenpai_Admin_UI::button(
			array(
				'label'    => __( '验证并连接', 'wp-china-yes' ),
				'type'     => 'button',
				'variant'  => 'primary',
				'class'    => 'btn-sm',
				'disabled' => $locked,
				'attrs'    => array(
					'data-wpcy-prov-connect' => $mall_id,
				),
			)
		);
		echo '</div>';
		echo '</div>';

		echo '<div class="wenpai-row wenpai-row--tight" data-wpcy-prov-connected="' . esc_attr( $mall_id ) . '" hidden>';
		\Wenpai_Admin_UI::button(
			array(
				'label'    => __( '检测连接', 'wp-china-yes' ),
				'type'     => 'button',
				'variant'  => 'secondary',
				'class'    => 'btn-sm',
				'disabled' => $locked,
				'attrs'    => array(
					'data-wpcy-prov-test' => $mall_id,
				),
			)
		);
		\Wenpai_Admin_UI::button(
			array(
				'label'   => __( '已购清单', 'wp-china-yes' ),
				'type'    => 'button',
				'variant' => 'secondary',
				'class'   => 'btn-sm',
				'attrs'   => array(
					'data-wpcy-prov-products-btn' => $mall_id,
				),
			)
		);
		$update_url = function_exists( 'admin_url' ) ? admin_url( 'update-core.php?force-check=1' ) : 'update-core.php?force-check=1';
		\Wenpai_Admin_UI::button(
			array(
				'label'   => __( '检查更新 ↗', 'wp-china-yes' ),
				'href'    => $update_url,
				'variant' => 'ghost',
				'size'    => 'sm',
				'attrs'   => array(
					'data-wpcy-prov-updates' => $mall_id,
				),
			)
		);
		\Wenpai_Admin_UI::button(
			array(
				'label'    => __( '断开连接', 'wp-china-yes' ),
				'type'     => 'button',
				'variant'  => 'ghost',
				'class'    => 'btn-sm',
				'disabled' => $locked,
				'attrs'    => array(
					'data-wpcy-prov-disconnect' => $mall_id,
				),
			)
		);
		echo '</div>';

		echo '<div data-wpcy-prov-products="' . esc_attr( $mall_id ) . '" hidden></div>';
		echo '</div>';

		\Wenpai_Admin_UI::row(
			array(
				'icon'  => 'sparkle',
				'title' => __( '文派集市', 'wp-china-yes' ),
				'desc'  => __( '文派生态商业插件与云服务统一分发渠道。', 'wp-china-yes' ),
				'pill'  => array(
					'text'  => __( '即将开放', 'wp-china-yes' ),
					'state' => 'mute',
				),
				'attrs' => array(
					'data-wpcy-prov-row' => $market_id,
				),
			)
		);

		\Wenpai_Admin_UI::card_close();
	}
}
