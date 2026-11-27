<?php
/**
 * Onboarding wizard screen renderer (`?page=wpcy-onboarding` or `?page=wpcy&view=wizard`).
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
 * Renders the 3-step onboarding wizard: scene choice, conflict check, and starter bundle.
 */
final class WizardView {

	/**
	 * Render Onboarding wizard screen.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param array<string, mixed> $links    Links map.
	 * @param string               $version  Plugin version string.
	 */
	public static function render( array $settings, array $links, string $version = '4.0.0' ): void {
		$profile                  = (string) ( $settings['profile'] ?? 'domestic' );
		$wl                       = WhiteLabel::resolve( $settings );
		$hide_eco                 = ! empty( $wl['enabled'] ) && ! empty( $wl['hide_links'] );
		$home_url                 = KitUi::page_url( 'wpcy' );
		$scan                     = ConflictDetector::scan();
		$shell                    = KitUi::shell_args( '', $version, $links, false, $settings );
		$shell['tabs']            = array();
		$shell['head_right_html'] = \Wenpai_Admin_UI::button_html(
			array(
				'label'   => __( '退出向导', 'wp-china-yes' ),
				'href'    => $home_url,
				'variant' => 'ghost',
			)
		);

		$wiz_steps = $hide_eco
			? array(
				__( '选择站点场景', 'wp-china-yes' ),
				__( '环境与冲突检测', 'wp-china-yes' ),
			)
			: array(
				__( '选择站点场景', 'wp-china-yes' ),
				__( '环境与冲突检测', 'wp-china-yes' ),
				__( '装机组件推荐', 'wp-china-yes' ),
			);

		\Wenpai_Admin_Shell::open( $shell );
		\Wenpai_Admin_UI::wizard_open(
			array(
				'steps'   => $wiz_steps,
				'current' => 0,
				'attrs'   => array( 'data-wpcy-wizard-shell' => '1' ),
			)
		);
		echo '<form id="wpcy-wizard-form" class="wenpai-stack" data-wpcy-screen="wizard" data-wpcy-home="' . esc_url( $home_url ) . '">';

		echo '<div data-wpcy-wiz-step="0">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title'   => __( '欢迎使用文派叶子', 'wp-china-yes' ),
				'sub'     => __( '请选择最符合本站服务器与访客分布的场景，系统将自动启用对应的推荐加速策略。', 'wp-china-yes' ),
				'heading' => 1,
				'tile'    => false,
			)
		);
		echo '<div class="choice-cards">';
		$scenes = KitUi::scene_descriptions();
		foreach ( $scenes as $key => $info ) {
			$is_on = $profile === $key;
			$cls   = $is_on ? 'choice is-on' : 'choice';
			echo '<label class="' . esc_attr( $cls ) . '"><input type="radio" name="profile" value="' . esc_attr( $key ) . '"';
			checked( $is_on );
			echo '><span><span class="t">' . esc_html( $info[0] ) . '</span><span class="d">' . esc_html( $info[1] ) . '</span></span></label>';
		}
		echo '</div>';
		\Wenpai_Admin_UI::card_foot(
			array(
				'actions' => array(
					array(
						'label'   => __( '跳过向导', 'wp-china-yes' ),
						'href'    => $home_url,
						'variant' => 'ghost',
					),
					array(
						'label'   => __( '下一步：冲突检测', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'primary',
						'attrs'   => array( 'data-wpcy-wiz-goto' => '1' ),
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';

		echo '<div data-wpcy-wiz-step="1" hidden>';
		\Wenpai_Admin_UI::card_open(
			array(
				'title'   => __( '环境与冲突检测', 'wp-china-yes' ),
				'sub'     => __( '自动核查已启用的同类改写插件与文派系列组件协同状态，避免多插件叠加冲突。', 'wp-china-yes' ),
				'heading' => 1,
				'tile'    => false,
			)
		);
		DiagnoseView::render_conflict_and_synergy_rows( $scan );
		\Wenpai_Admin_UI::card_foot(
			array(
				'actions' => array(
					array(
						'label'   => __( '上一步', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'ghost',
						'attrs'   => array( 'data-wpcy-wiz-goto' => '0' ),
					),
					$hide_eco
						? array(
							'label'   => __( '应用方案并进入概览', 'wp-china-yes' ),
							'type'    => 'submit',
							'variant' => 'primary',
						)
						: array(
							'label'   => __( '下一步：装机推荐', 'wp-china-yes' ),
							'type'    => 'button',
							'variant' => 'primary',
							'attrs'   => array( 'data-wpcy-wiz-goto' => '2' ),
						),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';

		if ( ! $hide_eco ) {
			echo '<div data-wpcy-wiz-step="2" hidden>';
			\Wenpai_Admin_UI::card_open(
				array(
					'title'   => __( '装机组件推荐', 'wp-china-yes' ),
					'sub'     => __( '精选常用免费组件，启用后与文派叶子自动协同接管；按需开启开关即可一键安装或启用。', 'wp-china-yes' ),
					'heading' => 1,
					'tile'    => false,
				)
			);
			$starters = ConflictDetector::wizard_starter_items( $profile );
			foreach ( $starters as $starter ) {
				$slug   = $starter['slug'];
				$pstate = ServiceCatalog::detect_companion_plugin( $starter['file'], $slug );
				if ( $pstate['active'] ) {
					\Wenpai_Admin_UI::row(
						array(
							'icon'         => $starter['icon'],
							'state'        => 'ok',
							'title'        => $starter['name'],
							'desc'         => $starter['short_desc'],
							'pill'         => array(
								'text'  => __( '已启用', 'wp-china-yes' ),
								'state' => 'ok',
							),
							'control_html' => KitUi::switch_control( 'suite_done_' . $slug, true, true, array(), false ),
						)
					);
				} else {
					$rec_dom   = in_array( $slug, array( 'wpavatar', 'wpslug', 'wpbridge' ), true ) ? '1' : '0';
					$rec_cross = in_array( $slug, array( 'wpslug', 'wp-chinese-converter' ), true ) ? '1' : '0';
					$rec_mixed = in_array( $slug, array( 'wpavatar', 'wpslug', 'wpbridge' ), true ) ? '1' : '0';
					$checked   = $pstate['installed'] || $starter['recommended'];
					$pill      = $pstate['installed']
						? array(
							'text'  => __( '已安装待启用', 'wp-china-yes' ),
							'state' => 'warn',
						)
						: ( $starter['recommended']
							? array(
								'text'  => __( '场景推荐', 'wp-china-yes' ),
								'state' => 'ok',
							)
							: array(
								'text'  => __( '按需选装', 'wp-china-yes' ),
								'state' => 'mute',
							) );
					\Wenpai_Admin_UI::row(
						array(
							'icon'         => $starter['icon'],
							'state'        => $pstate['installed'] ? 'warn' : '',
							'title'        => $starter['name'],
							'desc'         => $starter['short_desc'],
							'pill'         => $pill,
							'attrs'        => array( 'data-wpcy-starter-row' => $slug ),
							'control_html' => KitUi::switch_control(
								'suite_slug',
								$checked,
								false,
								array(
									'data-wpcy-starter-slug' => $slug,
									'data-wpcy-installed' => $pstate['installed'] ? '1' : '0',
									'data-wpcy-rec-domestic' => $rec_dom,
									'data-wpcy-rec-crossborder' => $rec_cross,
									'data-wpcy-rec-mixed' => $rec_mixed,
								),
								false
							),
						)
					);
				}
			}
			\Wenpai_Admin_UI::card_foot(
				array(
					'start'     => true,
					'meta_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '上一步', 'wp-china-yes' ),
							'type'    => 'button',
							'variant' => 'ghost',
							'attrs'   => array( 'data-wpcy-wiz-goto' => '1' ),
						)
					),
					'actions'   => array(
						array(
							'label'   => __( '仅保存加速配置', 'wp-china-yes' ),
							'type'    => 'button',
							'variant' => 'secondary',
							'attrs'   => array( 'data-wpcy-wiz-skip-suite' => '1' ),
						),
						array(
							'label'   => __( '应用方案并配置组件', 'wp-china-yes' ),
							'type'    => 'submit',
							'variant' => 'primary',
							'attrs'   => array( 'data-wpcy-wiz-submit' => '1' ),
						),
					),
				)
			);
			\Wenpai_Admin_UI::card_close();
			echo '</div>';
		}

		echo '</form>';
		\Wenpai_Admin_UI::wizard_close();
		\Wenpai_Admin_Shell::close( $shell );
	}
}
