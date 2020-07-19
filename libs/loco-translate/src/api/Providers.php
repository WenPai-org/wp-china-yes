<?php
/**
 * Third party API helpers
 */
abstract class Loco_api_Providers {


    /**
     * Export API credentials for all supported APIs
     * @return array[]
     */
    public static function export(){
        return apply_filters( 'loco_api_providers', self::builtin() );
    }
    
    
    /**
     * @return array[]
     */
    public static function builtin(){
        $settings = Loco_data_Settings::get();
        return array (
            array (
                'id' => 'google',
                'name' => 'Google Translate',
                'key' => $settings->offsetGet('google_api_key'),
            ),
            array (
                'id' => 'microsoft',
                'name' => 'Microsoft Translator',
                'key' => $settings->offsetGet('microsoft_api_key'),
                'region' => $settings->offsetGet('microsoft_api_region'),
            ),
            array (
                'id' => 'yandex',
                'name' => 'Yandex.Translate',
                'key' => $settings->offsetGet('yandex_api_key'),
            ),
        );
    } 
    
}