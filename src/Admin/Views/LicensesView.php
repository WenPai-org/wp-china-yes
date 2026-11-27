<?php
/**
 * Licenses & commercial update providers screen (`?page=wpcy-licenses`).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

use WenPai\ChinaYes\Admin\ProvidersView;
use WenPai\ChinaYes\Admin\WhiteLabel;
use WenPai\ChinaYes\Diagnostics\ConflictDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders Site Passport, White-Label authorization, and commercial update providers.
 */
final class LicensesView {

	/**
	 * Render dedicated Licenses screen (`?page=wpcy-licenses`).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are network-locked.
	 */
	public static function render( array $settings, bool $locked = false ): void {
		$wl         = WhiteLabel::resolve( $settings );
		$is_bound   = KitUi::is_site_bound();
		$hide_links = ! empty( $wl['enabled'] ) && ! empty( $wl['hide_links'] );
		$scan       = ConflictDetector::scan();
		$comp_count = count( $scan['synergies'] );
		$wl_url     = KitUi::page_url( 'wpcy-features', false, array( 'module' => 'whitelabel' ) );
		$svc_url    = KitUi::page_url( 'wpcy-services' );

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
}
