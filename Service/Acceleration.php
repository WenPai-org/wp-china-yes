<?php

namespace WenPai\ChinaYes\Service;

defined('ABSPATH') || exit;

use function WenPai\ChinaYes\get_settings;

class Acceleration {
    private $settings;

    public function __construct() {
        $this->settings = get_settings();
        $this->init();
    }

    /**
     * 初始化 admincdn 功能
     */
    private function init() {
        if (!empty($this->settings['admincdn'])) {
            add_action('wp_head', function () {
                echo "<!-- 此站点使用的前端静态资源库由萌芽加速（adminCDN）提供，智能加速转接基于文派叶子 WPCY.COM  -->\n";
            }, 1);
        }

        $this->load_admincdn();
    }

    /**
     * 加载 admincdn 功能
     */
    private function load_admincdn() {
        // 确保 $this->settings 中包含必要的键
        $this->settings['admincdn_files'] = $this->settings['admincdn_files'] ?? [];
        $this->settings['admincdn_public'] = $this->settings['admincdn_public'] ?? [];
        $this->settings['admincdn_dev'] = $this->settings['admincdn_dev'] ?? [];

        // WordPress 核心静态文件链接替换
        if (is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
            if (
                in_array('admin', (array) $this->settings['admincdn']) &&
                !stristr($GLOBALS['wp_version'], 'alpha') &&
                !stristr($GLOBALS['wp_version'], 'beta') &&
                !stristr($GLOBALS['wp_version'], 'RC')
            ) {
                // 禁用合并加载，以便于使用公共资源节点
                global $concatenate_scripts;
                $concatenate_scripts = false;

                $this->page_str_replace('init', 'preg_replace', [
                    '~' . home_url('/') . '(wp-admin|wp-includes)/(css|js)/~',
                    sprintf('https://wpstatic.admincdn.com/%s/$1/$2/', $GLOBALS['wp_version'])
                ]);
            }
        }

        // 前台静态加速
        if (in_array('frontend', (array) $this->settings['admincdn_files'])) {
            $this->page_str_replace('template_redirect', 'preg_replace', [
                '#(?<=[(\"\'])(?:' . quotemeta(home_url()) . ')?/(?:((?:wp-content|wp-includes)[^\"\')]+\.(css|js)[^\"\')]+))(?=[\"\')])#',
                'https://public.admincdn.com/$0'
            ]);
        }

        // Google 字体替换
        if (in_array('googlefonts', (array) $this->settings['admincdn_public'])) {
            $this->page_str_replace('init', 'str_replace', [
                'fonts.googleapis.com',
                'googlefonts.admincdn.com'
            ]);
        }

        // Google 前端公共库替换
        if (in_array('googleajax', (array) $this->settings['admincdn_public'])) {
            $this->page_str_replace('init', 'str_replace', [
                'ajax.googleapis.com',
                'googleajax.admincdn.com'
            ]);
        }

        // CDNJS 前端公共库替换
        if (in_array('cdnjs', (array) $this->settings['admincdn_public'])) {
            $this->page_str_replace('init', 'str_replace', [
                'cdnjs.cloudflare.com/ajax/libs',
                'cdnjs.admincdn.com'
            ]);
        }

        // jsDelivr 前端公共库替换
        if (in_array('jsdelivr', (array) $this->settings['admincdn_public'])) {
            $this->page_str_replace('init', 'str_replace', [
                'cdn.jsdelivr.net',
                'jsd.admincdn.com'
            ]);
        }

        // BootstrapCDN 前端公共库替换
        if (in_array('bootstrapcdn', (array) $this->settings['admincdn_public'])) {
            $this->page_str_replace('init', 'str_replace', [
                'maxcdn.bootstrapcdn.com',
                'jsd.admincdn.com'
            ]);
        }

        // Emoji 资源加速 - 使用 Twitter Emoji
        if (in_array('emoji', (array) $this->settings['admincdn_files'])) {
            $this->replace_emoji();
        }

        // WordPress.org 预览资源加速
        if (in_array('sworg', (array) $this->settings['admincdn_files'])) {
            $this->replace_sworg();
        }

        // React 前端库加速
        if (in_array('react', (array) $this->settings['admincdn_dev'])) {
            $this->page_str_replace('init', 'str_replace', [
                'unpkg.com/react',
                'jsd.admincdn.com/npm/react'
            ]);
        }

        // jQuery 前端库加速
        if (in_array('jquery', (array) $this->settings['admincdn_dev'])) {
            $this->page_str_replace('init', 'str_replace', [
                'code.jquery.com',
                'jsd.admincdn.com/npm/jquery'
            ]);
        }

        // Vue.js 前端库加速
        if (in_array('vuejs', (array) $this->settings['admincdn_dev'])) {
            $this->page_str_replace('init', 'str_replace', [
                'unpkg.com/vue',
                'jsd.admincdn.com/npm/vue'
            ]);
        }

        // DataTables 前端库加速
        if (in_array('datatables', (array) $this->settings['admincdn_dev'])) {
            $this->page_str_replace('init', 'str_replace', [
                'cdn.datatables.net',
                'jsd.admincdn.com/npm/datatables.net'
            ]);
        }

        // Tailwind CSS 加速
        if (in_array('tailwindcss', (array) $this->settings['admincdn_dev'])) {
            $this->page_str_replace('init', 'str_replace', [
                'unpkg.com/tailwindcss',
                'jsd.admincdn.com/npm/tailwindcss'
            ]);
        }
    }

    /**
     * 替换 Emoji 资源
     */
    private function replace_emoji() {
        // 禁用 WordPress 默认的 emoji 处理
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

        // 替换 emoji 图片路径
        $this->page_str_replace('init', 'str_replace', [
            's.w.org/images/core/emoji',
            'jsd.admincdn.com/npm/@twemoji/api/dist'
        ]);

        // 替换 wpemojiSettings 配置
        add_action('wp_head', function () {
            ?>
            <script>
                window._wpemojiSettings = {
                    "baseUrl": "https://jsd.admincdn.com/npm/@twemoji/api@15.0.3/dist/72x72/",
                    "ext": ".png",
                    "svgUrl": "https://jsd.admincdn.com/npm/@twemoji/api@15.0.3/dist/svg/",
                    "svgExt": ".svg",
                    "source": {
                        "concatemoji": "<?php echo includes_url('js/wp-emoji-release.min.js'); ?>"
                    }
                };
            </script>
            <?php
        }, 1);
    }

    /**
     * 替换 WordPress.org 预览资源
     */
    private function replace_sworg() {
        $this->page_str_replace('init', 'str_replace', [
            'ts.w.org',
            'ts.wenpai.net'
        ]);

        // 替换主题预览图片 URL
        add_filter('theme_screenshot_url', function ($url) {
            if (strpos($url, 'ts.w.org') !== false) {
                $url = str_replace('ts.w.org', 'ts.wenpai.net', $url);
            }
            return $url;
        });

        // 过滤主题 API 响应
        add_filter('themes_api_result', function ($res, $action, $args) {
            if (is_object($res) && !empty($res->screenshots)) {
                foreach ($res->screenshots as &$screenshot) {
                    if (isset($screenshot->src)) {
                        $screenshot->src = str_replace('ts.w.org', 'ts.wenpai.net', $screenshot->src);
                    }
                }
            }
            return $res;
        }, 10, 3);

        // 替换页面内容
        add_action('admin_init', function () {
            ob_start(function ($content) {
                return str_replace('ts.w.org', 'ts.wenpai.net', $content);
            });
        });

        // 确保前台内容替换
        add_action('template_redirect', function () {
            ob_start(function ($content) {
                return str_replace('ts.w.org', 'ts.wenpai.net', $content);
            });
        });
    }

    /**
     * 页面字符串替换
     *
     * @param string $hook      钩子名称
     * @param string $replace_func 替换函数
     * @param array  $param     替换参数
     */
    private function page_str_replace($hook, $replace_func, $param) {
        if (php_sapi_name() == 'cli') {
            return;
        }
        add_action($hook, function () use ($replace_func, $param) {
            ob_start(function ($buffer) use ($replace_func, $param) {
                $param[] = $buffer;
                return call_user_func_array($replace_func, $param);
            });
        }, PHP_INT_MAX);
    }
}