<?php
/**
 * Placeholder for missing PHP "json" extension.
 * Just avoids fatal errors. Does not actually replace functionality.
 *
 * If this extension is missing no JavaScript will work in the plugin at all.
 */
abstract class Loco_compat_JsonExtension {
    
    public static function json_encode( $value ){
        return '{"error":{"code":-1,"message":"json extension is not installed"}}';
    }

    public static function json_decode( $json ){
        return null;
    }
    
}


// @codeCoverageIgnoreStart

if( ! function_exists('json_encode') ){
    function json_encode( $value ){
        return Loco_compat_JsonExtension::json_encode( $value );
    }
}

if( ! function_exists('json_decode') ){
    function json_decode( $json ){
        return Loco_compat_JsonExtension::json_decode($json);
    }
}
