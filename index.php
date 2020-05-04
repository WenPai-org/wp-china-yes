<?php
/*
 * Plugin Name: WP-China-Yes
 * Description: 这是一个颠覆性的插件，她将全面改善中国大陆站点在访问WP官方服务时的用户体验，其原理是将位于国外的官方仓库源替换为由《WP中国区仓库源建设计划》维护的国内源，以此达到加速访问的目的。
 * Author: 《WP中国区仓库源建设计划》
 * Version: 2.2.0
 * Author URI:https://wp-china-yes.org/
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
register_activation_hook( __FILE__, function() {
    $check_incompatible = check_incompatible();
    if ( $check_incompatible  ) {
        set_transient( 'wp-china-yes-warning-notice', true, 5 );        
    }
});
add_action( 'admin_notices', function(){
    if( get_transient( 'wp-china-yes-warning-notice' ) ){
        echo check_incompatible()['error'];
        delete_transient( 'wp-china-yes-warning-notice' );
   }
} );
function check_incompatible(){
    $error = false;
    $html = '<div class="notice notice-warning is-dismissible"><h1>WP-CHINA-YES 启动提醒</h1>';
    $html .= '<ul>';
    if( ( defined('WP_PROXY_HOST') && defined('WP_PROXY_PORT') ) ){
        if(WP_PROXY_HOST=='us.centos.bz'){
            $error  = true;
            $html .= '<li>插件检测到您已开启代理，请参考<a href="https://www.centos.bz/2017/03/upgrade-wordpress-using-proxy-server/" target="_blank">https://www.centos.bz/2017/03/upgrade-wordpress-using-proxy-server/</a>关闭代理</li>';
        }
    }
	$html .= '</ul>';
    $incompatible_plugins = [
        'wpjam-basic/wpjam-basic.php',
        'cardui-x/cardui-x.php',
        'nicetheme-jimu/nc-plugins.php',
    ];
    if ( is_network_admin() ) {
        $active_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
        $active_plugins = array_keys( $active_plugins );
    } else{
        $active_plugins = (array) get_option( 'active_plugins' );
    }
	$matches = array_intersect( $active_plugins, $incompatible_plugins );
    if($matches){
        $error  = true;
        $html .= '<p>检测到您已启用下列提供类似功能的插件，请注意：您必须关闭这些插件中带有的wp代理更新、429错误解决等相关功能，以免系统更新被多次接管而产生错误。</p><ul>';
        foreach ( $matches as $matche ) {
			$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $matche );
            $html.= '<li>'.$data["Name"].'</li>';
        }
    }
    $html .= '</ul></div>';
    if($error) return ['error' => $html];
}
(new WP_CHINA_YES)->init();

class WP_CHINA_YES {
    private $wp_china_yes_options = [];

    public function init() {
        add_filter('pre_http_request', [$this, 'pre_http_request'], 10, 3);
        $post_action = isset($_POST['action']) ? sanitize_text_field(trim($_POST['action'])) : ' ';
        if (defined('DOING_AJAX') && DOING_AJAX && ! in_array($post_action, ['wpcy_set_config', 'wpcy_get_config'])) {
            return;
        }
        if (is_admin()) {
            $this->wp_china_yes_options = get_option('wp_china_yes_options');
            if (empty($this->wp_china_yes_options)) {
                self::set_wp_option();
            }
            register_deactivation_hook(__FILE__, [$this, 'wp_china_yes_deactivate']);
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
        $base = plugin_basename(__FILE__);
        if ($file == $base) {
            $links[] = '<a target="_blank" href="https://wp-china-yes.org/thread-20.htm">集成此插件到自己的产品中</a>';
            $links[] = '<a target="_blank" href="https://wp-china-yes.org/thread-7.htm">参与项目发展</a>';
            $links[] = '<a target="_blank" href="https://github.com/wp-china-yes">GitHub</a>';
        }

        return $links;
    }

    public function action_links($links, $file) {
        if ($file != plugin_basename(__FILE__)) {
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
        echo <<<EOT
<div class="wrap">
    <h1>WP-China-Yes</h1>
    <form method="post" id="mirrors" style="display: none;">
        <table class="form-table" role="presentation">
            <tbody>
                <p>这是一个颠覆性的插件，她将全面改善中国大陆站点在访问WP官方服务时的用户体验<br />
                    原理是将位于国外的官方仓库源替换为由社区志愿者维护的国内源，以此达到加速访问的目的</p>
                <tr>
                    <th scope="row">源：</th>
                    <td>
                        <fieldset>
                            <p>
                                <label><input name="select_mirrors" type="radio" value="0" checked="checked">
                                    社区源</label>&nbsp;&nbsp;&nbsp;
                                <label><input name="select_mirrors" type="radio" value="1">自定义源</label>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>

        <table id="community" class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="community_mirrors">社区源</label></th>
                    <td>
                        <select name="community_mirrors" id="community_mirrors" class="postform">
                            <option class="level-0" value="0">主源</option>
                            <option class="level-0" value="1">备源</option>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>

        <table id="custom" class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="api">API</label></th>
                    <td>
                        <input name="api" type="text" id="api" value="" class="regular-text ltr">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="download">Download</label></th>
                    <td>
                        <input name="download" type="text" id="download" value="" class="regular-text ltr">
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit"><button type="button" name="submit" id="submit" class="button button-primary">保存更改</button>
        </p>
    </form>

    <p>这是一个开源项目，她需要每个人的支持和贡献才能健康长久的发展。<br />项目地址：<a target="_blank"
            href="https://github.com/sunxiyuan/wp-china-yes">GitHub</a></p>
</div>
<script>
    const root_url = window.location.href.split('wp-admin')[0];

    function getCookie(name) {
        let arr, reg = new RegExp("(^| )" + name + "=([^;]*)(;|$)");
        arr = document.cookie.match(reg);
        if (arr)
            return (decodeURIComponent(arr[2]));
        else
            return null;
    }

    var token = JSON.parse(getCookie('wp-china-yes'));

    jQuery.ajax({
        type: 'post',
        url: root_url + 'wp-admin/admin-ajax.php',
        cache: false,
        data: {
            '_ajax_nonce': token.get_config,
            'action': 'wpcy_get_config',
        },
        success: function (data) {
            jQuery("#mirrors").show();
            if (data.data.custom_api_server == '' || data.data.custom_download_server == '') {
                jQuery('select[name="community_mirrors"]').val(data.data.community);
                jQuery('#custom').hide();
                jQuery('#community').show();
            } else {
                jQuery('input:radio[name="select_mirrors"]').val(['1']);
                jQuery('#api').val(data.data.custom_api_server);
                jQuery('#download').val(data.data.custom_api_server);
                jQuery('#community').hide();
                jQuery('#custom').show();

            }
        },
        error: function () {
            alert('请求失败，请刷新重试')
        }
    })

    jQuery('input:radio[name="select_mirrors"]').change(function () {
        var select_mirror = jQuery(this).val();
        if (select_mirror == 0) {
            jQuery('#custom').hide();
            jQuery('#community').show();
        } else if (select_mirror == 1) {
            jQuery('#community').hide();
            jQuery('#custom').show();
        }

    });

    jQuery('#submit').click(function () {
        var select_mirrors = jQuery('input:radio:checked').val();

        if (select_mirrors == 0) {
            var api = null;
            var download = null;
        } else {
            var api = jQuery('#api').val();
            var download = jQuery('#download').val();
        }

        jQuery.ajax({
            type: 'post',
            url: root_url + 'wp-admin/admin-ajax.php',
            cache: false,
            data: {
                '_ajax_nonce': token.set_config,
                'action': 'wpcy_set_config',
                'community': jQuery('#community_mirrors').val(),
                'custom_api_server': api,
                'custom_download_server': download,
            },
            success: function (data) {
                alert('保存成功');
            },
            error: function () {
                alert('保存失败，请刷新重试');
            }
        })

    });
</script>
EOT;
    }

    public function set_cookie() {
        if (current_user_can('manage_options')) {
            setcookie('wp-china-yes', json_encode([
                'get_config' => wp_create_nonce('wpcy_get_config'),
                'set_config' => wp_create_nonce('wpcy_set_config')
            ], JSON_UNESCAPED_UNICODE), time() + 1209600, COOKIEPATH, COOKIE_DOMAIN, false);
        }
    }

    public function get_config() {
	check_ajax_referer('wpcy_get_config');
        self::success('', $this->wp_china_yes_options);
    }

    public function set_config() {
	check_ajax_referer('wpcy_set_config');
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

    private static function set_wp_option($community = 0, $custom_api_server = '', $custom_download_server = '') {
        update_option("wp_china_yes_options", [
            'community'              => (int) $community,
            'custom_api_server'      => $custom_api_server,
            'custom_download_server' => $custom_download_server
        ]);
    }
}
