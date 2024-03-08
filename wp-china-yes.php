<?php
/**
 * Plugin Name: WP-China-Yes
 * Description: 文派叶子 🍃（WP-China-Yes）是中国 WordPress 生态基础设施软件，犹如落叶新芽，生生不息。
 * Author: 文派开源
 * Author URI: https://wp-china-yes.com
 * Version: 3.6.1
 * License: GPLv3 or later
 * Text Domain: wp-china-yes
 * Domain Path: /languages
 * Network: True
 * Requires at least: 4.9
 * Tested up to: 9.9.9
 * Requires PHP: 5.6.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace WenPai\ChinaYes;

defined( 'ABSPATH' ) || exit;

const VERSION     = '3.6.1';
const PLUGIN_FILE = __FILE__;
const PLUGIN_DIR  = __DIR__;

require_once( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' );

// 注册插件激活钩子
register_activation_hook( PLUGIN_FILE, [ Plugin::class, 'activate' ] );
// 注册插件删除钩子
register_uninstall_hook( PLUGIN_FILE, [ Plugin::class, 'uninstall' ] );

new Plugin();
