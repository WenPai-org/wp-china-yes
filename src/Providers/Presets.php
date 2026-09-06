<?php
/**
 * Frozen preset providers. Users cannot add vendors or change api_url.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two ids only: weixiaoduo-mall (available) and wenpai-marketplace (coming_soon).
 */
final class Presets {

	/**
	 * Weixiaoduo mall id.
	 *
	 * @since 4.0.0
	 */
	public const WEIXIAODUO_MALL = 'weixiaoduo-mall';

	/**
	 * WenPai marketplace id (placeholder).
	 *
	 * @since 4.0.0
	 */
	public const WENPAI_MARKETPLACE = 'wenpai-marketplace';

	/**
	 * Preset ids in display order.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public static function ids(): array {
		return array(
			self::WEIXIAODUO_MALL,
			self::WENPAI_MARKETPLACE,
		);
	}

	/**
	 * One preset, or null when $id is not in the enum.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 * @return array{id: string, name: string, status: string, api_url: string}|null
	 */
	public static function get( string $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Preset table. Names go through __().
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, array{id: string, name: string, status: string, api_url: string}>
	 */
	public static function all(): array {
		return array(
			self::WEIXIAODUO_MALL    => array(
				'id'      => self::WEIXIAODUO_MALL,
				'name'    => __( '薇晓朵商城', 'wp-china-yes' ),
				'status'  => 'available',
				'api_url' => 'https://mall.weixiaoduo.com',
			),
			self::WENPAI_MARKETPLACE => array(
				'id'      => self::WENPAI_MARKETPLACE,
				'name'    => __( '文派集市', 'wp-china-yes' ),
				'status'  => 'coming_soon',
				'api_url' => '',
			),
		);
	}
}
