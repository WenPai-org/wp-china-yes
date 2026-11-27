<?php
/**
 * Admin status card for signed mirror manifest health and manual refresh (W7).
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Connectivity\PublicAssets\Manifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the signed mirror manifest diagnostics card via Wenpai_Admin_UI.
 */
final class ManifestStatusCard {

	/**
	 * Build normalized card items from a Manifest status snapshot.
	 *
	 * @since 4.1.0
	 *
	 * @param array<string, mixed> $snap Manifest status snapshot.
	 * @return list<array<string, mixed>>
	 */
	public static function items_from_snapshot( array $snap ): array {
		$has      = ! empty( $snap['has_manifest'] );
		$source   = (string) ( $snap['active_source'] ?? 'static_fallback' );
		$version  = (int) ( $snap['version'] ?? 0 );
		$issued   = (string) ( $snap['issued_at'] ?? '' );
		$key_id   = (string) ( $snap['key_id'] ?? '' );
		$age      = isset( $snap['age_seconds'] ) ? (int) $snap['age_seconds'] : null;
		$stale    = ! empty( $snap['stale_lkg'] );
		$ks       = ! empty( $snap['kill_switch'] );
		$reason   = (string) ( $snap['switch_reason'] ?? '' );
		$last_err = (string) ( $snap['last_error'] ?? '' );

		$source_label = self::source_label( $source, $has );
		$age_label    = self::format_age( $age, $stale );

		$items = array(
			array(
				'label' => __( '当前调度来源', 'wp-china-yes' ),
				'value' => $source_label,
				'attrs' => array( 'data-wpcy-manifest-field' => 'active_source' ),
			),
			array(
				'label' => __( '最近同步版本', 'wp-china-yes' ),
				'value' => $has ? ( '#' . $version . ' · ' . self::format_issued_at( $issued ) ) : __( '尚未同步（使用内置默认映射）', 'wp-china-yes' ),
				'attrs' => array( 'data-wpcy-manifest-field' => 'version' ),
			),
			array(
				'label' => __( '缓存更新时间', 'wp-china-yes' ),
				'value' => $age_label,
				'attrs' => array( 'data-wpcy-manifest-field' => 'age' ),
			),
			array(
				'label' => __( '安全校验密钥', 'wp-china-yes' ),
				'value' => '' !== $key_id ? $key_id : '-',
				'attrs' => array( 'data-wpcy-manifest-field' => 'key_id' ),
			),
			array(
				'label' => __( '紧急回源状态', 'wp-china-yes' ),
				'value' => $ks ? __( '已暂停加速（直连官方源）', 'wp-china-yes' ) : __( '正常加速调度', 'wp-china-yes' ),
				'attrs' => array( 'data-wpcy-manifest-field' => 'kill_switch' ),
			),
		);

		if ( '' !== $reason || '' !== $last_err ) {
			$items[] = array(
				'label' => __( '最近切换或校验原因', 'wp-china-yes' ),
				'value' => '' !== $last_err ? ( $last_err . ( '' !== $reason ? ' · ' . $reason : '' ) ) : $reason,
				'attrs' => array( 'data-wpcy-manifest-field' => 'switch_reason' ),
			);
		}

		return $items;
	}

	/**
	 * Render the signed manifest card using Wenpai_Admin_UI.
	 *
	 * @since 4.1.0
	 *
	 * @param Manifest|null $manifest             Optional manifest client override.
	 * @param bool|null     $client_probe_enabled Optional diagnostics.client_probe_enabled override.
	 */
	public static function render( ?Manifest $manifest = null, ?bool $client_probe_enabled = null ): void {
		if ( ! class_exists( '\Wenpai_Admin_UI', false ) ) {
			return;
		}

		$client   = null !== $manifest ? $manifest : new Manifest();
		$snap     = $client->status_snapshot();
		$items    = self::items_from_snapshot( $snap );
		$probe_on = null !== $client_probe_enabled
			? $client_probe_enabled
			: (bool) ( new Repository() )->get( 'diagnostics.client_probe_enabled', true );
		$disabled = function_exists( 'is_multisite' ) && is_multisite() && ! ( function_exists( 'is_network_admin' ) && is_network_admin() );

		$toggle_html = '<label class="wenpai-switch">'
			. '<input type="checkbox" class="wenpai-switch__input" name="diagnostics_client_probe_enabled" value="1" data-wpcy-action="client-probe-toggle"'
			. ( $probe_on ? ' checked="checked"' : '' )
			. ( $disabled ? ' disabled="disabled"' : '' )
			. '>'
			. '<span class="wenpai-switch__track" aria-hidden="true"></span>'
			. '<span class="wenpai-switch__label">' . esc_html( $probe_on ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' ) ) . '</span>'
			. '</label>';

		\Wenpai_Admin_UI::card_open(
			array(
				'title' => __( '镜像调度与安全清单', 'wp-china-yes' ),
				'tile'  => false,
				'attrs' => array( 'data-wpcy-manifest-card' => '1' ),
			)
		);
		\Wenpai_Admin_UI::status_list(
			array(
				'attrs' => array( 'data-wpcy-manifest-list' => '1' ),
				'items' => $items,
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'refresh',
				'state'        => ! empty( $snap['kill_switch'] ) ? 'bad' : ( ! empty( $snap['has_manifest'] ) ? 'ok' : '' ),
				'title'        => __( '立即同步安全清单', 'wp-china-yes' ),
				'desc'         => __( '优先从主渠道获取并校验数字签名，异常时自动切换备用源或保留 7 天内本地缓存。', 'wp-china-yes' ),
				'control_html' => \Wenpai_Admin_UI::button_html(
					array(
						'label'   => __( '立即刷新清单', 'wp-china-yes' ),
						'variant' => 'secondary',
						'size'    => 'sm',
						'attrs'   => array( 'data-wpcy-action' => 'manifest-refresh' ),
					)
				),
			)
		);
		\Wenpai_Admin_UI::row(
			array(
				'icon'         => 'eye',
				'state'        => $probe_on ? 'ok' : '',
				'title'        => __( '浏览器连通性监测', 'wp-china-yes' ),
				'desc'         => __( '帮助文派判断镜像可用性，仅记录页面资源加载耗时与地区运营商摘要，可随时一键关闭。', 'wp-china-yes' ),
				'control_html' => $toggle_html,
			)
		);
		\Wenpai_Admin_UI::card_close();
	}

	/**
	 * Render status card HTML to a string (useful in unit tests).
	 *
	 * @param Manifest|null $manifest             Optional manifest override.
	 * @param bool|null     $client_probe_enabled Optional diagnostics.client_probe_enabled override.
	 */
	public static function render_html( ?Manifest $manifest = null, ?bool $client_probe_enabled = null ): string {
		ob_start();
		self::render( $manifest, $client_probe_enabled );
		return (string) ob_get_clean();
	}

	/**
	 * Format source label without forbidden terminology.
	 *
	 * @param string $source Source key.
	 * @param bool   $has    Whether manifest cache exists.
	 */
	private static function source_label( string $source, bool $has ): string {
		if ( ! $has ) {
			return __( '内置默认映射', 'wp-china-yes' );
		}
		if ( 'primary' === $source ) {
			return __( '主渠道（updates.wenpai.net）', 'wp-china-yes' );
		}
		if ( 'secondary' === $source ) {
			return __( '备用渠道（对象存储镜像）', 'wp-china-yes' );
		}
		return __( '本地最近有效缓存', 'wp-china-yes' );
	}

	/**
	 * Format ISO-8601 UTC timestamp to site-local readable datetime.
	 *
	 * @param string $issued Raw ISO-8601 timestamp.
	 */
	private static function format_issued_at( string $issued ): string {
		if ( '' === $issued ) {
			return '-';
		}
		$ts = strtotime( $issued );
		if ( false === $ts ) {
			return $issued;
		}
		if ( function_exists( 'wp_date' ) ) {
			return (string) wp_date( 'Y-m-d H:i', $ts );
		}
		return gmdate( 'Y-m-d H:i', $ts );
	}

	/**
	 * Format cache age cleanly.
	 *
	 * @param int|null $age   Age in seconds, or null.
	 * @param bool     $stale Whether age exceeds 7 days.
	 */
	private static function format_age( ?int $age, bool $stale ): string {
		if ( null === $age ) {
			return '-';
		}
		if ( $stale ) {
			return sprintf(
				/* translators: %d: hours elapsed since cache storage */
				__( '已超过 7 天安全期（%d 小时前），已回退默认映射', 'wp-china-yes' ),
				(int) floor( $age / 3600 )
			);
		}
		if ( $age < 60 ) {
			return __( '刚刚更新', 'wp-china-yes' );
		}
		if ( $age < 3600 ) {
			return sprintf(
				/* translators: %d: minutes elapsed */
				__( '%d 分钟前', 'wp-china-yes' ),
				(int) floor( $age / 60 )
			);
		}
		return sprintf(
			/* translators: %d: hours elapsed */
			__( '%d 小时前', 'wp-china-yes' ),
			(int) floor( $age / 3600 )
		);
	}
}
