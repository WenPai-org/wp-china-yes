<?php
/*
 * Plugin Name: WP-China-Yes
 * Description: 这是一个颠覆性的插件，她将全面改善中国大陆站点在访问WP官方服务时的用户体验，其原理是将位于国外的官方仓库源替换为由社区志愿者维护的国内源，以此达到加速访问的目的。
 * Author: WP-China-Yes
 * Version: 2.2.0
 * Author URI:https://wp-china-yes.org/
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

define('WP_CHINA_YES_PATH', __DIR__);
define('WP_CHINA_YES_BASE_FILE', __FILE__);

(new WP_CHINA_YES)->init();

class WP_CHINA_YES {
    private $wp_china_yes_options = [];

    public function init() {
        add_filter('pre_http_request', [$this, 'pre_http_request'], 10, 3);
        $post_action = isset($_POST['action']) ? sanitize_text_field(trim($_POST['action'])) : ' ';
        if( defined( 'DOING_AJAX' ) && DOING_AJAX && !in_array($post_action,['wpcy_set_config','wpcy_get_config'])){
            return;
        }
        if (is_admin()) {
            $this->wp_china_yes_options = get_option('wp_china_yes_options');
            if (empty($this->wp_china_yes_options)) {
                self::set_wp_option();
            }
            register_deactivation_hook(WP_CHINA_YES_BASE_FILE, [$this, 'wp_china_yes_deactivate']);
            add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);
            add_filter('plugin_action_links', [$this, 'action_links'], 10, 2);
            add_action('admin_menu', [$this, 'admin_menu']);
            add_action('init', [$this, 'set_cookie']);
            add_action('wp_ajax_wpcy_get_config', [$this, 'get_config']);
            add_action('wp_ajax_wpcy_set_config', [$this, 'set_config']);
            add_action('wp_dashboard_setup', [$this, 'sponsor_widget']);
        }
    }

    public function wp_china_yes_deactivate() {
        delete_option('wp_china_yes_options');
    }

    public function pre_http_request($preempt, $r, $url) {
        if ( ! stristr($url, 'api.wordpress.org') && ! stristr($url, 'downloads.wordpress.org')) {
            return false;
        }

        if ($this->wp_china_yes_options["community"] == 1) {
            $api_server      = 'api.w.org.ixmu.net';
            $download_server = 'd.w.org.ixmu.net';
        } else {
            $api_server      = 'api.w.org.ibadboy.net';
            $download_server = 'd.w.org.ibadboy.net';
        }

        $api_server      = $this->wp_china_yes_options["custom_api_server"] ?: $api_server;
        $download_server = $this->wp_china_yes_options["custom_download_server"] ?: $download_server;
        $url             = str_replace('api.wordpress.org', $api_server, $url);
        $url             = str_replace('downloads.wordpress.org', $download_server, $url);

        /**
         * 此处原本是复制了官方对外部请求处理的原始代码
         * 后经我爱水煮鱼(http://blog.wpjam.com/)提醒，可以直接调用wp_remote_request达成相同目的，由此精简掉100余行代码。
         */
        return wp_remote_request($url, $r);
    }

    public function plugin_row_meta($links, $file) {
        $base = plugin_basename(WP_CHINA_YES_BASE_FILE);
        if ($file == $base) {
            $links[] = '<a target="_blank" href="https://wp-china-yes.org">项目主页</a>';
            $links[] = '<a target="_blank" href="https://github.com/wp-china-yes/wp-china-yes">GitHub</a>';
        }

        return $links;
    }

    public function action_links($links, $file) {
        if ($file != plugin_basename(WP_CHINA_YES_BASE_FILE)) {
            return $links;
        }
        $settings_link = '<a href="' . menu_page_url('wp_china_yes', false) . '">设置</a>';
        array_unshift($links, $settings_link);

        return $links;
    }

    public function admin_menu() {
        add_options_page(
            'WP-China-Yes',
            'WP-China-Yes',
            'manage_options',
            'wp_china_yes',
            [$this, 'settings']
        );
    }

    public function settings() {
        $setting_page_url = plugins_url('settings.html', __FILE__) . '?v=2.2.0';
        echo <<<EOT
<iframe src="$setting_page_url" style="margin-top: 20px;"
frameborder="0" height="700px;" width="600px;" scrolling="No" leftmargin="0" topmargin="0">
</iframe>
EOT;
    }

    public function set_cookie() {
        if ( ! isset($_COOKIE['wp-china-yes']) && current_user_can('manage_options')) {
            setcookie('wp-china-yes', json_encode([
                'get_config' => wp_create_nonce('wpcy_get_config'),
                'set_config' => wp_create_nonce('wpcy_set_config')
            ], JSON_UNESCAPED_UNICODE), time() + 1209600, COOKIEPATH, COOKIE_DOMAIN, false);
        }
    }

    public function get_config() {
        self::success('', $this->wp_china_yes_options);
    }

    public function set_config() {
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

    public function sponsor_widget() {
        wp_add_dashboard_widget('sponsor_widget', '《WordPress中国区仓库源建设计划》赞助商', function () {
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
        update_option("wp_china_yes_options", [
            'community'              => (int) $community,
            'custom_api_server'      => $custom_api_server,
            'custom_download_server' => $custom_download_server
        ]);
    }
}
