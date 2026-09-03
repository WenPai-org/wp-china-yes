<?php

namespace WenPai\ChinaYes;

defined( 'ABSPATH' ) || exit;

use WenPai\ChinaYes\Service\Base;

class Plugin {

	/**
	 * 创建插件实例
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'plugins_loaded', [ $this, 'init_services' ] );
		
		if ( is_admin() ) {
			add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
		}
	}

	public function init_services() {
		try {
			new Base();
		} catch ( \Exception $e ) {
			error_log( 'WP-China-Yes: Failed to initialize Base service: ' . $e->getMessage() );
			add_action( 'admin_notices', function() use ( $e ) {
				echo '<div class="notice notice-error"><p>WP-China-Yes initialization error: ' . esc_html( $e->getMessage() ) . '</p></div>';
			});
		}

		// 加载文派云桥客户端（站点健康上报 + 更新降级策略）
		$this->init_bridge_client();
	}

	/**
	 * 初始化文派云桥客户端。
	 *
	 * 站点身份与每日兼容性报告始终加载；`bridge` 开关只控制更新通道降级策略（见客户端文件）。
	 */
	private function init_bridge_client() {
		$bridge_client = CHINA_YES_PLUGIN_PATH . 'client/wenpai-bridge-client.php';

		if ( file_exists( $bridge_client ) ) {
			require_once $bridge_client;
		}
	}

	/**
	 * 插件激活时执行
	 */
	public static function activate() {
		// 兼容性检测
		self::check();
	}

	/**
	 * 插件删除时执行
	 */
	public static function uninstall() {
		// 清除设置
		is_multisite() ? delete_site_option( 'wp_china_yes' ) : delete_option( 'wp_china_yes' );
	}

	/**
	 * 加载翻译文件
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-china-yes', false, dirname( plugin_basename( CHINA_YES_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * 插件加载时执行
	 */
	public function plugins_loaded() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		$hook = ( is_multisite() ? 'network_admin_' : '' )
			. 'plugin_action_links_' . plugin_basename( CHINA_YES_PLUGIN_FILE );
		add_filter( $hook, function ( $links ) {
			$settings_url = is_multisite()
				? network_admin_url( 'settings.php?page=wp-china-yes' )
				: admin_url( 'options-general.php?page=wp-china-yes' );
			array_unshift( $links, '<a href="' . esc_url( $settings_url ) . '">设置</a>' );
			return $links;
		} );
	}

	/**
	 * 插件兼容性检测函数
	 */
	public static function check() {
		$notices = [];
		if ( version_compare( PHP_VERSION, '7.4.0', '<' ) ) {
			deactivate_plugins( 'wp-china-yes/wp-china-yes.php' );
			$notices[] = '<div class="notice notice-error"><p>' . sprintf( 'WP-China-Yes 插件需要 PHP 7.4.0 或更高版本，当前版本为 %s，插件已自动禁用。',
					PHP_VERSION ) . '</p></div>';
		}
		if ( is_plugin_active( 'wp-china-no/wp-china-no.php' ) ) {
			$notices[] = '<div class="notice notice-error is-dismissible">
						<p><strong>检测到旧版插件 WP-China-No。WPCY 不会自动停用其他插件，请管理员确认后手动处理冲突。</strong></p>
					</div>';
		}
		if ( is_plugin_active( 'wp-china-plus/wp-china-plus.php' ) ) {
			$notices[] = '<div class="notice notice-error is-dismissible">
						<p><strong>检测到可能不兼容的插件 WP-China-Plus。WPCY 未修改其启用状态。</strong></p>
					</div>';
		}
		if ( is_plugin_active( 'kill-429/kill-429.php' ) ) {
			$notices[] = '<div class="notice notice-error is-dismissible">
						<p><strong>检测到可能不兼容的插件 Kill 429。WPCY 未修改其启用状态。</strong></p>
					</div>';
		}
		if ( defined( 'WP_PROXY_HOST' ) || defined( 'WP_PROXY_PORT' ) ) {
			$notices[] = '<div class="notice notice-warning is-dismissible">
					<p><strong>检测到已在 WordPress 配置文件中设置代理服务器，这可能会导致插件无法正常工作！</strong></p>
				</div>';
		}

		set_transient( 'wp-china-yes-admin-notices', $notices, 10 );
	}

	/**
	 * 输出管理后台提示信息
	 */
	public function admin_notices() {
		$notices = get_transient( 'wp-china-yes-admin-notices' );
		if ( $notices ) {
			foreach ( $notices as $notice ) {
				echo $notice;
			}
			delete_transient( 'wp-china-yes-admin-notices' );
		}
	}
}
