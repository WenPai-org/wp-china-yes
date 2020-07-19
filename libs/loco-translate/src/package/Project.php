<?php
/**
 * A project is a set of translations within a Text Domain.
 * Often a text domain will have just one set, but this allows domains to be split into multiple POT files.
 */
class Loco_package_Project {
    
    /**
     * Text Domain in which project lives
     * @var Loco_package_TextDomain
     */
    private $domain;
    
    /**
     * Bundle in which project lives
     * @var Loco_package_Bundle
     */
    private $bundle;
    
    /**
     * Friendly project name, e.g. "Network Admin"
     * @var string
     */
    private $name;
    
    /**
     * Short name used for naming files, e.g "admin"
     * @var string
     */
    private $slug;

    /**
     * Configured domain path[s] not including global search paths
     * @var Loco_fs_FileList
     */
    private $dpaths;

    /**
     * Additional system domain path[s] added separately from bundle config
     * @var Loco_fs_FileList
     */
    private $gpaths;

    /**
     * Directory paths to exclude during target scanning
     * @var Loco_fs_FileList
     */
    private $xdpaths;

    /**
     * Locations where POT, PO and MO files may be saved, including standard global paths
     * @var Loco_fs_FileFinder
     */
    private $target;
    
    /**
     * Configured source path[s] not including global search paths
     * @var Loco_fs_FileList
     */
    private $spaths;
    
    /**
     * File and directory paths to exclude from source file extraction
     * @var Loco_fs_FileList
     */
    private $xspaths;

    /**
     * Locations where extractable source files may be found
     * @var Loco_fs_FileFinder
     */
    private $source;

    /**
     * Explicitly added individual PHP source files
     * @var Loco_fs_FileList
     */
    private $sfiles;

    /**
     * Paths globally excluded by bundle-level configuration
     * @var Loco_fs_FileList
     */
    private $xgpaths;

    /**
     * POT template file, ideally named "<name>.pot"
     * @var Loco_fs_File
     */
    private $pot;

    /**
     * Whether POT file is protected from end-user update and sync operations.
     * @var bool
     */
    private $potlock;


    /**
     * Construct project from its domain and a descriptive name
     * @param Loco_package_Bundle
     * @param Loco_package_TextDomain
     * @param string
     */
    public function __construct( Loco_package_Bundle $bundle, Loco_package_TextDomain $domain, $name ){
        $this->name = $name;
        $this->bundle = $bundle;
        $this->domain = $domain;
        // take default slug from domain, avoiding wildcard
        $slug = $domain->getName();
        if( '*' === $slug ){
            $slug = '';
        }
        $this->slug = $slug;
        // sources
        $this->sfiles = new Loco_fs_FileList;
        $this->spaths = new Loco_fs_FileList;
        $this->xspaths = new Loco_fs_FileList;
        // targets
        $this->dpaths = new Loco_fs_FileList;
        $this->gpaths = new Loco_fs_FileList;
        $this->xdpaths = new Loco_fs_FileList;
        // global
        $this->xgpaths = new Loco_fs_FileList;
    }


    /**
     * Split project ID into domain and slug.
     * null and "" are meaningfully different. "" means deliberately empty slug, whereas null means default
     * @param string <domain>[.<slug>]
     * @return string[] [ <domain>, <slug> ]
     */
    public static function splitId( $id ){
        $r = preg_split('/(?<!\\\\)\\./', $id, 2 );
        $domain = stripcslashes($r[0]);
        $slug = isset($r[1]) ? stripcslashes($r[1]) : $domain;
        return array( $domain, $slug );
    }


    /**
     * Get ID identifying project uniquely within a bundle
     * @return string
     */
    public function getId(){
        $slug = $this->getSlug();
        $domain = (string) $this->getDomain();
        if( $slug === $domain ){
            return $slug;
        }
        return addcslashes($domain,'.').'.'.addcslashes($slug,'.');
    }


    /**
     * @return string
     */
    public function __toString(){
        return (string) $this->name;
    }


    /**
     * Set friendly name of project
     * @param string
     * @return Loco_package_Project
     */
    public function setName( $name ){
        $this->name = (string) $name;
        return $this;
    }


    /**
     * Set short name of project
     * @param string
     * @return Loco_package_Project
     */
    public function setSlug( $slug ){
        $this->slug = (string) $slug;
        return $this;
    }


    /**
     * Get friendly name of project, e.g. "Network Admin"
     * @return string
     */
    public function getName(){
        return $this->name;
    }


    /**
     * Get short name of project, e.g. "admin"
     * @return string
     */
    public function getSlug(){
        return $this->slug;
    }

    
    /**
     * @return Loco_package_TextDomain
     */
    public function getDomain(){
        return $this->domain;
    }

    
    /**
     * @return  Loco_package_Bundle
     */
    public function getBundle(){
        return $this->bundle;
    }


    /**
     * Whether project is the default for its domain.
     * @return bool
     */
    public function isDomainDefault(){
        $slug = $this->getSlug();
        $name = $this->getDomain()->getName();
        // default if slug matches text domain.
        // else special case for Core "default" domain which has empty slug
        return $slug === $name || ( 'default' === $name && '' === $slug ) || 1 === count($this->bundle);
    }


    /**
     * Add a root path where translation files may live
     * @param string | Loco_fs_File
     * @return Loco_package_Project
     */
    public function addTargetDirectory( $location ){
        $this->target = null;
        $this->dpaths->add( new Loco_fs_Directory($location) );
        return $this;
    }


    /**
     * Add a global search path where translation files may live
     * @param string | Loco_fs_Directory
     * @return Loco_package_Project
     */
    public function addSystemTargetDirectory( $location ){
        $this->target = null;
        $this->gpaths->add( new Loco_fs_Directory($location) );
        return $this;
    }


    /**
     * Get domain paths configured in project
     * @return Loco_fs_FileList
     */
    public function getConfiguredTargets(){
        return $this->dpaths;
    }


    /**
     * Get system paths added to project after configuration
     * @return Loco_fs_FileList
     */
    public function getSystemTargets(){
        return $this->gpaths;
    }
    
    
    /**
     * Get all target directory roots including global search paths
     * @return Loco_fs_FileList
     */
    public function getDomainTargets(){
        return $this->getTargetFinder()->getRootDirectories();
    }
    
    
    /**
     * Lazy create all searchable domain paths including global directories
     * @return Loco_fs_FileFinder
     */
    private function getTargetFinder(){    
        if( ! $this->target ){
            $target = new Loco_fs_FileFinder;
            $target->setRecursive(false)->group('pot','po','mo');
            foreach( $this->dpaths as $path ){
                // TODO search need not be recursive if it was the configured DomainPath
                // currently no way to know at this point, so recursing by default.
                $target->addRoot( (string) $path, true );
            }
            foreach( $this->gpaths as $path ){
                $target->addRoot( (string) $path, false );
            }
            $this->excludeTargets( $target );
            $this->target = $target;
        }
        return $this->target;
    }

    
    /**
     * utility excludes current exclude paths from target finder
     * @param Loco_fs_FileFinder
     * @return Loco_fs_FileFinder
     */
    private function excludeTargets( Loco_fs_FileFinder $finder ){
        foreach( $this->xdpaths as $file ){
            if( $path = realpath( (string) $file ) ){ 
                $finder->exclude( $path );
            }
        }
        foreach( $this->xgpaths as $file ){
            if( $path = realpath( (string) $file ) ){
                $finder->exclude( $path );
            }
        }
        return $finder;
    }


    /**
     * Check if target file or directory is excluded
     * @param Loco_fs_File PO or POT file
     * @return bool
     */
    private function isTargetExcluded( Loco_fs_File $file ){
        return $this->xgpaths->has($file) || $this->xdpaths->has($file);
    }

    
    /**
     * Add a path for excluding in a recursive target file search
     * @param string | Loco_fs_File
     * @return Loco_package_Project
     */
    public function excludeTargetPath( $path ){
        $this->target = null;
        $this->xdpaths->add( new Loco_fs_File($path) );
        return $this;
    }


    /**
     * Get all paths excluded when searching for targets
     * @return Loco_fs_FileList
     */
    public function getConfiguredTargetsExcluded(){
        return $this->xdpaths;
    }


    /**
     * Lazy create all searchable source paths
     * @return Loco_fs_FileFinder
     */
    private function getSourceFinder(){
        if( ! $this->source ){    
            $source = new Loco_fs_FileFinder;
            // .php extensions configured in plugin options
            $conf = Loco_data_Settings::get();
            $exts = $conf->php_alias or $exts = array('php');
            // Only add .js extensions if enabled
            $exts = array_merge( $exts, (array) $conf->jsx_alias );
            $source->setRecursive(true)->groupBy($exts);
            /* @var $file Loco_fs_File */
            foreach( $this->spaths as $file ){
                $path = realpath( (string) $file );    
                if( $path && is_dir($path) ){
                    $source->addRoot( $path, true );
                }
            }
            $this->excludeSources( $source );
            $this->source = $source;
        }
        return $this->source;
    }


    /**
     * Utility excludes current exclude paths from target finder
     * @param Loco_fs_FileFinder
     * @return Loco_fs_FileFinder
     */
    private function excludeSources( Loco_fs_FileFinder $finder ){
        foreach( $this->xspaths as $file ){
            if( $path = realpath( (string) $file ) ){ 
                $finder->exclude( $path );
            }
        }
        foreach( $this->xgpaths as $file ){
            if( $path = realpath( (string) $file ) ){
                $finder->exclude( $path );
            }
        }
        return $finder;
    }


    /**
     * Add a root path where source files may live under for this project
     * @param string | Loco_fs_File
     * @return Loco_package_Project
     */
    public function addSourceDirectory( $location ){
        $this->source = null;
        $this->spaths->add( new Loco_fs_File($location) );
        return $this;
    }


    /**
     * Add Explicit source file to project config
     * @param string | Loco_fs_File
     * @return Loco_package_Project
     */
    public function addSourceFile( $path ){
        $this->source = null;
        $this->sfiles->add( new Loco_fs_File($path) );
        return $this;
    }


    /**
     * Add a file or directory as a source location
     * @param string | Loco_fs_File
     * @return Loco_package_Project
     */
    public function addSourceLocation( $path ){
        $file = new Loco_fs_File( $path );
        if( $file->isDirectory() ){
            $this->addSourceDirectory( $file );
        }
        else {
            $this->addSourceFile( $file );
        }
        return $this;
    }


    /**
     * Get all source directories and files defined in project
     * @return Loco_fs_FileList
     */
    public function getConfiguredSources(){
        $dynamic = $this->spaths->getArrayCopy();
        $statics = $this->sfiles->getArrayCopy();
        return new Loco_fs_FileList( array_merge( $dynamic, $statics ) );
    }


    /**
     * Test if bundle has configured source files (even if they're excluded by other rules)
     * @return bool
     */
    public function hasSourceFiles(){
        return count( $this->sfiles ) || count( $this->spaths );
    }     

    
    /**
     * Add a path for excluding in source file search
     * @param string | Loco_fs_File
     * @return Loco_package_Project
     */
    public function excludeSourcePath( $path ){
        $this->source = null;
        $this->xspaths->add( new Loco_fs_File($path) );
        return $this;
    }


    /**
     * Get all paths excluded when searching for sources
     * @return Loco_fs_FileList
     */
    public function getConfiguredSourcesExcluded(){
        return $this->xspaths;
    }


    /**
     * Add a globally excluded location affecting sources and targets
     * @param string | Loco_fs_File
     * @return Loco_package_Project
     */
    public function excludeLocation( $path ){
        $this->source = null;
        $this->target = null;
        $this->xgpaths->add( new Loco_fs_File($path) );
        return $this;
    }


    /**
     * Check whether POT file is protected from end-user update and sync operations.
     * @return bool
     */
    public function isPotLocked(){
        return (bool) $this->potlock;
    }


    /**
     * Lock POT file to prevent end-user updates0
     * @param bool
     * @return Loco_package_Project
     */
    public function setPotLock( $locked ){
        $this->potlock = (bool) $locked;
        return $this;
    }


    /**
     * Get full path to template POT (file)
     * @return Loco_fs_File
     */
    public function getPot(){
        if( ! $this->pot ){
            $name = $this->getSlug().'.pot';
            if( '.pot' !== $name ){
                // find under configured domain paths
                $targets = $this->getConfiguredTargets()->copy();
                // always permit POT file in the bundle root (i.e. outside domain path)
                if( $this->isDomainDefault() && $this->bundle->hasDirectoryPath() ){
                    $root = $this->bundle->getDirectoryPath();
                    $targets->add( new Loco_fs_Directory($root) );
                    // look in alternative language directories if only root is configured
                    if( 1 === count($targets) ){
                        foreach( array('languages','language','lang','l10n','i18n') as $d ) {
                            $alt = new Loco_fs_Directory($root.'/'.$d);
                            if( ! $this->isTargetExcluded($alt) ){
                                $targets->add($alt);
                            }
                        }
                     }
                }
                // pot check is for exact name and not recursive
                foreach( $targets as $dir ){
                    $file = new Loco_fs_File($name);
                    $file->normalize( $dir->getPath() );
                    if( $file->exists() && ! $this->isTargetExcluded($file) ){
                        $this->pot = $file;
                        break;
                    }
                }
            }
        }
        return $this->pot;
    }

    
    /**
     * Force the use of a known POT file. This could be a PO file if necessary
     * @param Loco_fs_File template POT file
     * @return Loco_package_Project
     */
    public function setPot( Loco_fs_File $pot ){
        $this->pot = $pot;
        return $this;
    }


    /**
     * Take a guess at most likely POT file under target locations
     * @return Loco_fs_File|null
     */
    public function guessPot(){
        $slug = $this->getSlug();
        if( ! is_string($slug) || '' === $slug ){
            $slug = (string) $this->getDomain();
            if( '' === $slug ){
                $slug = 'default';
            }
        }
        // search only inside bundle for template
        $finder = new Loco_fs_FileFinder;
        foreach( $this->dpaths as $path ){
            $finder->addRoot( (string) $path, true );
        }
        $this->excludeTargets($finder);
        $files = $finder->group('pot','po','mo')->exportGroups();
        foreach( array('pot','po') as $ext ){
            /* @var $pot Loco_fs_File */
            foreach( $files[$ext] as $pot ){
                $name = $pot->filename();
                // use exact match on project slug if found
                if( $slug === $name ){
                    return $pot;
                }
                // support unconventional <slug>-en_US.<ext>
                foreach( array('-en_US'=>6, '-en'=>3 ) as $tail => $len ){
                    if( '-en_US' === substr($name,-$len) && $slug === substr($name,0,-$len) ){
                        return $pot;
                    }
                }
            }
        }
        // Failed to find correctly named POT file,
        // but if a single POT file is found we'll use it.
        if( 1 === count($files['pot']) ){
            return $files['pot'][0];
        }
        // Either no POT files are found, or multiple are found.
        // if the project is the default in its domain, we can try aliases which may be PO
        if( $this->isDomainDefault() ){
            $options = Loco_data_Settings::get();
            if( $aliases = $options->pot_alias ){
                $found = array();
                /* @var $pot Loco_fs_File */
                foreach( $finder as $pot ){
                    $priority = array_search( $pot->basename(), $aliases, true );
                    if( false !== $priority ){
                        $found[$priority] = $pot;
                    }
                }
                if( $found ){
                    ksort( $found );
                    return current($found);
                }
            }
        }
        // failed to guess POT file
        return null;
    }


    /**
     * Get all extractable PHP source files found under all source paths
     * @return Loco_fs_FileList
     */
    public function findSourceFiles(){
        $source = $this->getSourceFinder();
        // augment file list from directories unless already done so
        if( ! $source->isCached() ){
            $crawled = $source->exportGroups();
            foreach( $crawled as $ext => $files ){
                /* @var Loco_fs_File $file */
                foreach( $files as $file ){
                    $name = $file->filename();
                    // skip "{name}.min.{ext}" but only if "{name}.{ext}" exists
                    if( '.min' === substr($name,-4) && file_exists( $file->dirname().'/'.substr($name,0,-4).'.'.$ext ) ){
                        continue;
                    }
                    $this->sfiles->add($file);
                }
            }
        }
        return $this->sfiles;
    }


    /**
     * Get all translation files matching project prefix across target directories
     * @param string file extension, usually "po" or "mo"
     * @return Loco_fs_LocaleFileList
     */
    public function findLocaleFiles( $ext ){
        $finder = $this->getTargetFinder();
        $list = new Loco_fs_LocaleFileList;
        $files = $finder->exportGroups();
        $prefix = $this->getSlug(); 
        $domain = $this->domain->getName();
        $default = $this->isDomainDefault();
        /* @var $file Loco_fs_File */
        foreach( $files[$ext] as $file ){
            $file = new Loco_fs_LocaleFile( $file );
            // add file if prefix matches and has a suffix. locale will be validated later
            if( $file->getPrefix() === $prefix && $file->getSuffix() ){
                $list->addLocalized( $file );
            }
            // else in some cases a suffix-only file like "el.po" can match
            else if( $default && $file->hasSuffixOnly() ){
                // theme files under their own directory
                if( $file->underThemeDirectory() ){
                    $list->addLocalized( $file );
                }
                // check followed links if they were originally under theme dir
                else if( ( $link = $finder->getFollowed($file) ) && $link->underThemeDirectory() ){
                    $list->addLocalized( $file );
                }
                // WordPress core "default" domain, default project
                else if( 'default' === $domain ){
                    $list->addLocalized( $file );
                }
            }
        }
        return $list;
    }


    /**
     * @param string file extension
     * @return Loco_fs_FileList
     */
    public function findNotLocaleFiles( $ext ){
        $list = new Loco_fs_LocaleFileList;
        $files = $this->getTargetFinder()->exportGroups();
        /* @var $file Loco_fs_LocaleFile */
        foreach( $files[$ext] as $file ){
            $file = new Loco_fs_LocaleFile( $file );
            // add file if it has no locale suffix and is inside the bundle
            if( $file->hasPrefixOnly() && ! $file->underGlobalDirectory() ){
                $list->add( $file );
            }
        }
        return $list;
    }


    /**
     * Initialize choice of PO file paths for a given locale
     * @param Loco_Locale locale to initialize translation files for
     * @return Loco_fs_FileList
     */
    public function initLocaleFiles( Loco_Locale $locale ){
        $slug = $this->getSlug();
        $domain = $this->domain->getName();
        $default = $this->isDomainDefault();
        $suffix = sprintf( '%s.po', $locale );
        $prefix = $slug ? sprintf('%s-',$slug) : '';
        $choice = new Loco_fs_FileList;
        /* @var $dir Loco_fs_Directory */
        foreach( $this->getConfiguredTargets() as $dir ){
            // theme files under their own directory normally have no file prefix
            if( $default && $dir->underThemeDirectory() ){
                $path = $dir->getPath().'/'.$suffix;
            }
            // plugin files are prefixed even in their own directory, so empty prefix here implies incorrect bundle configuration
            //else if( $default && ! $prefix && $dir->underPluginDirectory() ){
            //    $path = $dir->getPath().'/'.$domain.'-'.$suffix;
            //}
            // all other paths use configured prefix, which may be empty
            else {
                $path = $dir->getPath().'/'.$prefix.$suffix;
            }
            $choice->add( new Loco_fs_LocaleFile($path) );
        }
        /* @var $dir Loco_fs_Directory */
        foreach( $this->getSystemTargets() as $dir ){
            $path = $dir->getPath();
            // themes and plugins under global locations will be loaded by domain, regardless of prefix
            if( '/themes' === substr($path,-7) || '/plugins' ===  substr($path,-8) ){
                $path .= '/'.$domain.'-'.$suffix;
            }
            // all other paths (probably core) use configured prefix, which may be empty
            else {
                $path .= '/'.$prefix.$suffix;
            }
            $choice->add( new Loco_fs_LocaleFile($path) );
        }

        return $choice;
    }


    /**
     * Get newest timestamp of all translation files (includes template, but exclude source files)
     * @return int
     */    
    public function getLastUpdated(){
        $t = 0;
        $file = $this->getPot();
        if( $file && $file->exists() ){
            $t = $file->modified();
        }
        /* @var $file Loco_fs_File */
        foreach( $this->findLocaleFiles('po') as $file ){
            $t = max( $t, $file->modified() );
        }
        return $t;
    }


}