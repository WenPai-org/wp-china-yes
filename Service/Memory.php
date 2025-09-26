<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use function WenPai\ChinaYes\get_settings;


/**
 * Class Memory 
 * 显示服务器 IP 和内存使用情况
 * @package WenPai\ChinaYes\Service
 */
class Memory {
    private $memory = [];
    private $server_ip_address = '';
    private $os_info = '';
    private $debug_status = '';
    private $cpu_usage = null;
    private $mysql_version = '';

    public function __construct() {
    $settings = get_settings();
    if (!empty($settings['memory'])) {
        add_action('plugins_loaded', [$this, 'initialize']);
    }
    register_activation_hook(CHINA_YES_PLUGIN_FILE, [$this, 'check_php_version']);
}

    /**
     * 初始化插件
     */
    public function initialize() {
        add_action('init', [$this, 'check_memory_limit']);
        add_filter('admin_footer_text', [$this, 'add_footer']);
    }
    
    /**
     * 获取操作系统信息
     */
    private function get_os_info() {
    $os = php_uname('s');
    
    // 转换为更直观的名称
    switch (strtolower($os)) {
        case 'linux':
            return 'Linux';
        case 'darwin':
            return 'macOS';
        case 'windows nt':
            return 'Windows';
        default:
            return $os;
    }
}


    /**
     * 获取调试状态
     */
    private function get_debug_status() {
        if (defined('WP_DEBUG') && true === WP_DEBUG) {
            return '<strong><font color="#F60">WP_DEBUG</font></strong>';
        }
        return '<span style="text-decoration: line-through;">WP_DEBUG</span>';
    }

    /**
     * 获取 CPU 使用率
     */
    private function get_cpu_usage() {
        if (function_exists('sys_getloadavg') && is_callable('sys_getloadavg')) {
            $load = sys_getloadavg();
            return round($load[0] * 100 / 4, 2); // 假设是4核CPU
        }
        return false;
    }

    /**
     * 获取 MySQL 版本
     */
    private function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var("SELECT VERSION()");
    }
    
    /**
     * 检查 PHP 内存限制
     */
    public function check_memory_limit() {
        $this->memory['limit'] = (int) ini_get('memory_limit');
    }

    /**
     * 检查内存使用情况
     */
    private function check_memory_usage() {
        $this->memory['usage'] = function_exists('memory_get_peak_usage') 
            ? round(memory_get_peak_usage(true) / 1024 / 1024, 2) 
            : 0;

        if (!empty($this->memory['usage']) && !empty($this->memory['limit'])) {
            $this->memory['percent'] = round($this->memory['usage'] / $this->memory['limit'] * 100, 0);
            $this->memory['color'] = $this->get_memory_color($this->memory['percent']);
        }
    }

    /**
     * 获取内存使用率的颜色
     */
    private function get_memory_color($percent) {
        if ($percent > 90) {
            return 'font-weight:bold;color:red';
        } elseif ($percent > 75) {
            return 'font-weight:bold;color:#E66F00';
        }
        return 'font-weight:normal;';
    }

    /**
     * 格式化 WordPress 内存限制
     */
    private function format_wp_limit($size) {
        $unit = strtoupper(substr($size, -1));
        $value = (int) substr($size, 0, -1);

        switch ($unit) {
            case 'P': $value *= 1024;
            case 'T': $value *= 1024;
            case 'G': $value *= 1024;
            case 'M': $value *= 1024;
            case 'K': $value *= 1024;
        }
        return $value;
    }

    /**
     * 获取 WordPress 内存限制
     */
    private function check_wp_limit() {
        $memory = $this->format_wp_limit(WP_MEMORY_LIMIT);
        return $memory ? size_format($memory) : 'N/A';
    }

    /**
     * 添加信息到管理界面页脚
     */
public function add_footer($content) {
    $settings = get_settings();
    
    // 设置默认显示选项
    $default_options = [
        'memory_usage',
        'wp_limit',
        'server_ip',
    ];
    
    // 如果设置为空或不是数组，使用默认选项
    $display_options = isset($settings['memory_display']) && is_array($settings['memory_display']) 
        ? $settings['memory_display'] 
        : $default_options;
    
    // 如果 memory 设置未启用，直接返回原始内容
    if (empty($settings['memory'])) {
        return $content;
    }
    
    $this->check_memory_usage();
    $this->server_ip_address = $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? '');
    $this->os_info = $this->get_os_info();

    $footer_parts = [];

    
    // 内存使用量
    if (in_array('memory_usage', $display_options)) {
        $footer_parts[] = sprintf('%s: %s %s %s MB (<span style="%s">%s%%</span>)',
            'Memory',
            $this->memory['usage'],
            'of',
            $this->memory['limit'],
            $this->memory['color'],
            $this->memory['percent']
        );
    }
    
    // WP内存限制
    if (in_array('wp_limit', $display_options)) {
        $footer_parts[] = sprintf('%s: %s',
            'WP LIMIT',
            $this->check_wp_limit()
        );
    }
    
    // 服务器IP和主机名
    if (in_array('server_ip', $display_options)) {
        $hostname_part = in_array('hostname', $display_options) ? " (" . gethostname() . ")" : "";
        $footer_parts[] = sprintf('IP: %s%s',
            $this->server_ip_address,
            $hostname_part
        );
    }
    
    // 操作系统信息
    if (in_array('os_info', $display_options)) {
        $footer_parts[] = sprintf('OS: %s', $this->os_info);
    }
    
    // PHP信息
    if (in_array('php_info', $display_options)) {
        $footer_parts[] = sprintf('PHP: %s @%sBitOS',
            PHP_VERSION,
            PHP_INT_SIZE * 8
        );
    }

    // Debug状态
    if (in_array('debug_status', $display_options)) {
        $footer_parts[] = $this->get_debug_status();
    }
    
    // CPU使用率
    if (in_array('cpu_usage', $display_options)) {
        $cpu_usage = $this->get_cpu_usage();
        if ($cpu_usage !== false) {
            $footer_parts[] = sprintf('CPU: %s%%', $cpu_usage);
        }
    }
    
    // MySQL版本
    if (in_array('mysql_version', $display_options)) {
        $footer_parts[] = sprintf('MySQL: %s', $this->get_mysql_version());
    }

    if (!empty($footer_parts)) {
        $content .= ' | WPCY - ' . implode(' | ', $footer_parts);
    }

    return $content;
}


    /**
     * 检查 PHP 版本
     */
    public function check_php_version() {
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            deactivate_plugins(plugin_basename(CHINA_YES_PLUGIN_FILE));
            wp_die(
                sprintf(
                    '<h1>%s</h1><p>%s</p>',
                    '插件无法激活：PHP 版本过低',
                    '请升级 PHP 至 7.0 或更高版本。'
                ),
                'PHP 版本错误',
                ['back_link' => true]
            );
        }
    }
    
    
	/**
	 * 更新设置
	 */
	private function update_settings() {
		if ( is_multisite() ) {
			update_site_option( 'wp_china_yes', $this->settings );
		} else {
			update_option( 'wp_china_yes', $this->settings, true );
		}
	}
}
