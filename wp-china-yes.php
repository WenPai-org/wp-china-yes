<?php
/**
 * Plugin Name: WP-China-Yes
 * Description: 将你的WordPress接入本土生态体系中，这将为你提供一个更贴近中国人使用习惯的WordPress
 * Author: WP中国本土化社区
 * Author URI:https://wp-china.org/
 * Version: 3.1.0-Beta1
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (is_admin()) {
    /**
     * 引入设置页
     */
    require __DIR__ . '/setting.php';


    /**
     * 插件列表项目中增加设置项
     */
    add_filter('plugin_action_links', function ($links, $file) {
        if ($file != plugin_basename(__FILE__)) {
            return $links;
        }
        $settings_link = '<a href="' . menu_page_url('wp_china_yes', false) . '">设置</a>';
        array_unshift($links, $settings_link);

        return $links;
    }, 10, 2);


    /**
     * 初始化设置项
     */
    if (empty(get_option('wpapi')) || empty(get_option('super_gravatar')) || empty(get_option('super_googlefonts'))) {
        update_option("wpapi", '2');
        update_option("super_gravatar", '1');
        update_option("super_googlefonts", '2');
    }


    /**
     * 禁用插件时删除配置
     */
    register_deactivation_hook(__FILE__, function () {
        delete_option("wpapi");
        delete_option("super_gravatar");
        delete_option("super_googlefonts");
    });


    /**
     * 菜单注册
     */
    add_action('admin_menu', function () {
        add_options_page(
            'WP-China-Yes',
            'WP-China-Yes',
            'manage_options',
            'wp_china_yes',
            'wpcy_options_page_html'
        );
    });


    /**
     * 替换api.wordpress.org和downloads.wordpress.org为WP-China.org维护的大陆加速节点
     * URL替换代码来自于我爱水煮鱼(http://blog.wpjam.com/)开发的WPJAM Basic插件
     */
    add_filter('pre_http_request', function ($preempt, $r, $url) {
        if ( ! stristr($url, 'api.wordpress.org') && ! stristr($url, 'downloads.wordpress.org')) {
            return false;
        }
        if (get_option('wpapi') == 1) {
            $url = str_replace('api.wordpress.org', 'api.wp-china-yes.net', $url);
            $url = str_replace('downloads.wordpress.org', 'download.wp-china-yes.net', $url);
        } else {
            $url = str_replace('api.wordpress.org', 'api.w.org.ibadboy.net', $url);
            $url = str_replace('downloads.wordpress.org', 'd.w.org.ibadboy.net', $url);
        }

        return wp_remote_request($url, $r);
    }, 10, 3);
}


/**
 * 替换G家头像为WP-China.org维护的大陆加速节点
 */
if (get_option('super_gravatar') == 1) {
    add_filter('get_avatar', function ($avatar) {
        return str_replace([
            'www.gravatar.com',
            '0.gravatar.com',
            '1.gravatar.com',
            '2.gravatar.com',
            'secure.gravatar.com',
            'cn.gravatar.com'
        ], 'gravatar.wp-china-yes.net', $avatar);
    });
}


/**
 * 替换谷歌字体为WP-China.org维护的大陆加速节点
 */
if (get_option('super_googlefonts') == 1) {
    add_action('init', function () {
        ob_start(function ($buffer) {
            return str_replace('fonts.googleapis.com', 'googlefonts.wp-china-yes.net', $buffer);
        });
    });
}
