<?php
namespace WenPai\ChinaYes\Service;

defined('ABSPATH') || exit;

use function WenPai\ChinaYes\get_settings;

class Maintenance {
    private $settings;
    private $allowed_pages = [
        'wp-login.php',
        'wp-register.php',
        'wp-cron.php',
        'async-upload.php',
        'admin-ajax.php'
    ];

    public function __construct() {
        // 使用更早的钩子并设置高优先级
        add_action('plugins_loaded', [$this, 'init'], 1);
    }

    public function init() {
        $this->settings = get_settings();
        
        // 仅在调试模式下输出调试信息
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Maintenance Settings Raw: ' . print_r($this->settings, true));
            error_log('Maintenance Mode Value: ' . var_export($this->settings['maintenance_mode'], true));
        }
        
        // 如果维护模式启用，挂载相关钩子
        if ($this->settings['maintenance_mode']) {
            add_action('template_redirect', [$this, 'check_maintenance_mode'], 1);
            add_action('admin_bar_menu', [$this, 'add_admin_bar_notice'], 100);
            add_action('init', [$this, 'check_ajax_maintenance'], 1);
        }
    }

    public function check_ajax_maintenance() {
        if (wp_doing_ajax() && !current_user_can('manage_options')) {
            wp_die('维护模式已启用');
        }
    }

    public function check_maintenance_mode() {
        // 如果是命令行环境，直接返回
        if (php_sapi_name() === 'cli') {
            return;
        }

        // 如果是管理员，直接返回
        if (current_user_can('manage_options')) {
            return;
        }

        // 检查是否是允许的页面
        global $pagenow;
        if (in_array($pagenow, $this->allowed_pages)) {
            return;
        }

        // 检查是否是后台请求
        if (is_admin()) {
            return;
        }

        // 检查是否是 REST API 请求
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        // 检查是否是 AJAX 请求
        if (wp_doing_ajax()) {
            return;
        }

        // 显示维护页面
        $this->show_maintenance_page();
    }

    private function show_maintenance_page() {
        $maintenance_settings = $this->settings['maintenance_settings'] ?? [];
        
        $title = $maintenance_settings['maintenance_title'] ?? '网站维护中';
        $heading = $maintenance_settings['maintenance_heading'] ?? '网站维护中';
        $message = $maintenance_settings['maintenance_message'] ?? '网站正在进行例行维护，请稍后访问。感谢您的理解与支持！';

        // 添加基本的样式
        $style = '
            <style>
                body { 
                    background: #f1f1f1; 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                }
                .maintenance-wrapper {
                    text-align: center;
                    padding: 50px 20px;
                    max-width: 800px;
                    margin: 100px auto;
                    background: white;
                    border-radius: 5px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .maintenance-wrapper h1 {
                    color: #333;
                    font-size: 36px;
                    margin-bottom: 20px;
                }
                .maintenance-wrapper h2 {
                    color: #666;
                    font-size: 24px;
                    margin-bottom: 30px;
                }
                .maintenance-message {
                    color: #555;
                    line-height: 1.6;
                    font-size: 16px;
                }
            </style>
        ';

        $output = $style . sprintf(
            '<div class="maintenance-wrapper">
                <h1>%s</h1>
                <h2>%s</h2>
                <div class="maintenance-message">%s</div>
            </div>',
            esc_html($heading),
            esc_html($title),
            wp_kses_post($message)
        );

        // 设置维护模式响应头
        if (!headers_sent()) {
            header('HTTP/1.1 503 Service Temporarily Unavailable');
            header('Status: 503 Service Temporarily Unavailable');
            header('Retry-After: 3600');
            header('Content-Type: text/html; charset=utf-8');
        }

        // 确保输出被清空
        if (ob_get_level()) {
            ob_end_clean();
        }

        wp_die($output, $title, [
            'response' => 503,
            'back_link' => false
        ]);
    }

    public function add_admin_bar_notice($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        // 添加调试信息
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Admin bar notice added for maintenance mode.');
        }

        $wp_admin_bar->add_node([
            'id'    => 'maintenance-mode-notice',
            'title' => '<span style="color: #ff0000;">维护模式已启用</span>',
            'href'  => admin_url('admin.php?page=wp-china-yes#tab=脉云维护')
        ]);
    }
}