<?php
/**
 * Plugin Name: WP-China-Yes
 * Description: 文派叶子 🍃（WP-China-Yes）是中国 WordPress 生态基础设施软件，犹如落叶新芽，生生不息。
 * Author: 文派开源
 * Author URI: https://wpcy.com
 * Version: 3.8.1
 * License: GPLv3 or later
 * Text Domain: wp-china-yes
 * Domain Path: /languages
 * Network: True
 * Requires at least: 4.9
 * Tested up to: 9.9.9
 * Requires PHP: 7.0.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace WenPai\ChinaYes;

defined( 'ABSPATH' ) || exit;

define( 'CHINA_YES_VERSION', '3.8' );
define( 'CHINA_YES_PLUGIN_FILE', __FILE__ );
define( 'CHINA_YES_PLUGIN_URL', plugin_dir_url( CHINA_YES_PLUGIN_FILE ) );
define( 'CHINA_YES_PLUGIN_PATH', plugin_dir_path( CHINA_YES_PLUGIN_FILE ) );

if (file_exists(CHINA_YES_PLUGIN_PATH . 'vendor/autoload.php')) {
    // 尽早初始化性能设置
    $settings = get_option('wenpai_china_yes'); // 替换成您实际的设置选项名
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
    }
    
    require_once(CHINA_YES_PLUGIN_PATH . 'vendor/autoload.php');
}

// 注册插件激活钩子
register_activation_hook( CHINA_YES_PLUGIN_FILE, [ Plugin::class, 'activate' ] );
// 注册插件删除钩子
register_uninstall_hook( CHINA_YES_PLUGIN_FILE, [ Plugin::class, 'uninstall' ] );


new Plugin();
