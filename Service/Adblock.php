<?php

namespace WenPai\ChinaYes\Service;

defined('ABSPATH') || exit;

use function WenPai\ChinaYes\get_settings;

/**
 * Class Adblock
 * 广告拦截服务
 * @package WenPai\ChinaYes\Service
 */
class Adblock {

    private $settings;

    public function __construct() {
        $this->settings = get_settings();
        $this->init();
    }

    /**
     * 初始化广告拦截功能
     */
    private function init() {
        if (!empty($this->settings['adblock']) && $this->settings['adblock'] == 'on') {
            add_action('admin_head', [$this, 'load_adblock']);
        }
    }

    /**
     * 加载广告拦截
     */
    public function load_adblock() {
        // 处理广告拦截规则
        foreach ((array) $this->settings['adblock_rule'] as $rule) {
            if (empty($rule['enable']) || empty($rule['selector'])) {
                continue;
            }
            echo sprintf('<style>%s{display:none!important;}</style>',
                htmlspecialchars_decode($rule['selector'])
            );
        }
    }
}