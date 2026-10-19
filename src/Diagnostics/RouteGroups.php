<?php
/**
 * Diagnostics target groups, provider brands, and worst-status aggregation.
 *
 * Single table for REST events `{route}` / `{provider}` / `{host}` and the
 * overview line list. M-UI-1 may export this to bootstrap; this class does
 * not touch Admin.
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
 * Frozen grouping from rest-api.md §/diagnostics (D1 provider column).
 */
final class RouteGroups {

	/**
	 * Rank: down is worst.
	 *
	 * @since 4.0.0
	 *
	 * @var array<string, int>
	 */
	private const RANK = array(
		Checker::RESULT_OK       => 0,
		Checker::RESULT_FALLBACK => 1,
		Checker::RESULT_DOWN     => 2,
	);

	/**
	 * Group table. Member hosts match Checker targets.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array{id: string, label: string, provider: string, description: string, members: list<string>}>
	 */
	public static function all(): array {
		return array(
			array(
				'id'          => 'wordpress_org',
				'label'       => 'WordPress.org 镜像',
				'provider'    => 'WenPai.org',
				'description' => '更新检查与安装包',
				'members'     => array( 'api.wenpai.net', 'downloads.wenpai.net' ),
			),
			array(
				'id'          => 'public_assets',
				'label'       => '公共库源',
				'provider'    => 'adminCDN',
				'description' => 'Google Fonts、Ajax、jsDelivr、Emoji',
				'members'     => array( 'googlefonts.admincdn.com', 'googleajax.admincdn.com', 'jsd.admincdn.com' ),
			),
			array(
				'id'          => 'cdnjs',
				'label'       => 'CDNJS 源',
				'provider'    => 'adminCDN',
				'description' => '备用公共库',
				'members'     => array( 'cdnjs.admincdn.com' ),
			),
			array(
				'id'          => 'cravatar',
				'label'       => 'Cravatar',
				'provider'    => 'Cravatar',
				'description' => '评论头像',
				'members'     => array( 'cn.cravatar.com', 'en.cravatar.com' ),
			),
			array(
				'id'          => 'icon_photos',
				'label'       => '图标与图片',
				'provider'    => 'MotuCloud',
				'description' => '核心图标与媒体库图片搜索',
				'members'     => array( 'motucloud' ),
			),
		);
	}

	/**
	 * Group row for a probe target host, or null when ungrouped.
	 *
	 * @since 4.0.0
	 *
	 * @param string $target Checker `target` host.
	 * @return array{id: string, label: string, provider: string, description: string, members: list<string>}|null
	 */
	public static function group_for_target( string $target ) {
		foreach ( self::all() as $group ) {
			if ( in_array( $target, $group['members'], true ) ) {
				return $group;
			}
		}

		return null;
	}

	/**
	 * Aggregate per-target rows into groups. Worst result wins
	 * (down > fallback > ok); latency is the max; checked_at is the earliest.
	 *
	 * Groups with no members in `$rows` are omitted.
	 *
	 * @since 4.0.0
	 *
	 * @param list<array{target: string, result: string, latency_ms: int|null, checked_at: string, suggestion: string|null}> $rows Checker rows.
	 * @return list<array{id: string, label: string, provider: string, result: string, latency_ms: int|null, checked_at: string, host: string}>
	 */
	public static function worst( array $rows ): array {
		$out = array();
		foreach ( self::all() as $group ) {
			$members = array();
			foreach ( $rows as $row ) {
				if ( in_array( (string) $row['target'], $group['members'], true ) ) {
					$members[] = $row;
				}
			}
			if ( array() === $members ) {
				continue;
			}
			$out[] = self::aggregate( $group, $members );
		}

		return $out;
	}

	/**
	 * One group snapshot from its member rows.
	 *
	 * @param array{id: string, label: string, provider: string, description: string, members: list<string>}                 $group   Group.
	 * @param list<array{target: string, result: string, latency_ms: int|null, checked_at: string, suggestion: string|null}> $members Member rows.
	 * @return array{id: string, label: string, provider: string, result: string, latency_ms: int|null, checked_at: string, host: string}
	 */
	private static function aggregate( array $group, array $members ): array {
		$result     = Checker::RESULT_OK;
		$rank       = 0;
		$latency    = null;
		$checked_at = null;
		$host       = (string) $members[0]['target'];

		foreach ( $members as $row ) {
			$row_result = (string) $row['result'];
			$row_rank   = self::RANK[ $row_result ] ?? 0;
			if ( $row_rank > $rank ) {
				$rank   = $row_rank;
				$result = $row_result;
				$host   = (string) $row['target'];
			}
			$row_latency = $row['latency_ms'];
			if ( is_int( $row_latency ) && ( null === $latency || $row_latency > $latency ) ) {
				$latency = $row_latency;
			}
			$row_checked = (string) $row['checked_at'];
			if ( '' !== $row_checked && ( null === $checked_at || $row_checked < $checked_at ) ) {
				$checked_at = $row_checked;
			}
		}

		return array(
			'id'         => $group['id'],
			'label'      => $group['label'],
			'provider'   => $group['provider'],
			'result'     => $result,
			'latency_ms' => $latency,
			'checked_at' => is_string( $checked_at ) ? $checked_at : '',
			'host'       => $host,
		);
	}
}
