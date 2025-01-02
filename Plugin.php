<?php

namespace WenPai\ChinaYes;

defined( 'ABSPATH' ) || exit;

use WenPai\ChinaYes\Service\Base;

class Plugin {

	/**
	 * 创建插件实例
	 */
	public function __construct() {
		new Base();
		if ( is_admin() ) {
			add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
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
	 * 插件加载时执行
	 */
	public function plugins_loaded() {
		load_plugin_textdomain( 'wp-china-yes', false, CHINA_YES_PLUGIN_PATH . 'languages' );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		/**
		 * 插件列表页中所有插件增加「参与翻译」链接
		 */
		add_filter( sprintf( '%splugin_action_links', is_multisite() ? 'network_admin_' : '' ), function ( $links, $plugin = '' ) {
			$links[] = '<a target="_blank" href="https://translate.wenpai.org/projects/plugins/' . substr( $plugin, 0, strpos( $plugin, '/' ) ) . '/">参与翻译</a>';
			$links[] = '<a target="_blank" href="https://wpcy.com/plugins/' . substr( $plugin, 0, strpos( $plugin, '/' ) ) . '/">去广告</a>';

			return $links;
		}, 10, 2 );
	}

	/**
	 * 插件兼容性检测函数
	 */
	public static function check() {
		$notices = [];
		if ( version_compare( PHP_VERSION, '7.0.0', '<' ) ) {
			deactivate_plugins( 'wp-china-yes/wp-china-yes.php' );
			$notices[] = '<div class="notice notice-error"><p>' . sprintf( __( 'WP-China-Yes 插件需要 PHP 7.0.0 或更高版本，当前版本为 %s，插件已自动禁用。',
					'wp-china-yes' ),
					PHP_VERSION ) . '</p></div>';
		}
		if ( is_plugin_active( 'wp-china-no/wp-china-no.php' ) ) {
			deactivate_plugins( 'wp-china-no/wp-china-no.php' );
			$notices[] = '<div class="notice notice-error is-dismissible">
					<p><strong>' . __( '检测到旧版插件 WP-China-No，已自动禁用！', 'wp-china-yes' ) . '</strong></p>
				</div>';
		}
		if ( is_plugin_active( 'wp-china-plus/wp-china-plus.php' ) ) {
			deactivate_plugins( 'wp-china-plus/wp-china-plus.php' );
			$notices[] = '<div class="notice notice-error is-dismissible">
					<p><strong>' . __( '检测到不兼容的插件 WP-China-Plus，已自动禁用！', 'wp-china-yes' ) . '</strong></p>
				</div>';
		}
		if ( is_plugin_active( 'kill-429/kill-429.php' ) ) {
			deactivate_plugins( 'kill-429/kill-429.php' );
			$notices[] = '<div class="notice notice-error is-dismissible">
					<p><strong>' . __( '检测到不兼容的插件 Kill 429，已自动禁用！', 'wp-china-yes' ) . '</strong></p>
				</div>';
		}
		// 代理服务器检测
		if ( defined( 'WP_PROXY_HOST' ) || defined( 'WP_PROXY_PORT' ) ) {
			$notices[] = '<div class="notice notice-warning is-dismissible">
					<p><strong>' . __( '检测到已在 WordPress 配置文件中设置代理服务器，这可能会导致插件无法正常工作！',
					'wp-china-yes' ) . '</strong></p>
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
