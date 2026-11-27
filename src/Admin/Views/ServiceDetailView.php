<?php
/**
 * Individual service detail view (`?page=wpcy-services&service=<id>`).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

use WenPai\ChinaYes\Admin\ManifestStatusCard;
use WenPai\ChinaYes\Diagnostics\ConflictDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders an individual service settings form and companion standalone plugin linkage card.
 */
final class ServiceDetailView {

	/**
	 * Render individual service settings + companion standalone plugin linkage page.
	 *
	 * @param string                $id       Service id.
	 * @param array<string, string> $spec     Service catalog entry.
	 * @param array<string, mixed>  $settings Settings snapshot.
	 * @param bool                  $locked   Whether subsite settings are locked.
	 * @param array<string, string> $report   Optional live status report from `wpcy_service_status`.
	 */
	public static function render( string $id, array $spec, array $settings, bool $locked, array $report = array() ): void {
		$back_url     = KitUi::page_url( 'wpcy-services' );
		$plugin_state = ServiceCatalog::detect_companion_plugin( $spec['plugin_file'], $spec['plugin_slug'] );
		$conn         = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror       = KitUi::conn_mirror( $conn );
		$avatar       = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat    = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds        = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$scope        = KitUi::conn_scope( $settings, $conn );
		$profile      = (string) ( $settings['profile'] ?? 'domestic' );

		$saveable   = in_array( $id, array( 'wporg', 'admincdn', 'cravatar' ), true );
		$aside_html = '';
		if ( $saveable && ! $locked ) {
			$aside_html = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '保存此项设置', 'wp-china-yes' ),
					'type'    => 'submit',
					'variant' => 'primary',
					'form'    => 'wpcy-settings-form',
				)
			);
		}

		\Wenpai_Admin_Shell::subpage_head(
			array(
				'title'      => $spec['title'],
				'back_url'   => $back_url,
				'back_label' => __( '返回服务列表', 'wp-china-yes' ),
				'lede'       => $spec['desc'],
				'aside_html' => $aside_html,
			)
		);
		echo '<div class="wenpai-stack" data-wpcy-screen="service-detail">';
		if ( 'cravatar' === $id && $plugin_state['active'] ) {
			\Wenpai_Admin_UI::notice(
				array(
					'tone' => 'info',
					'text' => __( '头像加速与本地头像能力当前已由文派头像（WPAvatar）协同接管，下方基础线路设置仅在停用文派头像后生效。', 'wp-china-yes' ),
				)
			);
		}
		if ( $saveable ) {
			echo '<form id="wpcy-settings-form" class="wenpai-stack" data-wpcy-network="0">';
			echo '<input type="hidden" name="profile" value="' . esc_attr( $profile ) . '">';
			if ( 'wporg' !== $id ) {
				echo '<input type="hidden" name="wporg_mirror" value="' . esc_attr( $mirror ) . '">';
			}
			if ( 'admincdn' !== $id ) {
				echo '<input type="hidden" name="public_assets_scope" value="' . esc_attr( $scope ) . '">';
			}
			if ( 'cravatar' !== $id ) {
				echo '<input type="hidden" name="avatar_source" value="' . esc_attr( $avatar ) . '">';
			}
			if ( $heartbeat ) {
				echo '<input type="hidden" name="heartbeat" value="1">';
			}
			if ( $feeds ) {
				echo '<input type="hidden" name="dashboard_feeds" value="1">';
			}

			\Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '通道参数', 'wp-china-yes' ),
					'tile'  => false,
				)
			);

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside field_row_* helpers.
			if ( 'wporg' === $id ) {
				echo KitUi::field_row_select(
					'wporg_mirror',
					__( 'WordPress.org 下载源', 'wp-china-yes' ),
					__( '控制核心、插件、主题与中文翻译包的下载方式；国内镜像不可达时自动切换至官方源。', 'wp-china-yes' ),
					array(
						'auto' => __( '国内镜像（异常自动回退）', 'wp-china-yes' ),
						'off'  => __( '直连 WordPress.org 官方源', 'wp-china-yes' ),
					),
					$mirror,
					$locked
				);
				\Wenpai_Admin_UI::row(
					array(
						'icon'  => 'map',
						'state' => 'off' === $mirror ? '' : 'ok',
						'title' => __( '中文语言包下载与翻译更新', 'wp-china-yes' ),
						'desc'  => __( '安装或升级核心、插件与主题时，自动从文派国内镜像同步简体中文语言包。', 'wp-china-yes' ),
						'pill'  => array(
							'text'  => 'off' === $mirror ? __( '直连官方源', 'wp-china-yes' ) : __( '已开启', 'wp-china-yes' ),
							'state' => 'off' === $mirror ? 'mute' : 'ok',
						),
					)
				);
				\Wenpai_Admin_UI::row(
					array(
						'icon'  => 'image',
						'state' => 'off' === $mirror ? '' : 'ok',
						'title' => __( '后台插件与主题商店预览图加速', 'wp-china-yes' ),
						'desc'  => __( '后台在线浏览插件图标、横幅与主题截图时自动走国内静态镜像（ts / ps.wenpai.net）。', 'wp-china-yes' ),
						'pill'  => array(
							'text'  => 'off' === $mirror ? __( '未开启', 'wp-china-yes' ) : __( '已开启', 'wp-china-yes' ),
							'state' => 'off' === $mirror ? 'mute' : 'ok',
						),
					)
				);
			} elseif ( 'cravatar' === $id ) {
				echo KitUi::field_row_select(
					'avatar_source',
					__( 'Cravatar 头像加速线路', 'wp-china-yes' ),
					__( '按访客分布选择中国线路或国际线路，关闭后恢复请求原始 Gravatar。', 'wp-china-yes' ),
					array(
						'cravatar_cn'     => __( 'Cravatar 中国线路', 'wp-china-yes' ),
						'cravatar_global' => __( 'Cravatar 国际线路', 'wp-china-yes' ),
						'off'             => __( '未开启（使用原始 Gravatar）', 'wp-china-yes' ),
					),
					$avatar,
					$locked
				);
			} elseif ( 'admincdn' === $id ) {
				echo KitUi::field_row_select(
					'public_assets_scope',
					__( '公共前端库生效范围', 'wp-china-yes' ),
					__( '加速 Google Fonts、Google AJAX、CDNJS、jsDelivr 与 Emoji 静态文件。', 'wp-china-yes' ),
					array(
						'both'     => __( '后台与前台同时加速（国内站推荐）', 'wp-china-yes' ),
						'admin'    => __( '仅后台加速（跨境站推荐）', 'wp-china-yes' ),
						'frontend' => __( '仅前台加速', 'wp-china-yes' ),
						'off'      => __( '未开启', 'wp-china-yes' ),
					),
					$scope,
					$locked
				);
			}
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			\Wenpai_Admin_UI::card_close();
			if ( ! $locked ) {
				\Wenpai_Admin_UI::savebar(
					array(
						'submit_label' => __( '保存此项设置', 'wp-china-yes' ),
					)
				);
			}
			echo '</form>';
		}

		if ( 'admincdn' === $id ) {
			ManifestStatusCard::render();
		}

		$plugin_url = ! empty( $spec['plugin_url'] ) ? $spec['plugin_url'] : 'https://wpcy.com/';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => $saveable ? __( '进阶能力与独立插件协同', 'wp-china-yes' ) : __( '独立插件协同', 'wp-china-yes' ),
				'tile'  => false,
			)
		);

		$can_install = ! function_exists( 'current_user_can' ) || current_user_can( 'install_plugins' );
		$can_act     = ! function_exists( 'current_user_can' ) || current_user_can( 'activate_plugins' );
		if ( $plugin_state['active'] ) {
			$ver_label    = '' !== $plugin_state['version'] ? ' v' . $plugin_state['version'] : '';
			$admin_link   = ! empty( $report['action_url'] )
				? $report['action_url']
				: ConflictDetector::resolve_companion_admin_url( $spec['plugin_slug'], $spec['plugin_file'] );
			$action_label = ! empty( $report['action_label'] ) ? $report['action_label'] : __( '打开插件设置', 'wp-china-yes' );
			$active_desc  = ! empty( $report['summary'] ) ? $report['summary'] : $spec['takeover_desc'];
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'sparkle',
					'state'        => 'ok',
					'title'        => $spec['plugin_name'] . $ver_label,
					'desc'         => $active_desc,
					'pill'         => array(
						'text'  => ! empty( $report['pill'] ) ? $report['pill'] : __( '已安装并接管', 'wp-china-yes' ),
						'state' => ! empty( $report['state'] ) ? $report['state'] : 'ok',
					),
					'control_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => $action_label,
							'href'    => $admin_link,
							'variant' => 'primary',
							'size'    => 'sm',
						)
					),
				)
			);
		} elseif ( $plugin_state['installed'] ) {
			$plugins_url = KitUi::wp_admin_link( 'plugins.php' );
			$act_btn     = $can_act
				? \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '立即启用插件', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'primary',
						'size'    => 'sm',
						'attrs'   => array(
							'data-wpcy-provision-slug' => $spec['plugin_slug'],
							'data-wpcy-provision-name' => $spec['plugin_name'],
						),
					)
				)
				: \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '前往插件列表', 'wp-china-yes' ),
						'href'    => $plugins_url,
						'variant' => 'secondary',
						'size'    => 'sm',
					)
				);
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'plug',
					'state'        => 'warn',
					'title'        => $spec['plugin_name'],
					'desc'         => $spec['plugin_pitch'],
					'pill'         => array(
						'text'  => __( '已安装未启用', 'wp-china-yes' ),
						'state' => 'warn',
					),
					'control_html' => $act_btn,
				)
			);
		} elseif ( $can_install && $can_act && ConflictDetector::is_companion_installable( $spec['plugin_slug'] ) ) {
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'download',
					'title'        => $spec['plugin_name'],
					'desc'         => $spec['plugin_pitch'],
					'pill'         => array(
						'text'  => __( '未安装', 'wp-china-yes' ),
						'state' => 'mute',
					),
					'control_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '查看插件页', 'wp-china-yes' ),
							'href'    => $plugin_url,
							'variant' => 'ghost',
							'size'    => 'sm',
							'target'  => '_blank',
						)
					) . ' ' . \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '一键安装并启用', 'wp-china-yes' ),
							'type'    => 'button',
							'variant' => 'primary',
							'size'    => 'sm',
							'attrs'   => array(
								'data-wpcy-provision-slug' => $spec['plugin_slug'],
								'data-wpcy-provision-name' => $spec['plugin_name'],
							),
						)
					),
				)
			);
		} else {
			$upload_base = KitUi::wp_admin_link( 'plugin-install.php' );
			$upload_url  = function_exists( 'add_query_arg' )
				? (string) add_query_arg( array( 'tab' => 'upload' ), $upload_base )
				: $upload_base;
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'download',
					'title'        => $spec['plugin_name'],
					'desc'         => $spec['plugin_pitch'],
					'pill'         => array(
						'text'  => __( '未安装', 'wp-china-yes' ),
						'state' => 'mute',
					),
					'control_html' => ( $can_install
						? \Wenpai_Admin_UI::button_html(
							array(
								'label'   => __( '上传安装包', 'wp-china-yes' ),
								'href'    => $upload_url,
								'variant' => 'ghost',
								'size'    => 'sm',
							)
						) . ' '
						: '' )
						. \Wenpai_Admin_UI::button_html(
							array(
								'label'   => __( '前往官网获取 ↗', 'wp-china-yes' ),
								'href'    => $plugin_url,
								'variant' => 'secondary',
								'size'    => 'sm',
								'target'  => '_blank',
							)
						),
				)
			);
		}
		\Wenpai_Admin_UI::card_close();
		echo '</div>';
	}
}
