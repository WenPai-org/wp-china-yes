<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Class Base
 * 插件主服务
 * @package WenPai\ChinaYes\Service
 */
class Base {

    private $services = [];

    public function __construct() {
        $this->init_services();
    }

    private function init_services() {
        $core_services = [
            'Super',
            'Monitor', 
            'Memory',
            'Update',
            'Database',
            'Acceleration',
            'Avatar',
            'Fonts',
            'Comments',
            'Media',
            'Performance',
            'Maintenance'
        ];

        foreach ($core_services as $service) {
            $this->load_service($service);
        }

        if (is_admin()) {
            $this->load_service('Setting');
            $this->load_service('Adblock');
        }
    }

    private function load_service($service_name) {
        $class_name = __NAMESPACE__ . '\\' . $service_name;
        
        if (class_exists($class_name)) {
            try {
                $this->services[$service_name] = new $class_name();
            } catch (\Exception $e) {
                error_log("WP-China-Yes: Failed to load service {$service_name}: " . $e->getMessage());
            }
        }
    }

    public function get_service($service_name) {
        return $this->services[$service_name] ?? null;
    }
}
