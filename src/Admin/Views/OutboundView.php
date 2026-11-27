<?php
/**
 * Dedicated Outbound Governance screen (`?page=wpcy-features&module=outbound`).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Site Blocklist, URL routing tester, and outbound rules log.
 */
final class OutboundView {

	/**
	 * Render Outbound Governance screen.
	 *
	 * @param bool $locked Whether subsite settings are locked.
	 */
	public static function render( bool $locked = false ): void {
		$dis_attr = $locked ? ' disabled' : '';

		\Wenpai_Admin_Shell::subpage_head(
			array(
				'title'      => __( '外链治理', 'wp-china-yes' ),
				'back_url'   => KitUi::page_url( 'wpcy-features' ),
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
				'control_html' => KitUi::switch_control( 'bl_enabled', false, $locked, array( 'data-wpcy-bl-enabled' => '1' ) ),
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
}
