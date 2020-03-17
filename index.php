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

            if (empty(get_option('wp_china_yes_options'))) {
                self::wp_china_yes_activate();
            }
        }
    }

    public static function wp_china_yes_activate() {
        $options                           = array();
        $options['community']              = '0';
        $options['custom_api_server']      = '';
        $options['custom_download_server'] = '';
        $options['api_server']             = 'api.w.org.ibadboy.net';
        $options['download_server']        = 'd.w.org.ibadboy.net';
        add_option('wp_china_yes_options', $options);
    }

    public static function wp_china_yes_deactivate() {
        delete_option('wp_china_yes_options');
    }

    public static function pre_http_request($preempt, $r, $url) {
        if ( ! stristr($url, 'api.wordpress.org') && ! stristr($url, 'downloads.wordpress.org')) {
            return false;
        }

        $options         = get_option('wp_china_yes_options');
        $api_server      = $options["custom_api_server"] ?: $options["api_server"];
        $download_server = $options["custom_download_server"] ?: $options["download_server"];
        $url             = str_replace('api.wordpress.org', $api_server, $url);
        $url             = str_replace('downloads.wordpress.org', $download_server, $url);

        if (function_exists('wp_kses_bad_protocol')) {
            if ($r['reject_unsafe_urls']) {
                $url = wp_http_validate_url($url);
            }
            if ($url) {
                $url = wp_kses_bad_protocol($url, array(
                    'http',
                    'https',
                    'ssl'
                ));
            }
        }

        $arrURL = @parse_url($url);

        if (empty($url) || empty($arrURL['scheme'])) {
            return new WP_Error('http_request_failed', __('A valid URL was not provided.'));
        }

        if ($r['stream']) {
            if (empty($r['filename'])) {
                $r['filename'] = get_temp_dir() . basename($url);
            }

            $r['blocking'] = true;
            if ( ! wp_is_writable(dirname($r['filename']))) {
                return new WP_Error('http_request_failed', __('Destination directory for file streaming does not exist or is not writable.'));
            }
        }

        if (is_null($r['headers'])) {
            $r['headers'] = array();
        }

        if ( ! is_array($r['headers'])) {
            $processedHeaders = WP_Http::processHeaders($r['headers']);
            $r['headers']     = $processedHeaders['headers'];
        }

        $headers = $r['headers'];
        $data    = $r['body'];
        $type    = $r['method'];
        $options = array(
            'timeout'   => $r['timeout'],
            'useragent' => $r['user-agent'],
            'blocking'  => $r['blocking'],
            'hooks'     => new WP_HTTP_Requests_Hooks($url, $r),
        );

        if ($r['stream']) {
            $options['filename'] = $r['filename'];
        }
        if (empty($r['redirection'])) {
            $options['follow_redirects'] = false;
        } else {
            $options['redirects'] = $r['redirection'];
        }

        if (isset($r['limit_response_size'])) {
            $options['max_bytes'] = $r['limit_response_size'];
        }

        if ( ! empty($r['cookies'])) {
            $options['cookies'] = WP_Http::normalize_cookies($r['cookies']);
        }

        if ( ! $r['sslverify']) {
            $options['verify']     = false;
            $options['verifyname'] = false;
        } else {
            $options['verify'] = $r['sslcertificates'];
        }

        if ('HEAD' !== $type && 'GET' !== $type) {
            $options['data_format'] = 'body';
        }

        $options['verify'] = apply_filters('https_ssl_verify', $options['verify'], $url);

        $proxy = new WP_HTTP_Proxy();
        if ($proxy->is_enabled() && $proxy->send_through_proxy($url)) {
            $options['proxy'] = new Requests_Proxy_HTTP($proxy->host() . ':' . $proxy->port());

            if ($proxy->use_authentication()) {
                $options['proxy']->use_authentication = true;
                $options['proxy']->user               = $proxy->username();
                $options['proxy']->pass               = $proxy->password();
            }
        }

        mbstring_binary_safe_encoding();

        try {
            $requests_response = Requests::request($url, $headers, $data, $type, $options);

            $http_response = new WP_HTTP_Requests_Response($requests_response, $r['filename']);
            $response      = $http_response->to_array();

            $response['http_response'] = $http_response;
        } catch (Requests_Exception $e) {
            $response = new WP_Error('http_request_failed', $e->getMessage());
        }

        reset_mbstring_encoding();

        do_action('http_api_debug', $response, 'response', 'Requests', $r, $url);
        if (is_wp_error($response)) {
            return $response;
        }

        if ( ! $r['blocking']) {
            return array(
                'headers'       => array(),
                'body'          => '',
                'response'      => array(
                    'code'    => false,
                    'message' => false,
                ),
                'cookies'       => array(),
                'http_response' => null,
            );
        }

        return apply_filters('http_response', $response, $r, $url);
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
        $iframe_str = <<<EOT
<div style="height: 20px"></div>
<iframe src="/wp-content/plugins/wp-china-yes/settings.html" 
frameborder="0" height="850" width="800px;" scrolling="No" leftmargin="0" topmargin="0">
</iframe>
EOT;
        $plugin_root_url = plugins_url();
        $iframe_str = str_replace('/wp-content/plugins', $plugin_root_url, $iframe_str);
        echo $iframe_str;
    }
}
