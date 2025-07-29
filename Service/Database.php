<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use function WenPai\ChinaYes\get_settings;

/**
 * Class Database
 * 数据库工具服务
 * @package WenPai\ChinaYes\Service
 */
class Database {

    private $settings;

    public function __construct() {

        $this->settings = get_settings();

        // 如果启用了数据库工具，则允许访问数据库修复工具
        if ( ! empty( $this->settings['enable_db_tools'] ) && $this->settings['enable_db_tools'] ) {
            define( 'WP_ALLOW_REPAIR', true );
        }

        // 处理调试常量
        $this->handle_debug_constants();

        // 安全相关常量
        $this->handle_security_constants();
    }

    /**
     * 处理调试模式相关常量
     */
    private function handle_debug_constants() {
        if ( ! empty( $this->settings['enable_debug'] ) && $this->settings['enable_debug'] ) {
            // 只有在常量未定义时才定义
            if ( ! defined( 'WP_DEBUG' ) ) {
                define( 'WP_DEBUG', true );
            }
            if ( ! empty( $this->settings['debug_options']['wp_debug_log'] ) && ! defined( 'WP_DEBUG_LOG' ) ) {
                define( 'WP_DEBUG_LOG', true );
            }
            if ( ! empty( $this->settings['debug_options']['wp_debug_display'] ) && ! defined( 'WP_DEBUG_DISPLAY' ) ) {
                define( 'WP_DEBUG_DISPLAY', true );
            }
            if ( ! empty( $this->settings['debug_options']['script_debug'] ) && ! defined( 'SCRIPT_DEBUG' ) ) {
                define( 'SCRIPT_DEBUG', true );
            }
            if ( ! empty( $this->settings['debug_options']['save_queries'] ) && ! defined( 'SAVEQUERIES' ) ) {
                define( 'SAVEQUERIES', true );
            }
        } else {
            // 禁用调试模式时的默认值
            if ( ! defined( 'WP_DEBUG' ) ) {
                define( 'WP_DEBUG', false );
            }
            if ( ! defined( 'WP_DEBUG_LOG' ) ) {
                define( 'WP_DEBUG_LOG', false );
            }
            if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
                define( 'WP_DEBUG_DISPLAY', false );
            }
            if ( ! defined( 'SCRIPT_DEBUG' ) ) {
                define( 'SCRIPT_DEBUG', false );
            }
            if ( ! defined( 'SAVEQUERIES' ) ) {
                define( 'SAVEQUERIES', false );
            }
        }
    }

    /**
     * 处理安全相关常量
     */
    private function handle_security_constants() {
        if ( ! empty( $this->settings['disallow_file_edit'] ) && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
            define( 'DISALLOW_FILE_EDIT', $this->settings['disallow_file_edit'] );
        }
        if ( ! empty( $this->settings['disallow_file_mods'] ) && ! defined( 'DISALLOW_FILE_MODS' ) ) {
            define( 'DISALLOW_FILE_MODS', $this->settings['disallow_file_mods'] );
        }
        if ( ! empty( $this->settings['force_ssl_admin'] ) && ! defined( 'FORCE_SSL_ADMIN' ) ) {
            define( 'FORCE_SSL_ADMIN', $this->settings['force_ssl_admin'] );
        }
        if ( ! empty( $this->settings['force_ssl_login'] ) && ! defined( 'FORCE_SSL_LOGIN' ) ) {
            define( 'FORCE_SSL_LOGIN', $this->settings['force_ssl_login'] );
        }
    }
}
