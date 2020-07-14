<?php
/**
 * Plugin Name: WP-China-Yes
 * Description: WP&中国？是的！此插件完全本土化你的WordPress为中国特供版。插件将WordPress核心的官方服务链接悉数替换为带有中国大陆节点的全球加速网络，并为官方仓库的所有作品追加基于机器翻译的汉化支持。
 * Author: WP中国本土化社区
 * Author URI:https://wp-china-yes.org/
 * Version: 3.0.0-Beta
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (is_admin()) {
    /**
     * 使用Composer作PHP文件自动加载
     */
    if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
        require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * WP-China-Yes的翻译校准基于Loco Translate插件开发，这里通过引入入口文件的方式激活该插件的二次开发版
     */
    if ( file_exists( __DIR__ . '/vendor/loco-translate/loco.php' ) ) {
        require __DIR__ . '/vendor/loco-translate/loco.php';
    }


    /**
     * 菜单注册
     */
    add_action('admin_menu', function () {
        add_menu_page(
            '本土化',
            '本土化',
            '',
            'wpcy'
        );

        add_submenu_page(
            'wpcy',
            '系统本土化',
            '系统本土化',
            'manage_options',
            'wpcy-setting',
            function () {
                echo 'a';
            },
            0
        );

        add_submenu_page(
            'wpcy',
            '关于',
            '关于',
            'manage_options',
            'wpcy-about',
            function () {
                echo 'a';
            },
            3
        );
    });
}
