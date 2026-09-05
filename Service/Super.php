<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use function WenPai\ChinaYes\get_settings;

class Super {

	private $settings;

	public function __construct() {
		$this->settings = get_settings();

		if ( is_admin() || wp_doing_cron() ) {
			if ( $this->settings['store'] != 'off' ) {
				add_filter( 'pre_http_request', [ $this, 'filter_wordpress_org' ], 100, 3 );
			}
		}

		if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			if ( ! empty( $this->settings['notice_block'] ) && $this->settings['notice_block'] == 'on' ) {
				add_action( 'admin_head', [ $this, 'load_notice_management' ] );
			}
		}

		if ( ! empty( $this->settings['plane'] ) && $this->settings['plane'] == 'on' ) {
			$this->load_plane();
		}
	}

	public function load_notice_management() {
		echo '<style>
		.notice, .update-nag, .updated, .error, .is-dismissible {
			display: none !important;
		}
		</style>';
	}

	public function load_plane() {
		$domains = [];

		foreach ( (array) $this->settings['plane_rule'] as $rule ) {
			$raw_domains = $rule['domain'] ?? ( $rule['url'] ?? '' );
			if ( empty( $rule['enable'] ) || empty( $raw_domains ) ) {
				continue;
			}

			foreach ( preg_split( '/[\r\n,]+/', (string) $raw_domains ) as $entry ) {
				$entry = trim( strtolower( $entry ) );
				if ( '' === $entry ) {
					continue;
				}

				$domain = wp_parse_url(
					false === strpos( $entry, '://' ) ? 'https://' . $entry : $entry,
					PHP_URL_HOST
				);
				if ( $domain ) {
					$domains[] = rtrim( strtolower( $domain ), '.' );
				}
			}
		}

		$domains = array_values( array_unique( $domains ) );
		if ( empty( $domains ) ) {
			return;
		}

		add_filter( 'pre_http_request', function ( $preempt, $parsed_args, $url ) use ( $domains ) {
			$host = rtrim( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ), '.' );
			foreach ( $domains as $domain ) {
				$is_subdomain = strlen( $host ) > strlen( $domain )
					&& substr( $host, -strlen( $domain ) - 1 ) === '.' . $domain;
				if ( $host === $domain || $is_subdomain ) {
					return new WP_Error( 'http_request_failed', 'Blocked by plane mode' );
				}
			}
			return $preempt;
		}, 10, 3 );
	}

	/** @var string 镜像可用性 transient key */
	const MIRROR_STATE_KEY = 'wpcy_wporg_mirror_state';

	/** @var int 判定可用后的缓存时长（秒） */
	const MIRROR_UP_TTL = HOUR_IN_SECONDS;

	/** @var int 判定不可用后的缓存时长（秒）。取短一些，镜像修好后能较快自动恢复加速。 */
	const MIRROR_DOWN_TTL = 600;

	/** @var int 探测超时（秒） */
	const MIRROR_PROBE_TIMEOUT = 3;

	/**
	 * 元数据镜像源。只出 API，不提供安装包。
	 *
	 * 对应上游 `api.wordpress.org`。
	 */
	const MIRROR_API_ORIGIN = 'https://api.wenpai.net';

	/**
	 * 安装包镜像源。与元数据源是**两台独立主机**，不可互换。
	 *
	 * 对应上游 `downloads.wordpress.org`。把包请求发到元数据源会得到
	 * WP REST 的 JSON 404，而不是安装包。
	 */
	const MIRROR_PACKAGE_ORIGIN = 'https://downloads.wenpai.net';

	/**
	 * 镜像探测用的规范路径。
	 *
	 * 必须用**真实安装包路径**，不能用根路径或 API 路径 ——
	 * 镜像的 API 半边可能正常（`plugins/info` 返回 200）而包下载半边是 404，
	 * 用 API 路径探测会把坏的判成好的。
	 *
	 * 探测目标主机是 `MIRROR_PACKAGE_ORIGIN`：这条路径只在安装包主机上存在，
	 * 打到元数据主机上是永久 404，会把可用的镜像永久判成不可用。
	 */
	const MIRROR_PROBE_PATH = '/plugin/classic-editor.zip';

	/**
	 * 安装包的最小合理体积（字节）。
	 *
	 * 有镜像会以 `200` + 正确内容类型返回一个**十几字节的空壳**
	 * （实例：`lib.baomitu.com` 返回 200 但 size 只有 14 字节）。
	 * 只看状态码和内容类型仍会把这种上游判成健康，所以必须验体积。
	 */
	const MIRROR_MIN_PACKAGE_BYTES = 1024;

	public function filter_wordpress_org( $preempt, $parsed_args, $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		
		if ( ! in_array( $host, [ 'api.wordpress.org', 'downloads.wordpress.org' ] ) ) {
			return $preempt;
		}

		// 镜像不可用时**不改写**，让请求照原样走 WordPress.org。
		//
		// 这个替换默认开启（store 默认为 wenpai），一旦镜像不可用，
		// 站点的插件/主题搜索、信息查询、安装、更新下载会全链路失效 ——
		// 把可用的上游换成不可用的镜像，比不加速糟得多。
		if ( ! self::is_mirror_usable() ) {
			return $preempt;
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$query = wp_parse_url( $url, PHP_URL_QUERY );

		// 元数据与安装包在拓扑上是**两台独立主机**，不能合并到一个域名：
		// `api.wenpai.net` 只出 API（元数据），安装包/语言包由 `downloads.wenpai.net` 出。
		// 3.9 的重构把两者都写成 `api.wenpai.net`，导致所有 `package` 链接
		// 落到不提供安装包的主机上，返回 WP REST 的 JSON 404 —— 插件/主题
		// 装不上也更新不了。此处按来源主机分别映射，恢复 3.8.x 的正确拓扑。
		$mirror_url = ( 'downloads.wordpress.org' === $host )
			? self::MIRROR_PACKAGE_ORIGIN . $path
			: self::MIRROR_API_ORIGIN . $path;

		if ( $query ) {
			$mirror_url .= '?' . $query;
		}

		$parsed_args['timeout'] = 30;
		return wp_remote_request( $mirror_url, $parsed_args );
	}

	/**
	 * 镜像当前是否真的能提供安装包。
	 *
	 * 状态缓存在 transient 里；未知时**同步探测一次**而不是先放行 ——
	 * 镜像不可用期间"先放行"等于继续让用户装不上插件，
	 * 一次 3 秒探测换取判断正确，是值得的。
	 *
	 * TODO 待 Service/MirrorHealth.php（镜像健康检测）合入后，
	 *      本方法可收敛为调用它，避免两处探测逻辑。
	 */
	public static function is_mirror_usable(): bool {
		$state = get_transient( self::MIRROR_STATE_KEY );

		if ( 'up' === $state ) {
			return true;
		}

		if ( 'down' === $state ) {
			return false;
		}

		$usable = self::probe_mirror();

		set_transient(
			self::MIRROR_STATE_KEY,
			$usable ? 'up' : 'down',
			$usable ? self::MIRROR_UP_TTL : self::MIRROR_DOWN_TTL
		);

		return $usable;
	}

	/**
	 * 探测镜像的安装包能力。
	 *
	 * 判据是"状态码 + 内容类型"两者都要对：镜像坏掉时会以 WP REST 的 404
	 * （`application/json`，正文 `{"code":"rest_no_route"}`）或主题化的
	 * HTML 404 应答，光看状态码或光看"有没有响应"都会误判。
	 */
	private static function probe_mirror(): bool {
		$response = wp_remote_get(
			self::MIRROR_PACKAGE_ORIGIN . self::MIRROR_PROBE_PATH,
			array(
				'timeout'     => self::MIRROR_PROBE_TIMEOUT,
				'redirection' => 2,
				// 只取首字节即可判断，不下载整个安装包
				'headers'     => array( 'Range' => 'bytes=0-0' ),
				// 标记自身请求，避免被本过滤器再次改写
				'_wp_china_yes' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 400 ) {
			return false;
		}

		$type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );

		// 安装包不应是 JSON 或 HTML。镜像故障时正是以这两种类型应答。
		if ( false !== strpos( $type, 'json' ) || false !== strpos( $type, 'text/html' ) ) {
			return false;
		}

		// 体积校验：有镜像会以 200 + 正确内容类型返回十几字节的空壳。
		$total = self::parse_total_bytes( $response );

		if ( null !== $total && $total < self::MIRROR_MIN_PACKAGE_BYTES ) {
			return false;
		}

		return true;
	}

	/**
	 * 从响应头取资源总体积。
	 *
	 * 探测带了 `Range: bytes=0-0`，所以正常情况下拿到的是 `206` +
	 * `content-range: bytes 0-0/87533` —— 总体积在斜杠之后，
	 * 而 `content-length` 此时是 1，不能直接用。
	 * 若上游忽略 Range 而返回 200，则退回读 `content-length`。
	 *
	 * @return int|null 取不到时返回 null（此时不因体积判不可用，避免过度拒绝）
	 */
	private static function parse_total_bytes( $response ): ?int {
		$range = (string) wp_remote_retrieve_header( $response, 'content-range' );

		if ( '' !== $range && false !== strpos( $range, '/' ) ) {
			$total = substr( $range, strpos( $range, '/' ) + 1 );

			if ( is_numeric( $total ) ) {
				return (int) $total;
			}
		}

		$length = wp_remote_retrieve_header( $response, 'content-length' );

		if ( '' !== $length && is_numeric( $length ) ) {
			return (int) $length;
		}

		return null;
	}

	public function set_user_profile_picture_for_cravatar( $description ) {
		return str_replace( 'Gravatar', 'Cravatar', $description );
	}

	public function set_defaults_for_cravatar( $avatar_defaults ) {
		$avatar_defaults['cravatar'] = 'Cravatar Logo (Generated)';
		return $avatar_defaults;
	}

	public function get_cravatar_url( $url ) {
		$sources = [
			'www.gravatar.com'        => 'cn.cravatar.com',
			'0.gravatar.com'          => 'cn.cravatar.com',
			'1.gravatar.com'          => 'cn.cravatar.com',
			'2.gravatar.com'          => 'cn.cravatar.com',
			'secure.gravatar.com'     => 'cn.cravatar.com',
			'cn.gravatar.com'         => 'cn.cravatar.com',
			'gravatar.com'            => 'cn.cravatar.com',
		];

		if ( $this->settings['cravatar'] == 'global' ) {
			$sources = [
				'www.gravatar.com'        => 'www.gravatar.com',
				'0.gravatar.com'          => 'www.gravatar.com',
				'1.gravatar.com'          => 'www.gravatar.com',
				'2.gravatar.com'          => 'www.gravatar.com',
				'secure.gravatar.com'     => 'www.gravatar.com',
				'cn.gravatar.com'         => 'www.gravatar.com',
				'gravatar.com'            => 'www.gravatar.com',
			];
		}

		return str_replace( array_keys( $sources ), array_values( $sources ), $url );
	}

	public function page_str_replace( $hook, $function, $args ) {
		add_action( $hook, function () use ( $function, $args ) {
			ob_start( function ( $buffer ) use ( $function, $args ) {
				return call_user_func_array( $function, array_merge( [ $args[0], $args[1], $buffer ] ) );
			} );
		}, 1 );

		add_action( 'wp_footer', function () {
			if ( ob_get_level() ) {
				ob_end_flush();
			}
		}, 999 );
	}
}
