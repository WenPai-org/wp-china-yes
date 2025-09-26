<?php

namespace WenPai\ChinaYes\Service;

class TranslationManager {
    
    private static $instance = null;
    private static $loaded = false;
    private static $translations = [];
    private static $fallbacks = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'loadTranslations'], 1);
        add_action('plugins_loaded', [$this, 'registerFallbacks'], 999);
    }
    
    public function loadTranslations() {
        if (!self::$loaded) {
            $domain = 'wp-china-yes';
            $locale = determine_locale();
            $mofile = CHINA_YES_PLUGIN_PATH . 'languages/' . $domain . '-' . $locale . '.mo';
            
            if (file_exists($mofile)) {
                load_textdomain($domain, $mofile);
            } else {
                load_plugin_textdomain($domain, false, dirname(plugin_basename(CHINA_YES_PLUGIN_FILE)) . '/languages');
            }
            
            self::$loaded = true;
            do_action('wp_china_yes_translations_loaded');
        }
    }
    
    public function registerFallbacks() {
        self::$fallbacks = [
            '应用市场' => '应用市场',
            '萌芽加速' => '萌芽加速',
            '文件加速' => '文件加速',
            '开发加速' => '开发加速',
            '初认头像' => '初认头像',
            '文风字体' => '文风字体',
            '字体家族' => '字体家族',
            '字体链接' => '字体链接',
            '字体字重' => '字体字重',
            '字体样式' => '字体样式',
            '字体应用' => '字体应用',
            '启用字体' => '启用字体',
            '排印优化' => '排印优化',
            '英文美化' => '英文美化',
            '墨图云集' => '墨图云集',
            '飞秒邮箱' => '飞秒邮箱',
            '无言会语' => '无言会语',
            '笔笙区块' => '笔笙区块',
            '灯鹿用户' => '灯鹿用户',
            'Woo电商' => 'Woo电商',
            '乐尔达思' => '乐尔达思',
            '瓦普文创' => '瓦普文创',
            '广告拦截' => '广告拦截',
            '规则名称' => '规则名称',
            '应用元素' => '应用元素',
            '启用规则' => '启用规则',
            '通知管理' => '通知管理',
            '管理和控制 WordPress 后台各类通知的显示。' => '管理和控制 WordPress 后台各类通知的显示。',
            '禁用所有通知' => '禁用所有通知',
            '选择性禁用' => '选择性禁用',
            '可以按住 Ctrl/Command 键进行多选' => '可以按住 Ctrl/Command 键进行多选',
            '禁用方式' => '禁用方式',
            '飞行模式' => '飞行模式',
            'URL' => 'URL',
            '显示参数' => '显示参数',
            '为网站维护人员提供参考依据，无需登录服务器即可查看相关信息参数' => '为网站维护人员提供参考依据，无需登录服务器即可查看相关信息参数',
            '为网站管理人员提供参考依据，进入后台仪表盘即可查看相关信息参数' => '为网站管理人员提供参考依据，进入后台仪表盘即可查看相关信息参数',
            '启用后，网站将显示维护页面，只有管理员可以访问。' => '启用后，网站将显示维护页面，只有管理员可以访问。',
            '雨滴安全' => '雨滴安全',
            '禁用文件编辑' => '禁用文件编辑',
            '启用后，用户无法通过 WordPress 后台编辑主题和插件文件。' => '启用后，用户无法通过 WordPress 后台编辑主题和插件文件。',
            '禁用文件修改' => '禁用文件修改',
            '启用后，用户无法通过 WordPress 后台安装、更新或删除主题和插件。' => '启用后，用户无法通过 WordPress 后台安装、更新或删除主题和插件。',
            '性能优化' => '性能优化',
            '性能优化设置可以帮助提升 WordPress 的运行效率，请根据服务器配置合理调整。' => '性能优化设置可以帮助提升 WordPress 的运行效率，请根据服务器配置合理调整。',
            '内存限制' => '内存限制',
            '设置 WordPress 的内存限制，例如 64M、128M、256M 等。' => '设置 WordPress 的内存限制，例如 64M、128M、256M 等。',
            '后台内存限制' => '后台内存限制',
            '设置 WordPress 后台的内存限制，例如 128M、256M、512M 等。' => '设置 WordPress 后台的内存限制，例如 128M、256M、512M 等。',
            '文章修订版本' => '文章修订版本',
            '设置为 0 禁用修订版本，或设置为一个固定值（如 5）限制修订版本数量。' => '设置为 0 禁用修订版本，或设置为一个固定值（如 5）限制修订版本数量。',
            '自动保存间隔' => '自动保存间隔',
            '设置文章自动保存的时间间隔，默认是 60 秒。' => '设置文章自动保存的时间间隔，默认是 60 秒。',
            '专为 WordPress 建站服务商和代理机构提供的自定义品牌 OEM 功能，输入您的品牌词启用后生效' => '专为 WordPress 建站服务商和代理机构提供的自定义品牌 OEM 功能，输入您的品牌词启用后生效',
            '注意：启用[隐藏菜单]前请务必保存或收藏当前设置页面 URL，否则将无法再次进入插件页面' => '注意：启用[隐藏菜单]前请务必保存或收藏当前设置页面 URL，否则将无法再次进入插件页面',
            '调试模式' => '调试模式',
            '启用后，WordPress 将显示 PHP 错误、警告和通知。临时使用完毕后，请保持禁用此选项。' => '启用后，WordPress 将显示 PHP 错误、警告和通知。临时使用完毕后，请保持禁用此选项。',
            '调试选项' => '调试选项',
            '注意：调试模式仅适用于开发和测试环境，不建议在生产环境中长时间启用。选择要启用的调试功能，适用于开发和测试环境。' => '注意：调试模式仅适用于开发和测试环境，不建议在生产环境中长时间启用。选择要启用的调试功能，适用于开发和测试环境。',
            '数据库工具' => '数据库工具',
            '启用后，您可以在下方访问数据库修复工具。定期使用完毕后，请保持禁用此选项。' => '启用后，您可以在下方访问数据库修复工具。定期使用完毕后，请保持禁用此选项。',
            '数据库修复工具' => '数据库修复工具',
            '打开数据库修复工具' => '打开数据库修复工具',
            '启用后，您可以在下方选用文派叶子功能，特别提醒：禁用对应功能后再次启用需重新设置。' => '启用后，您可以在下方选用文派叶子功能，特别提醒：禁用对应功能后再次启用需重新设置。',
            '帮助文档' => '帮助文档',
            '文派开源' => '文派开源',
            '官方镜像' => '官方镜像',
            '不启用' => '不启用'
        ];
    }
    
    public static function translate($text, $domain = 'wp-china-yes') {
        if (!self::$loaded) {
            return isset(self::$fallbacks[$text]) ? self::$fallbacks[$text] : $text;
        }
        
        if (function_exists('__')) {
            $translated = __($text, $domain);
            return $translated !== $text ? $translated : (isset(self::$fallbacks[$text]) ? self::$fallbacks[$text] : $text);
        }
        
        return isset(self::$fallbacks[$text]) ? self::$fallbacks[$text] : $text;
    }
    
    public static function translateLazy($text, $domain = 'wp-china-yes') {
        return function() use ($text, $domain) {
            return self::translate($text, $domain);
        };
    }
    
    public static function isLoaded() {
        return self::$loaded;
    }
    
    public static function getFallback($text) {
        return isset(self::$fallbacks[$text]) ? self::$fallbacks[$text] : $text;
    }
}