<?php

namespace WenPai\ChinaYes\Service;

defined('ABSPATH') || exit;

/**
 * 镜像端点健康检测 —— 加速不可用时不要把站点资源一起带死。
 *
 * 背景：加速替换原先是无条件的字符串/正则替换，一旦镜像端点故障，
 * 插件就会把本来可用的公共 CDN 链接换成已失效的镜像地址，
 * 站点前端 JS/CSS/字体大面积 404 —— 这个方向的失败比不加速更糟。
 *
 * 设计取舍：
 * - 资源是【浏览器】加载的，WP 的 http_response 钩子看不到，
 *   所以不能照 WenPai_Bridge_Fallback 那样被动观测，必须主动探测。
 * - 探测只在后台请求触发并全局限流，前台请求路径上零 HTTP 开销，
 *   只读一次 transient。
 * - 状态未知时【视为健康】：绝大多数时间镜像是好的，未知即拦会让
 *   新装站点在首次探测前完全失去加速。
 *
 * @since 3.9.3
 */
class MirrorHealth {

	/** @var int 探测轮次的最小间隔（秒），全局限流 */
	const PROBE_INTERVAL = 900;

	/** @var int 判定不可用后的维持时长（秒），到期重新探测 */
	const DOWN_DURATION = 1800;

	/** @var int 判定可用后的缓存时长（秒） */
	const UP_DURATION = 3600;

	/** @var int 单次探测超时（秒），不能拖慢后台 */
	const TIMEOUT = 3;

	/** @var string 状态 transient key 前缀 */
	const STATE_PREFIX = 'wpcy_mirror_state_';

	/** @var string 探测轮次限流 transient key */
	const LOCK_KEY = 'wpcy_mirror_probe_lock';

	/**
	 * 各镜像端点的规范探测路径。
	 *
	 * 必须用【插件实际生成的 URL 形态】，不能用根路径、也不能用上游的路径约定：
	 * - 反代型端点的根路径本来就可能 404 或 302，用它探测会把坏的判成好的
	 *   （public.admincdn.com 根路径 302 但真实资源路径 403，正是这种情况）
	 * - 各端点的路径前缀不一定与上游一致（见 cdnjs 的注释），
	 *   用上游约定去探测会得到与实际使用不同的结论
	 *
	 * @return array<string,string> host => path
	 */
	public static function probe_targets(): array {
		$wp_version = isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '6.8';

		return apply_filters( 'wp_china_yes_mirror_probe_targets', [
			// cdnjs：替换规则是 cdnjs.cloudflare.com/ajax/libs => cdnjs.admincdn.com，
			// 前缀 /ajax/libs 被吃掉了，所以本端点的实际路径【不带】该前缀。
			'cdnjs.admincdn.com'       => '/jquery/3.7.1/jquery.min.js',
			'jsd.admincdn.com'         => '/npm/jquery@3.7.1/dist/jquery.min.js',
			// googleajax：替换 ajax.googleapis.com，其路径本身就是 /ajax/libs/...
			'googleajax.admincdn.com'  => '/ajax/libs/jquery/3.7.1/jquery.min.js',
			'googlefonts.admincdn.com' => '/css2?family=Roboto:wght@400',
			// wpstatic：替换形态是 /{wp_version}/wp-admin|wp-includes/css|js/...
			'wpstatic.admincdn.com'    => '/' . $wp_version . '/wp-admin/css/common.min.css',
			// ts：替换 ts.w.org，形态为 /wp-content/themes/{slug}/screenshot.png
			'ts.wenpai.net'            => '/wp-content/themes/twentytwentyfour/screenshot.png',
		] );
	}

	/**
	 * 适配 Service\Base 的 new $class_name() 注册模式。
	 */
	public function __construct() {
		self::init();
	}

	/**
	 * 注册钩子。探测只挂后台，前台不承担任何探测开销。
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', [ __CLASS__, 'maybe_probe' ], 99 );
		add_action( 'admin_notices', [ __CLASS__, 'render_notice' ] );
	}

	/**
	 * 镜像不可用时提示站长：加速已自动回退。
	 *
	 * 没有这条提示，站长无从知晓加速已静默关闭 —— 静默回退保住了站点，
	 * 但也会掩盖故障，所以必须让人看得见。
	 */
	public static function render_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$down = self::unhealthy_hosts();

		if ( empty( $down ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p><code>%s</code></p></div>',
			esc_html__( '萌芽加速：部分镜像端点当前不可用，相关替换已自动回退到原始来源。', 'wp_china_yes' ),
			esc_html__( '站点资源不受影响，无需操作；端点恢复后会自动重新启用。', 'wp_china_yes' ),
			esc_html( implode( ', ', $down ) )
		);
	}

	/**
	 * 该镜像主机当前是否可用。
	 *
	 * @param string $host 主机名，例如 jsd.admincdn.com
	 * @return bool 未知视为可用，见类注释的设计取舍
	 */
	public static function is_healthy( string $host ): bool {
		if ( '' === $host ) {
			return true;
		}

		$state = get_transient( self::STATE_PREFIX . md5( $host ) );

		return 'down' !== $state;
	}

	/**
	 * 从替换目标里取出主机名。
	 *
	 * 替换表里的目标可能带路径（如 jsd.admincdn.com/npm/react），
	 * 也可能不带协议，所以不能直接用 parse_url。
	 *
	 * @param string $target 替换目标
	 * @return string 主机名，取不到则返回空串
	 */
	public static function host_of( string $target ): string {
		$target = preg_replace( '#^[a-z]+://#i', '', trim( $target ) );
		$host   = strtok( (string) $target, '/' );

		return ( false === $host ) ? '' : $host;
	}

	/**
	 * 限流后的探测轮次。
	 */
	public static function maybe_probe(): void {
		if ( get_transient( self::LOCK_KEY ) ) {
			return;
		}

		// 先占锁再探测，避免并发后台请求同时打一轮
		set_transient( self::LOCK_KEY, 1, self::PROBE_INTERVAL );

		foreach ( self::probe_targets() as $host => $path ) {
			$key = self::STATE_PREFIX . md5( $host );

			// 已判可用且未过期的跳过，减少无谓请求
			if ( 'up' === get_transient( $key ) ) {
				continue;
			}

			if ( self::probe( $host, $path ) ) {
				set_transient( $key, 'up', self::UP_DURATION );
			} else {
				set_transient( $key, 'down', self::DOWN_DURATION );
			}
		}
	}

	/**
	 * 探测单个端点。
	 *
	 * @param string $host 主机名
	 * @param string $path 规范探测路径
	 * @return bool
	 */
	private static function probe( string $host, string $path ): bool {
		$response = wp_remote_get( 'https://' . $host . $path, [
			'timeout'     => self::TIMEOUT,
			'redirection' => 2,
			'sslverify'   => true,
			// 只要响应头即可，省流量
			'headers'     => [ 'Range' => 'bytes=0-0' ],
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// 2xx/3xx 视为可用；206 是 Range 请求的正常返回
		return $code >= 200 && $code < 400;
	}

	/**
	 * 当前处于不可用状态的镜像清单，供后台提示使用。
	 *
	 * @return string[]
	 */
	public static function unhealthy_hosts(): array {
		$down = [];

		foreach ( array_keys( self::probe_targets() ) as $host ) {
			if ( ! self::is_healthy( $host ) ) {
				$down[] = $host;
			}
		}

		return $down;
	}
}
