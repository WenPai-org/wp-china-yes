<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Class Base
 * 插件主服务
 * @package WenPai\ChinaYes\Service
 */
class Base {

    public function __construct() {
        // 确保所有类文件都存在后再实例化
        if (class_exists(__NAMESPACE__ . '\Super')) {
            new Super();
        }
        
        if (class_exists(__NAMESPACE__ . '\Monitor')) {
            new Monitor();
        }
        
        if (class_exists(__NAMESPACE__ . '\Memory')) {
            new Memory();
        }
        
        if (class_exists(__NAMESPACE__ . '\Update')) {
            new Update();
        }
        
        if (class_exists(__NAMESPACE__ . '\Database')) {
            new Database();
        }
        
        if (class_exists(__NAMESPACE__ . '\Acceleration')) {
            new Acceleration();
        }

        if (class_exists(__NAMESPACE__ . '\Maintenance')) {
            new Maintenance();
        }

        if ( is_admin() && class_exists(__NAMESPACE__ . '\Setting')) {
            new Setting();
        }
    }
}
