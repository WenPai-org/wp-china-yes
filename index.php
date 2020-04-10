<?php
/*
 * Plugin Name: WP-China-Yes
 * Description: 这是一个颠覆性的插件，她将全面改善中国大陆站点在访问WP官方服务时的用户体验，其原理是将位于国外的官方仓库源替换为由社区志愿者维护的国内源，以此达到加速访问的目的。
 * Author: 孙锡源
 * Version: 2.1.0
 * Author URI:https://www.ibadboy.net/
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

define('WP_CHINA_YES_PATH', __DIR__);
define('WP_CHINA_YES_BASE_FILE', __FILE__);

WP_CHINA_YES::init();

class WP_CHINA_YES {
    public static function init() {
        if (is_admin()) {
            register_activation_hook(WP_CHINA_YES_BASE_FILE, array(
                __CLASS__,
                'wp_china_yes_activate'
            ));
            register_deactivation_hook(WP_CHINA_YES_BASE_FILE, array(
                __CLASS__,
                'wp_china_yes_deactivate'
            ));
            add_filter('pre_http_request', array(
                __CLASS__,
                'pre_http_request'
            ), 10, 3);
            add_filter('plugin_row_meta', array(
                __CLASS__,
                'plugin_row_meta'
            ), 10, 2);
            add_filter('plugin_action_links', array(
                __CLASS__,
                'action_links'
            ), 10, 2);
            add_action('admin_menu', array(
                __CLASS__,
                'admin_menu'
            ));
            add_action('init', array(
                __CLASS__,
                'set_cookie'
            ));
            add_action('wp_ajax_wpcy_get_config', array(
                __CLASS__,
                'get_config'
            ));
            add_action('wp_ajax_wpcy_set_config', array(
                __CLASS__,
                'set_config'
            ));
            add_action('wp_dashboard_setup', array(
                __CLASS__,
                'sponsor_widget'
            ));

            if (empty(get_option('wp_china_yes_options'))) {
                self::wp_china_yes_activate();
            }
        }
    }

    public static function wp_china_yes_activate() {
        self::set_wp_option();
    }

    public static function wp_china_yes_deactivate() {
        delete_option('wp_china_yes_options');
    }

    public static function pre_http_request($preempt, $r, $url) {
        if ( ! stristr($url, 'api.wordpress.org') && ! stristr($url, 'downloads.wordpress.org')) {
            return false;
        }

        $options = get_option('wp_china_yes_options');

        if ($options["community"] == 1) {
            $api_server      = 'api.w.org.ixmu.net';
            $download_server = 'd.w.org.ixmu.net';
        } else {
            $api_server      = 'api.w.org.ibadboy.net';
            $download_server = 'd.w.org.ibadboy.net';
        }

        $api_server      = $options["custom_api_server"] ?: $api_server;
        $download_server = $options["custom_download_server"] ?: $download_server;
        $url             = str_replace('api.wordpress.org', $api_server, $url);
        $url             = str_replace('downloads.wordpress.org', $download_server, $url);

        /**
         * 此处原本是复制了官方对外部请求处理的原始代码
         * 后经我爱水煮鱼(http://blog.wpjam.com/)提醒，可以直接调用wp_remote_request达成相同目的，由此精简掉100余行代码。
         */
        return wp_remote_request($url, $r);
    }

    public static function plugin_row_meta($links, $file) {
        $base = plugin_basename(WP_CHINA_YES_BASE_FILE);
        if ($file == $base) {
            $links[] = '<a target="_blank" href="https://www.ibadboy.net/archives/3204.html">发布地址</a>';
            $links[] = '<a target="_blank" href="https://github.com/sunxiyuan/wp-china-yes">GitHub</a>';
        }

        return $links;
    }

    public static function action_links($links, $file) {
        if ($file != plugin_basename(WP_CHINA_YES_BASE_FILE)) {
            return $links;
        }

        $settings_link = '<a href="' . menu_page_url('wp_china_yes', false) . '">设置</a>';

        array_unshift($links, $settings_link);

        return $links;
    }

    public static function admin_menu() {
        add_options_page(
            'WP-China-Yes',
            'WP-China-Yes',
            'manage_options',
            'wp_china_yes',
            array(__CLASS__, 'settings')
        );
    }

    public static function settings() {
        $setting_page_url = plugins_url('settings.html', __FILE__) . '?v=2.1.0';
        echo <<<EOT
<div style="height: 20px"></div>
<iframe src="$setting_page_url" 
frameborder="0" height="850" width="800px;" scrolling="No" leftmargin="0" topmargin="0">
</iframe>
EOT;
    }

    public static function set_cookie() {
        if ( ! isset($_COOKIE['wp-china-yes']) && current_user_can('manage_options')) {
            setcookie('wp-china-yes', json_encode([
                'get_config' => wp_create_nonce('wpcy_get_config'),
                'set_config' => wp_create_nonce('wpcy_set_config')
            ], JSON_UNESCAPED_UNICODE), time() + 1209600, COOKIEPATH, COOKIE_DOMAIN, false);
        }
    }

    public static function get_config() {
        self::success('', get_option('wp_china_yes_options'));
    }

    public static function set_config() {
        if ( ! array_key_exists('community', $_POST) ||
             ( ! array_key_exists('custom_api_server', $_POST) && ! array_key_exists('custom_download_server', $_POST))) {
            self::error('参数错误', - 1);
        }

        self::set_wp_option(
            sanitize_text_field(trim($_POST['community'])),
            sanitize_text_field(trim($_POST['custom_api_server'])),
            sanitize_text_field(trim($_POST['custom_download_server']))
        );

        self::success();
    }

    public static function sponsor_widget() {
        wp_add_dashboard_widget('custom_help_widget', '《WordPress中国区仓库源建设计划》赞助者', function () {
            require_once plugin_dir_path(__FILE__) . 'sponsor_widget.php';
        });
    }

    private static function success($message = '', $data = []) {
        header('Content-Type:application/json; charset=utf-8');

        echo json_encode([
            'code'    => 0,
            'message' => $message,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function error($message = '', $code = - 1) {
        header('Content-Type:application/json; charset=utf-8');
        header('Status:500');

        echo json_encode([
            'code'    => $code,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function set_wp_option(
        $community = 0,
        $custom_api_server = '',
        $custom_download_server = ''
    ) {
        $options                           = array();
        $options['community']              = (int) $community;
        $options['custom_api_server']      = $custom_api_server;
        $options['custom_download_server'] = $custom_download_server;
        update_option("wp_china_yes_options", $options);
    }
}