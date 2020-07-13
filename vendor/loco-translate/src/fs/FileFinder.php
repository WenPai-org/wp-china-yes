<?php
/**
 * Lazy file iterator. Pulls directory listings when required.
 */
class Loco_fs_FileFinder implements Iterator, Countable, Loco_fs_FileListInterface {

    /**
     * Top-level search directories
     * @var Loco_fs_FileList
     */
    private $roots;

    /**
     * All directories to search, including those recursed into
     * @var Loco_fs_FileList
     */
    private $subdir;
    
    /**
     * Whether directories all read into memory
     * @var bool
     */
    private $cached;

    /**
     * File listing already matched
     * @var Loco_fs_FileList
     */
    private $cache;
    
    /**
     * Internal array pointer for whole list of paths
     * @var int
     */
    private $i;
    
    /**
     * Internal pointer for directory being read
     * @var int
     */
    private $d;
    
    /**
     * Current directory being read
     * @var resource
     */
    private $dir;

    /**
     * Path of current directory being read
     * @var string
     */
    private $cwd;

    /**
     * Whether directories added to search will be recursive by default
     * @var bool
     */
    private $recursive = false;

    /**
     * Whether currently recursing into subdirectories
     * This is switched on and off as each directories is opened
     * @var bool
     */
    private $recursing;

    /**
     * Whether to follow symlinks when recursing into subdirectories
     * Root-level symlinks are always resolved when possible
     * @var bool
     */
    private $symlinks = true;

    /**
     * Registry of followed links by their original path
     * @var Loco_fs_FileList
     */
    private $linked;

    /**
     * List of file extensions to filter on and group by
     * @var Loco_fs_FileList[]
     */
    private $exts;

    /**
     * List of directory names to exclude from recursion
     * @var Loco_fs_File[]
     */
    private $excluded;     
              

    /**
     * Create initial list of directories to search
     * @param string default root to start
     */
    public function __construct( $root = '' ){
        $this->roots = new Loco_fs_FileList;
        $this->linked = new Loco_fs_FileList;
        $this->excluded = array();
        if( $root ){
            $this->addRoot( $root );
        }
    } 


    /**
     * Set recursive state of all defined roots
     * @param bool
     * @return Loco_fs_FileFinder
     */
    public function setRecursive( $bool ){
        $this->invalidate();
        $this->recursive = $bool;
        /* @var $dir Loco_fs_Directory */
        foreach( $this->roots as $dir ){
            $dir->setRecursive( $bool );
        }
        return $this;
    }


    /**
     * @param bool
     * @return Loco_fs_FileFinder
     */
    public function followLinks( $bool ){
        $this->invalidate();
        $this->symlinks = (bool) $bool;
        return $this;
    }


    /**
     * @param string
     * @return Loco_fs_Link
     */
    public function getFollowed( $path ){
        $path = (string) $path;
        /* @var Loco_fs_Link $link */
        foreach( $this->linked as $link ){
            $file = $link->resolve();
            $orig = $file->getPath();
            // exact match on followed path
            if( $orig === $path ){
                return $link;
            }
            // match further up the directory tree
            if( $file instanceof Loco_fs_Directory ){
                $orig = trailingslashit($orig);
                $snip = strlen($orig);
                if( $orig === substr($path,0,$snip) ){
                    return new Loco_fs_Link( $link->getPath().'/'.substr($path,$snip) );
                }
            }
        }
        return null;
    }
    


    /**
     * @return void
     */
    private function invalidate(){
        $this->cached = false;
        $this->cache = null;
        $this->subdir = null;
    }
    
    
    /**
     * @return Loco_fs_FileList
     */    
    public function export(){
        if( ! $this->cached ){
            $this->rewind();
            while( $this->valid() ){
                $this->next();
            }
        }
        return $this->cache;
    }    


    /**
     * @return Loco_fs_FileList[]
     */
    public function exportGroups(){
        $this->cached || $this->export();
        return $this->exts;
    }


    /**
     * Add a directory root to search.
     * @param string
     * @param bool|null
     * @return Loco_fs_FileFinder 
     */
    public function addRoot( $root, $recursive = null ){
        $this->invalidate();
        $dir = new Loco_fs_Directory($root);
        $this->roots->add( $dir );
        // new directory inherits current global setting unless set explicitly
        $dir->setRecursive( is_bool($recursive) ? $recursive : $this->recursive );
        return $this;
    }


    /**
     * Get all root directories to be searched
     * @return Loco_fs_FileList
     */
    public function getRootDirectories(){
        return $this->roots;
    }



    /**
     * Group results by file extension
     * @return Loco_fs_FileFinder
     */
    public function group(){
        return $this->groupBy( func_get_args() );
    }


    /**
     * Group results by file extensions given in array
     * @param array file extensions
     * @return Loco_fs_FileFinder
     */
    public function groupBy( array $exts ){
        $this->invalidate();
        $this->exts = array();
        foreach( $exts as $ext ){
            $this->exts[ trim($ext,'*.') ] = new Loco_fs_FileList;
        }
        return $this;
    }


    /**
     * Add one or more paths to exclude from listing
     * @param string e.g "node_modules"
     * @return Loco_fs_FileFinder
     */
    public function exclude(){
        $this->invalidate();
        foreach( func_get_args() as $path ){
            $file = new Loco_fs_File($path);
            // if path is absolute, add straight onto list
            if( $file->isAbsolute() ){
                $file->normalize();
                $this->excluded[] = $file;
            }
            // else append to all defined roots
            else {
                foreach( $this->roots as $dir ) {
                    $file = new Loco_fs_File( $dir.'/'.$path );
                    $file->normalize();
                    $this->excluded[] = $file;
                }
            }
        }
        return $this;
    }


    /**
     * Export excluded paths as file objects
     * @return Loco_fs_File[]
     */
    public function getExcluded(){
        return $this->excluded;
    }

    
    /**
     * @param Loco_fs_Directory
     * @return void
     */    
    private function open( Loco_fs_Directory $dir ){
        $path = $dir->getPath();
        $recursive = $dir->isRecursive();
        if( is_link($path) ){
            $link = new Loco_fs_Link($path);
            if( $link->isDirectory() ){
                $path = $link->resolve()->getPath();
                $this->linked->add($link);
            }
        }
        $this->cwd = $path;
        $this->recursing = $recursive;
        $this->dir = opendir($path);
    }


    /**
     * @return void
     */
    private function close(){
        closedir( $this->dir );
        $this->dir = null;
        $this->recursing = null;
    }


    /**
     * Test if given path is matched by one of our exclude rules
     * TODO would prefer a method that didn't require iteration
     * @param string
     * @return bool
     */
    public function isExcluded( $path ){
        /* @var $excl Loco_fs_File */
        foreach( $this->excluded as $excl ){
            if( $excl->equal($path) ){
                return true;
            }
        }
        return false;
    }


    /**
     * Read next valid file path from root directories
     * @return Loco_fs_File|null
     */
    private function read(){
        $path = null;
        if( is_resource($this->dir) ){
            while( $f = readdir($this->dir) ){
                // dot-files always excluded
                if( '.' === substr($f,0,1) ){
                    continue;
                }
                $path = $this->cwd.'/'.$f;
                // follow symlinks (subdir hash ensures against loops)
                if( is_link($path) ){
                    if( ! $this->symlinks ){
                        continue;
                    }
                    $link = new Loco_fs_Link($path);
                    if( $file = $link->resolve() ){
                        $path = $file->getPath();
                        $this->linked->add($link);
                    }
                    else {
                        continue;
                    }
                }
                // add subdirectory to recursion list
                // this will result in breadth-first listing
                if( is_dir($path) ){
                    if( $this->recursing && ! $this->isExcluded($path) ){
                        $subdir = new Loco_fs_Directory($path);
                        $subdir->setRecursive(true);
                        $this->subdir->add( $subdir );
                    }
                    continue;
                } 
                else if( $this->isExcluded($path) ){
                    continue;
                }
                // file represented as object containing original path
                $file = new Loco_fs_File($path);
                $this->add( $file );
                return $file;
            }
            $this->close();
        }
        // try next dir if nothing matched in this one
        $d = $this->d + 1;
        if( isset($this->subdir[$d]) ){
            $this->d = $d;
            $this->open( $this->subdir[$d] );
            return $this->read();
        }
        // else at end of all available files
        $this->cached = true;
        return null;
    }


    /**
     * {@inheritDoc}
     */
    public function add( Loco_fs_File $file ){
        if( $this->exts ){
            $ext = $file->extension();
            if( ! isset($this->exts[$ext]) ){
                return false;
            }
            $this->exts[$ext]->add($file);
        }
        if( $this->cache->add($file) ){
            $this->i++;
            return true;
        }
        return false;
    }


    /**
     * @return int
     */
    public function count(){
        return count( $this->export() );
    }



    /**
     * @return Loco_fs_File|null
     */
    public function current(){
        $i = $this->i;
        if( is_int($i) && isset($this->cache[$i]) ){
            return $this->cache[$i];
        }
        return null;
    }



    /**
     * @return Loco_fs_File|null
     */
    public function next(){
        if( $this->cached ){
            $i = $this->i + 1;
            if( isset($this->cache[$i]) ){
                $this->i = $i;
                return $this->cache[$i];
            }
        }
        else if( $path = $this->read() ){
            return $path;
        }
        // else at end of all directory listings
        $this->i = null;
        return null;
    }


    /**
     * @return int
     */
    public function key(){
        return $this->i;
    }


    /**
     * @return bool
     */
    public function valid(){
        // may be in lazy state after rewind
        // must do initial read now in case list is empty
        return is_int($this->i);
    }


    /**
     * @return void
     */
    public function rewind(){
        if( $this->cached ){
            $this->cache->rewind();
            $this->i = $this->cache->key();
        }
        else {
            $this->d = 0;
            $this->dir = null;
            $this->cache = new Loco_fs_FileList;
            // add only root directories that exist
            $this->subdir = new Loco_fs_FileList;
            /* @var Loco_fs_Directory */
            foreach( $this->roots as $root ){
                if( $root instanceof Loco_fs_Directory && $root->exists() && ! $this->isExcluded( $root->getPath() ) ){
                    $this->subdir->add($root);
                }
            }
            if( $this->subdir->offsetExists(0) ){
                $this->i = -1;
                $this->open( $this->subdir->offsetGet(0) );
                $this->next();
            }
            else {
                $this->i = null;
                $this->subdir = null;
                $this->cached = true;
            }
        }
    }


    /**
     * test whether internal list has been fully cached in memory
     */
    public function isCached(){
        return $this->cached;
    }

    
}
