<?php
/*
Plugin Name: WP-China-Yes
Description: 此插件将全面替换WP访问官方服务的链接为高速稳定的中国大陆节点，以此加快站点更新版本、安装升级插件主题的速度，并彻底解决429报错问题。
Author: 孙锡源
Version: 1.0.1
Author URI:https://www.ibadboy.net/
*/

define('WP_CHINA_YES_PATH', __DIR__);
define('WP_CHINA_YES_BASE_FILE', __FILE__);
define('WP_CHINA_YES_VERSION', '1.0.0');

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

        $url = str_replace('api.wordpress.org', 'api.w.org.ibadboy.net', $url);
        $url = str_replace('downloads.wordpress.org', 'd.w.org.ibadboy.net', $url);

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

        // If we are streaming to a file but no filename was given drop it in the WP temp dir
        // and pick its name using the basename of the $url
        if ($r['stream']) {
            if (empty($r['filename'])) {
                $r['filename'] = get_temp_dir() . basename($url);
            }

            // Force some settings if we are streaming to a file and check for existence and perms of destination directory
            $r['blocking'] = true;
            if ( ! wp_is_writable(dirname($r['filename']))) {
                return new WP_Error('http_request_failed', __('Destination directory for file streaming does not exist or is not writable.'));
            }
        }

        if (is_null($r['headers'])) {
            $r['headers'] = array();
        }

        // WP allows passing in headers as a string, weirdly.
        if ( ! is_array($r['headers'])) {
            $processedHeaders = WP_Http::processHeaders($r['headers']);
            $r['headers']     = $processedHeaders['headers'];
        }

        // Setup arguments
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

        // Use byte limit, if we can
        if (isset($r['limit_response_size'])) {
            $options['max_bytes'] = $r['limit_response_size'];
        }

        // If we've got cookies, use and convert them to Requests_Cookie.
        if ( ! empty($r['cookies'])) {
            $options['cookies'] = WP_Http::normalize_cookies($r['cookies']);
        }

        // SSL certificate handling
        if ( ! $r['sslverify']) {
            $options['verify']     = false;
            $options['verifyname'] = false;
        } else {
            $options['verify'] = $r['sslcertificates'];
        }

        // All non-GET/HEAD requests should put the arguments in the form body.
        if ('HEAD' !== $type && 'GET' !== $type) {
            $options['data_format'] = 'body';
        }

        /**
         * Filters whether SSL should be verified for non-local requests.
         *
         * @param bool $ssl_verify Whether to verify the SSL connection. Default true.
         * @param string $url The request URL.
         *
         * @since 2.8.0
         * @since 5.1.0 The `$url` parameter was added.
         *
         */
        $options['verify'] = apply_filters('https_ssl_verify', $options['verify'], $url);

        // Check for proxies.
        $proxy = new WP_HTTP_Proxy();
        if ($proxy->is_enabled() && $proxy->send_through_proxy($url)) {
            $options['proxy'] = new Requests_Proxy_HTTP($proxy->host() . ':' . $proxy->port());

            if ($proxy->use_authentication()) {
                $options['proxy']->use_authentication = true;
                $options['proxy']->user               = $proxy->username();
                $options['proxy']->pass               = $proxy->password();
            }
        }

        // Avoid issues where mbstring.func_overload is enabled
        mbstring_binary_safe_encoding();

        try {
            $requests_response = Requests::request($url, $headers, $data, $type, $options);

            // Convert the response into an array
            $http_response = new WP_HTTP_Requests_Response($requests_response, $r['filename']);
            $response      = $http_response->to_array();

            // Add the original object to the array.
            $response['http_response'] = $http_response;
        } catch (Requests_Exception $e) {
            $response = new WP_Error('http_request_failed', $e->getMessage());
        }

        reset_mbstring_encoding();

        /**
         * Fires after an HTTP API response is received and before the response is returned.
         *
         * @param array|WP_Error $response HTTP response or WP_Error object.
         * @param string $context Context under which the hook is fired.
         * @param string $class HTTP transport used.
         * @param array $r HTTP request arguments.
         * @param string $url The request URL.
         *
         * @since 2.8.0
         *
         */
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

        /**
         * Filters the HTTP API response immediately before the response is returned.
         *
         * @param array $response HTTP response.
         * @param array $r HTTP request arguments.
         * @param string $url The request URL.
         *
         * @since 2.9.0
         *
         */
        return apply_filters('http_response', $response, $r, $url);
    }

    public static function plugin_row_meta($links, $file) {
        $base = plugin_basename(WP_CHINA_YES_BASE_FILE);
        if ($file == $base) {
            $links[] = '<a href="https://github.com/sunxiyuan/wp-china-yes">插件发布页</a>';
        }

        return $links;
    }
}