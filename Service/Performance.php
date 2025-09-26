<?php

namespace WenPai\ChinaYes\Service;

defined('ABSPATH') || exit;

use function WenPai\ChinaYes\get_settings;

class Performance {

    private $settings;

    public function __construct() {
        $this->settings = get_settings();
        $this->init();
    }

    private function init() {
        add_action('init', [$this, 'optimize_wordpress']);
        add_action('wp_enqueue_scripts', [$this, 'optimize_scripts'], 999);
        add_action('wp_head', [$this, 'add_performance_hints'], 1);
        
        if (is_admin()) {
            add_action('admin_init', [$this, 'optimize_admin']);
        }
    }

    public function optimize_wordpress() {
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
        
        if (!is_admin()) {
            wp_deregister_script('jquery-migrate');
        }
        
        add_filter('xmlrpc_enabled', '__return_false');
        
        add_filter('wp_headers', function($headers) {
            if (isset($headers['X-Pingback'])) {
                unset($headers['X-Pingback']);
            }
            return $headers;
        });
        
        add_filter('emoji_svg_url', '__return_false');
        
        if (!empty($this->settings['disable_embeds'])) {
            wp_deregister_script('wp-embed');
        }
    }

    public function optimize_scripts() {
        if (!is_admin() && !empty($this->settings['defer_scripts'])) {
            add_filter('script_loader_tag', function($tag, $handle) {
                if (is_admin() || strpos($tag, 'defer') !== false) {
                    return $tag;
                }
                
                $defer_scripts = ['jquery', 'wp-embed'];
                if (in_array($handle, $defer_scripts)) {
                    return str_replace(' src', ' defer src', $tag);
                }
                
                return $tag;
            }, 10, 2);
        }
    }

    public function add_performance_hints() {
        echo '<link rel="dns-prefetch" href="//admincdn.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//cn.cravatar.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//wenpai.org">' . "\n";
        echo '<link rel="preconnect" href="https://admincdn.com" crossorigin>' . "\n";
    }

    public function optimize_admin() {
        add_filter('heartbeat_settings', function($settings) {
            $settings['interval'] = 60;
            return $settings;
        });
        
        if (!empty($this->settings['disable_admin_bar']) && !current_user_can('manage_options')) {
            show_admin_bar(false);
        }
        
        add_action('admin_enqueue_scripts', function() {
            wp_dequeue_script('thickbox');
            wp_dequeue_style('thickbox');
        });
    }
}