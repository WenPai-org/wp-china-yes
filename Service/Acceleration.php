<?php

namespace WenPai\ChinaYes\Service;

defined('ABSPATH') || exit;

use function WenPai\ChinaYes\get_settings;

class Acceleration {
    private $settings;
    private $replacements = [];
    private $regex_patterns = [];
    private $compiled_patterns = [];
    private $buffer_started = false;
    private static $cache = [];
    private $performance_data = [];
    private $debug_mode = false;

    public function __construct() {
        $this->settings = get_settings();
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $this->init();
    }

    /**
     * 初始化 admincdn 功能
     */
    private function init() {
        if (!$this->should_enable()) {
            return;
        }

        add_action('wp_head', function () {
            echo "<!-- 此站点使用的前端静态资源库由萌芽加速（adminCDN）提供，智能加速转接基于文派叶子 WPCY.COM  -->\n";
        }, 1);
        
        $this->prepare_replacements();
        $this->start_output_buffer();
        $this->init_version_control();
    }

    /**
     * 检查是否应该启用加速功能
     */
    private function should_enable() {
        return !empty($this->settings['admincdn']) || 
               !empty($this->settings['admincdn_files']) || 
               !empty($this->settings['admincdn_public']);
    }

    /**
     * 准备所有替换规则
     */
    private function prepare_replacements() {
        if ($this->has_admin_acceleration()) {
            $this->prepare_admin_replacements();
        }

        if ($this->has_frontend_acceleration()) {
            $this->prepare_frontend_replacements();
        }

        if ($this->has_public_library_acceleration()) {
            $this->prepare_public_library_replacements();
        }

        if ($this->has_dev_library_acceleration()) {
            $this->prepare_dev_library_replacements();
        }

        if ($this->has_special_features()) {
            $this->prepare_special_replacements();
        }
    }

    /**
     * 检查是否启用管理后台加速
     */
    private function has_admin_acceleration() {
        return !empty($this->settings['admincdn']) && 
               in_array('admin', (array) $this->settings['admincdn']);
    }

    /**
     * 检查是否启用前台加速
     */
    private function has_frontend_acceleration() {
        return !empty($this->settings['admincdn_files']) && 
               in_array('frontend', (array) $this->settings['admincdn_files']);
    }

    /**
     * 检查是否启用公共库加速
     */
    private function has_public_library_acceleration() {
        return !empty($this->settings['admincdn_public']) && 
               is_array($this->settings['admincdn_public']) && 
               count($this->settings['admincdn_public']) > 0;
    }

    /**
     * 检查是否启用开发库加速
     */
    private function has_dev_library_acceleration() {
        return !empty($this->settings['admincdn_dev']) && 
               is_array($this->settings['admincdn_dev']) && 
               count($this->settings['admincdn_dev']) > 0;
    }

    /**
     * 检查是否启用特殊功能
     */
    private function has_special_features() {
        return !empty($this->settings['admincdn_files']) && 
               (in_array('emoji', (array) $this->settings['admincdn_files']) || 
                in_array('sworg', (array) $this->settings['admincdn_files']));
    }

    /**
     * 启动统一的输出缓冲
     */
    private function start_output_buffer() {
        if ($this->buffer_started || php_sapi_name() == 'cli') {
            return;
        }

        $hook = is_admin() ? 'admin_init' : 'template_redirect';
        
        add_action($hook, function () {
            if (!$this->buffer_started) {
                ob_start([$this, 'process_buffer']);
                $this->buffer_started = true;
            }
        }, 1);

        add_action('wp_footer', [$this, 'end_buffer'], 999);
        add_action('admin_footer', [$this, 'end_buffer'], 999);
    }

    /**
     * 处理输出缓冲内容
     */
    public function process_buffer($buffer) {
        if (empty($buffer)) {
            return $buffer;
        }

        $start_time = microtime(true);
        $buffer_size = strlen($buffer);
        $buffer_hash = md5($buffer);

        if (isset(self::$cache[$buffer_hash])) {
            $this->log_performance('cache_hit', microtime(true) - $start_time, $buffer_size);
            return self::$cache[$buffer_hash];
        }

        $original_buffer = $buffer;
        $replacements_made = 0;

        if (!empty($this->regex_patterns)) {
            $regex_start = microtime(true);
            $this->compile_patterns();
            
            foreach ($this->compiled_patterns as $pattern_data) {
                $before = $buffer;
                $buffer = preg_replace($pattern_data['pattern'], $pattern_data['replacement'], $buffer);
                
                if (preg_last_error() !== PREG_NO_ERROR) {
                    $this->log_error('Regex error in pattern: ' . $pattern_data['pattern']);
                    continue;
                }
                
                if ($before !== $buffer) {
                    $replacements_made++;
                }
            }
            
            $this->log_performance('regex_processing', microtime(true) - $regex_start, count($this->compiled_patterns));
        }

        if (!empty($this->replacements)) {
            $str_start = microtime(true);
            $searches = array_keys($this->replacements);
            $replaces = array_values($this->replacements);
            $before = $buffer;
            $buffer = str_replace($searches, $replaces, $buffer);
            
            if ($before !== $buffer) {
                $replacements_made++;
            }
            
            $this->log_performance('string_processing', microtime(true) - $str_start, count($this->replacements));
        }

        if (count(self::$cache) < 100) {
            self::$cache[$buffer_hash] = $buffer;
        }

        $total_time = microtime(true) - $start_time;
        $this->log_performance('total_processing', $total_time, $replacements_made);

        if ($this->debug_mode && $replacements_made > 0) {
            $buffer .= sprintf(
                '<!-- Acceleration: %d replacements in %.4fs, buffer size: %s -->',
                $replacements_made,
                $total_time,
                $this->format_bytes($buffer_size)
            );
        }

        return $buffer;
    }

    /**
     * 记录性能数据
     */
    private function log_performance($operation, $time, $count) {
        if (!$this->debug_mode) {
            return;
        }

        $this->performance_data[] = [
            'operation' => $operation,
            'time' => $time,
            'count' => $count,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * 记录错误信息
     */
    private function log_error($message) {
        if ($this->debug_mode) {
            error_log('Acceleration: ' . $message);
        }
    }

    /**
     * 格式化字节数
     */
    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 编译正则表达式模式
     */
    private function compile_patterns() {
        if (!empty($this->compiled_patterns)) {
            return;
        }

        foreach ($this->regex_patterns as $pattern => $replacement) {
            if (!$this->is_valid_regex($pattern)) {
                error_log('Acceleration: Invalid regex pattern: ' . $pattern);
                continue;
            }

            $this->compiled_patterns[] = [
                'pattern' => $pattern,
                'replacement' => $replacement
            ];
        }
    }

    /**
     * 验证正则表达式是否有效
     */
    private function is_valid_regex($pattern) {
        return @preg_match($pattern, '') !== false;
    }

    /**
     * 结束输出缓冲
     */
    public function end_buffer() {
        if ($this->buffer_started && ob_get_level()) {
            ob_end_flush();
        }
    }

    /**
     * 准备管理后台替换规则
     */
    private function prepare_admin_replacements() {
        if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }

        if (in_array('admin', (array) $this->settings['admincdn']) &&
            !stristr($GLOBALS['wp_version'], 'alpha') &&
            !stristr($GLOBALS['wp_version'], 'beta') &&
            !stristr($GLOBALS['wp_version'], 'RC')) {
            
            global $concatenate_scripts;
            $concatenate_scripts = false;

            $pattern = '~' . preg_quote(home_url('/'), '~') . '(wp-admin|wp-includes)/(css|js)/~';
            $replacement = sprintf('https://wpstatic.admincdn.com/%s/$1/$2/', $GLOBALS['wp_version']);
            $this->regex_patterns[$pattern] = $replacement;
        }
    }

    /**
     * 准备前台替换规则
     */
    private function prepare_frontend_replacements() {
        if (in_array('frontend', (array) $this->settings['admincdn_files'])) {
            $pattern = '#(?<=[(\"\'])(?:' . quotemeta(home_url()) . ')?/(?:((?:wp-content|wp-includes)[^\"\')]+\.(css|js)[^\"\')]+))(?=[\"\')])#';
            $this->regex_patterns[$pattern] = 'https://public.admincdn.com/$0';
        }
    }

    /**
     * 准备公共库替换规则
     */
    private function prepare_public_library_replacements() {
        $public_libraries = [
            'googlefonts' => ['fonts.googleapis.com', 'googlefonts.admincdn.com'],
            'googleajax' => ['ajax.googleapis.com', 'googleajax.admincdn.com'],
            'cdnjs' => ['cdnjs.cloudflare.com/ajax/libs', 'cdnjs.admincdn.com'],
            'jsdelivr' => ['cdn.jsdelivr.net', 'jsd.admincdn.com'],
            'bootstrapcdn' => ['maxcdn.bootstrapcdn.com', 'jsd.admincdn.com'],
        ];

        foreach ($public_libraries as $key => $replacement) {
            if (in_array($key, (array) $this->settings['admincdn_public'])) {
                $this->replacements[$replacement[0]] = $replacement[1];
            }
        }
    }

    /**
     * 准备开发库替换规则
     */
    private function prepare_dev_library_replacements() {
        $dev_libraries = [
            'react' => ['unpkg.com/react', 'jsd.admincdn.com/npm/react'],
            'jquery' => ['code.jquery.com', 'jsd.admincdn.com/npm/jquery'],
            'vuejs' => ['unpkg.com/vue', 'jsd.admincdn.com/npm/vue'],
            'datatables' => ['cdn.datatables.net', 'jsd.admincdn.com/npm/datatables.net'],
            'tailwindcss' => ['unpkg.com/tailwindcss', 'jsd.admincdn.com/npm/tailwindcss'],
        ];

        foreach ($dev_libraries as $key => $replacement) {
            if (in_array($key, (array) $this->settings['admincdn_dev'])) {
                $this->replacements[$replacement[0]] = $replacement[1];
            }
        }
    }

    /**
     * 准备特殊功能替换规则
     */
    private function prepare_special_replacements() {
        if (in_array('emoji', (array) $this->settings['admincdn_files'])) {
            $this->prepare_emoji_replacements();
        }

        if (in_array('sworg', (array) $this->settings['admincdn_files'])) {
            $this->prepare_sworg_replacements();
        }
    }

    /**
     * 准备Emoji替换规则
     */
    private function prepare_emoji_replacements() {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

        $this->replacements['s.w.org/images/core/emoji'] = 'jsd.admincdn.com/npm/@twemoji/api/dist';

        add_action('wp_head', function () {
            ?>
            <script>
                window._wpemojiSettings = {
                    "baseUrl": "https://jsd.admincdn.com/npm/@twemoji/api@15.0.3/dist/72x72/",
                    "ext": ".png",
                    "svgUrl": "https://jsd.admincdn.com/npm/@twemoji/api@15.0.3/dist/svg/",
                    "svgExt": ".svg",
                    "source": {
                        "concatemoji": "<?php echo includes_url('js/wp-emoji-release.min.js'); ?>"
                    }
                };
            </script>
            <?php
        }, 1);
    }

    /**
     * 准备WordPress.org资源替换规则
     */
    private function prepare_sworg_replacements() {
        $this->replacements['ts.w.org'] = 'ts.wenpai.net';

        add_filter('theme_screenshot_url', function ($url) {
            return str_replace('ts.w.org', 'ts.wenpai.net', $url);
        });

        add_filter('themes_api_result', function ($res, $action, $args) {
            if (is_object($res) && !empty($res->screenshots)) {
                foreach ($res->screenshots as &$screenshot) {
                    if (isset($screenshot->src)) {
                        $screenshot->src = str_replace('ts.w.org', 'ts.wenpai.net', $screenshot->src);
                    }
                }
            }
            return $res;
        }, 10, 3);
    }

    /**
     * 加载 admincdn 功能（保持向后兼容）
     */
    private function load_admincdn() {
        $this->settings['admincdn_files'] = $this->settings['admincdn_files'] ?? [];
        $this->settings['admincdn_public'] = $this->settings['admincdn_public'] ?? [];
        $this->settings['admincdn_dev'] = $this->settings['admincdn_dev'] ?? [];
    }



    /**
     * 初始化版本控制功能
     */
    private function init_version_control() {
        if (empty($this->settings['admincdn_version_enable'])) {
            return;
        }

        $version_settings = (array) $this->settings['admincdn_version'];
        
        if (empty($version_settings)) {
            return;
        }

        if (in_array('css', $version_settings)) {
            add_filter('style_loader_src', [$this, 'version_filter']);
        }

        if (in_array('js', $version_settings)) {
            add_filter('script_loader_src', [$this, 'version_filter']);
        }
    }

    /**
     * 版本控制过滤器
     */
    public function version_filter($src) {
        $version_settings = (array) $this->settings['admincdn_version'];
        
        $url_parts = wp_parse_url($src);
        
        if (!isset($url_parts['path'])) {
            return $src;
        }

        $extension = pathinfo($url_parts['path'], PATHINFO_EXTENSION);
        if (!$extension || !in_array($extension, ['css', 'js'])) {
            return $src;
        }

        if (!in_array($extension, $version_settings)) {
            return $src;
        }

        if (defined('AUTOVER_DISABLE_' . strtoupper($extension))) {
            return $src;
        }

        $file_path = rtrim(ABSPATH, '/') . urldecode($url_parts['path']);
        if (!is_file($file_path)) {
            return $src;
        }

        $timestamp_version = filemtime($file_path) ?: filemtime(utf8_decode($file_path));
        if (!$timestamp_version) {
            return $src;
        }

        if (!isset($url_parts['query'])) {
            $url_parts['query'] = '';
        }

        $query = [];
        parse_str($url_parts['query'], $query);
        
        if (in_array('disable_query', $version_settings)) {
            unset($query['v']);
            unset($query['ver']);
            unset($query['version']);
        } else {
            unset($query['v']);
            unset($query['ver']);
            
            if (in_array('timestamp', $version_settings)) {
                $query['ver'] = $timestamp_version;
            } else {
                $query['ver'] = md5($timestamp_version);
            }
        }
        
        $url_parts['query'] = http_build_query($query);

        return $this->build_url($url_parts);
    }

    /**
     * 构建URL
     */
    private function build_url(array $parts) {
        return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') .
               ((isset($parts['user']) || isset($parts['host'])) ? '//' : '') .
               (isset($parts['user']) ? "{$parts['user']}" : '') .
               (isset($parts['pass']) ? ":{$parts['pass']}" : '') .
               (isset($parts['user']) ? '@' : '') .
               (isset($parts['host']) ? "{$parts['host']}" : '') .
               (isset($parts['port']) ? ":{$parts['port']}" : '') .
               (isset($parts['path']) ? "{$parts['path']}" : '') .
               (isset($parts['query']) ? "?{$parts['query']}" : '') .
               (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
    }

    /**
     * 页面字符串替换
     *
     * @param string $hook      钩子名称
     * @param string $replace_func 替换函数
     * @param array  $param     替换参数
     */
    private function page_str_replace($hook, $replace_func, $param) {
        if (php_sapi_name() == 'cli') {
            return;
        }
        add_action($hook, function () use ($replace_func, $param) {
            ob_start(function ($buffer) use ($replace_func, $param) {
                $param[] = $buffer;
                return call_user_func_array($replace_func, $param);
            });
        }, PHP_INT_MAX);
    }
}