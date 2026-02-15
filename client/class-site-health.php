<?php
/**
 * WenPai Bridge — 站点健康检查模块
 *
 * 纯 PHP 独立模块，不依赖任何框架。
 * 仅使用 WordPress 原生 API（wp_remote_post, get_option, wp_schedule_single_event）。
 *
 * @package WenPai\Bridge
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WenPai_Bridge_Site_Identity' ) ) {
	require_once __DIR__ . '/class-site-identity.php';
}

/**
 * 每日站点健康检查上报。
 *
 * 通过 WP-Cron 每天上报一次站点环境信息到文派云桥。
 * 包含雪崩防护（0-6 小时随机延迟）和防重复机制。
 */
class WenPai_Bridge_Site_Health {

	/** @var string Cron hook 名称 */
	const CRON_HOOK = 'wpcy_daily_telemetry';

	/** @var string 端点 */
	const ENDPOINT = 'https://updates.wenpai.net/api/v1/telemetry';

	/** @var string 开关 option key */
	const ENABLED_KEY = 'wpcy_telemetry_enabled';

	/** @var string 站点 URL 上报开关 option key */
	const SITE_URL_KEY = 'wpcy_telemetry_send_site_url';

	/** @var string 数据格式版本 */
	const TELEMETRY_VERSION = '2.1';

	/** @var array 关键 PHP 扩展白名单 */
	const CRITICAL_PHP_EXTENSIONS = [
		'gd', 'imagick', 'curl', 'mbstring', 'xml',
		'zip', 'openssl', 'mysqli', 'pdo_mysql', 'intl',
		'json', 'sodium', 'exif', 'fileinfo', 'iconv',
	];

	/**
	 * 初始化模块。
	 */
	public static function init(): void {
		add_action( self::CRON_HOOK, [ __CLASS__, 'send_report' ] );

		if ( ! get_option( self::ENABLED_KEY, 1 ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$delay = wp_rand( 0, 6 * HOUR_IN_SECONDS );
			wp_schedule_single_event( time() + $delay, self::CRON_HOOK );
		}
	}

	/**
	 * 发送健康检查报告。
	 */
	public static function send_report(): void {
		if ( ! get_option( self::ENABLED_KEY, 1 ) ) {
			return;
		}

		$today     = gmdate( 'Y-m-d' );
		$last_sent = get_transient( 'wpcy_telemetry_last_sent' );
		if ( $last_sent === $today ) {
			self::schedule_next();
			return;
		}

		try {
			$report = self::collect_report();

			wp_remote_post( self::ENDPOINT, [
				'body'      => wp_json_encode( $report ),
				'headers'   => [ 'Content-Type' => 'application/json' ],
				'timeout'   => 10,
				'blocking'  => false,
				'sslverify' => true,
			] );

			set_transient( 'wpcy_telemetry_last_sent', $today, 36 * HOUR_IN_SECONDS );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[wpcy-health] Error: ' . $e->getMessage() );
			}
		}

		self::schedule_next();
	}

	/**
	 * 收集报告数据（v2.1 格式）。
	 *
	 * @return array 报告数组
	 */
	private static function collect_report(): array {
		$report = [
			'site_uuid'          => WenPai_Bridge_Site_Identity::get_uuid(),
			'site_url'           => get_option( self::SITE_URL_KEY, 1 ) ? home_url() : '',
			'wp_version'         => get_bloginfo( 'version' ),
			'php_version'        => PHP_VERSION,
			'mysql_version'      => self::get_mysql_version(),
			'is_multisite'       => is_multisite(),
			'active_theme'       => get_stylesheet(),
			'locale'             => get_locale(),
			'server_software'    => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ) : '',
			'wpcy_version'       => defined( 'CHINA_YES_VERSION' ) ? CHINA_YES_VERSION : 'unknown',
			'telemetry_version'  => self::TELEMETRY_VERSION,
			'plugins'            => self::get_plugin_list(),
			'platform'           => self::get_platform_info(),
			'themes'             => self::get_theme_list(),
			'translations'       => self::get_translations(),
		];

		// WooCommerce 数据（仅当 WC 激活时）
		$wc_data = self::get_woocommerce_data();
		if ( $wc_data !== null ) {
			$report['woocommerce'] = $wc_data;
		}

		return $report;
	}

	/**
	 * 获取 MySQL 版本。
	 *
	 * @return string MySQL/MariaDB 版本号
	 */
	private static function get_mysql_version(): string {
		global $wpdb;
		if ( ! $wpdb ) {
			return '';
		}
		$version = $wpdb->get_var( 'SELECT VERSION()' );
		return $version ? $version : '';
	}

	/**
	 * 获取已安装插件列表（含完整元数据）。
	 *
	 * @return array 插件信息数组
	 */
	private static function get_plugin_list(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );
		$network_active = is_multisite() ? array_keys( get_site_option( 'active_sitewide_plugins', [] ) ) : [];
		$list           = [];

		foreach ( $all_plugins as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}
			$list[] = [
				'slug'           => $slug,
				'version'        => isset( $data['Version'] ) ? $data['Version'] : '',
				'active'         => in_array( $file, $active_plugins, true ),
				'name'           => isset( $data['Name'] ) ? $data['Name'] : '',
				'author'         => isset( $data['Author'] ) ? wp_strip_all_tags( $data['Author'] ) : '',
				'requires_wp'    => isset( $data['RequiresWP'] ) ? $data['RequiresWP'] : '',
				'requires_php'   => isset( $data['RequiresPHP'] ) ? $data['RequiresPHP'] : '',
				'network_active' => in_array( $file, $network_active, true ),
			];
		}

		return $list;
	}

	/**
	 * 获取服务器平台信息。
	 *
	 * @return array 平台信息
	 */
	private static function get_platform_info(): array {
		return [
			'os'                     => PHP_OS,
			'bits'                   => PHP_INT_SIZE * 8,
			'php_memory_limit'       => ini_get( 'memory_limit' ) ?: '',
			'php_max_input_vars'     => (int) ini_get( 'max_input_vars' ),
			'php_post_max_size'      => ini_get( 'post_max_size' ) ?: '',
			'php_max_execution_time' => (int) ini_get( 'max_execution_time' ),
			'php_extensions'         => self::get_php_extensions(),
			'is_ssl'                 => is_ssl(),
			'image_support'          => self::get_image_support(),
			'myisam_tables'          => self::count_myisam_tables(),
			'users_count'            => self::get_users_count(),
			'blogs_count'            => is_multisite() ? (int) get_blog_count() : 1,
		];
	}

	/**
	 * 获取关键 PHP 扩展列表。
	 *
	 * @return array PHP 扩展信息 [{name, version}]
	 */
	private static function get_php_extensions(): array {
		$loaded = get_loaded_extensions();
		$result = [];

		foreach ( self::CRITICAL_PHP_EXTENSIONS as $ext ) {
			if ( in_array( $ext, $loaded, true ) ) {
				$ver = phpversion( $ext );
				$result[] = [
					'name'    => $ext,
					'version' => $ver !== false ? $ver : '',
				];
			}
		}

		return $result;
	}

	/**
	 * 获取图片格式支持信息。
	 *
	 * @return array 图片格式支持
	 */
	private static function get_image_support(): array {
		$support = [
			'webp' => false,
			'avif' => false,
			'heic' => false,
			'jxl'  => false,
		];

		if ( function_exists( 'gd_info' ) ) {
			$gd = gd_info();
			if ( ! empty( $gd['WebP Support'] ) ) {
				$support['webp'] = true;
			}
			if ( ! empty( $gd['AVIF Support'] ) ) {
				$support['avif'] = true;
			}
		}

		if ( class_exists( 'Imagick' ) ) {
			try {
				$formats = \Imagick::queryFormats();
				if ( in_array( 'WEBP', $formats, true ) ) {
					$support['webp'] = true;
				}
				if ( in_array( 'AVIF', $formats, true ) ) {
					$support['avif'] = true;
				}
				if ( in_array( 'HEIC', $formats, true ) ) {
					$support['heic'] = true;
				}
				if ( in_array( 'JXL', $formats, true ) ) {
					$support['jxl'] = true;
				}
			} catch ( \Throwable $e ) {
				// Imagick 不可用
			}
		}

		return $support;
	}

	/**
	 * 统计 MyISAM 表数量。
	 *
	 * @return int MyISAM 表数量
	 */
	private static function count_myisam_tables(): int {
		global $wpdb;
		if ( ! $wpdb ) {
			return 0;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND ENGINE = 'MyISAM'",
				DB_NAME
			)
		);

		return $count ? (int) $count : 0;
	}

	/**
	 * 获取用户总数（使用缓存）。
	 *
	 * @return int 用户总数
	 */
	private static function get_users_count(): int {
		$count = get_transient( 'wpcy_users_count' );
		if ( false === $count ) {
			$count = count_users();
			$count = isset( $count['total_users'] ) ? (int) $count['total_users'] : 0;
			set_transient( 'wpcy_users_count', $count, DAY_IN_SECONDS );
		}
		return (int) $count;
	}

	/**
	 * 获取所有已安装主题列表。
	 *
	 * @return array 主题信息数组
	 */
	private static function get_theme_list(): array {
		$themes_data = get_transient( 'wpcy_themes_cache' );
		if ( false !== $themes_data ) {
			return $themes_data;
		}

		$all_themes    = wp_get_themes();
		$active_theme  = get_stylesheet();
		$list          = [];

		foreach ( $all_themes as $slug => $theme ) {
			$list[] = [
				'slug'           => $slug,
				'name'           => $theme->get( 'Name' ),
				'version'        => $theme->get( 'Version' ),
				'author'         => wp_strip_all_tags( $theme->get( 'Author' ) ),
				'is_active'      => ( $slug === $active_theme ),
				'is_child_theme' => ( $theme->parent() !== false ),
				'parent_slug'    => $theme->parent() ? $theme->parent()->get_stylesheet() : '',
				'is_block_theme' => method_exists( $theme, 'is_block_theme' ) ? $theme->is_block_theme() : false,
			];
		}

		set_transient( 'wpcy_themes_cache', $list, DAY_IN_SECONDS );
		return $list;
	}

	/**
	 * 获取已安装翻译数据。
	 *
	 * @return array 翻译信息数组
	 */
	private static function get_translations(): array {
		if ( ! function_exists( 'wp_get_installed_translations' ) ) {
			return [];
		}

		$result = [];
		$types  = [ 'plugins', 'themes', 'core' ];

		foreach ( $types as $type ) {
			$translations = wp_get_installed_translations( $type );
			foreach ( $translations as $slug => $languages ) {
				foreach ( $languages as $lang => $data ) {
					$result[] = [
						'type'     => rtrim( $type, 's' ),
						'slug'     => $slug,
						'language' => $lang,
						'version'  => isset( $data['PO-Revision-Date'] ) ? $data['PO-Revision-Date'] : '',
					];
				}
			}
		}

		if ( count( $result ) > 500 ) {
			$result = array_slice( $result, 0, 500 );
		}

		return $result;
	}

	/**
	 * 获取 WooCommerce 数据（仅当 WC 激活时）。
	 * 包含 P1 基础数据 + P2 扩展数据。
	 *
	 * @return array|null WooCommerce 数据，未激活返回 null
	 */
	private static function get_woocommerce_data(): ?array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return null;
		}

		global $wpdb;

		$data = [
			'wc_version'    => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'currency'      => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'base_location' => '',
			'is_ssl'        => is_ssl(),
			'hpos_enabled'  => self::is_hpos_enabled(),
		];

		// 商店位置
		if ( function_exists( 'wc_get_base_location' ) ) {
			$location = wc_get_base_location();
			$data['base_location'] = isset( $location['country'] ) ? $location['country'] : '';
		}

		// 产品统计
		$data['products_total']   = 0;
		$data['products_by_type'] = [];

		$product_counts = $wpdb->get_results(
			"SELECT t.slug, COUNT(*) as cnt
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_type'
			 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			 WHERE p.post_type = 'product' AND p.post_status = 'publish'
			 GROUP BY t.slug"
		);

		if ( $product_counts ) {
			foreach ( $product_counts as $row ) {
				$data['products_by_type'][ $row->slug ] = (int) $row->cnt;
				$data['products_total'] += (int) $row->cnt;
			}
		}

		// 订单统计（HPOS 兼容）
		$data['orders_total']     = 0;
		$data['orders_by_status'] = [];

		if ( $data['hpos_enabled'] && $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}wc_orders'" ) ) {
			$order_counts = $wpdb->get_results(
				"SELECT status, COUNT(*) as cnt FROM {$wpdb->prefix}wc_orders GROUP BY status"
			);
		} else {
			$order_counts = $wpdb->get_results(
				"SELECT post_status as status, COUNT(*) as cnt
				 FROM {$wpdb->posts}
				 WHERE post_type = 'shop_order'
				 GROUP BY post_status"
			);
		}

		if ( $order_counts ) {
			foreach ( $order_counts as $row ) {
				$data['orders_by_status'][ $row->status ] = (int) $row->cnt;
				$data['orders_total'] += (int) $row->cnt;
			}
		}

		// 支付网关
		$data['gateways'] = [];
		if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
			foreach ( WC()->payment_gateways->payment_gateways() as $gateway ) {
				$data['gateways'][] = [
					'id'      => $gateway->id,
					'enabled' => $gateway->enabled === 'yes',
				];
			}
		}

		// 配送方式
		$data['shipping_methods'] = [];
		if ( function_exists( 'WC' ) && WC()->shipping ) {
			foreach ( WC()->shipping->get_shipping_methods() as $method ) {
				$data['shipping_methods'][] = [
					'id'      => $method->id,
					'enabled' => $method->enabled === 'yes',
				];
			}
		}

		// === P2 扩展数据 ===

		// 用户角色分布
		$data['user_roles'] = self::get_user_role_distribution();

		// 模板覆盖列表
		$data['template_overrides'] = self::get_wc_template_overrides();

		// Block 购物车/结账使用
		$data['block_cart']     = self::page_has_block( 'woocommerce/cart', 'woocommerce_cart_page_id' );
		$data['block_checkout'] = self::page_has_block( 'woocommerce/checkout', 'woocommerce_checkout_page_id' );

		// 税费/优惠券/游客结账设置
		$data['calc_taxes']      = get_option( 'woocommerce_calc_taxes', 'no' ) === 'yes';
		$data['coupons_enabled'] = get_option( 'woocommerce_enable_coupons', 'yes' ) === 'yes';
		$data['guest_checkout']  = get_option( 'woocommerce_enable_guest_checkout', 'yes' ) === 'yes';

		// 配送区域数量
		$data['shipping_zones_count'] = self::count_shipping_zones();

		return $data;
	}

	/**
	 * 获取用户角色分布（使用缓存）。
	 *
	 * @return array 角色 => 数量
	 */
	private static function get_user_role_distribution(): array {
		$cached = get_transient( 'wpcy_user_roles' );
		if ( false !== $cached ) {
			return $cached;
		}

		$user_count = count_users();
		$roles      = [];

		if ( isset( $user_count['avail_roles'] ) && is_array( $user_count['avail_roles'] ) ) {
			foreach ( $user_count['avail_roles'] as $role => $count ) {
				if ( $count > 0 ) {
					$roles[ $role ] = (int) $count;
				}
			}
		}

		set_transient( 'wpcy_user_roles', $roles, DAY_IN_SECONDS );
		return $roles;
	}

	/**
	 * 获取 WooCommerce 模板覆盖列表。
	 *
	 * @return array 被覆盖的模板路径列表
	 */
	private static function get_wc_template_overrides(): array {
		if ( ! function_exists( 'WC' ) ) {
			return [];
		}

		$template_path = WC()->plugin_path() . '/templates/';
		$theme_path    = get_stylesheet_directory() . '/woocommerce/';

		if ( ! is_dir( $theme_path ) ) {
			return [];
		}

		$overrides = [];
		$iterator  = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $theme_path, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $file ) {
			if ( $file->getExtension() !== 'php' ) {
				continue;
			}
			$relative = str_replace( $theme_path, '', $file->getPathname() );
			if ( file_exists( $template_path . $relative ) ) {
				$overrides[] = $relative;
			}
		}

		if ( count( $overrides ) > 100 ) {
			$overrides = array_slice( $overrides, 0, 100 );
		}

		return $overrides;
	}

	/**
	 * 检测指定页面是否使用了指定的 Block。
	 *
	 * @param string $block_name Block 名称
	 * @param string $option_key 页面 ID 的 option key
	 * @return bool 是否使用了该 Block
	 */
	private static function page_has_block( string $block_name, string $option_key ): bool {
		$page_id = (int) get_option( $option_key, 0 );
		if ( $page_id < 1 ) {
			return false;
		}

		$post = get_post( $page_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		return has_block( $block_name, $post );
	}

	/**
	 * 统计配送区域数量。
	 *
	 * @return int 配送区域数量
	 */
	private static function count_shipping_zones(): int {
		global $wpdb;

		$table = $wpdb->prefix . 'woocommerce_shipping_zones';
		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ) {
			return 0;
		}

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		return $count ? (int) $count : 0;
	}

	/**
	 * 检测 WooCommerce HPOS 是否启用。
	 *
	 * @return bool HPOS 是否启用
	 */
	private static function is_hpos_enabled(): bool {
		if ( ! class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' ) ) {
			return false;
		}
		try {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * 注册次日 Cron（带随机延迟）。
	 */
	private static function schedule_next(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$tomorrow = strtotime( 'tomorrow midnight' );
			$delay    = wp_rand( 0, 6 * HOUR_IN_SECONDS );
			wp_schedule_single_event( $tomorrow + $delay, self::CRON_HOOK );
		}
	}

	/**
	 * 插件停用时清理 Cron。
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
