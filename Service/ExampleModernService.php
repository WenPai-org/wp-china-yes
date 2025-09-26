<?php

namespace WenPai\ChinaYes\Service;

use WenPai\ChinaYes\Service\TranslationManager;
use WenPai\ChinaYes\Service\LazyTranslation;

class ExampleModernService {
    
    private $settings;
    
    public function __construct() {
        add_action('init', [$this, 'initializeService'], 15);
    }
    
    public function initializeService() {
        $this->setupTranslatedContent();
    }
    
    private function setupTranslatedContent() {
        $translatedOptions = [
            'store_section' => [
                'title' => $this->t('应用市场'),
                'description' => $this->t('选择您的应用市场加速方式'),
                'options' => [
                    'wenpai' => $this->t('文派开源'),
                    'proxy' => $this->t('官方镜像'),
                    'off' => $this->t('不启用')
                ]
            ],
            'acceleration_section' => [
                'title' => $this->t('萌芽加速'),
                'description' => $this->t('前端资源加速设置'),
                'options' => [
                    'googlefonts' => 'Google 字体',
                    'googleajax' => 'Google 前端库',
                    'cdnjs' => 'CDNJS 前端库'
                ]
            ],
            'notification_section' => [
                'title' => $this->t('通知管理'),
                'description' => $this->t('管理和控制 WordPress 后台各类通知的显示。'),
                'options' => [
                    'disable_all' => $this->t('禁用所有通知'),
                    'selective' => $this->t('选择性禁用'),
                    'method' => $this->t('禁用方式')
                ]
            ]
        ];
        
        $this->processTranslatedOptions($translatedOptions);
    }
    
    private function processTranslatedOptions($options) {
        foreach ($options as $section => $data) {
            error_log("处理部分: " . $data['title']);
            error_log("描述: " . $data['description']);
            
            foreach ($data['options'] as $key => $value) {
                error_log("选项 {$key}: {$value}");
            }
        }
    }
    
    private function t($text) {
        return TranslationManager::translate($text);
    }
    
    public function demonstrateLazyTranslation() {
        $lazyTitle = LazyTranslation::create('应用市场');
        $lazyArray = LazyTranslation::createArray([
            'title' => '萌芽加速',
            'subtitle' => '文件加速',
            'options' => [
                'enable' => '启用',
                'disable' => '禁用'
            ]
        ]);
        
        return [
            'lazy_title' => $lazyTitle,
            'lazy_array' => $lazyArray,
            'resolved_title' => (string)$lazyTitle,
            'resolved_array' => LazyTranslation::resolveArray($lazyArray)
        ];
    }
    
    public function getTranslationStatus() {
        return [
            'translations_loaded' => TranslationManager::isLoaded(),
            'init_action_fired' => did_action('init'),
            'plugins_loaded_fired' => did_action('plugins_loaded'),
            'current_hook' => current_action()
        ];
    }
}