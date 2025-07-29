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
    }

    /**
     * 加载文风字体
     */
    public function load_windfonts() {
        echo <<<HTML
        <link rel="preconnect" href="//cn.windfonts.com">
        <!-- 此中文网页字体由文风字体（Windfonts）免费提供，您可以自由引用，请务必保留此授权许可标注 https://wenfeng.org/license -->
HTML;

        $loaded = [];
        foreach ((array) $this->settings['windfonts_list'] as $font) {
            if (empty($font['enable'])) {
                continue;
            }
            if (empty($font['family'])) {
                continue;
            }
            if (in_array($font['css'], $loaded)) {
                continue;
            }
            echo sprintf(<<<HTML
            <link rel="stylesheet" type="text/css" href="%s">
            <style>
            %s {
                font-style: %s;
                font-weight: %s;
                font-family: '%s',sans-serif!important;
            }
            </style>
HTML
                ,
                $font['css'],
                htmlspecialchars_decode($font['selector']),
                $font['style'],
                $font['weight'],
                $font['family']
            );
            $loaded[] = $font['css'];
        }
    }

    /**
     * 加载排印优化
     */
    public function load_typography() {
        // 支持中文排版段首缩进 2em
        if (in_array('indent', (array) $this->settings['windfonts_typography'])) {
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

        // 支持中文排版两端对齐
        if (in_array('align', (array) $this->settings['windfonts_typography'])) {
            add_action('wp_head', function () {
                if (is_single()) { // 仅在文章页面生效
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
    }
}