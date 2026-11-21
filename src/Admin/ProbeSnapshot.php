<?php
/**
 * Server-rendered browser probe snapshot helper for Overview and Diagnose.
 *
 * Reads the latest client-side connectivity summary persisted in
 * `ClientProbeController::OPTION` without issuing any remote HTTP requests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

use WenPai\ChinaYes\Rest\ClientProbeController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats cached browser probe records into `Wenpai_Admin_UI` data arrays.
 */
final class ProbeSnapshot {

	/**
	 * Build the 4th `Wenpai_Admin_UI::metric_tiles` item (`浏览器直测`) for Overview.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function overview_browser_tile(): array {
		$probes    = self::stored_probes();
		$latencies = array();
		$down_rows = 0;

		foreach ( $probes as $row ) {
			if ( 'ok' === $row['result'] && null !== $row['latency_ms'] && $row['latency_ms'] > 0 ) {
				$latencies[] = $row['latency_ms'];
			} elseif ( 'down' === $row['result'] ) {
				++$down_rows;
			}
		}

		$base = array(
			'label'       => __( '浏览器直测', 'wp-china-yes' ),
			'attrs'       => array( 'data-wpcy-tile' => 'browser' ),
			'value_attrs' => array( 'data-wpcy-tile-val' => 'browser' ),
			'sub_attrs'   => array( 'data-wpcy-tile-sub' => 'browser' ),
		);

		if ( array() !== $probes && count( $probes ) === $down_rows ) {
			return array_merge(
				$base,
				array(
					'value' => __( '超时', 'wp-china-yes' ),
					'unit'  => '',
					'sub'   => __( '连接波动', 'wp-china-yes' ),
					'tone'  => 'warn',
				)
			);
		}

		if ( array() !== $latencies ) {
			$avg = (int) round( array_sum( $latencies ) / count( $latencies ) );
			return array_merge(
				$base,
				array(
					'value' => (string) $avg,
					'unit'  => 'ms',
					'sub'   => __( '访客侧可达', 'wp-china-yes' ),
					'tone'  => '',
				)
			);
		}

		return array_merge(
			$base,
			array(
				'value' => '—',
				'unit'  => '',
				'sub'   => __( '访客侧可达', 'wp-china-yes' ),
				'tone'  => '',
			)
		);
	}

	/**
	 * Build the 3 `Wenpai_Admin_UI::status_list` items for Diagnose (`当前浏览器连通性`).
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, string>>
	 */
	public static function diagnose_browser_items(): array {
		$probes = self::stored_probes();

		return array(
			self::diagnose_item( __( 'Cravatar 头像节点', 'wp-china-yes' ), 'cravatar.com', $probes ),
			self::diagnose_item( __( 'adminCDN 公共前端库', 'wp-china-yes' ), 'admincdn.com', $probes ),
			self::diagnose_item( __( 'Windfonts 中文字体源', 'wp-china-yes' ), 'windfonts.com', $probes ),
		);
	}

	/**
	 * Build a single Diagnose browser status row from cached probes.
	 *
	 * @param string                                                            $label  Row label.
	 * @param string                                                            $domain Root domain to match.
	 * @param list<array{target: string, result: string, latency_ms: int|null}> $probes Normalized probe rows.
	 * @return array<string, string>
	 */
	private static function diagnose_item( string $label, string $domain, array $probes ): array {
		$matched = self::match_domain_probe( $domain, $probes );
		if ( null === $matched ) {
			return array(
				'label' => $label,
				'value' => __( '等待检测', 'wp-china-yes' ),
			);
		}

		if ( 'ok' === $matched['result'] && null !== $matched['latency_ms'] && $matched['latency_ms'] > 0 ) {
			return array(
				'label'       => $label,
				'value'       => $matched['latency_ms'] . 'ms',
				'state'       => 'ok',
				'state_label' => __( '已连接', 'wp-china-yes' ),
			);
		}

		if ( 'down' === $matched['result'] ) {
			return array(
				'label'       => $label,
				'value'       => __( '连接超时', 'wp-china-yes' ),
				'state'       => 'bad',
				'state_label' => __( '未连接', 'wp-china-yes' ),
			);
		}

		return array(
			'label' => $label,
			'value' => __( '等待检测', 'wp-china-yes' ),
		);
	}

	/**
	 * Find the best cached probe row matching `$domain` or `*.$domain`.
	 *
	 * Prefers a healthy `ok` row with positive latency when multiple subdomains exist.
	 *
	 * @param string                                                            $domain Root domain.
	 * @param list<array{target: string, result: string, latency_ms: int|null}> $probes Normalized probe rows.
	 * @return array{target: string, result: string, latency_ms: int|null}|null
	 */
	private static function match_domain_probe( string $domain, array $probes ): ?array {
		$fallback = null;
		$suffix   = '.' . $domain;
		$suf_len  = strlen( $suffix );

		foreach ( $probes as $row ) {
			$target = $row['target'];
			$is_hit = $domain === $target
				|| ( strlen( $target ) > $suf_len && $suffix === substr( $target, -$suf_len ) );
			if ( ! $is_hit ) {
				continue;
			}
			if ( 'ok' === $row['result'] && null !== $row['latency_ms'] && $row['latency_ms'] > 0 ) {
				return $row;
			}
			if ( null === $fallback ) {
				$fallback = $row;
			}
		}

		return $fallback;
	}

	/**
	 * Read and normalize stored browser probe entries from WordPress options.
	 *
	 * @return list<array{target: string, result: string, latency_ms: int|null}>
	 */
	private static function stored_probes(): array {
		$raw = get_option( ClientProbeController::OPTION, array() );
		if ( ! is_array( $raw ) || ! isset( $raw['probes'] ) || ! is_array( $raw['probes'] ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw['probes'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$target = isset( $row['target'] ) && is_string( $row['target'] ) ? strtolower( trim( $row['target'] ) ) : '';
			$result = isset( $row['result'] ) && is_string( $row['result'] ) ? $row['result'] : '';
			if ( '' === $target || ! in_array( $result, array( 'ok', 'down' ), true ) ) {
				continue;
			}

			$latency = null;
			if ( isset( $row['latency_ms'] ) && is_numeric( $row['latency_ms'] ) ) {
				$ms = (int) $row['latency_ms'];
				if ( $ms > 0 ) {
					$latency = $ms;
				}
			}

			$out[] = array(
				'target'     => $target,
				'result'     => $result,
				'latency_ms' => $latency,
			);
		}

		return $out;
	}
}
