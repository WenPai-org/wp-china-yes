<?php
/**
 * Placeholder for missing PHP "ctype" extension.
 */
abstract class Loco_compat_CtypeExtension {

    public static function digit( $value ){
        return 1 === preg_match('/^[0-9]+$/',$value);
    }

}


// @codeCoverageIgnoreStart

if( ! function_exists('ctype_digit') ){
    function ctype_digit( $value ){
        return Loco_compat_CtypeExtension::digit( $value );
    }
}
