<?php
/**
 * WenPai Bridge — 站点唯一标识管理
 *
 * 纯 PHP 独立模块，不依赖任何框架。
 * 仅使用 WordPress 原生 API。
 *
 * @package WenPai\Bridge
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * 站点 UUID 生成与管理。
 *
 * UUID v4 格式，首次调用时生成并持久化到 wp_options。
 * 多站点环境下使用 get_option（非 get_site_option），确保每个子站点独立 UUID。
 */
class WenPai_Bridge_Site_Identity {

	/** @var string Option key */
	const OPTION_KEY = 'wpcy_site_uuid';

	/**
	 * 获取或生成站点唯一标识。
	 *
	 * 使用 add_option 避免并发竞态条件：
	 * add_option 在 key 已存在时返回 false，不会覆盖。
	 *
	 * @return string UUID v4 字符串
	 */
	public static function get_uuid(): string {
		$uuid = get_option( self::OPTION_KEY );
		if ( ! $uuid ) {
			$uuid = wp_generate_uuid4();
			if ( ! add_option( self::OPTION_KEY, $uuid, '', 'yes' ) ) {
				// 其他进程已创建，重新读取
				$uuid = get_option( self::OPTION_KEY );
			}
		}
		return $uuid;
	}

	/**
	 * 重置站点 UUID（用户主动操作）。
	 *
	 * @return string 新生成的 UUID
	 */
	public static function reset_uuid(): string {
		$uuid = wp_generate_uuid4();
		update_option( self::OPTION_KEY, $uuid );
		return $uuid;
	}
}
