<?php
/**
 * Diagnose, Help, and Conflict/Synergy row renderers.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

use WenPai\ChinaYes\Admin\ManifestStatusCard;
use WenPai\ChinaYes\Admin\ProbeSnapshot;
use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Diagnostics\ConflictDetector;
use WenPai\ChinaYes\Diagnostics\HealthAudit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Diagnose screen (`?page=wpcy-diagnose`), Help screen, and conflict/synergy rows.
 */
final class DiagnoseView {

	/**
	 * Built-in Help screen (`?page=wpcy&view=help`).
	 *
	 * @param array<string, mixed> $links External links.
	 */
	public static function render_help( array $links ): void {
		$support_url = isset( $links['help'] ) ? (string) $links['help'] : 'https://wpcy.com/go/support';
		\Wenpai_Admin_Shell::page_head(
			__( '帮助与支持', 'wp-china-yes' ),
			__( '了解站点场景选择、功能与服务边界，以及常见网络连接问题的排查方法。', 'wp-china-yes' ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '前往诊断页', 'wp-china-yes' ),
					'href'    => KitUi::page_url( 'wpcy-diagnose' ),
					'variant' => 'secondary',
				)
			)
		);

		\Wenpai_Admin_UI::card_open();
		\Wenpai_Admin_UI::help(
			array(
				'groups' => array(
					array(
						'title'       => __( '站点场景与自动回退', 'wp-china-yes' ),
						'icon'        => 'cloud',
						'desc'        => __( '如何按服务器与访客位置选择场景，以及镜像异常时的保护机制。', 'wp-china-yes' ),
						'collapsible' => true,
						'open'        => true,
						'steps'       => array(
							array(
								'title' => __( '国内站、跨境站与混合站该如何选择？', 'wp-china-yes' ),
								'body'  => __( '服务器和主要访客都在中国大陆时选「国内站」，前后台均走国内高速节点；面向海外买家的外贸或出海站点选「跨境站」，前台保持原始海外连接以确保支付与物流顺畅，仅加速国内运营团队使用的后台；国内外访客兼顾的站点选「混合站」，可按需在高级线路里分别设定。', 'wp-china-yes' ),
							),
							array(
								'title' => __( '国内镜像临时波动时会阻碍插件或系统更新吗？', 'wp-china-yes' ),
								'body'  => __( '不会。文派叶子内置连通性健康巡检，一旦检测到镜像响应超时或异常，会自动将下载请求回退至 WordPress.org 官方源，并在概览页运行记录中留存说明。', 'wp-china-yes' ),
							),
						),
					),
					array(
						'title'       => __( '「功能」与「服务」的分工', 'wp-china-yes' ),
						'icon'        => 'sparkle',
						'desc'        => __( '文派叶子内置能力与文派生态独立插件如何协同工作。', 'wp-china-yes' ),
						'collapsible' => true,
						'open'        => true,
						'steps'       => array(
							array(
								'title' => __( '「功能」与「服务」两个页面有什么区别？', 'wp-china-yes' ),
								'body'  => __( '「功能」汇聚文派叶子自身携带的本地治理模块（前后台语言分离、后台减噪、资讯与公告源、外链治理与全套白标）；「服务」负责管理云端加速通道，并与文派智储、文派心思、文派素格、文派头像、文派字库等独立插件联动。', 'wp-china-yes' ),
							),
							array(
								'title' => __( '安装独立插件后会与文派叶子的基础加速冲突吗？', 'wp-china-yes' ),
								'body'  => __( '不会。文派叶子提供底层网络通道与连通性检测，启用对应独立插件后，「服务」页会自动将其置顶显示为「已接管」，由独立插件接手细粒度业务配置。', 'wp-china-yes' ),
							),
						),
					),
					array(
						'title'       => __( '全套白标与交付锁定', 'wp-china-yes' ),
						'icon'        => 'settings',
						'desc'        => __( '如何为客户站点统一替换品牌名称并锁定后台配置。', 'wp-china-yes' ),
						'collapsible' => true,
						'open'        => false,
						'steps'       => array(
							array(
								'title' => __( '全套白标会修改插件源码或影响后续升级吗？', 'wp-china-yes' ),
								'body'  => __( '不会。全套白标通过统一的显示接口替换全站文派系列插件的品牌名、帮助链接、文档链接与插件列表署名，插件目录名与在线更新通道保持完好；交付客户站时还可在 wp-config.php 中定义 WPCY_WHITELABEL 常量固定配置。', 'wp-china-yes' ),
							),
						),
					),
				),
				'links'  => array(
					array(
						'label' => __( '文派产品文档中心', 'wp-china-yes' ),
						'href'  => 'https://wpcy.com/document',
					),
					array(
						'label' => __( '技术支持与问题反馈', 'wp-china-yes' ),
						'href'  => $support_url,
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
	}

	/**
	 * Diagnose screen (`?page=wpcy-diagnose`).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @param bool                 $locked   Whether subsite settings are network-locked.
	 */
	public static function render( array $settings, bool $locked = false ): void {
		$recovery = ! empty( $settings['recovery_mode'] );
		$probes   = array();
		if ( function_exists( 'get_transient' ) ) {
			$raw_probes = get_transient( Checker::STORE_KEY );
			if ( is_array( $raw_probes ) ) {
				$probes = $raw_probes;
			}
		}
		$audit       = HealthAudit::evaluate( $settings, $probes );
		$audit_score = $audit['score'];
		$audit_tone  = $audit['status'];
		$fix_count   = $audit['fixable_count'];
		$score_pill  = sprintf(
			/* translators: 1: numeric score out of 100, 2: status text */
			__( '%1$d 分 · %2$s', 'wp-china-yes' ),
			$audit_score,
			'ok' === $audit_tone
				? __( '已就绪', 'wp-china-yes' )
				: ( $fix_count > 0 ? __( '可一键优化', 'wp-china-yes' ) : __( '需留意', 'wp-china-yes' ) )
		);

		\Wenpai_Admin_Shell::page_head(
			__( '诊断', 'wp-china-yes' ),
			__( '一键体检国内化状态，并对比服务器出站与当前浏览器的连通延迟。', 'wp-china-yes' ),
			\Wenpai_Admin_UI::button_html(
				array(
					'label'   => __( '开始检测', 'wp-china-yes' ),
					'variant' => 'primary',
					'attrs'   => array( 'data-wpcy-action' => 'diagnose' ),
				)
			)
		);

		echo '<div class="wenpai-stack" data-wpcy-screen="diagnose">';

		$audit_head_actions  = '<div class="wenpai-row wenpai-row--tight">';
		$audit_head_actions .= \Wenpai_Admin_UI::pill_html(
			array(
				'text'  => $score_pill,
				'state' => $audit_tone,
				'attrs' => array( 'data-wpcy-audit-score' => '1' ),
			)
		);
		if ( ! $locked ) {
			$audit_head_actions .= \Wenpai_Admin_UI::button_html(
				array(
					'label'    => $fix_count > 0
						? sprintf( /* translators: %d: fixable count */ __( '一键修复（%d 项）', 'wp-china-yes' ), $fix_count )
						: __( '已全部优化', 'wp-china-yes' ),
					'type'     => 'button',
					'variant'  => $fix_count > 0 ? 'primary' : 'secondary',
					'size'     => 'sm',
					'disabled' => 0 === $fix_count,
					'attrs'    => array( 'data-wpcy-audit-fix' => '1' ),
				)
			);
		}
		$audit_head_actions .= '</div>';

		\Wenpai_Admin_UI::card_open(
			array(
				'title'             => __( '国内化体检与一键修复', 'wp-china-yes' ),
				'tile'              => false,
				'head_control_html' => $audit_head_actions,
				'attrs'             => array(
					'id'                   => 'wpcy-audit',
					'data-wpcy-audit-card' => '1',
				),
			)
		);
		foreach ( $audit['items'] as $item ) {
			$row_control = '';
			if ( 'conflicts' === $item['id'] && 'ok' !== $item['status'] && function_exists( 'admin_url' ) ) {
				$row_control = \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '插件管理', 'wp-china-yes' ),
						'href'    => admin_url( 'plugins.php' ),
						'variant' => 'ghost',
						'size'    => 'sm',
					)
				);
			}
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => $item['icon'],
					'title'        => $item['label'],
					'desc'         => $item['detail'],
					'pill'         => array(
						'text'  => $item['pill'],
						'state' => $item['status'],
					),
					'control_html' => $row_control,
					'attrs'        => array( 'data-wpcy-audit-item' => $item['id'] ),
				)
			);
		}
		\Wenpai_Admin_UI::card_foot(
			array(
				'meta'    => $fix_count > 0
					? sprintf( /* translators: %d: count of auto-fixable items */ __( '发现 %d 项可自动优化的国内化配置，一键修复不影响第三方插件。', 'wp-china-yes' ), $fix_count )
					: __( '国内化核心配置已就绪，修复动作始终可逆。', 'wp-china-yes' ),
				'attrs'   => array( 'data-wpcy-audit-summary' => '1' ),
				'actions' => array(
					array(
						'label'   => __( '重新体检', 'wp-china-yes' ),
						'type'    => 'button',
						'variant' => 'ghost',
						'attrs'   => array( 'data-wpcy-audit-refresh' => '1' ),
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '<div class="wenpai-grid-2">';
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '服务器出站连通性', 'wp-china-yes' ),
				'icon'  => 'server',
			)
		);
		\Wenpai_Admin_UI::status_list(
			array(
				'attrs' => array( 'data-wpcy-diag-server' => '1' ),
				'items' => array(
					array(
						'label' => __( 'WP.org 国内镜像接口', 'wp-china-yes' ),
						'value' => __( '等待检测', 'wp-china-yes' ),
					),
					array(
						'label' => __( '安装包国内下载源', 'wp-china-yes' ),
						'value' => __( '等待检测', 'wp-china-yes' ),
					),
					array(
						'label' => __( 'Cravatar 头像服务', 'wp-china-yes' ),
						'value' => __( '等待检测', 'wp-china-yes' ),
					),
					array(
						'label' => __( 'adminCDN 公共前端库', 'wp-china-yes' ),
						'value' => __( '等待检测', 'wp-china-yes' ),
					),
				),
			)
		);
		\Wenpai_Admin_UI::card_close();

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '当前浏览器连通性', 'wp-china-yes' ),
				'icon'  => 'eye',
			)
		);
		\Wenpai_Admin_UI::status_list(
			array(
				'attrs' => array( 'data-wpcy-diag-browser' => '1' ),
				'items' => ProbeSnapshot::diagnose_browser_items(),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';

		$scan = ConflictDetector::scan();
		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '组件互联与冲突检测', 'wp-china-yes' ),
				'tile'  => false,
				'attrs' => array( 'data-wpcy-conflict-card' => '1' ),
			)
		);
		self::render_conflict_and_synergy_rows( $scan );
		\Wenpai_Admin_UI::card_close();

		ManifestStatusCard::render();

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '安全恢复模式', 'wp-china-yes' ),
				'tile'  => false,
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'tools',
				'state'        => $recovery ? 'ok' : 'bad',
				'title'        => $recovery ? __( '恢复正常加速', 'wp-china-yes' ) : __( '临时暂停全部加速规则', 'wp-china-yes' ),
				'desc'         => $recovery
					? __( '退出恢复模式并重新应用已保存的镜像与加速设置。', 'wp-china-yes' )
					: __( '切回 WordPress 默认直连以排查冲突，原有配置完整保留。', 'wp-china-yes' ),
				'control_html' => \Wenpai_Admin_UI::button_html(
					array(
						'label'   => $recovery ? __( '恢复正常运行', 'wp-china-yes' ) : __( '进入恢复模式', 'wp-china-yes' ),
						'variant' => $recovery ? 'secondary' : 'danger',
						'size'    => 'sm',
						'attrs'   => $recovery
							? array( 'data-wpcy-action' => 'recovery-off' )
							: array(
								'data-wpcy-action'    => 'recovery-on',
								'data-wenpai-confirm' => __( '确认暂停全部镜像与静态资源加速并切回默认直连吗？原有设置不会丢失。', 'wp-china-yes' ),
							),
					)
				),
			)
		);
		\Wenpai_Admin_UI::card_close();
		echo '</div>';
	}

	/**
	 * Render conflict & WenPai companion synergy rows inside Diagnose or Onboarding Wizard.
	 *
	 * @param array<string, mixed> $scan Scan snapshot from `ConflictDetector::scan()`.
	 * @phpstan-param array{
	 *     conflicts: list<array{id: string, slug: string, file: string, name: string, level: string, reason: string}>,
	 *     synergies: list<array{slug: string, file: string, name: string, admin: string, url: string, synergy: string}>
	 * } $scan
	 */
	public static function render_conflict_and_synergy_rows( array $scan ): void {
		$plugins_base = KitUi::wp_admin_link( 'plugins.php' );
		if ( array() === $scan['conflicts'] ) {
			\Wenpai_Admin_UI::row(
				array(
					'icon'  => 'shield-check',
					'state' => 'ok',
					'title' => __( '未检测到同类改写或失效镜像冲突', 'wp-china-yes' ),
					'desc'  => __( '当前站点未同时启用重复的镜像、字体或头像替换插件；与文派系列组件自动协同让渡。', 'wp-china-yes' ),
					'pill'  => array(
						'text'  => __( '环境正常', 'wp-china-yes' ),
						'state' => 'ok',
					),
				)
			);
		} else {
			foreach ( $scan['conflicts'] as $conf ) {
				$state      = 'bad' === $conf['level'] ? 'bad' : 'warn';
				$manage_url = function_exists( 'add_query_arg' )
					? (string) add_query_arg( array( 's' => $conf['slug'] ), $plugins_base )
					: $plugins_base;
				\Wenpai_Admin_UI::row(
					array(
						'icon'         => 'warning',
						'state'        => $state,
						'title'        => $conf['name'],
						'desc'         => $conf['reason'],
						'pill'         => array(
							'text'  => 'bad' === $state ? __( '失效节点冲突', 'wp-china-yes' ) : __( '同类重复改写', 'wp-china-yes' ),
							'state' => $state,
						),
						'control_html' => \Wenpai_Admin_UI::button_html(
							array(
								'label'   => __( '管理插件', 'wp-china-yes' ),
								'href'    => $manage_url,
								'variant' => 'secondary',
								'size'    => 'sm',
							)
						),
					)
				);
			}
		}

		foreach ( $scan['synergies'] as $syn ) {
			\Wenpai_Admin_UI::row(
				array(
					'icon'         => 'sparkle',
					'state'        => 'ok',
					'title'        => $syn['name'],
					'desc'         => $syn['synergy'],
					'pill'         => array(
						'text'  => __( '协同接管', 'wp-china-yes' ),
						'state' => 'ok',
					),
					'control_html' => \Wenpai_Admin_UI::button_html(
						array(
							'label'   => __( '组件设置', 'wp-china-yes' ),
							'href'    => KitUi::wp_admin_link( $syn['admin'] ),
							'variant' => 'ghost',
							'size'    => 'sm',
						)
					),
				)
			);
		}
	}
}
