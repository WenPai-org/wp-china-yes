<?php
/*
 * Plugin Name: WP-China-Yes
 * Description: 这是一个颠覆性的插件，她将全面改善中国大陆站点在访问WP官方服务时的用户体验，其原理是将位于国外的官方仓库源替换为由社区志愿者维护的国内源，以此达到加速访问的目的。
 * Author: 孙锡源
 * Version: 2.0.0
 * Author URI:https://www.ibadboy.net/
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

WP_CHINA_YES::init();

class WP_CHINA_YES {
    public static function init() {
        if (is_admin()) {
            add_filter('pre_http_request', array(
                __CLASS__,
                'pre_http_request'
            ), 10, 3);
            add_filter('plugin_row_meta', array(
                __CLASS__,
                'plugin_row_meta'
            ), 10, 2);
        }
    }

    public static function pre_http_request($preempt, $r, $url) {
        if ( ! stristr($url, 'api.wordpress.org') && ! stristr($url, 'downloads.wordpress.org')) {
            return false;
        }

        $url             = str_replace('api.wordpress.org', 'api.w.org.ibadboy.net', $url);
        $url             = str_replace('downloads.wordpress.org', 'd.w.org.ibadboy.net', $url);

        /**
         * 此处原本是复制了官方对外部请求处理的原始代码
         * 后经我爱水煮鱼(http://blog.wpjam.com/)提醒，可以直接调用wp_remote_request达成相同目的，由此精简掉100余行代码。
         */
        return wp_remote_request($url, $r);
    }

    public static function plugin_row_meta($links, $file) {
        $base = plugin_basename(__FILE__);
        if ($file == $base) {
            $links[] = '<a target="_blank" href="https://www.ibadboy.net/archives/3204.html">发布地址</a>';
            $links[] = '<a target="_blank" href="https://github.com/sunxiyuan/wp-china-yes">GitHub</a>';
        }

        return $links;
    }
}