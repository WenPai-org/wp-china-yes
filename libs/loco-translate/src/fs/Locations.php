<?php
/**
 * Handles various file locations
 */
class Loco_fs_Locations extends ArrayObject {

    /**
     * Singleton of WordPress root directory
     * @var Loco_fs_Locations
     */    
    private static $roots;

    /**
     * Singleton of wp-content directory
     * @var Loco_fs_Locations
     */    
    private static $conts;

    /**
     * Singleton of global languages directories
     * @var Loco_fs_Locations
     */    
    private static $langs;


    /**
     * Singleton of registered theme paths
     * @var Loco_fs_Locations
     */    
    private static $theme;


    /**
     * Singleton of registered plugin locations
     * @var Loco_fs_Locations
     */    
    private static $plugin;


    /**
     * Clear static caches
     */
    public static function clear(){
        self::$roots = null;
        self::$conts = null;
        self::$langs = null;
        self::$theme = null;
        self::$plugin = null;
    }


    /**
     * @return Loco_fs_Locations 
     */
    public static function getRoot(){
        if( ! self::$roots ){
            self::$roots = new Loco_fs_Locations( array(
                loco_constant('ABSPATH'),
            ) );
        }
        return self::$roots;
    }


    /**
     * @return Loco_fs_Locations 
     */
    public static function getContent(){
        if( ! self::$conts ){
            self::$conts = new Loco_fs_Locations( array(
                loco_constant('WP_CONTENT_DIR'),  // <- defined WP_CONTENT_DIR
                trailingslashit(ABSPATH).'wp-content', // <- default /wp-content
            ) );
        }
        return self::$conts;
    }


    /**
     * @return Loco_fs_Locations 
     */
    public static function getGlobal(){
        if( ! self::$langs ){
            self::$langs = new Loco_fs_Locations( array(
                loco_constant('WP_LANG_DIR'),
            ) );
        }
        return self::$langs;
    }


    /**
     * @return Loco_fs_Locations 
     */
    public static function getThemes(){
        if( ! self::$theme ){
            $roots = isset($GLOBALS['wp_theme_directories']) ? $GLOBALS['wp_theme_directories'] : array();
            if( ! $roots ){
                $roots[] = trailingslashit( loco_constant('WP_CONTENT_DIR') ).'themes';
            }
            self::$theme = new Loco_fs_Locations( $roots );
        }
        return self::$theme;
    }


    /**
     * @return Loco_fs_Locations 
     */
    public static function getPlugins(){
        if( ! self::$plugin ){
            self::$plugin = new Loco_fs_Locations( array(
                loco_constant('WP_PLUGIN_DIR'),
            ) );
        }
        return self::$plugin;
    }


    /**
     * @param array
     */
    public function __construct( array $paths ){
        parent::__construct( array() );
        foreach( $paths as $path ){
            $this->add( $path );
        }
    }


    /**
     * @param string normalized absolute path
     * @return Loco_fs_Locations
     */ 
    public function add( $path ){
        foreach( $this->expand($path) as $path ){
            // path must have trailing slash, otherwise "/plugins/foobar" would match "/plugins/foo/"
            $this[$path] = strlen($path);
        }
        return $this;
    }


    /**
     * Check if a given path begins with any of the registered ones
     * @param string absolute path
     * @return bool whether path matched
     */    
    public function check( $path ){
        foreach( $this->expand($path) as $path ){
            foreach( $this as $prefix => $length ){
                if( $prefix === $path || substr($path,0,$length) === $prefix ){
                    return true;
                }
            }
        }
        return false;
    }
    
    
    /**
     * Match location and return the relative subpath.
     * Note that exact match is returned as "." indicating self
     * @param string
     * @return string | null
     */
    public function rel( $path ){
        foreach( $this->expand($path) as $path ){
            foreach( $this as $prefix => $length ){
                if( $prefix === $path ){
                    return '.';
                }
                if( substr($path,0,$length) === $prefix ){
                    return untrailingslashit( substr($path,$length) );
                }
            }
        }
        return null;
    }


    /**
     * @param string
     * @return string[]
     */
    private function expand( $path ){
        $path = Loco_fs_File::abs($path);
        if( ! $path ){
            throw new InvalidArgumentException('Expected absolute path');
        }
        $paths = array( trailingslashit($path) );
        // add real path if differs
        $real = realpath($path);
        if( $real && $real !== $path ){
            $paths[] = trailingslashit($real);
        }
        return $paths;
    }
    
    
}
