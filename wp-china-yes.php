<?php
/**
 * Plugin Name: WP-China-Yes
 * Description: 将你的WordPress接入本土生态体系中，这将为你提供一个更贴近中国人使用习惯的WordPress
 * Author: WP中国本土化社区
 * Author URI:https://wp-china.org/
 * Version: 3.1.3
 * Network: True
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

(new WP_CHINA_YES)->init();

class WP_CHINA_YES {
    private $page_url;

    public function __construct() {
        $this->page_url = network_admin_url(is_multisite() ? 'settings.php?page=wp-china-yes' : 'options-general.php?page=wp-china-yes');
    }

    public function init() {
        if (is_admin() && ! (defined('DOING_AJAX') && DOING_AJAX)) {
            /**
             * 插件列表项目中增加设置项
             */
            add_filter(sprintf('%splugin_action_links_%s', is_multisite() ? 'network_admin_' : '', plugin_basename(__FILE__)), function ($links) {
                return array_merge(
                    [sprintf('<a href="%s">%s</a>', $this->page_url, '设置')],
                    $links
                );
            });


            /**
             * 初始化设置项
             */
            if (empty(get_option('wpapi')) || empty(get_option('super_admin')) || empty(get_option('super_gravatar')) || empty(get_option('super_googlefonts'))) {
                update_option("wpapi", get_option('wpapi') ?: '2');
                update_option("super_admin", get_option('super_admin') ?: '1');
                update_option("super_gravatar", get_option('super_gravatar') ?: '1');
                update_option("super_googlefonts", get_option('super_googlefonts') ?: '2');
            }


            /**
             * 禁用插件时删除配置
             */
            register_deactivation_hook(__FILE__, function () {
                delete_option("wpapi");
                delete_option("super_admin");
                delete_option("super_gravatar");
                delete_option("super_googlefonts");
            });


            /**
             * 菜单注册
             */
            add_action(is_multisite() ? 'network_admin_menu' : 'admin_menu', function () {
                add_submenu_page(
                    is_multisite() ? 'settings.php' : 'options-general.php',
                    'WP-China-Yes',
                    'WP-China-Yes',
                    is_multisite() ? 'manage_network_options' : 'manage_options',
                    'wp-china-yes',
                    [$this, 'options_page_html']
                );
            });


            /**
             * 将WordPress核心所依赖的静态文件访问链接替换为公共资源节点
             */
            if (get_option('super_admin') == 1) {
                add_action('init', function () {
                    ob_start(function ($buffer) {
                        return preg_replace('~' . home_url('/') . '(wp-admin|wp-includes)/(css|js)/~', sprintf('https://a2.wp-china-yes.net/WordPress@%s/$1/$2/', $GLOBALS['wp_version']), $buffer);
                    });
                });
            }
        }


        if (is_admin()) {
            add_action('admin_init', function () {
                /**
                 * wpapi用以标记用户所选的仓库api，数值说明：1 使用由WP-China.org提供的国区定制API，2 只是经代理加速的api.wordpress.org原版API
                 */
                register_setting('wpcy', 'wpapi');

                /**
                 * super_admin用以标记用户是否启用管理后台加速功能
                 */
                register_setting('wpcy', 'super_admin');

                /**
                 * super_gravatar用以标记用户是否启用G家头像加速功能
                 */
                register_setting('wpcy', 'super_gravatar');

                /**
                 * super_googlefonts用以标记用户是否启用谷歌字体加速功能
                 */
                register_setting('wpcy', 'super_googlefonts');

                add_settings_section(
                    'wpcy_section_main',
                    '将你的WordPress接入本土生态体系中，这将为你提供一个更贴近中国人使用习惯的WordPress',
                    '',
                    'wpcy'
                );

                add_settings_field(
                    'wpcy_field_select_wpapi',
                    '选择应用市场',
                    [$this, 'field_wpapi_cb'],
                    'wpcy',
                    'wpcy_section_main'
                );

                add_settings_field(
                    'wpcy_field_select_super_admin',
                    '管理后台加速',
                    [$this, 'field_super_admin_cb'],
                    'wpcy',
                    'wpcy_section_main'
                );

                add_settings_field(
                    'wpcy_field_select_super_gravatar',
                    '加速G家头像',
                    [$this, 'field_super_gravatar_cb'],
                    'wpcy',
                    'wpcy_section_main'
                );

                add_settings_field(
                    'wpcy_field_select_super_googlefonts',
                    '加速谷歌字体',
                    [$this, 'field_super_googlefonts_cb'],
                    'wpcy',
                    'wpcy_section_main'
                );
            });

            /**
             * 替换api.wordpress.org和downloads.wordpress.org为WP-China.org维护的大陆加速节点
             * URL替换代码来自于我爱水煮鱼(http://blog.wpjam.com/)开发的WPJAM Basic插件
             */
            add_filter('pre_http_request', function ($preempt, $r, $url) {
                if (( ! stristr($url, 'api.wordpress.org') && ! stristr($url, 'downloads.wordpress.org')) || get_option('wpapi') == 3) {
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


        if ( ! (defined('DOING_AJAX') && DOING_AJAX)) {
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
        }
    }

    public function field_wpapi_cb() {
        $wpapi = get_option('wpapi');
        ?>
        <label>
            <input type="radio" value="2" name="wpapi" <?php checked($wpapi, '2'); ?>>官方应用市场加速镜像
        </label>
        <label>
            <input type="radio" value="1" name="wpapi" <?php checked($wpapi, '1'); ?>>本土应用市场（技术试验）
        </label>
        <label>
            <input type="radio" value="3" name="wpapi" <?php checked($wpapi, '3'); ?>>不接管应用市场
        </label>
        <p class="description">
            <b>官方应用市场加速镜像</b>：直接从官方反代并在大陆分发，除了增加对WP-China-Yes插件的更新支持外未做任何更改
        </p>
        <p class="description">
            <b>本土应用市场</b>：与<a href="https://translate.wp-china.org/" target="_blank">本土翻译平台</a>深度整合，为大家提供基于AI翻译+人工辅助校准的全量作品汉化支持<b>（注意，这仍属于试验阶段，存在可能的接口报错、速度缓慢等问题，<a href="https://wp-china.org/forums/forum/228" target="_blank">问题反馈</a>）</b>
        </p>
        <?php
    }

    public function field_super_admin_cb() {
        $super_admin = get_option('super_admin');
        ?>
        <label>
            <input type="radio" value="1" name="super_admin" <?php checked($super_admin, '1'); ?>>启用
        </label>
        <label>
            <input type="radio" value="2" name="super_admin" <?php checked($super_admin, '2'); ?>>禁用
        </label>
        <p class="description">
            将WordPress核心所依赖的静态文件切换为公共资源，此选项极大的加快管理后台访问速度
        </p>
        <?php
    }

    public function field_super_gravatar_cb() {
        $super_gravatar = get_option('super_gravatar');
        ?>
        <label>
            <input type="radio" value="1" name="super_gravatar" <?php checked($super_gravatar, '1'); ?>>启用
        </label>
        <label>
            <input type="radio" value="2" name="super_gravatar" <?php checked($super_gravatar, '2'); ?>>禁用
        </label>
        <p class="description">
            为Gravatar头像加速，推荐所有用户启用该选项
        </p>
        <?php
    }

    public function field_super_googlefonts_cb() {
        $super_googlefonts = get_option('super_googlefonts');
        ?>
        <label>
            <input type="radio" value="1" name="super_googlefonts" <?php checked($super_googlefonts, '1'); ?>>启用
        </label>
        <label>
            <input type="radio" value="2" name="super_googlefonts" <?php checked($super_googlefonts, '2'); ?>>禁用
        </label>
        <p class="description">
            请只在主题包含谷歌字体的情况下才启用该选项，以免造成不必要的性能损失
        </p>
        <?php
    }

    public function options_page_html() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            update_option("wpapi", sanitize_text_field($_POST['wpapi']));
            update_option("super_admin", sanitize_text_field($_POST['super_admin']));
            update_option("super_gravatar", sanitize_text_field($_POST['super_gravatar']));
            update_option("super_googlefonts", sanitize_text_field($_POST['super_googlefonts']));

            echo '<div class="notice notice-success settings-error is-dismissible"><p><strong>设置已保存</strong></p></div>';
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        settings_errors('wpcy_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="<?php echo $this->page_url; ?>" method="post">
                <?php
                settings_fields('wpcy');
                do_settings_sections('wpcy');
                submit_button('保存配置');
                ?>
            </form>
        </div>
        <p>
            <a href="https://wp-china.org" target="_blank">WP中国本土化社区</a>的使命是帮助WordPress在中国建立起良好的本土生态环境，以求推进行业整体发展，做大市场蛋糕。<br/>
            特别感谢<a href="https://zmingcx.com/" target="_blank">知更鸟</a>、<a href="https://www.weixiaoduo.com/" target="_blank">薇晓朵团队</a>、<a href="https://www.appnode.com/" target="_blank">AppNode</a>在项目萌芽期给予的帮助。
        </p>
        <?php
    }
}
