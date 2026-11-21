<?php
/**
 * Server-rendered Apps Toolbox card and sandboxed mini-app host screen.
 *
 * Uses wenpai-admin-ui kit primitives only (zero inline styles, zero custom CSS).
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
 * Renders the Services toolbox directory card and the isolated mini-app host view.
 */
final class AppsView {

	/**
	 * Render the sandboxed mini-app host screen (`?page=wpcy-services&app=<id>`).
	 *
	 * @since 4.0.0
	 *
	 * @param string $app_id   Verified mini-app identifier.
	 * @param string $back_url Parent Services screen URL.
	 * @return void
	 */
	public static function render_host( string $app_id, string $back_url ): void {
		if ( ! class_exists( '\Wenpai_Admin_Shell', false ) || ! class_exists( '\Wenpai_Admin_UI', false ) ) {
			return;
		}

		\Wenpai_Admin_Shell::subpage_head(
			array(
				'title'      => __( '轻便小工具', 'wp-china-yes' ),
				'back_url'   => $back_url,
				'back_label' => __( '返回服务列表', 'wp-china-yes' ),
				'lede'       => __( '在隔离沙箱中运行文派生态轻便工具。', 'wp-china-yes' ),
			)
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="app-host" data-wpcy-miniapp-id="' . esc_attr( $app_id ) . '">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '轻便小工具', 'wp-china-yes' ),
				'tile'  => false,
				'attrs' => array(
					'data-wpcy-miniapp-card' => '1',
				),
			)
		);
		echo '<p role="status" aria-live="polite" class="meta" data-wpcy-miniapp-status="1">' . esc_html__( '正在校验签名目录与运行环境…', 'wp-china-yes' ) . '</p>';
		echo '<div data-wpcy-miniapp-stage="1"></div>';
		\Wenpai_Admin_UI::card_close();
		echo '</div>';
	}

	/**
	 * Render the Services screen toolbox card (`?page=wpcy-services`).
	 *
	 * Suppressed when White-Label is active with external links hidden.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $wl           Resolved white-label configuration.
	 * @param string               $services_url Base URL for the Services screen.
	 * @return void
	 */
	public static function render_services_card( array $wl, string $services_url ): void {
		if ( ! empty( $wl['enabled'] ) && ! empty( $wl['hide_links'] ) ) {
			return;
		}

		if ( ! class_exists( '\Wenpai_Admin_UI', false ) ) {
			return;
		}

		echo '<section aria-labelledby="wpcy-apps-heading" data-wpcy-apps-section="1" data-wpcy-services-url="' . esc_url( $services_url ) . '">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '轻便工具箱', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		echo '<p id="wpcy-apps-heading" role="status" aria-live="polite" class="meta" data-wpcy-apps-live="1">' . esc_html__( '绑定站点后自动同步文派云端签名的轻便运维与内容小工具，并在概览页快捷工具区点亮入口。', 'wp-china-yes' ) . '</p>';
		echo '<div data-wpcy-apps-grid="1"></div>';
		\Wenpai_Admin_UI::card_close();
		echo '</section>';
	}
}
