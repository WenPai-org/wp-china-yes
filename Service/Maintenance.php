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
            add_action('template_redirect', [$this, 'check_maintenance_mode'], 1);
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
        if ($screen && $screen->id === 'dashboard') {
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
                sprintf('%d 个媒体', $media_count)
            );
        }

        // 管理员统计
        if (in_array('admin_num', $display_options)) {
            $admin_count = count(get_users(['role' => 'administrator', 'fields' => 'ID']));
            $items['admins'] = sprintf(
                '<a href="%s" class="stat-item"><span class="dashicons dashicons-shield-alt"></span> %s</a>',
                admin_url('users.php?role=administrator'),
                sprintf('%d 个管理员', $admin_count)
            );
        }

        // 用户总数统计
        if (in_array('user_num', $display_options)) {
            $total_users = count(get_users(['fields' => 'ID']));
            $items['users'] = sprintf(
                '<a href="%s" class="stat-item"><span class="dashicons dashicons-groups"></span> %s</a>',
                admin_url('users.php'),
                sprintf('%d 个用户', $total_users)
            );
        }

        // 磁盘使用统计
        $show_disk_usage = in_array('disk_usage', $display_options, true);
        $show_disk_limit = in_array('disk_limit', $display_options, true);
        $disk_info       = ($show_disk_usage || $show_disk_limit) ? $this->get_disk_usage_info() : null;
        if ($show_disk_usage && $disk_info) {
            $items['disk_usage'] = sprintf(
                '<span class="stat-item"><span class="dashicons dashicons-database"></span> 磁盘用量：%s / %s</span>',
                size_format($disk_info['used']),
                size_format($disk_info['total'])
            );
        }

        if ($show_disk_limit && $disk_info && $disk_info['total'] > 0) {
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
            if (false === $disk_total || false === $disk_free) {
                return null;
            }
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
        if ($user instanceof \WP_User) {
            update_user_meta($user->ID, 'last_login', time());
        }
    }

    /**
     * Show the maintenance response only to anonymous front-end visitors.
     */
    public function check_maintenance_mode() {
        if (
            'cli' === PHP_SAPI ||
            current_user_can('manage_options') ||
            is_admin() ||
            wp_doing_ajax() ||
            (defined('REST_REQUEST') && REST_REQUEST)
        ) {
            return;
        }

        $maintenance_settings = is_array($this->settings['maintenance_settings'] ?? null)
            ? $this->settings['maintenance_settings']
            : [];
        $title   = $maintenance_settings['maintenance_title'] ?? '网站维护中';
        $heading = $maintenance_settings['maintenance_heading'] ?? '网站维护中';
        $message = $maintenance_settings['maintenance_message'] ?? '网站正在进行例行维护，请稍后访问。感谢您的理解与支持！';

        $output = sprintf(
            '<style>body{background:#f1f1f1;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.maintenance-wrapper{max-width:800px;margin:100px auto;padding:50px 20px;text-align:center;background:#fff;border-radius:5px;box-shadow:0 1px 3px rgba(0,0,0,.1)}.maintenance-wrapper h1{font-size:36px;color:#333}.maintenance-wrapper h2{font-size:24px;color:#666}.maintenance-message{font-size:16px;line-height:1.6;color:#555}</style><div class="maintenance-wrapper"><h1>%s</h1><h2>%s</h2><div class="maintenance-message">%s</div></div>',
            esc_html($heading),
            esc_html($title),
            wp_kses_post($message)
        );

        wp_die($output, esc_html($title), [
            'response'  => 503,
            'back_link' => false,
        ]);
    }

    public function add_admin_bar_notice($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_node([
            'id'    => 'maintenance-mode-notice',
            'title' => '<span style="color:#d63638">维护模式已启用</span>',
            'href'  => admin_url('options-general.php?page=wp-china-yes'),
        ]);
    }
}
