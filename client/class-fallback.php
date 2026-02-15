<?php
/**
 * WenPai Bridge — 多级降级策略
 *
 * 当文派云桥不可用时,自动降级到 WordPress.org 原始源。
 * 降级梯度:Bridge → WordPress.org → 本地缓存
 *
 * @package WenPai\Bridge
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * 更新源降级管理器。
 *
 * 拦截 WordPress HTTP API 错误,当 Bridge 连续失败时自动降级。
 * 使用 transient 记录失败次数和降级状态。
 */
class WenPai_Bridge_Fallback {

	/** @var int 触发降级的连续失败次数 */
	const FAIL_THRESHOLD = 3;

	/** @var int 降级持续时间(秒),30 分钟后重试 Bridge */
	const FALLBACK_DURATION = 1800;

	/** @var string 失败计数 transient key */
	const FAIL_COUNT_KEY = 'wpcy_bridge_fail_count';

	/** @var string 降级状态 transient key */
	const FALLBACK_KEY = 'wpcy_bridge_fallback_active';

	/** @var string 本地缓存 transient key 前缀 */
	const CACHE_PREFIX = 'wpcy_update_cache_';

	/** @var string Bridge 主机名 */
	const BRIDGE_HOST = 'updates.wenpai.net';

	/**
	 * 初始化降级模块。
	 */
	public static function init(): void {
		// 在 HTTP 请求前检查是否需要降级
		add_filter( 'pre_http_request', [ __CLASS__, 'maybe_bypass_bridge' ], 5, 3 );

		// 在 HTTP 响应后记录结果
		add_filter( 'http_response', [ __CLASS__, 'record_result' ], 10, 3 );
	}

	/**
	 * 请求前检查:如果处于降级状态,将 Bridge 请求重定向到 WordPress.org。
	 *
	 * @param false|array|\WP_Error $preempt 预处理结果
	 * @param array                 $args    请求参数
	 * @param string                $url     请求 URL
	 * @return false|array|\WP_Error
	 */
	public static function maybe_bypass_bridge( $preempt, array $args, string $url ) {
		// 只处理发往 Bridge 的更新检查请求
		if ( ! self::is_bridge_update_request( $url ) ) {
			return $preempt;
		}

		// 未处于降级状态,正常请求 Bridge
		if ( ! get_transient( self::FALLBACK_KEY ) ) {
			// 设置较短的 timeout(5秒快速失败)
			add_filter( 'http_request_timeout', [ __CLASS__, 'short_timeout' ] );
			return $preempt;
		}

		// 处于降级状态:尝试返回本地缓存
		$cache_key = self::CACHE_PREFIX . md5( $url );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[wpcy-fallback] 使用本地缓存: ' . $url );
			}
			return $cached;
		}

		// 无缓存:让请求正常发出(WordPress 会走默认源)
		// 不拦截,返回 false 让 WordPress 自行处理
		return false;
	}

	/**
	 * 响应后记录:跟踪 Bridge 请求的成功/失败。
	 *
	 * @param array  $response HTTP 响应
	 * @param array  $args     请求参数
	 * @param string $url      请求 URL
	 * @return array 原样返回响应
	 */
	public static function record_result( $response, array $args, string $url ): array {
		if ( ! self::is_bridge_update_request( $url ) ) {
			return $response;
		}

		remove_filter( 'http_request_timeout', [ __CLASS__, 'short_timeout' ] );

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 400 ) {
			// 成功:重置失败计数,缓存响应
			delete_transient( self::FAIL_COUNT_KEY );
			delete_transient( self::FALLBACK_KEY );

			// 缓存成功响应(24小时)
			$cache_key = self::CACHE_PREFIX . md5( $url );
			set_transient( $cache_key, $response, DAY_IN_SECONDS );
		} else {
			// 失败:递增计数
			self::record_failure();
		}

		return $response;
	}

	/**
	 * 记录一次失败,达到阈值时激活降级。
	 */
	private static function record_failure(): void {
		$count = (int) get_transient( self::FAIL_COUNT_KEY );
		$count++;
		set_transient( self::FAIL_COUNT_KEY, $count, HOUR_IN_SECONDS );

		if ( $count >= self::FAIL_THRESHOLD ) {
			set_transient( self::FALLBACK_KEY, 1, self::FALLBACK_DURATION );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[wpcy-fallback] Bridge 连续失败 %d 次,降级 %d 分钟',
					$count,
					self::FALLBACK_DURATION / 60
				) );
			}
		}
	}

	/**
	 * 判断是否为发往 Bridge 的更新检查请求。
	 *
	 * @param string $url 请求 URL
	 * @return bool
	 */
	private static function is_bridge_update_request( string $url ): bool {
		$parsed = wp_parse_url( $url );
		return isset( $parsed['host'] ) && self::BRIDGE_HOST === $parsed['host']
			&& isset( $parsed['path'] ) && str_contains( $parsed['path'], '/api/v1/update-check' );
	}

	/**
	 * 短超时过滤器(5秒快速失败)。
	 *
	 * @return int 超时秒数
	 */
	public static function short_timeout(): int {
		return 5;
	}
}
