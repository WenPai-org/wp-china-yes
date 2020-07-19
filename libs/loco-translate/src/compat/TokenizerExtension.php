<?php
/**
 * Placeholder for missing PHP "tokenizer" extension.
 * Just avoids fatal errors. Does not actually replace functionality.
 * 
 * If this extension is missing PHP string extraction will not work at all.
 */
abstract class Loco_compat_TokenizerExtension {
    
    public static function token_get_all( $value ){
        return array();
    }
    
}



// @codeCoverageIgnoreStart
 
if( ! function_exists('token_get_all') ){
    function token_get_all( $value ){
        return Loco_compat_TokenizerExtension::token_get_all($value);
    }
}
