<?php
/**
 * Plugin Name: WP-China-Yes
 * Description: 这是一个革命性的插件，从此中国人会拥有针对国内环境专门定制的WordPress，以及一个由中国人主导的社区生态环境
 * Author: WP中国本土化社区
 * Author URI:https://wp-china-yes.org/
 * Version: 3.0.0-Beta
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (is_admin()) {
    /**
     * 使用Composer作PHP文件自动加载
     */
    if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
        require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * WP-China-Yes的翻译校准基于Loco Translate插件开发，这里通过引入入口文件的方式激活该插件的二次开发版
     */
    if ( file_exists( __DIR__ . '/vendor/loco-translate/loco.php' ) ) {
        require __DIR__ . '/vendor/loco-translate/loco.php';
    }

    /**
     * 引入设置页
     */
    require __DIR__ . '/setting.php';


    /**
     * 菜单注册
     */
    add_action('admin_menu', function () {
        add_menu_page(
            '本土化',
            '本土化',
            '',
            'wpcy'
        );

        add_submenu_page(
            'wpcy',
            'China Yes!!!',
            '系统本土化',
            'manage_options',
            'wpcy-setting',
            'wpcy_options_page_html',
            0
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
        if (get_option('super_gravatar') == 1) {
            $url = str_replace('api.wordpress.org', 'api.wp-china-yes.net', $url);
        } else {
            $url = str_replace('api.wordpress.org', 'api-original.wp-china-yes.net', $url);
        }
        $url = str_replace('downloads.wordpress.org', 'd.w.org.ibadboy.net', $url);

        return wp_remote_request($url, $r);
    }, 10, 3);

    
    /**
     * 替换仪表盘默认的“WordPress活动与新闻”为本土化版本
     */
    add_action('wp_dashboard_setup', function () {
        global $wp_meta_boxes;

        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);

        wp_add_dashboard_widget('sponsor_widget', 'WordPress活动及新闻', function () {
            echo <<<EOT
<div class="wordpress-news hide-if-no-js">
<div class="rss-widget">
EOT;
            wp_widget_rss_output('https://wp-china.org/archives/category/news/feed/');
            echo <<<EOT
</div>
</div>
<p class="community-events-footer" style="padding-bottom: 3px;">
<a href="https://wp-china.org/" target="_blank">WP中国本土化社区</a>
 | 
<a href="https://wp-china.org/thank" target="_blank">赞助者名单</a>
</p>
EOT;
        });
    });
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
            'secure.gravatar.com'
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
