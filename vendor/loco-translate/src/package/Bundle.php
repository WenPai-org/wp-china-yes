<?php
/**
 * A bundle may use one or more text domains, and may or may not physically house them. 
 * Essentially a bundle "uses" a text domain.
 * Types are "theme", "plugin" and "core" 
 */
abstract class Loco_package_Bundle extends ArrayObject implements JsonSerializable {

    /**
     * Internal handle for targeting in WordPress, e.g. "twentyfifteen" or "loco-translate/loco.php"
     * @var string
     */
    private $handle;

    /**
     * Short name, e.g. "twentyfifteen" or "loco-translate"
     * @var string
     */
    private $slug;

    /**
     * Friendly name, e.g. "Twenty Fifteen
     * @var string
     */
    private $name;

    /**
     * Full path to root directory of bundle
     * @var Loco_fs_Directory
     */
    private $root;

    /**
     * Directory paths to exclude from all projects
     * @var Loco_fs_FileList
     */
    private $xpaths;

    /**
     * Full path to PHP bootstrap file
     * @var string
     */
    private $boot;

    /**
     * Whether bundle is a single file, as opposed to in its own directory
     * @var bool
     */
    protected $solo;

    /**
     * Method with which bundle has been configured
     * @var string|false (file|db|meta|internal)
     */
    private $saved = false;

    /**
     * Get system (i.e. "global") target locations for all projects of this type.
     * These are aways append to configs, and always excluded from serialization
     * @return array<string> absolute directory paths
     */
    abstract public function getSystemTargets();

    /**
     * Get canonical info registered with WordPress, i.e. plugin or theme headers
     * @return Loco_package_Header
     */
    abstract public function getHeaderInfo();

    /**
     * Get built-in translatable values mapped to annotation for translators
     * @return array 
     */
    abstract public function getMetaTranslatable();

    /**
     * Get type of Bundle (title case)
     * @return string
     */
    abstract public function getType();


    /**
     * Construct bundle from unique ID containing type and handle
     * @param string
     * @return Loco_package_Bundle
     */
    public static function fromId( $id ){
        $r = explode( '.', $id, 2 );
        return self::createType( $r[0], isset($r[1]) ? $r[1] : '' );
    }

    
    /**
     * @param string
     * @param string
     * @return Loco_package_Bundle
     * @throws Loco_error_Exception
     */
    public static function createType( $type, $handle ){
        $func = array( 'Loco_package_'.ucfirst($type), 'create' );
        if( is_callable($func) ){
            $bundle = call_user_func( $func, $handle );
        }
        else {
            throw new Loco_error_Exception('Unexpected bundle type: '.$type );
        }  
        return $bundle;
    }

 
    /**
     * Construct from WordPress handle and friendly name
     * @param string
     * @param string
     */
    public function __construct( $handle, $name ){
        parent::__construct( array() );
        $this->setHandle($handle)->setName($name);
        $this->xpaths = new Loco_fs_FileList;
    }


    /**
     * Re-fetch this bundle from its currently saved location
     * @return Loco_package_Bundle
     */
    public function reload(){
        return call_user_func( array( get_class($this), 'create' ), $this->getSlug() );
    }


    /**
     * Get ID that uniquely identifies bundle by its type and handle
     * @return string
     */
    public function getId(){
        $type = strtolower( $this->getType() );
        return $type.'.'.$this->getHandle();
    }



    /**
     * @return string
     */
    public function __toString(){
        return (string) $this->name;
    }


    /**
     * @return bool
     */
    public function isTheme(){
        return false;
    }


    /**
     * Get parent bundle if possible
     * @codeCoverageIgnore
     * @return Loco_package_Bundle|null
     */
    public function getParent(){
        trigger_error( $this->getType().' bundles cannot have parents. Check isTheme first', E_USER_NOTICE );
        return null;
    }


    /**
     * @return bool
     */
    public function isPlugin(){
        return false;
    }


    /**
     * Get handle of bundle unique for its type, e.g. "twentyfifteen" or "loco-translate/loco.php"
     * @return string
     */
    public function getHandle(){
        return $this->handle;
    }


    /**
     * Attempt to get the vendor-specific slug, which may or may not be the same as the internal handle
     * @return string
     */
    public function getSlug(){
        if( $slug = $this->slug ){
            return $slug;
        }
        // fall back to runtime handle
        return $this->getHandle();
    }


    /**
     * Set friendly name of bundle
     * @param string
     * @return Loco_package_Bundle
     */
    public function setName( $name ){
        $this->name = $name;
        return $this;
    }


    /**
     * Set short name of bundle which may or may not match unique handle
     * @param string
     * @return Loco_package_Bundle
     */
    public function setSlug( $slug ){
        $this->slug = $slug;
        return $this;
    }


    /**
     * Set internal handle registered with WordPress for this bundle type
     * @param string
     * @return Loco_package_Bundle
     */
    public function setHandle( $handle ){
        $this->handle = $handle;
        return $this;
    }


    /**
     * Get friendly name of bundle, e.g. "Twenty Fifteen" or "Loco Translate"
     * @return string
     */
    public function getName(){
        return $this->name;
    }
    
    
    /**
     * Whether bundle root is currently known
     * @return bool
     */
    public function hasDirectoryPath(){
        return (bool) $this->root;
    }    


    /**
     * Set root directory for bundle. e.g. theme or plugin directory
     * @param string
     * @return Loco_package_Bundle
     */
    public function setDirectoryPath( $path ){
        $this->root = new Loco_fs_Directory( $path );
        $this->root->normalize();
        return $this;
    }


    /**
     * Get absolute path to root directory for bundle. e.g. theme or plugin directory
     * @return string
     */
    public function getDirectoryPath(){
        if( $this->root ){
            return $this->root->getPath();
        }
        // without a root directory return WordPress root
        return untrailingslashit(ABSPATH);
    }


    /**
     * @return string[]
     */
    public function getVendorRoots(){
        $dirs = array();
        $base = (string) $this->getDirectoryPath();
        foreach( array('node_modules','vendor') as $f ){
            $path = $base.'/'.$f;
            if( is_dir($path) ){
                $dirs[] = $path;
            }
        }
        return $dirs;
    }


    /**
     * Get file locations to exclude from all projects in bundle. These are effectively "hidden"
     * @return Loco_fs_FileList
     */
    public function getExcludedLocations(){
        return $this->xpaths;
    }


    /**
     * Add a path for excluding from all projects
     * @param Loco_fs_File|string
     * @return Loco_package_Bundle
     */
    public function excludeLocation( $path ){
        $this->xpaths->add( new Loco_fs_File($path) );
        return $this;
    }


    /**
     * Create a file searcher from root location, excluding that which is excluded
     * @return Loco_fs_FileFinder
     */
    public function getFileFinder(){
        $root = $this->getDirectoryPath();
        /*/ if bundle is symlinked it's resource files won't be matched properly
        if( is_link($root) && ( $real = realpath($root) ) ){
            $root = $real;
        }*/
        $finder = new Loco_fs_FileFinder( $root );
        foreach( $this->xpaths as $path ){
            $finder->exclude( (string) $path );
        }
        return $finder;
    }


    /**
     * Get primary PHP source file containing bundle bootstrap code, if applicable
     * @return string
     */
    public function getBootstrapPath(){
        return $this->boot;
    }


    /**
     * Set primary PHP source file containing bundle bootstrap code, if applicable.
     * @param string path to PHP file
     * @return Loco_package_Bundle
     */
    public function setBootstrapPath( $path ){
        $path = (string) $path;
        // sanity check this is a PHP file even if it doesn't exist
        if( '.php' !== substr($path,-4) ){
            throw new Loco_error_Exception('Bootstrap file should end .php'.$path );
        }
        $this->boot = $path;
        // base directory can be inferred from bootstrap path
        if( ! $this->hasDirectoryPath() ){
            $this->setDirectoryPath( dirname($path) );
        }
        return $this;
    }


    /**
     * Test whether bundle consists of a single file
     */
    public function isSingleFile(){
        return (bool) $this->solo;
    }
    
    
    /**
     * Add all projects defined in a TextDomain
     * @param Loco_package_TextDomain
     * @return Loco_package_Bundle
     */
    public function addDomain( Loco_package_TextDomain $domain ){
        /* @var Loco_package_Project $proj */
        foreach( $domain as $proj ){
            $this->addProject($proj);
        }
        return $this;
    }


    /**
     * Add a translation project to bundle.
     * Note that this always adds without checking uniqueness. Call hasProject first if it could be a duplicate
     * @param Loco_package_Project
     * @return Loco_package_Bundle
     */
    public function addProject( Loco_package_Project $project ){
        // add global targets
        foreach( $this->getSystemTargets() as $path ){
            $project->addSystemTargetDirectory( $path );
        }
        // add global exclusions affecting source and target locations
        foreach( $this->xpaths as $path ){
            $project->excludeLocation( $path );
        }
        // projects must be unique by Text Domain and "slug" (used to prefix files)
        // however, I am not indexing them here on purpose so domain and slug may be added at any time.
        $this[] = $project;
        return $this;
    }


    /**
     * Export projects grouped by domain
     * @return array indexed by Text Domain name
     */
    public function exportGrouped(){
        $domains = array();
        /* @var $proj Loco_package_Project */
        foreach( $this as $proj ){
            $domain = $proj->getDomain();
            $key = $domain->getName();
            $domains[$key][] = $proj; 
        }
        return $domains;
    }



    /**
     * Create a suitable Text Domain from bundle's name.
     * Note that internal handle may be a directory name differing entirely from the author's intention, hence the configured bundle name is slugged instead
     * @return Loco_package_TextDomain
     */
    public function createDomain(){
        $slug = sanitize_title( $this->name, $this->slug );
        return new Loco_package_TextDomain( $slug );
    }



    /**
     * Generate default configuration. 
     * Adds a simple one domain, one project config
     * @param string optional Text Domain to use
     * @return Loco_package_Project
     */
    public function createDefault( $domainName = null ){
        if( is_null($domainName) ){
            $domain = $this->createDomain();
        }
        else {
            $domain = new Loco_package_TextDomain($domainName);
        }
        $project = $domain->createProject( $this, $this->name );
        if( $this->solo ){
            $project->addSourceFile( $this->getBootstrapPath() );
        }
        else {
            $project->addSourceDirectory( $this->getDirectoryPath() );
        }

        $this->addProject( $project );
        return $project;
    }



    /**
     * Configure from custom saved option
     * @return bool whether configured
     */
    public function configureDb(){
        if( $option = $this->getCustomConfig() ){
            $option->configure();
            $this->saved = 'db';
            return true;
        }
        return false;
    }



    /**
     * Configure from XML config
     * @return bool whether configured
     */
    public function configureXml(){
        if( $xmlfile = $this->getConfigFile() ){
            $reader = new Loco_config_BundleReader($this);
            $reader->loadXml( $xmlfile );
            $this->saved = 'file';
            return true;
        }
        return false;
    }



    /**
     * Get XML configuration file used to define this bundle
     * TODO will we also support JSON for when dom extension is loaded?
     * TODO support custom location for user-saved XML?
     * @return Loco_fs_File
     */
    public function getConfigFile(){
        $base = $this->getDirectoryPath();
        $file = new Loco_fs_File( $base.'/loco.xml' );
        if( ! $file->exists() || ! loco_check_extension('dom') ){
            return null;
        }
        return $file;
    }



    /**
     * Check whether bundle is manually configured, as opposed to guessed
     * @return string (file|db|meta|internal)
     */    
    public function isConfigured(){
        return $this->saved;
    }


    /**
     * Do basic configuration from bundle meta data (file headers)
     * @param array header tags from theme or plugin bootstrapper
     * @return bool whether configured
     */
    public function configureMeta( array $header ){
        if( isset($header['Name']) ){
            $this->setName( $header['Name'] );
        }
        if( isset($header['TextDomain']) && ( $slug = $header['TextDomain'] ) ){
            $domain = new Loco_package_TextDomain($slug);
            $domain->setCanonical( true );
            // use domain as bundle handle and slug if not set when constructed
            if( ! $this->handle ){
                $this->handle = $slug;
            }
            if( ! $this->getSlug() ){
                $this->setSlug( $slug );
            }
            $project = $domain->createProject( $this, $this->name );
            // May have declared DomainPath
            $base = $this->getDirectoryPath();
            if( isset($header['DomainPath']) && ( $path = trim($header['DomainPath'],'/') ) ){
                $project->addTargetDirectory( $base.'/'.$path );
            }
            else if( $this->solo ){
                // skip
            }
            // else use standard language path if it exists
            else if( is_dir($base.'/languages') ) {
                $project->addTargetDirectory($base.'/languages');
            }
            // else add bundle root by default
            else {
                $project->addTargetDirectory( $base );
            }
            // single file bundles can have only one source file
            if( $this->solo ){
                $project->addSourceFile( $this->getBootstrapPath() );
            }
            // else add bundle root as default source file location
            else {
                $project->addSourceDirectory( $base );
            }
            // automatically block common vendor locations
            foreach( $this->getVendorRoots() as $root ){
                $this->excludeLocation($root);
            }
            // default domain added
            $this->addProject($project);
            $this->saved = 'meta';
            return true;
        }

        return false;
    }


    /**
     * Configure bundle from canonical sources.
     * Source order is "db","file","meta" where meta is the auto-config fallback.
     * No deep scanning is performed at this point
     * @return Loco_package_Bundle
     */
    public function configure( $base, array $header ){
        $this->setDirectoryPath( $base );
        $this->configureDb() || $this->configureXml() || $this->configureMeta($header);
        return $this;
    }


    /**
     * Get the custom config saved in WordPress DB for this bundle
     * @return Loco_config_CustomSaved
     */
    public function getCustomConfig(){
        $custom = new Loco_config_CustomSaved;
        if( $custom->setBundle($this)->fetch() ){
            return $custom;
        }
    }


    /**
     * Inherit another bundle. Used for child themes to display parent translations
     * @return Loco_package_Bundle
     */
    public function inherit( Loco_package_Bundle $parent ){
        foreach( $parent as $project ){
            if( ! $this->hasProject($project) ){
                $this->addProject( $project );
            }
        }
        return $this;
    }



    /**
     * Get unique translation project by text domain (and optionally slug)
     * TODO would prefer to avoid iteration, but slug can be changed at any time
     * @param string
     * @param string | null
     * @return Loco_package_Project
     */
    public function getProject( $domain, $slug = null ){
        if( is_null($slug) ){
            $slug = $domain;
        }
        /* @var $project Loco_package_Project */
        foreach( $this as $project ){
            if( $project->getSlug() === $slug && $project->getDomain()->getName() === $domain ){
                return $project;
            }
        }
        return null;
    }


    /**
     * @return Loco_package_Project
     */
    public function getDefaultProject(){
        $i = 0;
        /* @var $project Loco_package_Project */
        foreach( $this as $project ){
            if( $project->isDomainDefault() ){
                return $project;
            }
            $i++;
        }
        // nothing is domain default, but if we only have one, then duh
        if( 1 === $i ){
            return $project;
        }
    }

    
    /**
     * Test if project already exists in bundle
     * @param Loco_package_Project
     * @return bool
     */
    public function hasProject( Loco_package_Project $project ){
        return (bool) $this->getProject( $project->getDomain()->getName(), $project->getSlug() );
    }


    /**
     * @return array<Loco_package_TextDomain>
     */
    public function getDomains(){
        $domains = array();
        /* @var $project Loco_package_Project */
        foreach( $this as $project ){
            if( $domain = $project->getDomain() ){
                $d = (string) $domain;
                if( ! isset($domains[$d]) ){
                    $domains[$d] = $domain;
                }
            }
        }
        return $domains;
    }



    /**
     * Get newest timestamp of all translation files (includes template, but exclude source files)
     * @return int
     */    
    public function getLastUpdated(){
        // recent items is a convenient cache for checking last modified times
        $t = Loco_data_RecentItems::get()->hasBundle( $this->getId() );
        // else have to scan targets across all projects
        if( 0 === $t ){
            /* @var $project Loco_package_Project */
            foreach( $this as $project ){
                $t = max( $t, $project->getLastUpdated() );
            }
        }
        return $t;
    }
     

    /**
     * Get project by ID
     * @param string <domain>[.<slug>]
     * @return Loco_package_Project
     */
    public function getProjectById( $id ){
        list( $domain, $slug ) = Loco_package_Project::splitId($id);
        return $this->getProject( $domain, $slug );
    }


    /**
     * Reset bundle configuration, but keep metadata like name and slug.
     * Call this before applying a saved config, otherwise values will just be added on top.
     * @return Loco_package_Bundle
     */
    public function clear(){
        $this->exchangeArray( array() );
        $this->xpaths = new Loco_fs_FileList;
        $this->saved = false;
        return $this;
    }


    /**
     * @return array
     */
    public function jsonSerialize(){
        $writer = new Loco_config_BundleWriter( $this );
        return $writer->toArray();
    }


    /**
     * Create a copy of this bundle containing any files found that aren't currently configured
     * @return Loco_package_Bundle
     */
    public function invert(){
        return Loco_package_Inverter::compile( $this );
    }


}