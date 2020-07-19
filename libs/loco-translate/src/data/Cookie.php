<?php
/**
 * Basic abstraction of cookie setting.
 * - Provides loco_setcookie filter for tests.
 * - Provides multiple values as url-encoded pairs. Not using JSON, because stripslashes
 * 
 * Not currently used anywhere - replaced with usermeta-based session
 * @codeCoverageIgnore
 */
class Loco_data_Cookie extends ArrayObject {
    
    private $name = 'loco';

    private $expires = 0;


    /**
     * Get and deserialize cookie sent to server
     * @return Loco_data_Cookie
     */
    public static function get( $name ){
        if( isset($_COOKIE[$name]) ){
            parse_str( $_COOKIE[$name], $data );
            if( $data ){
                $cookie = new Loco_data_Cookie( $data );
                return $cookie->setName( $name );
            }
        }
    }

    
    /**
     * @internal
     */
    public function __toString(){
        $data = $this->getArrayCopy();
        return http_build_query( $data, null, '&' );
    }

    
    /**
     * @return Loco_data_Cookie
     */
    public function setName( $name ){
        $this->name = $name;
        return $this;
    }


    /**
     * @return string
     */
    public function getName(){
        return $this->name;
    }


    /**
     * Send cookie to the browser, unless filtered out.
     * @return bool|null
     */
    public function send(){
        if( false !== apply_filters( 'loco_setcookie', $this ) ){
            $value = (string) $this;
            // @codeCoverageIgnoreStart
            return setcookie( $this->name, $value, $this->expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
    }


    /**
     * Empty values such that sending cookie would remove it from browser
     * @return Loco_data_Cookie
     */
    public function kill(){
        $this->exchangeArray( array() );
        $this->expires = time() - 86400;
        return $this;
    }

    
    
}