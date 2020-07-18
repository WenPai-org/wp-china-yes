<?php
/**
 * Common access to bundle headers.
 * Because access to theme and plugin header data via WordPress is a total mess.
 * 
 * @property-read string $Name
 * @property-read string $Author
 * @property-read string $AuthorURI
 * @property-read string $TextDomain
 * @property-read string $DomainPath
 */
class Loco_package_Header {
    
    /**
     * WordPress's internal data
     * @var array|ArrayAccess
     */
    private $wp;    

    
    public function __construct( $header ){
        $this->wp = $header;
    }


    /**
     * @return string
     */
    public function __get( $prop ){
        $wp = $this->wp;
        // prefer require "get" method to access raw properties (WP_Theme)
        if( method_exists($wp, 'get') && ( $value = $wp->get($prop) ) ){
            return $value;
        }
        // may have key directly, e.g. TextDomain in plugin array
        if( isset($wp[$prop]) ){
            return $wp[$prop];
        }
        // else header not defined, which is probably fine
        return '';
    }


    /**
     * @codeCoverageIgnore
     */
    public function __set( $prop, $value ){
        throw new RuntimeException('Read only');
    }


    /**
     * Get bundle author as linked text, just like the WordPress plugin list does
     * @return string escaped HTML
     */
    public function getAuthorLink(){
        if( ( $link = $this->AuthorURI ) || ( $link = $this->PluginURI ) || ( $link = $this->ThemeURI ) ){
            $author = $this->Author or $author = $link;
            return '<a href="'.esc_url($link).'" target="_blank">'.esc_html($author).'</a>';
        }
        return '';
    }


    /**
     * Get "name" by <author> credit
     * @return string escaped HTML
     */
    public function getAuthorCredit(){
        if( $author = $this->Author ){
            $author = esc_html( strip_tags($author) );
            if( $link = $this->AuthorURI ){
                $author = '<a href="'.esc_url($link).'" target="_blank">'.$author.'</a>';
            }
        }
        else {
            $author = __('Unknown author','loco-translate');
        }
        // translators: Author credit: "<product>" <version> by <author>
        $html = sprintf( __('"%s" %s by %s','default'), esc_html($this->Name), $this->Version, $author );
        
        if( ( $link = $this->PluginURI ) || ( $link = $this->ThemeURI ) ){
            $html .= sprintf( ' &mdash; <a href="%s" target="_blank">%s</a>', esc_url($link), __('Visit official site','loco-translate') );
        }
        
        return $html;
    }


    /**
     * Get hostname of vendor that hosts theme/plugin
     * @return string e.g. "wordpress.org"
     */
    public function getVendorHost(){
        $host = '';
        if( ( $url = $this->PluginURI ) || ( $url = $this->ThemeURI ) ){
            if( $host = parse_url($url,PHP_URL_HOST) ){
                $bits = explode( '.', $host );
                $host = implode( '.', array_slice($bits,-2) );
            }
        }
        return $host;
    }

}