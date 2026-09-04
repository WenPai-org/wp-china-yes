<?php
/**
 * Plugin Name: WPCY.COM
 * Description: 文派叶子 🍃（WPCY.COM）是中国 WordPress 生态基础设施软件，犹如落叶新芽，生生不息。
 * Author: 文派开源
 * Author URI: https://wpcy.com
 * Version: 3.9.3
 * License: GPLv3 or later
 * Text Domain: wp-china-yes
 * Domain Path: /languages
 * Network: True
 * Requires at least: 4.9
 * Tested up to: 7.0
 * Requires PHP: 7.4.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace WenPai\ChinaYes;

defined( 'ABSPATH' ) || exit;

define( 'CHINA_YES_VERSION', '3.9.3' );
define( 'CHINA_YES_PLUGIN_FILE', __FILE__ );
define( 'CHINA_YES_PLUGIN_URL', plugin_dir_url( CHINA_YES_PLUGIN_FILE ) );
define( 'CHINA_YES_PLUGIN_PATH', plugin_dir_path( CHINA_YES_PLUGIN_FILE ) );

if (file_exists(CHINA_YES_PLUGIN_PATH . 'vendor/autoload.php')) {
	    $settings = is_multisite() ? get_site_option('wp_china_yes') : get_option('wp_china_yes');

	    // A damaged serialized option must never reach array-offset reads.
	    if ( ! is_array( $settings ) ) {
	        $settings = [];
	    }
    
    if (!empty($settings)) {
        if (!defined('WP_MEMORY_LIMIT') && !empty($settings['wp_memory_limit'])) {
            define('WP_MEMORY_LIMIT', $settings['wp_memory_limit']);
            @ini_set('memory_limit', $settings['wp_memory_limit']);
        }
        if (!defined('WP_MAX_MEMORY_LIMIT') && !empty($settings['wp_max_memory_limit'])) {
            define('WP_MAX_MEMORY_LIMIT', $settings['wp_max_memory_limit']);
        }
        if (!defined('WP_POST_REVISIONS') && isset($settings['wp_post_revisions'])) {
            define('WP_POST_REVISIONS', intval($settings['wp_post_revisions']));
        }
        if (!defined('AUTOSAVE_INTERVAL') && !empty($settings['autosave_interval'])) {
            define('AUTOSAVE_INTERVAL', intval($settings['autosave_interval']));
        }
        if (!defined('EMPTY_TRASH_DAYS') && isset($settings['empty_trash_days'])) {
            define('EMPTY_TRASH_DAYS', intval($settings['empty_trash_days']));
        }
    }
    
    require_once(CHINA_YES_PLUGIN_PATH . 'vendor/autoload.php');

    if ( defined( 'WPCY_KERNEL' ) && 'v4' === WPCY_KERNEL ) {
        \WenPai\ChinaYes\Core\Plugin::boot();
        return;
    }
    
    // 初始化翻译管理器
    require_once(CHINA_YES_PLUGIN_PATH . 'Service/TranslationManager.php');
    require_once(CHINA_YES_PLUGIN_PATH . 'Service/LazyTranslation.php');
    \WenPai\ChinaYes\Service\TranslationManager::getInstance();
    
	    // 包含测试文件（仅在开发环境且文件确实存在）
	    if (defined('WP_DEBUG') && WP_DEBUG && file_exists(CHINA_YES_PLUGIN_PATH . 'test-translation.php')) {
        require_once(CHINA_YES_PLUGIN_PATH . 'test-translation.php');
    }
    
} else {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>WPCY.COM: Composer autoloader not found. Please run "composer install".</p></div>';
    });
    return;
}

// 注册插件激活钩子
register_activation_hook( CHINA_YES_PLUGIN_FILE, [ Plugin::class, 'activate' ] );
// 注册插件删除钩子
register_uninstall_hook( CHINA_YES_PLUGIN_FILE, [ Plugin::class, 'uninstall' ] );


new Plugin();
