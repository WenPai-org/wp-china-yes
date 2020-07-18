<?php
/**
 * Placeholder for missing PHP "mbstring" extension.
 * Just avoids fatal errors. Does not actually replace functionality.
 * 
 * If the mbstring library is missing any PO files that aren't UTF-8 encoded will result in parsing failures.
 */
abstract class Loco_compat_MbstringExtension {
    
    public static function mb_detect_encoding( $str, array $encoding_list = null, $strict = null ){
        // return ! $str || preg_match('/^(?:[\\0-\\x7F]|[\\xC0-\\xDF][\\x80-\\xBF]|[\\xE0-\\xEF][\\x80-\\xBF]{2}|[\\xF0-\\xFF][\\x80-\\xBF]{3})+$/',$str)
        return ! $str || preg_match('/./u',$str)
         ? 'UTF-8' 
         : 'ISO-8859-1'
         ;
    }

    public static function mb_list_encodings(){
        return array('UTF-8','ISO-8859-1');
    }

    public static function mb_strlen( $str, $encoding = null ){
        return strlen($str);
    }

    public static function mb_convert_encoding( $str, $to_encoding, $from_encoding ){
        return $str;
    }

}


// @codeCoverageIgnoreStart

if( ! function_exists('mb_detect_encoding') ){
    function mb_detect_encoding( $str = '', array $encoding_list = array(), $strict = false ){
        return Loco_compat_MbstringExtension::mb_detect_encoding( $str, $encoding_list, $strict );
    }
}

if( ! function_exists('mb_list_encodings') ){
    function mb_list_encodings(){
        return Loco_compat_MbstringExtension::mb_list_encodings();
    }
}

if( ! function_exists('mb_strlen') ){
    function mb_strlen( $str, $encoding = null ){
        return Loco_compat_MbstringExtension::mb_strlen( $str, $encoding );
    }
}

if( ! function_exists('mb_convert_encoding') ){
    function mb_convert_encoding( $str, $to_encoding, $from_encoding = null ){
        return Loco_compat_MbstringExtension::mb_convert_encoding( $str, $to_encoding, $from_encoding );
    }
}

if( ! function_exists('mb_encoding_aliases') ){
    function mb_encoding_aliases(){
        return false;
    }
}
