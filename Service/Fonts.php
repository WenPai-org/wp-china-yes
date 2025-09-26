<?php

namespace WenPai\ChinaYes\Service;

defined('ABSPATH') || exit;

use function WenPai\ChinaYes\get_settings;

/**
 * Class Fonts
 * 文风字体服务
 * @package WenPai\ChinaYes\Service
 */
class Fonts {

    private $settings;

    public function __construct() {
        $this->settings = get_settings();
        $this->init();
    }

    /**
     * 初始化文风字体功能
     */
    private function init() {
        if (!empty($this->settings['windfonts']) && $this->settings['windfonts'] != 'off') {
            $this->load_typography();
        }

        if (!empty($this->settings['windfonts']) && $this->settings['windfonts'] == 'optimize') {
            add_action('init', function () {
                wp_enqueue_style('windfonts-optimize', CHINA_YES_PLUGIN_URL . 'assets/css/fonts.css', [], CHINA_YES_VERSION);
            });
        }

        if (!empty($this->settings['windfonts']) && $this->settings['windfonts'] == 'on') {
            add_action('wp_head', [$this, 'load_windfonts']);
            add_action('admin_head', [$this, 'load_windfonts']);
        }

        if (!empty($this->settings['windfonts']) && $this->settings['windfonts'] == 'frontend') {
            add_action('wp_head', [$this, 'load_windfonts']);
        }

        $this->init_rtl_mirror();
    }

    /**
     * 初始化RTL镜像测试功能
     */
    private function init_rtl_mirror() {
        if (empty($this->settings['windfonts_reading_enable'])) {
            return;
        }

        $reading_setting = $this->settings['windfonts_reading'] ?? 'off';

        if ($reading_setting === 'global' || $reading_setting === 'frontend') {
            $this->load_rtl_mirror($reading_setting);
        }
    }

    /**
     * 加载文风字体
     */
    public function load_windfonts() {
        static $license_shown = false;
        
        if (!$license_shown) {
            echo <<<HTML
        <link rel="preconnect" href="https://cn.windfonts.com">
        <!-- 此中文网页字体由文风字体（Windfonts）免费提供，您可以自由引用，请务必保留此授权许可标注 https://wenfeng.org/license -->
HTML;
            $license_shown = true;
        }

        $loaded = [];
        foreach ((array) $this->settings['windfonts_list'] as $font) {
            if (empty($font['enable'])) {
                continue;
            }
            if (empty($font['family'])) {
                continue;
            }
            
            $css_url = $this->build_font_css_url($font);
            
            if (in_array($css_url, $loaded)) {
                continue;
            }
            
            $font_family = $this->extract_font_family_name($font['family']);
            
            echo sprintf(<<<HTML
            <link rel="stylesheet" type="text/css" crossorigin="anonymous" href="%s">
            <style>
            %s {
                font-style: %s;
                font-weight: %s;
                font-family: '%s',sans-serif!important;
            }
            </style>
HTML
                ,
                $css_url,
                htmlspecialchars_decode($font['selector']),
                $font['style'] ?? 'normal',
                $font['weight'] ?? 400,
                $font_family
            );
            $loaded[] = $css_url;
        }
    }

    /**
     * 构建字体CSS URL
     */
    private function build_font_css_url($font) {
        $base_url = 'https://app.windfonts.com/api/css';
        $params = [];
        
        $params['family'] = $font['family'];
        
        if (!empty($font['subset'])) {
            $params['subset'] = $font['subset'];
        }
        
        if (!empty($font['lang'])) {
            $params['lang'] = $font['lang'];
        }
        
        return $base_url . '?' . http_build_query($params);
    }

    /**
     * 提取字体家族名称
     */
    private function extract_font_family_name($family_param) {
        if (strpos($family_param, ':') !== false) {
            return explode(':', $family_param)[0];
        }
        return $family_param;
    }

    /**
     * 加载排印优化
     */
    public function load_typography() {
        $this->load_chinese_typography();
        $this->load_english_typography();
    }

    /**
     * 加载中文排印优化
     */
    private function load_chinese_typography() {
        $cn_settings = (array) $this->settings['windfonts_typography_cn'];

        if (in_array('indent', $cn_settings)) {
            add_action('wp_head', function () {
                echo '<style>
                .entry-content p {
                    text-indent: 2em;
                }
                .entry-content .wp-block-group p,
                .entry-content .wp-block-columns p,
                .entry-content .wp-block-media-text p,
                .entry-content .wp-block-quote p {
                    text-indent: 0;
                }
                </style>';
            });
        }

        if (in_array('align', $cn_settings)) {
            add_action('wp_head', function () {
                if (is_single()) {
                    echo '<style>
                    .entry-content p {
                        text-align: justify;
                    }
                    .entry-content .wp-block-group p,
                    .entry-content .wp-block-columns p,
                    .entry-content .wp-block-media-text p,
                    .entry-content .wp-block-quote p {
                        text-align: unset !important;
                    }
                    .entry-content .wp-block-columns .has-text-align-center {
                        text-align: center !important;
                    }
                    </style>';
                }
            });
        }

        if (in_array('corner', $cn_settings)) {
            add_action('wp_head', function () {
                echo '<style>
                .entry-content {
                    font-feature-settings: "halt" 1;
                }
                </style>';
            });
        }

        if (in_array('space', $cn_settings)) {
            add_action('wp_head', function () {
                echo '<style>
                .entry-content {
                    word-spacing: 0.1em;
                    letter-spacing: 0.05em;
                }
                </style>';
            });
        }

        if (in_array('punctuation', $cn_settings)) {
            add_action('wp_head', function () {
                echo '<style>
                .entry-content {
                    text-spacing: trim-start;
                    hanging-punctuation: first last;
                }
                </style>';
            });
        }
    }

    /**
     * 加载英文排印优化
     */
    private function load_english_typography() {
        $en_settings = (array) $this->settings['windfonts_typography_en'];

        if (in_array('optimize', $en_settings)) {
            add_action('wp_head', function () {
                echo '<style>
                .entry-content {
                    text-rendering: optimizeLegibility;
                    font-variant-ligatures: common-ligatures;
                    font-variant-numeric: oldstyle-nums;
                }
                </style>';
            });
        }

        if (in_array('spacing', $en_settings)) {
            add_action('wp_head', function () {
                echo '<style>
                .entry-content {
                    white-space: pre-line;
                }
                .entry-content p {
                    white-space: normal;
                }
                </style>';
            });
        }

        if (in_array('orphan', $en_settings)) {
            add_action('wp_head', function () {
                echo '<style>
                .entry-content p {
                    orphans: 3;
                }
                </style>';
            });
        }

        if (in_array('widow', $en_settings)) {
            add_action('wp_head', function () {
                echo '<style>
                .entry-content p {
                    widows: 3;
                }
                </style>';
            });
        }

        if (in_array('punctuation', $en_settings)) {
            add_action('wp_head', function () {
                echo '<style>
                .entry-content {
                    font-feature-settings: "kern" 1, "liga" 1, "clig" 1;
                }
                </style>';
            });
        }
    }



    /**
     * 加载RTL镜像测试功能
     */
    private function load_rtl_mirror($mode = 'global') {
        $rtl_styles = '<style type="text/css" media="screen">
        html {
            transform: scaleX(-1);
        }
        html::after {
            content: "RTL镜像测试模式";
            position: fixed;
            display: inline-block;
            left: 50%;
            top: -3px;
            padding: 10px 20px;
            font-size: 12px;
            font-family: sans-serif;
            text-transform: uppercase;
            background: #21759b;
            color: #fff;
            white-space: nowrap;
            z-index: 9999999;
            border-radius: 3px;
            transform: scaleX(-1) translateX(50%);
            transform-origin: 50% 0;
        }
        #wpadminbar { margin-top: -32px; }
        .wp-admin #wpadminbar { margin-top: 0; }
        </style>';

        if ($mode === 'global') {
            add_action('wp_head', function () use ($rtl_styles) {
                echo $rtl_styles;
            }, 9999);

            add_action('admin_print_styles', function () use ($rtl_styles) {
                echo $rtl_styles;
            }, 9999);
        } elseif ($mode === 'frontend') {
            add_action('wp_head', function () use ($rtl_styles) {
                echo $rtl_styles;
            }, 9999);
        }
    }


}