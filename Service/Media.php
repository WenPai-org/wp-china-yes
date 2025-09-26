<?php

namespace WenPai\ChinaYes\Service;

defined('ABSPATH') || exit;

use function WenPai\ChinaYes\get_settings;

class Media {

    private $settings;

    public function __construct() {
        $this->settings = get_settings();
        $this->init();
    }

    private function init() {
        if (!empty($this->settings['optimize_images'])) {
            add_filter('wp_image_editors', [$this, 'set_image_editor']);
            add_filter('jpeg_quality', [$this, 'set_jpeg_quality']);
        }
        
        if (!empty($this->settings['lazy_load'])) {
            add_filter('wp_lazy_loading_enabled', '__return_true');
        }
        
        add_filter('wp_get_attachment_image_attributes', [$this, 'add_image_attributes'], 10, 3);
        
        if (!empty($this->settings['webp_support'])) {
            add_filter('wp_generate_attachment_metadata', [$this, 'generate_webp_versions']);
        }
    }

    public function set_image_editor($editors) {
        if (extension_loaded('imagick')) {
            array_unshift($editors, 'WP_Image_Editor_Imagick');
        }
        return $editors;
    }

    public function set_jpeg_quality($quality) {
        return intval($this->settings['jpeg_quality'] ?? 85);
    }

    public function add_image_attributes($attr, $attachment, $size) {
        if (!empty($this->settings['lazy_load']) && !is_admin()) {
            $attr['loading'] = 'lazy';
        }
        
        if (!empty($this->settings['responsive_images'])) {
            $attr['sizes'] = '(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw';
        }
        
        return $attr;
    }

    public function generate_webp_versions($metadata) {
        if (!function_exists('imagewebp')) {
            return $metadata;
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $metadata['file'];
        
        if (file_exists($file_path)) {
            $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
            
            $image_type = wp_check_filetype($file_path)['type'];
            
            switch ($image_type) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($file_path);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($file_path);
                    break;
                default:
                    return $metadata;
            }
            
            if ($image) {
                imagewebp($image, $webp_path, 85);
                imagedestroy($image);
            }
        }
        
        return $metadata;
    }
}