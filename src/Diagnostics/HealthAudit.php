<?php
/**
 * China-localization & site readiness health audit (0–100 score) plus safe one-click fix patch builder.
 *
 * Strictly zero outbound HTTP requests: inspects effective settings, active companion/conflict plugins,
 * and locally cached server probe snapshots only.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes deterministic 100-point China-localization health audit and safe config patches.
 */
final class HealthAudit {

	/**
	 * Evaluate the site's China-localization & stability posture across 6 checks (total 100 pts).
	 *
	 * @param array<string, mixed>             $settings Effective settings snapshot.
	 * @param array<int, array<string, mixed>> $probes   Cached server probe rows from `Checker::latest()`.
	 * @return array{
	 *     score: int,
	 *     status: string,
	 *     profile: string,
	 *     recovery_mode: bool,
	 *     fixable_count: int,
	 *     avg_latency_ms: int|null,
	 *     items: list<array{
	 *         id: string,
	 *         label: string,
	 *         icon: string,
	 *         status: string,
	 *         pill: string,
	 *         detail: string,
	 *         fixable: bool,
	 *         weight: int
	 *     }>
	 * }
	 */
	public static function evaluate( array $settings, array $probes = array() ): array {
		$profile  = (string) ( $settings['profile'] ?? 'domestic' );
		$recovery = ! empty( $settings['recovery_mode'] );
		$conn     = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$is_xb    = 'crossborder' === $profile;

		$wporg = (string) ( $conn['wordpress_org'] ?? 'auto' );
		$scope = 'off';
		if ( isset( $conn['public_assets'] ) && is_array( $conn['public_assets'] ) && isset( $conn['public_assets']['scope'] ) ) {
			$scope = (string) $conn['public_assets']['scope'];
		}
		$avatar    = (string) ( $conn['avatar'] ?? 'cravatar_cn' );
		$heartbeat = (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds     = (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		$wpavatar  = ConflictDetector::is_companion_active( 'wpavatar' );
		$scan      = ConflictDetector::scan();
		$conflicts = $scan['conflicts'];
		$items     = array();
		$score     = 0;
		$fix_count = 0;

		// 1. WP.org mirror (20 pts).
		if ( $recovery ) {
			$items[] = array(
				'id'      => 'wporg',
				'label'   => __( 'WP.org 更新镜像', 'wp-china-yes' ),
				'icon'    => 'cloud',
				'status'  => 'warn',
				'pill'    => __( '已暂停', 'wp-china-yes' ),
				'detail'  => __( '恢复模式生效中，更新加速已暂时暂停。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 20,
			);
		} elseif ( 'off' !== $wporg || $is_xb ) {
			$score  += 20;
			$items[] = array(
				'id'      => 'wporg',
				'label'   => __( 'WP.org 更新镜像', 'wp-china-yes' ),
				'icon'    => 'cloud',
				'status'  => 'ok',
				'pill'    => __( '已优化', 'wp-china-yes' ),
				'detail'  => $is_xb && 'off' === $wporg
					? __( '跨境站策略保持直连官方源。', 'wp-china-yes' )
					: __( '已启用 WenPai.org 国内更新镜像，异常自动回退。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 20,
			);
		} else {
			++$fix_count;
			$items[] = array(
				'id'      => 'wporg',
				'label'   => __( 'WP.org 更新镜像', 'wp-china-yes' ),
				'icon'    => 'cloud',
				'status'  => 'warn',
				'pill'    => __( '待优化', 'wp-china-yes' ),
				'detail'  => __( '当前直连官方源，国内后台检查更新与装插件容易超时。', 'wp-china-yes' ),
				'fixable' => true,
				'weight'  => 20,
			);
		}

		// 2. Public frontend/admin static libraries & Google Fonts (20 pts).
		if ( $recovery ) {
			$items[] = array(
				'id'      => 'public_assets',
				'label'   => __( '公共前端库与字体加速', 'wp-china-yes' ),
				'icon'    => 'flash',
				'status'  => 'warn',
				'pill'    => __( '已暂停', 'wp-china-yes' ),
				'detail'  => __( '恢复模式生效中，静态库改写已暂停。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 20,
			);
		} elseif ( 'off' !== $scope || $is_xb ) {
			$score  += 20;
			$items[] = array(
				'id'      => 'public_assets',
				'label'   => __( '公共前端库与字体加速', 'wp-china-yes' ),
				'icon'    => 'flash',
				'status'  => 'ok',
				'pill'    => __( '已优化', 'wp-china-yes' ),
				'detail'  => $is_xb && 'off' === $scope
					? __( '跨境站策略按访客源站直出静态资源。', 'wp-china-yes' )
					: __( 'Google 字体与公共前端库已切至 adminCDN 国内加速。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 20,
			);
		} else {
			++$fix_count;
			$items[] = array(
				'id'      => 'public_assets',
				'label'   => __( '公共前端库与字体加速', 'wp-china-yes' ),
				'icon'    => 'flash',
				'status'  => 'warn',
				'pill'    => __( '待优化', 'wp-china-yes' ),
				'detail'  => __( '未启用静态库替换，主题引用的海外字体与脚本会阻塞首屏。', 'wp-china-yes' ),
				'fixable' => true,
				'weight'  => 20,
			);
		}

		// 3. Cravatar / WPAvatar acceleration (15 pts).
		if ( $wpavatar ) {
			$score  += 15;
			$items[] = array(
				'id'      => 'avatar',
				'label'   => __( 'Cravatar 头像加速', 'wp-china-yes' ),
				'icon'    => 'user',
				'status'  => 'ok',
				'pill'    => __( '已接管', 'wp-china-yes' ),
				'detail'  => __( '已由文派头像（WPAvatar）协同提供本地缓存与加速。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 15,
			);
		} elseif ( $recovery ) {
			$items[] = array(
				'id'      => 'avatar',
				'label'   => __( 'Cravatar 头像加速', 'wp-china-yes' ),
				'icon'    => 'user',
				'status'  => 'warn',
				'pill'    => __( '已暂停', 'wp-china-yes' ),
				'detail'  => __( '恢复模式生效中，头像线路已暂时回退默认源。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 15,
			);
		} elseif ( 'off' !== $avatar ) {
			$score  += 15;
			$items[] = array(
				'id'      => 'avatar',
				'label'   => __( 'Cravatar 头像加速', 'wp-china-yes' ),
				'icon'    => 'user',
				'status'  => 'ok',
				'pill'    => __( '已优化', 'wp-china-yes' ),
				'detail'  => 'cravatar_global' === $avatar
					? __( '已启用 Cravatar 国际线路，兼顾全球访客加载速度。', 'wp-china-yes' )
					: __( '已切换至 Cravatar 中国大陆节点，避免 Gravatar 超时。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 15,
			);
		} else {
			++$fix_count;
			$items[] = array(
				'id'      => 'avatar',
				'label'   => __( 'Cravatar 头像加速', 'wp-china-yes' ),
				'icon'    => 'user',
				'status'  => 'warn',
				'pill'    => __( '待优化', 'wp-china-yes' ),
				'detail'  => __( '仍使用原始 Gravatar，评论区与后台头像易加载失败。', 'wp-china-yes' ),
				'fixable' => true,
				'weight'  => 15,
			);
		}

		// 4. Admin noise reduction (heartbeat / slow external feeds) (15 pts).
		if ( 'on' === $heartbeat || 'block' === $feeds ) {
			$score  += 15;
			$items[] = array(
				'id'      => 'noise',
				'label'   => __( '后台心跳与外部源减噪', 'wp-china-yes' ),
				'icon'    => 'equalizer',
				'status'  => 'ok',
				'pill'    => __( '已优化', 'wp-china-yes' ),
				'detail'  => __( '已开启后台心跳降频或屏蔽慢速外部源，降低后台卡顿。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 15,
			);
		} else {
			++$fix_count;
			$items[] = array(
				'id'      => 'noise',
				'label'   => __( '后台心跳与外部源减噪', 'wp-china-yes' ),
				'icon'    => 'equalizer',
				'status'  => 'warn',
				'pill'    => __( '待优化', 'wp-china-yes' ),
				'detail'  => __( '建议开启心跳降频并屏蔽慢速海外仪表盘新闻源。', 'wp-china-yes' ),
				'fixable' => true,
				'weight'  => 15,
			);
		}

		// 5. Conflicting / dead-CDN plugins (15 pts, manual action only).
		if ( array() === $conflicts ) {
			$score  += 15;
			$items[] = array(
				'id'      => 'conflicts',
				'label'   => __( '冲突与失效节点插件', 'wp-china-yes' ),
				'icon'    => 'shield-check',
				'status'  => 'ok',
				'pill'    => __( '无冲突', 'wp-china-yes' ),
				'detail'  => __( '未检测到 360 公共库等失效加速源或重复功能插件。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 15,
			);
		} else {
			$names = array();
			foreach ( array_slice( $conflicts, 0, 3 ) as $conf ) {
				$names[] = $conf['name'];
			}
			$items[] = array(
				'id'      => 'conflicts',
				'label'   => __( '冲突与失效节点插件', 'wp-china-yes' ),
				'icon'    => 'shield-check',
				'status'  => 'bad',
				'pill'    => sprintf( /* translators: %d: conflict plugin count */ __( '%d 项冲突', 'wp-china-yes' ), count( $conflicts ) ),
				'detail'  => sprintf(
					/* translators: %s: comma-separated conflicting plugin names */
					__( '检测到 %s，建议前往插件页停用以避免重复改写或死链。', 'wp-china-yes' ),
					implode( '、', $names )
				),
				'fixable' => false,
				'weight'  => 15,
			);
		}

		// 6. Cached core node reachability & latency (15 pts).
		$latencies = array();
		$issues    = 0;
		foreach ( $probes as $row ) {
			if ( isset( $row['latency_ms'] ) && is_numeric( $row['latency_ms'] ) && (int) $row['latency_ms'] > 0 ) {
				$latencies[] = (int) $row['latency_ms'];
			}
			$res = isset( $row['result'] ) ? (string) $row['result'] : '';
			if ( 'fallback' === $res || 'down' === $res ) {
				++$issues;
			}
		}
		$avg_ms = array() !== $latencies ? (int) round( array_sum( $latencies ) / count( $latencies ) ) : null;
		if ( array() === $probes ) {
			$score  += 10;
			$items[] = array(
				'id'      => 'probes',
				'label'   => __( '核心加速节点连通性', 'wp-china-yes' ),
				'icon'    => 'server',
				'status'  => 'warn',
				'pill'    => __( '待测速', 'wp-china-yes' ),
				'detail'  => __( '尚无近期节点测速缓存，点击上方「开始检测」可即时测量延迟。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 15,
			);
		} elseif ( 0 === $issues ) {
			$score  += 15;
			$items[] = array(
				'id'      => 'probes',
				'label'   => __( '核心加速节点连通性', 'wp-china-yes' ),
				'icon'    => 'server',
				'status'  => 'ok',
				'pill'    => null !== $avg_ms
					? sprintf( /* translators: %d: average latency in ms */ __( '均值 %d ms', 'wp-china-yes' ), $avg_ms )
					: __( '节点正常', 'wp-china-yes' ),
				'detail'  => __( '全部核心加速节点可达，未触发异常回退。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 15,
			);
		} else {
			$score  += 5;
			$items[] = array(
				'id'      => 'probes',
				'label'   => __( '核心加速节点连通性', 'wp-china-yes' ),
				'icon'    => 'server',
				'status'  => 'warn',
				'pill'    => sprintf( /* translators: %d: fallback channel count */ __( '%d 项波动', 'wp-china-yes' ), $issues ),
				'detail'  => __( '部分节点近期存在波动，已由熔断机制自动回退，可点「开始检测」复测。', 'wp-china-yes' ),
				'fixable' => false,
				'weight'  => 15,
			);
		}

		$overall = 'ok';
		if ( array() !== $conflicts || $score < 60 ) {
			$overall = 'bad';
		} elseif ( $score < 95 || $fix_count > 0 || $recovery ) {
			$overall = 'warn';
		}

		return array(
			'score'          => $score,
			'status'         => $overall,
			'profile'        => $profile,
			'recovery_mode'  => $recovery,
			'fixable_count'  => $fix_count,
			'avg_latency_ms' => $avg_ms,
			'items'          => $items,
		);
	}

	/**
	 * Build a minimal, reversible flat dot-key patch map for all auto-fixable items.
	 *
	 * Never touches third-party plugins, never disables recovery mode silently.
	 *
	 * @param array<string, mixed> $settings Current settings snapshot.
	 * @return array<string, mixed> Map of `Repository::set()` dot-keys to values.
	 */
	public static function build_fix_patch( array $settings ): array {
		if ( ! empty( $settings['recovery_mode'] ) ) {
			return array();
		}

		$profile = (string) ( $settings['profile'] ?? 'domestic' );
		$is_xb   = 'crossborder' === $profile;
		$conn    = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$patch   = array();

		if ( ! $is_xb && 'off' === (string) ( $conn['wordpress_org'] ?? 'auto' ) ) {
			$patch['connectivity.wordpress_org'] = 'auto';
		}

		$scope = ( isset( $conn['public_assets'] ) && is_array( $conn['public_assets'] ) && isset( $conn['public_assets']['scope'] ) )
			? (string) $conn['public_assets']['scope']
			: 'off';
		if ( ! $is_xb && 'off' === $scope ) {
			$patch['connectivity.public_assets.scope'] = 'both';
		}

		if ( ! ConflictDetector::is_companion_active( 'wpavatar' ) && 'off' === (string) ( $conn['avatar'] ?? 'cravatar_cn' ) ) {
			$patch['connectivity.avatar'] = $is_xb ? 'cravatar_global' : 'cravatar_cn';
		}

		$hb    = (string) ( $conn['heartbeat'] ?? 'off' );
		$feeds = (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		if ( 'on' !== $hb && 'block' !== $feeds ) {
			$patch['connectivity.heartbeat']       = 'on';
			$patch['connectivity.dashboard_feeds'] = 'block';
		}

		return $patch;
	}
}
