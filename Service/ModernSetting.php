<?php

namespace WenPai\ChinaYes\Service;

use WenPai\ChinaYes\Service\TranslationManager;
use WenPai\ChinaYes\Service\LazyTranslation;
use WP_CHINA_YES;
use function WenPai\ChinaYes\get_settings;

class ModernSetting {
    
    private $settings;
    private $prefix = 'wp_china_yes';
    
    public function __construct() {
        $this->settings = get_settings();
        add_filter( 'wp_china_yes_enqueue_assets', '__return_true' );
        add_filter( 'wp_china_yes_fa4', '__return_true' );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', [ $this, 'admin_menu' ] );
        
        // 延迟到 init 动作后执行
        add_action( 'init', [ $this, 'admin_init' ], 20 );
    }
    
    public function admin_init() {
        // 确保翻译已加载
        if (!TranslationManager::isLoaded()) {
            TranslationManager::getInstance()->loadTranslations();
        }
        
        $this->setupFramework();
    }
    
    private function setupFramework() {
        $enabled_sections = $this->settings['enable_sections'] ?? [];
        
        if (in_array('store', $enabled_sections)) {
            WP_CHINA_YES::createSection( $this->prefix, [
                'title'  => $this->t('应用市场'),
                'icon'   => 'icon icon-shop',
                'fields' => [
                    [
                        'id'       => 'store',
                        'type'     => 'radio',
                        'title'    => $this->t('应用市场'),
                        'inline'   => true,
                        'options'  => [
                            'wenpai' => $this->t('文派开源'),
                            'proxy'  => $this->t('官方镜像'),
                            'off'    => $this->t('不启用')
                        ],
                        'default'  => 'wenpai',
                        'subtitle' => '是否启用市场加速',
                        'desc'     => '<a href="https://wenpai.org/" target="_blank">文派开源（WenPai.org）</a>中国境内自建托管仓库，同时集成文派翻译平台。<a href="https://wpmirror.com/" target="_blank">官方加速源（WPMirror）</a>直接从 .org 反代至大陆分发；可参考<a href="https://wpcy.com/document/wordpress-marketplace-acceleration" target="_blank">源站说明</a>。',
                    ],
                    [
                        'id'       => 'bridge',
                        'type'     => 'switcher',
                        'default'  => true,
                        'title'    => '云桥更新',
                        'subtitle' => '是否启用更新加速',
                        'desc'     => '<a href="https://wpbridge.com" target="_blank">文派云桥（wpbridge）</a>托管更新和应用分发渠道，可解决因 WordPress 社区分裂导致的混乱、旧应用无法更新，频繁 API 请求拖慢网速等问题。',
                    ],
                    [
                        'id'       => 'arkpress',
                        'type'     => 'switcher',
                        'default'  => false,
                        'title'    => '联合存储库',
                        'subtitle' => '自动监控加速节点可用性',
                        'desc'     => '<a href="https://maiyun.org" target="_blank">ArkPress.org </a>支持自动监控各加速节点可用性，当节点不可用时自动切换至可用节点或关闭加速，以保证您的网站正常访问',
                    ],
                ],
            ] );
        }
        
        if (in_array('admincdn', $enabled_sections)) {
            WP_CHINA_YES::createSection( $this->prefix, [
                'title'  => $this->t('萌芽加速'),
                'icon'   => 'icon icon-flash-1',
                'fields' => [
                    [
                        'id'       => 'admincdn_public',
                        'type'     => 'checkbox',
                        'title'    => $this->t('萌芽加速'),
                        'inline'   => true,
                        'options'  => [
                            'googlefonts'    => 'Google 字体',
                            'googleajax'     => 'Google 前端库',
                            'cdnjs'          => 'CDNJS 前端库',
                            'jsdelivr'       => 'jsDelivr 前端库',
                            'bootstrapcdn'   => 'Bootstrap 前端库',
                        ],
                        'default'  => [
                            'googlefonts'    => 'googlefonts',
                            'googleajax'     => 'googleajax',
                            'cdnjs'          => 'cdnjs',
                            'jsdelivr'       => 'jsdelivr',
                            'bootstrapcdn'   => 'bootstrapcdn',
                        ],
                        'subtitle' => '是否启用前端公共库加速',
                        'desc'     => '启用后，将自动替换前端页面中的 Google Fonts、Google Ajax、CDNJS、jsDelivr、Bootstrap 等公共库为国内加速节点。',
                    ],
                    [
                        'id'       => 'admincdn_files',
                        'type'     => 'checkbox',
                        'title'    => $this->t('文件加速'),
                        'inline'   => true,
                        'options'  => [
                            'gravatar'       => 'Gravatar 头像',
                            'emoji'          => 'Emoji 表情',
                            'googlefonts'    => 'Google 字体',
                        ],
                        'default'  => [
                            'gravatar'       => 'gravatar',
                            'emoji'          => 'emoji',
                            'googlefonts'    => 'googlefonts',
                        ],
                        'subtitle' => '是否启用文件资源加速',
                        'desc'     => '启用后，将自动替换 Gravatar 头像、Emoji 表情、Google 字体等文件资源为国内加速节点。',
                    ],
                    [
                        'id'       => 'admincdn_dev',
                        'type'     => 'checkbox',
                        'title'    => $this->t('开发加速'),
                        'inline'   => true,
                        'options'  => [
                            'wordpress'      => 'WordPress 官方',
                            'themes'         => '主题仓库',
                            'plugins'        => '插件仓库',
                        ],
                        'default'  => [
                            'wordpress'      => 'wordpress',
                            'themes'         => 'themes',
                            'plugins'        => 'plugins',
                        ],
                        'subtitle' => '是否启用开发资源加速',
                        'desc'     => '启用后，将自动替换 WordPress 官方、主题仓库、插件仓库等开发资源为国内加速节点。',
                    ],
                ],
            ] );
        }
        
        // 通知管理部分
        if (in_array('notice', $enabled_sections)) {
            WP_CHINA_YES::createSection( $this->prefix, [
                'title'  => $this->t('通知管理'),
                'icon'   => 'icon icon-bell',
                'fields' => [
                    [
                        'id'       => 'notice_block',
                        'type'     => 'radio',
                        'title'    => $this->t('通知管理'),
                        'inline'   => true,
                        'options'  => [
                            'on'  => '启用',
                            'off' => '不启用',
                        ],
                        'default'  => 'off',
                        'subtitle' => '是否启用后台通知管理',
                        'desc'     => $this->t('管理和控制 WordPress 后台各类通知的显示。'),
                    ],
                    [
                        'id'         => 'disable_all_notices',
                        'type'       => 'switcher',
                        'title'      => $this->t('禁用所有通知'),
                        'subtitle'   => '一键禁用所有后台通知',
                        'default'    => false,
                        'dependency' => ['notice_block', '==', 'on'],
                    ],
                    [
                        'id'         => 'notice_control',
                        'type'       => 'checkbox',
                        'title'      => $this->t('选择性禁用'),
                        'inline'     => true,
                        'subtitle'   => '选择需要禁用的通知类型',
                        'desc'       => $this->t('可以按住 Ctrl/Command 键进行多选'),
                        'chosen'     => true,
                        'multiple'   => true,
                        'options'    => [
                            'update_nag'           => '更新提醒',
                            'plugin_update'        => '插件更新',
                            'theme_update'         => '主题更新',
                            'core_update'          => '核心更新',
                            'admin_notices'        => '管理员通知',
                            'user_admin_notices'   => '用户管理通知',
                            'network_admin_notices'=> '网络管理通知',
                        ],
                        'dependency' => ['notice_block', '==', 'on'],
                    ],
                    [
                        'id'         => 'notice_method',
                        'type'       => 'radio',
                        'title'      => $this->t('禁用方式'),
                        'inline'     => true,
                        'options'    => [
                            'hide'   => '隐藏通知',
                            'remove' => '移除通知',
                        ],
                        'default'    => 'hide',
                        'subtitle'   => '选择通知的禁用方式',
                        'desc'       => '隐藏通知：通过 CSS 隐藏通知元素；移除通知：通过 PHP 移除通知钩子。',
                        'dependency' => ['notice_block', '==', 'on'],
                    ],
                ],
            ] );
        }
    }
    
    private function t($text) {
        return TranslationManager::translate($text);
    }
    
    public function admin_menu() {
        add_options_page(
            'WP-China-Yes',
            'WP-China-Yes',
            'manage_options',
            'wp-china-yes',
            [ $this, 'admin_page' ]
        );
    }
    
    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>WP-China-Yes 设置</h1>';
        echo '<p>现代化翻译系统已启用</p>';
        echo '</div>';
    }
    
    public function enqueue_admin_assets() {
        // 管理员资源加载
    }
}