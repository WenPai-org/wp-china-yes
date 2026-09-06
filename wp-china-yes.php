<?php
/**
 * Plugin Name: WPCY.COM
 * Description: 文派叶子 🍃（WPCY.COM）是中国 WordPress 生态基础设施软件，犹如落叶新芽，生生不息。
 * Author: 文派开源
 * Author URI: https://wpcy.com
 * Version: 3.9.3
 * License: GPLv3 or later
 * Text Domain: wp-china-yes
 * Network: True
 * Requires at least: 4.9
 * Tested up to: 7.1
 * Requires PHP: 8.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'CHINA_YES_VERSION', '3.9.3' );
define( 'CHINA_YES_PLUGIN_FILE', __FILE__ );
define( 'CHINA_YES_PLUGIN_URL', plugin_dir_url( CHINA_YES_PLUGIN_FILE ) );
define( 'CHINA_YES_PLUGIN_PATH', plugin_dir_path( CHINA_YES_PLUGIN_FILE ) );

if ( file_exists( CHINA_YES_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	require_once CHINA_YES_PLUGIN_PATH . 'vendor/autoload.php';
	\WenPai\ChinaYes\Core\Plugin::boot();
	return;
}

add_action(
	'admin_notices',
	function () {
		echo '<div class="notice notice-error"><p>WPCY.COM: Composer autoloader not found. Please run "composer install".</p></div>';
	}
);
