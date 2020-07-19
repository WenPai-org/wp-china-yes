<?php
/**
 * Represents a bundle of type "theme"
 */
class Loco_package_Theme extends Loco_package_Bundle {

    /**
     * @var Loco_package_Theme
     */
    private $parent;


    /**
     * {@inheritdoc}
     */
    public function getSystemTargets(){
        return array ( 
            trailingslashit( loco_constant('LOCO_LANG_DIR') ).'themes',
            trailingslashit( loco_constant('WP_LANG_DIR') ).'themes',
        );
    }


    /**
     * {@inheritdoc}
     */
    public function isTheme(){
        return true;
    }


    /**
     * {@inheritdoc}
     */
    public function getType(){
        return 'Theme';
    }


    /**
     * {@inheritdoc}
     */
    public function getHeaderInfo(){
        $root = dirname( $this->getDirectoryPath() );
        $theme = new WP_Theme( $this->getSlug(), $root );
        return new Loco_package_Header( $theme );
    }


    /**
     * {@inheritdoc}
     */
    public function getMetaTranslatable(){
        return array (
            'Name'        => 'Name of the theme',
            'Description' => 'Description of the theme',
            'ThemeURI'    => 'URI of the theme',
            'Author'      => 'Author of the theme',
            'AuthorURI'   => 'Author URI of the theme',
            // 'Tags'        => 'Tags of the theme',
        );
    }


    /**
     * Get parent bundle if theme is a child
     * @return Loco_package_Theme
     */
    public function getParent(){
        return $this->parent;
    }


    /**
     * Create theme bundle definition from WordPress theme handle 
     * 
     * @param string short name of theme, e.g. "twentyfifteen"
     * @return Loco_package_Plugin
     */
    public static function create( $slug, $root = null ){
        return self::createFromTheme( wp_get_theme( $slug, $root ) );
    }



    /**
     * Create theme bundle definition from WordPress theme data 
     */
    public static function createFromTheme( WP_Theme $theme ){
        $slug = $theme->get_stylesheet();
        $base = $theme->get_stylesheet_directory();
        $name = $theme->get('Name') or $name = $slug;
        if( ! $theme->exists() ){
            throw new Loco_error_Exception('Theme not found: '.$name );
        }

        $bundle = new Loco_package_Theme( $slug, $name );
        
        // ideally theme has declared its TextDomain
        $domain = $theme->get('TextDomain') or
        // if not, we can see if the Domain listener has picked it up 
        $domain = Loco_package_Listener::singleton()->getDomain($slug);
        // otherwise we won't try to guess as it results in silent problems when guess is wrong
        
        // ideally theme has declared its DomainPath
        $target = $theme->get('DomainPath') or
        // if not, we can see if the Domain listener has picked it up 
        $target = Loco_package_Listener::singleton()->getDomainPath($domain);
        // otherwise project will use theme root by default

        
        $bundle->configure( $base, array (
            'Name' => $name,
            'TextDomain' => $domain,
            'DomainPath' => $target,
        ) );
        
        // parent theme inheritance:
        if( $parent = $theme->parent() ){
            try {
                $bundle->parent = self::createFromTheme($parent);
                $bundle->inherit( $bundle->parent );
            }
            catch( Loco_error_Exception $e ){
                Loco_error_AdminNotices::add($e);
            }
        }
        
        // TODO provide hook to modify bundle?
        // do_action( 'loco_bundle_configured', $bundle );

        return $bundle;
    }    
}