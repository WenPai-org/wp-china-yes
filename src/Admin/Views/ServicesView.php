<?php
/**
 * Services screen renderer (`?page=wpcy-services`).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

use WenPai\ChinaYes\Admin\AppsView;
use WenPai\ChinaYes\Admin\WhiteLabel;
use WenPai\ChinaYes\Diagnostics\ConflictDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Services hub: active cloud/plugin cards, ecosystem catalog, bundles, and micro-apps.
 */
final class ServicesView {

	/**
	 * Render Services screen (`?page=wpcy-services`).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are locked.
	 */
	public static function render( array $settings, bool $locked = false ): void {
		$raw_app = isset( $_GET['app'] ) ? (string) wp_unslash( $_GET['app'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by regex below.
		$app_id  = (string) preg_replace( '/[^a-zA-Z0-9_.-]/', '', $raw_app );
		if ( '' !== $app_id ) {
			AppsView::render_host( $app_id, KitUi::page_url( 'wpcy-services' ) );
			return;
		}

		$service = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( (string) $_GET['service'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only detail routing.
		$catalog = ServiceCatalog::catalog();
		$reports = ServiceCatalog::status_reports( $settings );
		if ( '' !== $service && isset( $catalog[ $service ] ) ) {
			ServiceDetailView::render( $service, $catalog[ $service ], $settings, $locked, $reports[ $service ] ?? array() );
			return;
		}

		$profile   = (string) ( $settings['profile'] ?? 'domestic' );
		$conn      = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$mirror    = KitUi::conn_mirror( $conn );
		$scope     = KitUi::conn_scope( $settings, $conn );
		$avatar    = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat = 'on' === (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds     = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );

		$builtin_active = array(
			'wporg'    => 'off' !== $mirror,
			'admincdn' => 'off' !== $scope,
			'cravatar' => 'off' !== $avatar,
			'noise'    => $heartbeat || $feeds,
		);

		$scope_labels    = array(
			'both'     => __( '后台与前台同时加速', 'wp-china-yes' ),
			'admin'    => __( '仅后台加速', 'wp-china-yes' ),
			'frontend' => __( '仅前台加速', 'wp-china-yes' ),
			'off'      => __( '未开启', 'wp-china-yes' ),
		);
		$avatar_labels   = array(
			'cravatar_cn'     => __( 'Cravatar 中国线路', 'wp-china-yes' ),
			'cravatar_global' => __( 'Cravatar 国际线路', 'wp-china-yes' ),
			'off'             => __( '未开启（原始 Gravatar）', 'wp-china-yes' ),
		);
		$builtin_metrics = array(
			'wporg'    => 'off' === $mirror ? __( '直连 WordPress.org 官方源', 'wp-china-yes' ) : __( 'WenPai.org 镜像 · 异常自动回退', 'wp-china-yes' ),
			'admincdn' => $scope_labels[ $scope ] ?? $scope_labels['both'],
			'cravatar' => $avatar_labels[ $avatar ] ?? $avatar_labels['cravatar_cn'],
			'noise'    => ( $heartbeat || $feeds ) ? __( '心跳降频或外部源屏蔽已生效', 'wp-china-yes' ) : __( '保持 WordPress 默认行为', 'wp-china-yes' ),
		);

		$scene_recommended = array(
			'domestic'    => array( 'compliance', 'slug', 'windfonts' ),
			'crossborder' => array( 'converter', 'ai', 'storage' ),
			'mixed'       => array( 'ai', 'storage', 'converter' ),
		);
		$recommended_ids   = $scene_recommended[ $profile ] ?? $scene_recommended['domestic'];
		$profile_label     = KitUi::profile_label( $profile );

		$plugin_states = array();
		foreach ( $catalog as $id => $spec ) {
			$plugin_states[ $id ] = ServiceCatalog::detect_companion_plugin( $spec['plugin_file'], $spec['plugin_slug'] );
		}

		\Wenpai_Admin_Shell::page_head(
			__( '服务', 'wp-china-yes' ),
			__( '管理云端加速通道，并与已安装的文派独立插件协同。', 'wp-china-yes' ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '绑定本站', 'wp-china-yes' ),
					'variant' => 'primary',
					'attrs'   => array( 'data-wpcy-action' => 'bind' ),
				)
			)
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="services">';

		$svc_items = array();
		foreach ( $catalog as $id => $spec ) {
			$is_builtin   = 'builtin' === ( $spec['group'] ?? 'ext' );
			$plugin_state = $plugin_states[ $id ];
			if ( ! $is_builtin && ! $plugin_state['active'] ) {
				continue;
			}
			$report     = $reports[ $id ] ?? array();
			$detail_url = KitUi::page_url( 'wpcy-services', false, array( 'service' => $id ) );
			$is_on      = $is_builtin ? ! empty( $builtin_active[ $id ] ) : true;
			$is_ok      = $plugin_state['active'] || $is_on;

			if ( ! empty( $report['pill'] ) ) {
				$pill_text  = (string) $report['pill'];
				$pill_state = ! empty( $report['state'] ) ? (string) $report['state'] : 'ok';
			} elseif ( $plugin_state['active'] ) {
				$pill_text  = __( '已接管', 'wp-china-yes' );
				$pill_state = 'ok';
			} else {
				$pill_text  = $is_on ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' );
				$pill_state = $is_on ? 'ok' : 'mute';
			}

			$short_plugin_name = trim( (string) preg_replace( '/[（(].*$/u', '', (string) ( $spec['plugin_name'] ?? '' ) ) );
			$short_takeover    = '' !== $short_plugin_name
				/* translators: %s: companion plugin short name */
				? sprintf( __( '已由%s接管', 'wp-china-yes' ), $short_plugin_name )
				: __( '已由独立插件接管', 'wp-china-yes' );

			$metric_text = ! empty( $report['summary'] )
				? (string) $report['summary']
				: ( $plugin_state['active'] ? $short_takeover : ( $is_builtin ? ( $builtin_metrics[ $id ] ?? '' ) : $short_takeover ) );

			$ctl_html = ( ! $is_builtin || $plugin_state['active'] )
				? \Wenpai_Admin_UI::pill_html(
					array(
						'text'  => $pill_text,
						'state' => $pill_state,
					)
				)
				: KitUi::switch_control( 'core_' . $id, $is_on, $locked, array( 'data-wpcy-service-toggle' => $id ), false );

			$card_action_url   = $detail_url;
			$card_action_label = $is_builtin ? __( '设置', 'wp-china-yes' ) : __( '管理', 'wp-china-yes' );
			if ( ! $is_builtin && $plugin_state['active'] ) {
				$card_action_url   = ! empty( $report['action_url'] )
					? $report['action_url']
					: ConflictDetector::resolve_companion_admin_url( $spec['plugin_slug'], $spec['plugin_file'] );
				$card_action_label = ! empty( $report['action_label'] ) ? $report['action_label'] : __( '打开设置', 'wp-china-yes' );
			}

			$svc_items[] = array(
				'title'          => $spec['title'],
				'desc'           => $spec['short_desc'],
				'icon'           => $spec['icon'],
				'tone'           => $is_ok ? 'accent' : 'mute',
				'control_html'   => $ctl_html,
				'status'         => $metric_text,
				'status_tone'    => $is_ok ? 'ok' : 'mute',
				'status_dot'     => true,
				'status_attrs'   => array( 'data-wpcy-core-metric-text' => $id ),
				'action_label'   => $card_action_label,
				'action_url'     => $card_action_url,
				'action_variant' => 'ghost',
				'attrs'          => array( 'data-wpcy-core-card' => $id ),
			);
		}
		\Wenpai_Admin_UI::module_grid( array( 'items' => $svc_items ) );

		$wl            = WhiteLabel::resolve( $settings );
		$hide_unlinked = $wl['enabled'] && $wl['hide_links'];
		$featured_ext  = array();
		$other_ext     = array();
		foreach ( $catalog as $id => $spec ) {
			if ( 'ext' !== ( $spec['group'] ?? 'ext' ) ) {
				continue;
			}
			$st = $plugin_states[ $id ];
			if ( $st['active'] ) {
				continue;
			}
			if ( $hide_unlinked && ! $st['installed'] ) {
				continue;
			}
			if ( $st['installed'] || in_array( $id, $recommended_ids, true ) ) {
				$featured_ext[ $id ] = $spec;
			} else {
				$other_ext[ $id ] = $spec;
			}
		}

		if ( ! empty( $featured_ext ) || ! empty( $other_ext ) ) {
			\Wenpai_Admin_UI::card_open(
				array(
					'title' => $hide_unlinked
						? __( '已安装待启用的生态插件', 'wp-china-yes' )
						: sprintf( /* translators: %s: site scene label */ __( '文派生态目录 · 适合「%s」', 'wp-china-yes' ), $profile_label ),
					'tile'  => false,
				)
			);
			foreach ( $featured_ext as $id => $spec ) {
				self::render_extension_row( $id, $spec, $plugin_states[ $id ], $reports[ $id ] ?? array() );
			}

			if ( ! empty( $other_ext ) ) {
				$cat_labels   = array(
					'all'     => __( '全部', 'wp-china-yes' ),
					'ai'      => __( 'AI 与出海', 'wp-china-yes' ),
					'media'   => __( '媒体与设计', 'wp-china-yes' ),
					'chinese' => __( '中文体验', 'wp-china-yes' ),
					'ops'     => __( '合规与运维', 'wp-china-yes' ),
				);
				$present_cats = array();
				$other_titles = array();
				foreach ( $other_ext as $other_spec ) {
					$other_titles[] = $other_spec['title'];
					$cat_key        = (string) ( $other_spec['category'] ?? 'ops' );
					if ( isset( $cat_labels[ $cat_key ] ) ) {
						$present_cats[ $cat_key ] = $cat_labels[ $cat_key ];
					}
				}

				ob_start();
				echo '<div data-wpcy-catalog-filter="1">';
				if ( count( $present_cats ) > 1 ) {
					echo '<div class="wpcy-chips wpcy-chips--flush">';
					echo '<button type="button" class="wpcy-chip is-active" data-wpcy-cat="all">' . esc_html( $cat_labels['all'] ) . '</button>';
					foreach ( $present_cats as $cat_key => $cat_label ) {
						echo '<button type="button" class="wpcy-chip" data-wpcy-cat="' . esc_attr( $cat_key ) . '">' . esc_html( $cat_label ) . '</button>';
					}
					echo '</div>';
				}
				foreach ( $other_ext as $id => $spec ) {
					$cat_key = (string) ( $spec['category'] ?? 'ops' );
					echo '<div data-wpcy-cat-item="' . esc_attr( $cat_key ) . '">';
					self::render_extension_row( $id, $spec, $plugin_states[ $id ], $reports[ $id ] ?? array() );
					echo '</div>';
				}
				echo '</div>';
				$other_html = (string) ob_get_clean();

				\Wenpai_Admin_UI::fold_list(
					array(
						'items' => array(
							array(
								'icon'      => 'sparkle',
								'title'     => sprintf( /* translators: %d: count of hidden extensions */ __( '浏览更多文派生态插件（%d 款）', 'wp-china-yes' ), count( $other_ext ) ),
								'desc'      => implode( ' / ', array_slice( $other_titles, 0, 5 ) ) . ( count( $other_titles ) > 5 ? ' …' : '' ),
								'body_html' => $other_html,
							),
						),
					)
				);
			}
			\Wenpai_Admin_UI::card_close();
		}

		if ( ! $hide_unlinked ) {
			self::render_scenario_bundles_card( $profile, $plugin_states );
		}

		if ( KitUi::is_site_bound() ) {
			AppsView::render_services_card( $wl, KitUi::page_url( 'wpcy-services' ) );
		} elseif ( ! $hide_unlinked ) {
			ob_start();
			AppsView::render_services_card( $wl, KitUi::page_url( 'wpcy-services' ) );
			$apps_html = (string) ob_get_clean();

			\Wenpai_Admin_UI::card_open(
				array(
					'title' => __( '云端微应用', 'wp-china-yes' ),
					'tile'  => false,
				)
			);
			\Wenpai_Admin_UI::fold_list(
				array(
					'items' => array(
						array(
							'icon'      => 'sparkle',
							'title'     => __( '轻便工具箱', 'wp-china-yes' ),
							'desc'      => __( '绑定文派云通行证后自动同步云端签名的运维与内容小工具', 'wp-china-yes' ),
							'body_html' => $apps_html,
						),
					),
				)
			);
			\Wenpai_Admin_UI::card_foot(
				array(
					'meta'    => __( '商业扩展授权与更新源已独立至顶栏「授权」页管理', 'wp-china-yes' ),
					'actions' => array(
						array(
							'label'   => __( '前往授权页 →', 'wp-china-yes' ),
							'href'    => KitUi::page_url( 'wpcy-licenses' ),
							'variant' => 'ghost',
						),
					),
				)
			);
			\Wenpai_Admin_UI::card_close();
		}

		echo '</div>';
	}

	/**
	 * Render curated 1-click scenario starter bundles on the Services screen.
	 *
	 * @param string                              $profile       Active site profile.
	 * @param array<string, array<string, mixed>> $plugin_states Detected plugin states keyed by catalog id.
	 */
	private static function render_scenario_bundles_card( string $profile, array $plugin_states ): void {
		$can_install = ! function_exists( 'current_user_can' ) || current_user_can( 'install_plugins' );
		$slug_labels = array(
			'wpavatar'             => __( '文派头像', 'wp-china-yes' ),
			'wpslug'               => __( '文派别名', 'wp-china-yes' ),
			'wpcomments'           => __( '文派评论', 'wp-china-yes' ),
			'wp-chinese-converter' => __( '译词简繁', 'wp-china-yes' ),
			'wpsss'                => __( '文派智储', 'wp-china-yes' ),
			'wpmind'               => __( '文派心思', 'wp-china-yes' ),
		);
		$slug_to_id  = array(
			'wpavatar'             => 'cravatar',
			'wpslug'               => 'slug',
			'wpcomments'           => 'comments',
			'wp-chinese-converter' => 'converter',
			'wpsss'                => 'storage',
			'wpmind'               => 'ai',
		);

		$bundles = array(
			array(
				'id'      => 'domestic',
				'match'   => 'domestic',
				'icon'    => 'home',
				'title'   => __( '国内企业与官网组合', 'wp-china-yes' ),
				'summary' => __( '头像本地化 + 拼音别名 + 评论增强', 'wp-china-yes' ),
				'slugs'   => array( 'wpavatar', 'wpslug', 'wpcomments' ),
			),
			array(
				'id'      => 'content',
				'match'   => 'mixed',
				'icon'    => 'sparkle',
				'title'   => __( '内容与社区组合', 'wp-china-yes' ),
				'summary' => __( '头像加速 + 拼音别名 + 简繁互转 + 心思 AI', 'wp-china-yes' ),
				'slugs'   => array( 'wpavatar', 'wpslug', 'wp-chinese-converter', 'wpmind' ),
			),
			array(
				'id'      => 'crossborder',
				'match'   => 'crossborder',
				'icon'    => 'cloud',
				'title'   => __( '外贸与跨境组合', 'wp-china-yes' ),
				'summary' => __( '对象存储加速 + 简繁多语 + 心思 AI', 'wp-china-yes' ),
				'slugs'   => array( 'wpsss', 'wp-chinese-converter', 'wpmind' ),
			),
		);

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '场景一键装机包', 'wp-china-yes' ),
				'tile'  => false,
				'attrs' => array( 'data-wpcy-bundles' => '1' ),
			)
		);

		foreach ( $bundles as $bundle ) {
			$missing = array();
			$active  = 0;
			$names   = array();
			foreach ( $bundle['slugs'] as $slug ) {
				$names[] = $slug_labels[ $slug ];
				$cat_id  = $slug_to_id[ $slug ];
				$is_act  = ! empty( $plugin_states[ $cat_id ]['active'] )
					|| ConflictDetector::is_companion_active( $slug );
				if ( $is_act ) {
					++$active;
				} else {
					$missing[] = $slug;
				}
			}

			$total     = count( $bundle['slugs'] );
			$is_match  = $profile === $bundle['match'];
			$all_ready = array() === $missing;

			if ( $all_ready ) {
				$pill_text  = __( '已全部启用', 'wp-china-yes' );
				$pill_state = 'ok';
			} elseif ( $is_match ) {
				$pill_text  = sprintf( /* translators: 1: active count, 2: total count */ __( '匹配当前场景 · %1$d/%2$d', 'wp-china-yes' ), $active, $total );
				$pill_state = '';
			} else {
				$pill_text  = sprintf( /* translators: 1: active count, 2: total count */ __( '已启用 %1$d/%2$d', 'wp-china-yes' ), $active, $total );
				$pill_state = 'mute';
			}

			$control_html = '';
			if ( ! $all_ready && $can_install ) {
				$control_html = \Wenpai_Admin_UI::button_html(
					array(
						'label'   => sprintf( /* translators: %d: remaining plugin count */ __( '一键安装剩余 %d 款', 'wp-china-yes' ), count( $missing ) ),
						'type'    => 'button',
						'variant' => $is_match ? 'primary' : 'secondary',
						'size'    => 'sm',
						'attrs'   => array(
							'data-wpcy-bundle-install' => implode( ',', $missing ),
							'data-wpcy-bundle-id'      => $bundle['id'],
						),
					)
				);
			}

			\Wenpai_Admin_UI::row(
				array(
					'icon'         => $bundle['icon'],
					'title'        => $bundle['title'],
					'desc'         => implode( ' · ', $names ) . ' — ' . $bundle['summary'],
					'pill'         => array(
						'text'  => $pill_text,
						'state' => $pill_state,
					),
					'control_html' => $control_html,
					'attrs'        => array( 'data-wpcy-bundle-row' => $bundle['id'] ),
				)
			);
		}

		\Wenpai_Admin_UI::card_close();
	}

	/**
	 * Render one ecosystem extension row inside the Services card.
	 *
	 * @param string                                                $id           Service id.
	 * @param array<string, string>                                 $spec         Service catalog entry.
	 * @param array{installed: bool, active: bool, version: string} $plugin_state Companion plugin state.
	 * @param array<string, string>                                 $report       Optional status report.
	 */
	private static function render_extension_row( string $id, array $spec, array $plugin_state, array $report ): void {
		$detail_url = KitUi::page_url( 'wpcy-services', false, array( 'service' => $id ) );

		if ( ! empty( $report['pill'] ) ) {
			$pill      = array(
				'text'  => (string) $report['pill'],
				'state' => ! empty( $report['state'] ) ? (string) $report['state'] : 'ok',
			);
			$row_state = 'ok' === $pill['state'] ? 'ok' : '';
		} elseif ( $plugin_state['active'] ) {
			$pill      = array(
				'text'  => __( '已接管', 'wp-china-yes' ),
				'state' => 'ok',
			);
			$row_state = 'ok';
		} elseif ( $plugin_state['installed'] ) {
			$pill      = array(
				'text'  => __( '未启用', 'wp-china-yes' ),
				'state' => 'warn',
			);
			$row_state = 'warn';
		} else {
			$pill      = array(
				'text'  => __( '未安装', 'wp-china-yes' ),
				'state' => 'mute',
			);
			$row_state = '';
		}

		$row_desc    = ! empty( $report['summary'] ) ? (string) $report['summary'] : $spec['short_desc'];
		$can_install = ! function_exists( 'current_user_can' ) || current_user_can( 'install_plugins' );
		$can_act     = ! function_exists( 'current_user_can' ) || current_user_can( 'activate_plugins' );

		if ( $plugin_state['active'] ) {
			$settings_url = ! empty( $report['action_url'] )
				? $report['action_url']
				: ConflictDetector::resolve_companion_admin_url( $spec['plugin_slug'], $spec['plugin_file'] );
			$action_ctl   = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '打开设置', 'wp-china-yes' ),
					'href'    => $settings_url,
					'variant' => 'secondary',
					'size'    => 'sm',
				)
			);
		} elseif ( $plugin_state['installed'] && $can_act ) {
			$action_ctl = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '详情', 'wp-china-yes' ),
					'href'    => $detail_url,
					'variant' => 'ghost',
					'size'    => 'sm',
				)
			) . ' ' . \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '立即启用', 'wp-china-yes' ),
					'type'    => 'button',
					'variant' => 'secondary',
					'size'    => 'sm',
					'attrs'   => array(
						'data-wpcy-provision-slug' => $spec['plugin_slug'],
						'data-wpcy-provision-name' => $spec['plugin_name'],
						'data-wpcy-suite-state'    => 'installed',
					),
				)
			);
		} elseif ( ! $plugin_state['installed'] && $can_install && $can_act && ConflictDetector::is_companion_installable( $spec['plugin_slug'] ) ) {
			$action_ctl = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '详情', 'wp-china-yes' ),
					'href'    => $detail_url,
					'variant' => 'ghost',
					'size'    => 'sm',
				)
			) . ' ' . \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '一键安装', 'wp-china-yes' ),
					'type'    => 'button',
					'variant' => 'secondary',
					'size'    => 'sm',
					'attrs'   => array(
						'data-wpcy-provision-slug' => $spec['plugin_slug'],
						'data-wpcy-provision-name' => $spec['plugin_name'],
						'data-wpcy-suite-state'    => 'missing',
					),
				)
			);
		} else {
			$action_ctl = \Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '查看详情', 'wp-china-yes' ),
					'href'    => $detail_url,
					'variant' => 'ghost',
					'size'    => 'sm',
				)
			);
		}

		\Wenpai_Admin_UI::row(
			array(
				'icon'         => $spec['icon'],
				'state'        => $row_state,
				'title'        => $spec['title'],
				'desc'         => $row_desc,
				'pill'         => $pill,
				'control_html' => $action_ctl,
			)
		);
	}
}
