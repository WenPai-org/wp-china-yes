<?php

namespace WenPai\ChinaYes\Service;

defined('ABSPATH') || exit;

use function WenPai\ChinaYes\get_settings;

/**
 * Class Avatar
 * 初认头像服务
 * @package WenPai\ChinaYes\Service
 */
class Avatar {

    private $settings;

    public function __construct() {
        $this->settings = get_settings();
        $this->init();
    }

    /**
     * 初始化初认头像功能
     */
    private function init() {
        if (!empty($this->settings['cravatar'])) {
            add_filter('user_profile_picture_description', [$this, 'set_user_profile_picture_for_cravatar'], 1);
            add_filter('avatar_defaults', [$this, 'set_defaults_for_cravatar'], 1);
            add_filter('um_user_avatar_url_filter', [$this, 'get_cravatar_url'], 1);
            add_filter('bp_gravatar_url', [$this, 'get_cravatar_url'], 1);
            add_filter('get_avatar_url', [$this, 'get_cravatar_url'], 1);
        }
    }

    /**
     * 获取 Cravatar URL
     */
    public function get_cravatar_url($url) {
        switch ($this->settings['cravatar']) {
            case 'cn':
                return $this->replace_avatar_url($url, 'cn.cravatar.com');
            case 'global':
                return $this->replace_avatar_url($url, 'en.cravatar.com');
            case 'weavatar':
                return $this->replace_avatar_url($url, 'weavatar.com');
            default:
                return $url;
        }
    }

    /**
     * 替换头像 URL
     */
    public function replace_avatar_url($url, $domain) {
        $sources = array(
            'www.gravatar.com',
            '0.gravatar.com',
            '1.gravatar.com',
            '2.gravatar.com',
            's.gravatar.com',
            'secure.gravatar.com',
            'cn.gravatar.com',
            'en.gravatar.com',
            'gravatar.com',
            'sdn.geekzu.org',
            'gravatar.duoshuo.com',
            'gravatar.loli.net',
            'dn-qiniu-avatar.qbox.me'
        );

        return str_replace($sources, $domain, $url);
    }

    /**
     * 设置 WordPress 讨论设置中的默认 LOGO 名称
     */
    public function set_defaults_for_cravatar($avatar_defaults) {
        if ($this->settings['cravatar'] == 'weavatar') {
            $avatar_defaults['gravatar_default'] = 'WeAvatar';
        } else {
            $avatar_defaults['gravatar_default'] = '初认头像';
        }

        return $avatar_defaults;
    }

    /**
     * 设置个人资料卡中的头像上传地址
     */
    public function set_user_profile_picture_for_cravatar() {
        if ($this->settings['cravatar'] == 'weavatar') {
            return '<a href="https://weavatar.com" target="_blank">您可以在 WeAvatar 修改您的资料图片</a>';
        } else {
            return '<a href="https://cravatar.com" target="_blank">您可以在初认头像修改您的资料图片</a>';
        }
    }
}