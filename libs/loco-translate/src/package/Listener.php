<?php
/**
 * Captures text domains being loaded at runtime and establishes what bundles they belong to.
 * 
 */
class Loco_package_Listener extends Loco_hooks_Hookable {

    /**
     * Global availability of a single listener
     * @var Loco_package_Listener
     */
    private static $singleton;

    /**
     * Buffer of captured text domain loads before they're resolved
     * @var array
     */
    private $buffer; 

    /**
     * Whether buffer can be flushed. i.e. whether there is anything new to resolve
     * @var bool
     */
    private $buffered;

    /**
     * Resolved theme bundles, indexed by slug (stylesheet dir)
     * @var array
     */
    private $themes;

    /**
     * Resolved plugin bundles, indexed by slug (relative file path)
     * @var array
     */
    private $plugins;

    /**
     * Map of all established bundle's and their *primary* text domain
     * @var array { slug: domain }
     */
    private $domains;

    /**
     * Map of all text domains and their official directory location
     * @var array { slug: domain }
     */
    private $domainPaths;

    /**
     * Map of all known plugin handles indexed by their relative containing directory
     * @var array { slug: domain }
     */
    private $pluginHandles;

    /**
     * List of common directories that don't indicate ownership to a bundle, e.g. WP_LANG_DIR
     * @var array
     */    
    private $globalPaths;


    /**
     * Get singleton listener or create new if not already exists
     * @return Loco_package_Listener
     */
    public static function singleton(){
        $active = self::$singleton or $active = self::create();
        return $active;
    }


    /**
     * @internal
     */
    public static function destroy(){
        if( $active = self::$singleton ){
            $active->unhook();
            self::$singleton = null;
        }
    } 


    /**
     * Create a singleton listener that we can query from anywhere
     * @return Loco_package_Listener
     */
    public static function create(){
        self::destroy();
        self::$singleton = new Loco_package_Listener;
        return self::$singleton->clear();
    }



    /**
     * @return Loco_package_Listener
     */
    public function clear(){
        $this->buffer = array();
        $this->themes = array();
        $this->plugins = array();
        $this->domains = array();
        $this->domainPaths = array();
        $this->pluginHandles = null;
        $this->buffered = false;
        $this->globalPaths = array();

        foreach( array('WP_LANG_DIR') as $name ){
            if( $value = loco_constant($name) ){
                $this->globalPaths[$value] = strlen($value);
            }
        }
        return $this;
    }



    /**
     * Early hook listening for active bundles loading their own text domains.
     */
    public function on_load_textdomain( $domain, $mofile ){
        // echo '<pre>Debug:',esc_html( json_encode(compact('domain','mofile'),JSON_UNESCAPED_SLASHES)),'</pre>';
        $this->buffered = true;
        $this->buffer[$domain][] = $mofile;
    }



    /**
     * Get primary Text Domain that's uniquely assigned to a bundle
     * @param string theme or plugin relative path
     */
    public function getDomain( $handle ){
        $this->flush();
        return isset($this->domains[$handle]) ? $this->domains[$handle] : '';
    }



    /**
     * Get the default directory path where captured files of a given domain are held 
     * @param string TextDomain
     * @return string relative path
     */
    public function getDomainPath( $domain ){
        $this->flush();
        return isset($this->domainPaths[$domain]) ? $this->domainPaths[$domain] : '';
    }

    
    
    /**
     * Utility: checks if a file path is under a given root
     * @return string subpath relative to given root
     */
    private static function relative( $path, $root ){
        $root = trailingslashit($root);
        $snip = strlen($root);
        // attempt unaltered path
        if( substr($path,0,$snip) === $root ){
            return substr( $path, $snip );
        }
        // attempt resolved in case symlinks along path
        $real = realpath($path);
        if( $real && $real !== $path && substr($real,0,$snip) === $root ){
            return substr( $real, $snip );
        }
        // path not under root
        return null;
    }



    /**
     * Check if given relative directory path the root of a known plugin
     * @param string relative plugin directory name, e.g. "foo/bar"
     * @return string relative plugin file handle, e.g. "foo/bar/baz.php"
     */
    private function isPlugin( $check ){
        if( ! $this->pluginHandles ){
            $this->pluginHandles = array();
            foreach( Loco_package_Plugin::get_plugins() as $handle => $data ){
                $this->pluginHandles[ dirname($handle) ] = $handle;
                // set default text domain because additional domains could be discovered before the canonical one
                if( isset($data['TextDomain']) && ( $domain = $data['TextDomain'] ) ){
                    $this->domains[$handle] = $domain;
                }
            }
        }
        if( ! array_key_exists($check, $this->pluginHandles) ){
            return null;
        }
        
        return $this->pluginHandles[$check];
    }


    /**
     * Convert a file path to a theme or plugin bundle
     * @return Loco_package_Bundle
     */
    private function resolve( $path, $domain ){
        $file = new Loco_fs_LocaleFile( $path );
        // ignore suffix-only files when locale is invalid as locale code would be taken wrongly as slug, e.g. if you tried to load "english.po"
        if( $file->hasPrefixOnly() ){
            return;
        }
        // no point looking at files in global directory as they tell us only the domain which we already know
        foreach( $this->globalPaths as $prefix => $length ){
            if( substr($path,0,$length) === $prefix ){
                return;
            }
        }
        // avoid infinite loops during bundle resolution
        $wasBuffered = $this->buffered;
        $this->buffered = false;
        // file prefix is *probably* the Text Domain, but can differ if load_textdomain called directly from bundle code
        $slug = $file->getPrefix() or $slug = $domain;
        $path = dirname($path);
        $bundle = null;
        while( true ){
            // check if MO file lives inside a theme 
            foreach( $GLOBALS['wp_theme_directories'] as $root ){
                $relative = self::relative($path, $root);
                if( is_null($relative) ){
                    continue;
                }
                // theme's "stylesheet directory" must be immediately under this root
                // passed path could root of theme, or any directory below it, but we only need the top level
                $chunks = explode( '/', $relative, 2 );
                $handle = current( $chunks );
                if( ! $handle ){
                    continue;
                }
                $theme = new WP_Theme( $handle, $root );
                if( ! $theme->exists() ){
                    continue;
                }
                $abspath = $root.'/'.$handle;
                // theme may have officially declared text domain
                if( $default = $theme->get('TextDomain') ){
                    $this->domains[$handle] = $default;
                }
                // else set current domain as default if not already set
                else if ( ! isset($this->domains[$handle]) ){
                    $this->domains[$handle] = $domain;
                }
                if( ! isset($this->domainPaths[$domain]) ){
                    $this->domainPaths[$domain] = self::relative( $path, $abspath );
                }
                // theme bundle may already exist
                if( isset($this->themes[$handle]) ){
                    $bundle = $this->themes[$handle];
                }
                // create default project for theme bundle
                else {
                    $bundle = Loco_package_Theme::createFromTheme($theme);
                    $this->themes[$handle] = $bundle;
                }
                // possibility that additional text domains are being added
                $project = $bundle->getProject($slug);
                if( ! $project ){
                    $project = new Loco_package_Project( $bundle, new Loco_package_TextDomain($domain), $slug );
                    $bundle->addProject( $project );
                }
                // bundle was a theme, even if we couldn't configure it, so no point checking plugins
                break 2;
            }

            // check if MO file lives inside a plugin
            foreach( array( 'WP_PLUGIN_DIR', 'WPMU_PLUGIN_DIR' ) as $const ){
                $root = loco_constant( $const );
                $relative = self::relative($path, $root);
                if( is_null($relative) ){
                    continue;
                }
                // plugin *might* live directly under root
                $stack = array();
                foreach( explode( '/', dirname($relative) ) as $next ){
                    $stack[] = $next;
                    $relbase = implode('/', $stack );
                    if( $handle = $this->isPlugin($relbase) ){
                        $abspath = $root.'/'.$handle;
                        // set this as default domain if not already cached
                        if( ! isset($this->domains[$handle]) ){
                            $this->domains[$handle] = $domain;
                        }
                        if( ! isset($this->domainPaths[$domain]) ){
                            $target = self::relative( $path, dirname($abspath) );
                            $this->domainPaths[$domain] = $target;
                        }
                        // plugin bundle may already exist
                        if( isset($this->plugins[$handle]) ){
                            $bundle = $this->plugins[$handle];
                        }
                        // create default project for plugin bundle (not necessarily the current text domain)
                        else {
                            $bundle = Loco_package_Plugin::create($handle);
                            $this->plugins[$handle] = $bundle;
                        }
                        // add current domain as translation project if not already set
                        // this avoids extra domains getting set before the default one
                        if( ! $bundle->getProject($slug) ){
                            $project = new Loco_package_Project( $bundle, new Loco_package_TextDomain($domain), $slug );
                            $bundle->addProject( $project );
                        }
                        break;
                    }
                }
            }

            // failed to establish a bundle
            break;
        }

        $this->buffered = $wasBuffered;
        return $bundle;
    }
    


    /**
     * @internal
     * Resolve all currently buffered text domain paths
     */    
    private function flush(){
        if( $this->buffered ){
            foreach( $this->buffer as $domain => $paths ){
                foreach( $paths as $path ){
                    try {
                        if( $bundle = $this->resolve($path,$domain) ){
                            continue 2;
                        }
                    }
                    catch( Loco_error_Exception $e ){
                        // silent errors for non-critical function
                    }
                }
            }
            $this->buffer = array();
            $this->buffered = false;
        }
    }



    /**
     * @return array 
     */
    public function getThemes(){
        $this->flush();
        return $this->themes;
    }



    /**
     * @return array 
     */
    public function getPlugins(){
        $this->flush();
        return $this->plugins;
    }

    
}