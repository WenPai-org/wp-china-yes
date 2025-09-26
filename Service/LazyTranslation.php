<?php

namespace WenPai\ChinaYes\Service;

class LazyTranslation {
    
    private $text;
    private $domain;
    private $context;
    
    public function __construct($text, $domain = 'wp-china-yes', $context = null) {
        $this->text = $text;
        $this->domain = $domain;
        $this->context = $context;
    }
    
    public function __toString() {
        return $this->resolve();
    }
    
    public function resolve() {
        if (did_action('init')) {
            if ($this->context) {
                return _x($this->text, $this->context, $this->domain);
            }
            return __($this->text, $this->domain);
        }
        
        return TranslationManager::getFallback($this->text);
    }
    
    public function getText() {
        return $this->text;
    }
    
    public function getDomain() {
        return $this->domain;
    }
    
    public function getContext() {
        return $this->context;
    }
    
    public static function create($text, $domain = 'wp-china-yes', $context = null) {
        return new self($text, $domain, $context);
    }
    
    public static function createArray($texts, $domain = 'wp-china-yes') {
        $result = [];
        foreach ($texts as $key => $text) {
            if (is_string($text)) {
                $result[$key] = new self($text, $domain);
            } else {
                $result[$key] = $text;
            }
        }
        return $result;
    }
    
    public static function resolveArray($array) {
        $result = [];
        foreach ($array as $key => $value) {
            if ($value instanceof self) {
                $result[$key] = $value->resolve();
            } elseif (is_array($value)) {
                $result[$key] = self::resolveArray($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}

function t($text, $domain = 'wp-china-yes', $context = null) {
    return LazyTranslation::create($text, $domain, $context);
}

function tr($text, $domain = 'wp-china-yes', $context = null) {
    if (did_action('init')) {
        if ($context) {
            return _x($text, $context, $domain);
        }
        return __($text, $domain);
    }
    return TranslationManager::getFallback($text);
}