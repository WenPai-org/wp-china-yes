<?php

namespace WenPai\ChinaYes\Service;

defined('ABSPATH') || exit;

use function WenPai\ChinaYes\get_settings;

class Maintenance {
    private $settings;

    public function __construct() {
        $this->settings = get_settings();
        
        // 维护模式检查
        if (!empty($this->settings['maintenance_mode'])) {
            add_action('template_redirect', [$this, 'check_maintenance_mode']);
            add_action('admin_bar_menu', [$this, 'add_admin_bar_notice'], 100);
        }

        // 仪表盘统计信息
        if (!empty($this->settings['disk']) && $this->settings['disk']) {
            add_action('dashboard_glance_items', [$this, 'add_dashboard_stats']);
            add_action('admin_head', [$this, 'add_admin_css']);
        }

        // 添加登录记录钩子
        add_action('wp_login', [$this, 'record_last_login'], 10, 2);
    }

    // 添加 CSS 样式
    public function add_admin_css() {
        $screen = get_current_screen();
        if ($screen->id === 'dashboard') {
            echo '<style>
                #dashboard_right_now .stat-item span.dashicons {
                    margin: 0 3px 0 -25px;
                    background: white;
                    position: relative;
                    z-index: 1;
                }
            </style>';
        }
    }

    public function add_dashboard_stats($items) {
        if (!is_array($items)) {
            $items = array();
        }

        // 获取显示选项设置
        $display_options = $this->settings['disk_display'] ?? [];

        // 媒体文件统计
        if (in_array('media_num', $display_options)) {
            $media_count = wp_count_posts('attachment')->inherit;
            $items['media'] = sprintf(
                '<a href="%s" class="stat-item"><span class="dashicons dashicons-format-gallery"></span> %s</a>',
                admin_url('upload.php'),
                sprintf(_n('%d 个媒体', '%d 个媒体', $media_count), $media_count)
            );
        }

        // 管理员统计
        if (in_array('admin_num', $display_options)) {
            $admin_count = count(get_users(['role' => 'administrator', 'fields' => 'ID']));
            $items['admins'] = sprintf(
                '<a href="%s" class="stat-item"><span class="dashicons dashicons-shield-alt"></span> %s</a>',
                admin_url('users.php?role=administrator'),
                sprintf(_n('%d 个管理员', '%d 个管理员', $admin_count), $admin_count)
            );
        }

        // 用户总数统计
        if (in_array('user_num', $display_options)) {
            $total_users = count(get_users(['fields' => 'ID']));
            $items['users'] = sprintf(
                '<a href="%s" class="stat-item"><span class="dashicons dashicons-groups"></span> %s</a>',
                admin_url('users.php'),
                sprintf(_n('%d 个用户', '%d 个用户', $total_users), $total_users)
            );
        }

        // 磁盘使用统计
        $disk_info = $this->get_disk_usage_info();
        if (in_array('disk_usage', $display_options)) {
            $items['disk_usage'] = sprintf(
                '<span class="stat-item"><span class="dashicons dashicons-database"></span> 磁盘用量：%s / %s</span>',
                size_format($disk_info['used']),
                size_format($disk_info['total'])
            );
        }

        if (in_array('disk_limit', $display_options)) {
            $items['disk_free'] = sprintf(
                '<span class="stat-item"><span class="dashicons dashicons-chart-area"></span> 剩余空间：%s (%s%%)</span>',
                size_format($disk_info['free']),
                round(($disk_info['free'] / $disk_info['total']) * 100, 2)
            );
        }

        // 上次登录时间
        if (in_array('lastlogin', $display_options)) {
            $current_user_id = get_current_user_id();
            $last_login = get_user_meta($current_user_id, 'last_login', true);
            $items['lastlogin'] = sprintf(
                '<span class="stat-item"><span class="dashicons dashicons-clock"></span> 上次登录：%s</span>',
                $last_login ? date('Y.m.d H:i:s', $last_login) : '从未登录'
            );
        }

        return $items;
    }

    private function get_disk_usage_info() {
        $disk_info = get_transient('disk_usage_info');
        if (false === $disk_info) {
            $upload_dir = wp_upload_dir();
            $disk_total = disk_total_space($upload_dir['basedir']);
            $disk_free = disk_free_space($upload_dir['basedir']);
            $disk_used = $disk_total - $disk_free;

            $disk_info = [
                'used'  => $disk_used,
                'total' => $disk_total,
                'free'  => $disk_free,
            ];
            set_transient('disk_usage_info', $disk_info, HOUR_IN_SECONDS);
        }
        return $disk_info;
    }

    public function record_last_login($user_login, $user) {
        update_user_meta($user->ID, 'last_login', time());
    }
}