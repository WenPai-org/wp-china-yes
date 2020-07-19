<?php
/**
 * 
 */
class Loco_fs_File {
    
    /**
     * @var Loco_fs_FileWriter
     */
    private $w;

    /**
     * Path to file
     * @var string
     */
    private $path;
    
    /**
     * Cached pathinfo() data 
     * @var array
     */
    private $info;

    /**
     * Base path which path has been normalized against
     * @var string
     */
    private $base;

    /**
     * Flag set when current path is relative
     * @var bool
     */
    private $rel;


    /**
     * Check if a path is absolute and return fixed slashes for readability
     * @param string
     * @return string fixed path, or "" if not absolute
     */
    public static function abs( $path ){
        $path = (string) $path;
        if( '' !== $path ){
            $chr1 = substr($path,0,1);
            // return unmodified path if starts "/"
            if( '/' === $chr1 ){
                return $path;
            }
            // Windows drive path if "X:" or network path if "\\"
            $chr2 = (string) substr($path,1,1);
            if( '' !== $chr2 ){
                if( ':' === $chr2 ||  ( '\\' === $chr1 && '\\' === $chr2 ) ){
                    return strtoupper($chr1).$chr2.strtr( substr($path,2), '\\', '/' );
                }
            }
        }
        // else path is relative, so return falsey string
        return '';
    }
    

    /**
     * Create file with initial, unvalidated path
     * @param string
     */    
    public function __construct( $path ){
        $this->setPath( $path );
    }


    /**
     * Internally set path value and flag whether relative or absolute
     * @param string
     * @return string
     */
    private function setPath( $path ){
        $path = (string) $path;
        if( $fixed = self::abs($path) ){
            $path = $fixed;
            $this->rel = false;
        }
        else {
            $this->rel = true;
        }
        if( $path !== $this->path ){
            $this->path = $path;
            $this->info = null;
        }
        return $path;
    }


    /**
     * @return bool
     */
    public function isAbsolute(){
        return ! $this->rel;
    }


    /**
     * @internal
     */
    public function __clone(){
        $this->cloneWriteContext( $this->w );
    }


    /**
     * Copy write context with our file reference
     * @param Loco_fs_FileWriter 
     * @return Loco_fs_File
     */
    private function cloneWriteContext( Loco_fs_FileWriter $context = null ){
        if( $context ){
            $context = clone $context;
            $this->w = $context->setFile($this);
        }
        return $this;
    }


    /**
     * Get file system context for operations that *modify* the file system.
     * Read operations and operations that stat the file will always do so directly.
     * @return Loco_fs_FileWriter 
     */
    public function getWriteContext(){
        if( ! $this->w ){
            $this->w = new Loco_fs_FileWriter( $this );
        }
        return $this->w;
    }


    /**
     * @internal
     */
    private function pathinfo(){
        return is_array($this->info) ? $this->info : ( $this->info = pathinfo($this->path) );
    }


    /**
     * @return bool
     */
    public function exists(){
        return file_exists( $this->path );
    }


    /**
     * @return bool
     */
    public function writable(){
        return $this->getWriteContext()->writable();
    }


    /**
     * @return bool
     */
    public function deletable(){
        $parent = $this->getParent();
        if( $parent && $parent->writable() ){
            // sticky directory requires that either the file its parent is owned by effective user
            if( $parent->mode() & 01000 ){
                $writer = $this->getWriteContext();
                if( $writer->isDirect() && ( $uid = Loco_compat_PosixExtension::getuid() ) ){
                    return $uid === $this->uid() || $uid === $parent->uid();
                }
                // else delete operation won't be done directly, so can't pre-empt sticky problems
                // TODO is it worth comparing FTP username etc.. for ownership?
            }
            // defaulting to "deletable" based on fact that parent is writable.
            return true;
        }
        return false;
    }


    /**
     * Get owner uid
     * @return int
     */
    public function uid(){
        return fileowner($this->path);
    }


    /**
     * Get group gid
     * @return int
     */
    public function gid(){
        return filegroup($this->path);
    }


    /**
     * Check if file can't be overwitten when existent, nor created when non-existent
     * This does not check permissions recursively as directory trees are not built implicitly
     * @return bool
     */
    public function locked(){
        if( $this->exists() ){
            return ! $this->writable();
        }
        if( $dir = $this->getParent() ){
            return ! $dir->writable();
        }
        return true;
    }


    /**
     * Check if full path can be built to non-existent file.
     * @return bool
     */
    public function creatable(){
        $file = $this;
        while( $file = $file->getParent() ){
            if( $file->exists() ){
                return $file->writable();
            }
        }
        return false;
    }
    
    
    /**
     * @return string
     */
    public function dirname(){
        $info = $this->pathinfo();
        return $info['dirname'];
    }

    
    /**
     * @return string
     */
    public function basename(){
        $info = $this->pathinfo();
        return $info['basename'];
    }

    
    /**
     * @return string
     */
    public function filename(){
        $info = $this->pathinfo();
        return $info['filename'];
    }

    
    /**
     * @return string
     */
    public function extension(){
        $info = $this->pathinfo();
        return isset($info['extension']) ? $info['extension'] : '';
    }

    
    /**
     * @return string
     */
    public function getPath(){
        return $this->path;
    }


    /**
     * Get file modification time as unix timestamp in seconds
     * @return int
     */
    public function modified(){
        return filemtime( $this->path );
    }


    /**
     * Get file size in bytes
     * @return int
     */
    public function size(){
        return filesize( $this->path );
    }


    /**
     * @return int
     */
    public function mode(){
        if( is_link($this->path) ){
            $stat = lstat( $this->path );
            $mode = $stat[2];
        }
        else {
            $mode = fileperms($this->path);
        }
        return $mode;
    }
    

    /**
     * Set file mode
     * @param int file mode integer e.g 0664
     * @param bool whether to set recursively (directories)
     * @return Loco_fs_File
     */
    public function chmod( $mode, $recursive = false ){
        $this->getWriteContext()->chmod( $mode, $recursive );
        return $this->clearStat();
    }

    
    /**
     * Clear stat cache if any file data has changed
     * @return Loco_fs_File
     */
    public function clearStat(){
        $this->info = null;
        // PHP 5.3.0 Added optional clear_realpath_cache and filename parameters.
        if( version_compare( PHP_VERSION, '5.3.0', '>=' ) ){
            clearstatcache( true, $this->path );
        }
        // else no choice but to drop entire stat cache
        else {
            clearstatcache();
        }
        return $this;
    }

    
    /**
     * @return string
     */
    public function __toString(){
        return $this->getPath();
    }


    /**
     * Check if passed path is equal to ours
     * @param string
     * @return bool
     */
    public function equal( $path ){
        return $this->path === (string) $path;
    }


    /**
     * Normalize path for string comparison, resolves redundant dots and slashes.
     * @param string path to prefix
     * @return string
     */
    public function normalize( $base = '' ){
        if( $path = self::abs($base) ){
            $base = $path;
        }
        if( $base !== $this->base ){
            $path = $this->path;
            if( '' === $path ){
                $this->setPath($base);
            }
            else {
                if( ! $this->rel || ! $base ){
                    $b = array();
                }
                else {
                    $b = self::explode( $base, array() );
                }
                $b = self::explode( $path, $b );
                $this->setPath( implode('/',$b) );
            }
            $this->base = $base;
        }
        return $this->path;
    }


    /**
     * @param string
     * @param string[]
     * @return array
     */
    private static function explode( $path, array $b ){
        $a = explode( '/', $path );
        foreach( $a as $i => $s ){
            if( '' === $s ){
                if( 0 !== $i ){
                    continue;
                }
            }
            if( '.' === $s ){
                continue;
            }
            if( '..' === $s ){
                if( array_pop($b) ){
                    continue;
                }
            }
            $b[] = $s;
        }
        return $b;
    }


    /**
     * Get path relative to given location, unless path is already relative
     * @param string base path
     * @return string path relative to given base
     */
    public function getRelativePath( $base ){
        $path = $this->normalize();
        if( $abspath = self::abs($path) ){
            // base may needs require normalizing
            $file = new Loco_fs_File($base);
            $base = $file->normalize();
            $length = strlen($base);
            // if we are below given base path, return ./relative
            if( substr($path,0,$length) === $base ){
                ++$length;
                if( strlen($path) > $length ){
                    return substr( $path, $length );
                }
                // else paths were identical
                return '';
            }
            // else attempt to find nearest common root
            $i = 0;
            $source = explode('/',$base);
            $target = explode('/',$path);
            while( isset($source[$i]) && isset($target[$i]) && $source[$i] === $target[$i] ){
                $i++;
            }
            if( $i > 1 ){
                $depth = count($source) - $i;
                $build = array_merge( array_fill( 0, $depth, '..' ), array_slice( $target, $i ) );
                $path = implode( '/', $build );
            }
        }
        // else return unmodified
        return $path;
    }


    /**
     * @return bool
     */
    public function isDirectory(){
        if( file_exists($this->path) ){
            return is_dir($this->path);
        }
        return ! $this->extension();
    }



    /**
     * Load contents of file into a string
     * @return string
     */
    public function getContents(){
        return file_get_contents( $this->path );
    }


    /**
     * Check if path is under a theme directory 
     * @return bool
     */
    public function underThemeDirectory(){
        return Loco_fs_Locations::getThemes()->check( $this->path );
    }


    /**
     * Check if path is under a plugin directory 
     * @return bool
     */
    public function underPluginDirectory(){
        return Loco_fs_Locations::getPlugins()->check( $this->path );
    }


    /**
     * Check if path is under wp-content directory 
     * @return bool
     */
    public function underContentDirectory(){
        return Loco_fs_Locations::getContent()->check( $this->path );
    }


    /**
     * Check if path is under WordPress root directory (ABSPATH) 
     * @return bool
     */
    public function underWordPressDirectory(){
        return Loco_fs_Locations::getRoot()->check( $this->path );
    }


    /**
     * Check if path is under the global system directory 
     * @return bool
     */
    public function underGlobalDirectory(){
        return Loco_fs_Locations::getGlobal()->check( $this->path );
    }


    /**
     * @return Loco_fs_Directory|null
     */
    public function getParent(){
        $dir = null;
        $path = $this->dirname();
        if( '.' !== $path && $this->path !== $path ){ 
            $dir = new Loco_fs_Directory( $path );
            $dir->cloneWriteContext( $this->w );
        }
        return $dir;
    }


    /**
     * Copy this file for real
     * @param string new path
     * @throws Loco_error_WriteException
     * @return Loco_fs_File new file
     */
    public function copy( $dest ){
        $copy = clone $this;
        $copy->path = $dest;
        $copy->clearStat();
        $this->getWriteContext()->copy($copy);
        return $copy;
    }


    /**
     * Move/rename this file for real
     * @param Loco_fs_File target file with new path
     * @throws Loco_error_WriteException
     * @return Loco_fs_File original file that should no longer exist
     */
    public function move( Loco_fs_File $dest ){
        $this->getWriteContext()->move($dest);
        return $this->clearStat();
    }


    /**
     * Delete this file for real
     * @throws Loco_error_WriteException
     * @return Loco_fs_File
     */
    public function unlink(){
        $recursive = $this->isDirectory();
        $this->getWriteContext()->delete( $recursive );
        return $this->clearStat();
    }


    /**
     * Copy this object with an alternative file extension
     * @param string new extension
     * @return Loco_fs_File
     */
    public function cloneExtension( $ext ){
        $snip = strlen( $this->extension() );
        $file = clone $this;
        if( $snip ){
            $file->path = substr_replace( $this->path, $ext, - $snip );
        }
        else {
            $file->path .= '.'.$ext;
        }
        $file->info = null;
        return $file;
    }


    /**
     * Ensure full parent directory tree exists
     * @return Loco_fs_Directory
     */
    public function createParent(){
        if( $dir = $this->getParent() ){
            if( ! $dir->exists() ){
                $dir->mkdir();
            }
        }
        return $dir;
    }


    /**
     * @param string file contents
     * @return int number of bytes written to file
     */
    public function putContents( $data ){
        $this->getWriteContext()->putContents($data);
        $this->clearStat();
        return $this->size();
    }


    /**
     * Establish what part of the WordPress file system this is.
     * Value is that used by WP_Automatic_Updater::should_update.
     * @return string "core", "plugin", "theme" or "translation"
     */
    public function getUpdateType(){
        // global languages directory root, and canonical subdirectories
        $dirpath = (string) ( $this->isDirectory() ? $this : $this->getParent() );
        if( $sub = Loco_fs_Locations::getGlobal()->rel($dirpath) ){
            list($root) = explode('/', $sub, 2 );
            if( '.' === $root || 'themes' === $root || 'plugins' === $root ){
                return 'translation';
            }
        }
        // theme and plugin locations can be at any depth
        else if( $this->underThemeDirectory() ){
            return 'theme';
        }
        else if( $this->underPluginDirectory() ){
            return 'plugin';
        }
        // core locations are under WordPress root, but not under wp-content
        else if( $this->underWordPressDirectory() && ! $this->underContentDirectory() ){
            return 'core';
        }
        // else not an update type
        return '';
    }
    
}
