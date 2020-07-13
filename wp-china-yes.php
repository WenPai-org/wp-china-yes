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

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require __DIR__ . '/vendor/autoload.php';
}

if ( file_exists( __DIR__ . '/vendor/loco-translate/loco.php' ) ) {
    require __DIR__ . '/vendor/loco-translate/loco.php';
}