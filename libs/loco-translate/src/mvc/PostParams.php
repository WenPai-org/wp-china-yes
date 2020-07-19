<?php
/**
 * Postdata wrapper
 */
class Loco_mvc_PostParams extends Loco_mvc_ViewParams {
    
    /**
     * @var Loco_mvc_PostParams
     */
    private static $singleton;


    /**
     * Get actual postdata, not hacked postdata WordPress ruined with wp_magic_quotes
     * @return Loco_mvc_PostParams
     */
    public static function get(){
        if( ! self::$singleton ){
            self::$singleton = self::create();
        }
        return self::$singleton;
    }


    /**
     * @return void
     */
    public static function destroy(){
        self::$singleton = null;
    }


    /**
     * Check if either magic_quotes_gpc or magic_quotes_runtime are enabled.
     * Note that get_magic_quotes_gpc and get_magic_quotes_runtime are deprecated as of PHP 7.4 and always return false
     * @return bool
     */
    private static function has_magic_quotes(){
        return version_compare(PHP_VERSION,'7.4','<') && ( get_magic_quotes_gpc() || get_magic_quotes_runtime() );
    }


    /**
     * Construct clean postdata from current HTTP request
     * @return Loco_mvc_PostParams
     */
     public static function create(){
        $post = array();
        if( 'POST' === $_SERVER['REQUEST_METHOD'] ){
            // attempt to use clean input if available (without added slashes)
            $raw = (string) file_get_contents('php://input');
            if( '' !== $raw && ! self::has_magic_quotes() ){
                parse_str( $raw, $post );
            }
            // else reverse wp_magic_quotes (assumes no other process has hacked the array)
            else {
                $post = stripslashes_deep( $_POST );
            }
        }
        return new Loco_mvc_PostParams( $post );
    }


    /**
     * Construct postdata from a series of value pairs.
     * This is used in tests to simulate how a form is serialized and posted
     * 
     * @param array
     * @return Loco_mvc_PostParams
     */
    public static function fromSerial( array $serial ){
        $pairs = array();
        foreach( $serial as $pair ){
            $pairs[] = rawurlencode($pair[0]).'='.rawurlencode($pair[1]);
        }
        parse_str( implode('&',$pairs), $parsed );
        return new Loco_mvc_PostParams( $parsed );
    }


    /**
     * Collapse nested array down to series of scalar forms
     * @return array
     */
    public function getSerial(){
        $serial = array();
        $query = http_build_query( $this->getArrayCopy(), false, '&' );
        foreach( explode('&',$query) as $str ){
            $serial[] = array_map( 'urldecode', explode( '=', $str, 2 ) );
        }
        return $serial;
    }

}