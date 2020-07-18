<?php
/**
 * Provides write operation context via the WordPress file system API
 */
class Loco_fs_FileWriter {

   /**
    * @var Loco_fs_File
    */
    private $file;

    /**
     * @var WP_Filesystem_Base
     */
    private $fs;

    /**
     * @param Loco_fs_File
     */
    public function __construct( Loco_fs_File $file ){
        $this->file = $file;
        $this->disconnect();
    }
    
    
    /**
     * @param Loco_fs_File
     * @return Loco_fs_FileWriter
     */
    public function setFile( Loco_fs_File $file ){
        $this->file = $file;
        return $this;
    }
    

    /**
     * Connect to alternative file system context
     * 
     * @param WP_Filesystem_Base
     * @param bool whether reconnect required
     * @return Loco_fs_FileWriter
     * @throws Loco_error_WriteException
     */
    public function connect( WP_Filesystem_Base $fs, $disconnected = true ){
        if( $disconnected && ! $fs->connect() ){
            $errors = $fs->errors;
            if( is_wp_error($errors) ){
                foreach( $errors->get_error_messages() as $reason ){
                    Loco_error_AdminNotices::warn($reason);
                }
            }
            throw new Loco_error_WriteException( __('Failed to connect to remote server','loco-translate') );
        }
        $this->fs = $fs;
        return $this;
    }
    
    
    /**
     * Revert to direct file system connection
     * @return Loco_fs_FileWriter
     */
    public function disconnect(){
        $this->fs = Loco_api_WordPressFileSystem::direct();
        return $this;
    }


    /**
     * Get mapped path for use in indirect file system manipulation
     * @return string
     */
    public function getPath(){
        return $this->mapPath( $this->file->getPath() );
    }


    /**
     * Map virtual path for remote file system
     * @param string
     * @return string
     */
    private function mapPath( $path ){
        if( ! $this->isDirect() ){
            $base = untrailingslashit( Loco_fs_File::abs(loco_constant('WP_CONTENT_DIR')) );
            $snip = strlen($base);
            if( substr( $path, 0, $snip ) !== $base ){
                // fall back to default path in case of symlinks
                $base = trailingslashit(ABSPATH).'wp-content';
                $snip = strlen($base);
                if( substr( $path, 0, $snip ) !== $base ){
                    throw new Loco_error_WriteException('Remote path must be under WP_CONTENT_DIR');
                }
            }
            $virt = $this->fs->wp_content_dir();
            if( false === $virt ){
                throw new Loco_error_WriteException('Failed to find WP_CONTENT_DIR via remote connection');
            }
            $virt = untrailingslashit( $virt );
            $path = substr_replace( $path, $virt, 0, $snip );
        }
        return $path;
    }


    /**
     * Test if a direct (not remote) file system
     * @return bool
     */
    public function isDirect(){
        return $this->fs instanceof WP_Filesystem_Direct;
    }


    /**
     * @return bool
     */
    public function writable(){
        return ! $this->disabled() && $this->fs->is_writable( $this->getPath() );
    }


    /**
     * @param int file mode integer e.g 0664
     * @param bool whether to set recursively (directories)
     * @return Loco_fs_FileWriter
     * @throws Loco_error_WriteException
     */
    public function chmod( $mode, $recursive = false ){
        $this->authorize();
        if( ! $this->fs->chmod( $this->getPath(), $mode, $recursive ) ){
            throw new Loco_error_WriteException( sprintf( __('Failed to chmod %s','loco-translate'), $this->file->basename() ) );
        }
        return $this;
    }


    /**
     * @param Loco_fs_File target for copy
     * @return Loco_fs_FileWriter
     * @throws Loco_error_WriteException
     */
    public function copy( Loco_fs_File $copy ){
        $this->authorize();
        $source = $this->getPath();
        $target = $this->mapPath( $copy->getPath() );
        // bugs in WP file system "exists" methods means we must force $overwrite=true; so checking file existence first
        if( $copy->exists() ){
            Loco_error_AdminNotices::debug(sprintf('Cannot copy %s to %s (target already exists)',$source,$target));
            throw new Loco_error_WriteException( __('Refusing to copy over an existing file','loco-translate') );
        }
        // ensure target directory exists, although in most cases copy will be in situ
        $parent = $copy->getParent();
        if( $parent && ! $parent->exists() ){
            $this->mkdir($parent);
        }
        // perform WP file system copy method
        if( ! $this->fs->copy($source,$target,true) ){
            Loco_error_AdminNotices::debug(sprintf('Failed to copy %s to %s via "%s" method',$source,$target,$this->fs->method));
            throw new Loco_error_WriteException( sprintf( __('Failed to copy %s to %s','loco-translate'), basename($source), basename($target) ) );
        }

        return $this;
    }


    /**
     * @param Loco_fs_File target file with new path
     * @return Loco_fs_FileWriter
     * @throws Loco_error_WriteException
     */
    public function move( Loco_fs_File $dest ){
        $orig = $this->file;
        try {
            // target should have been authorized to create the new file
            $context = clone $dest->getWriteContext();
            $context->setFile($orig);
            $context->copy($dest);
            // source should have been authorized to delete the original file
            $this->delete(false);
            return $this;
        }
        catch( Loco_error_WriteException $e ){
            Loco_error_AdminNotices::debug('copy/delete failure: '.$e->getMessage() );
            throw new Loco_error_WriteException( sprintf( 'Failed to move %s', $orig->basename() ) );
        }
    }


    /**
     * @param bool
     * @return Loco_fs_FileWriter
     * @throws Loco_error_WriteException
     */
    public function delete( $recursive = false ){
        $this->authorize();
        if( ! $this->fs->delete( $this->getPath(), $recursive ) ){
            throw new Loco_error_WriteException( sprintf( __('Failed to delete %s','loco-translate'), $this->file->basename() ) );
        }

        return $this;
    }


    /**
     * @param string
     * @return Loco_fs_FileWriter
     * @throws Loco_error_WriteException
     */
    public function putContents( $data ){
        $this->authorize();
        $file = $this->file;
        if( $file->isDirectory() ){
            throw new Loco_error_WriteException( sprintf( __('"%s" is a directory, not a file','loco-translate'), $file->basename() ) );
        }
        // file having no parent directory is likely an error, like a relative path.
        $dir = $file->getParent();
        if( ! $dir ){
            throw new Loco_error_WriteException( sprintf('Bad file path "%s"',$file) );
        }
        // avoid chmod of existing file
        if( $file->exists() ){
            $mode = $file->mode();
        }
        // may have bypassed definition of FS_CHMOD_FILE
        else {
            $mode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;
            // new file may also require directory path building
            if( ! $dir->exists() ){
                $this->mkdir($dir);
            }
        }
        $fs = $this->fs;
        $path = $this->getPath();
        if( ! $fs->put_contents($path,$data,$mode) ){
            // provide useful reason for failure if possible
            if( $file->exists() && ! $file->writable() ){
                Loco_error_AdminNotices::debug( sprintf('File not writable via "%s" method, check permissions on %s',$fs->method,$path) );
                throw new Loco_error_WriteException( __("Permission denied to update file",'loco-translate') );
            }
            // directory path should exist or have thrown error earlier.
            // directory path may not be writable by same fs context
            if( ! $dir->writable() ){
                Loco_error_AdminNotices::debug( sprintf('Directory not writable via "%s" method; check permissions for %s',$fs->method,$dir) );
                throw new Loco_error_WriteException( __("Parent directory isn't writable",'loco-translate') );
            }
            // else reason for failure is not established
            Loco_error_AdminNotices::debug( sprintf('Unknown write failure via "%s" method; check %s',$fs->method,$path) );
            throw new Loco_error_WriteException( __('Failed to save file','loco-translate').': '.$file->basename() );
        }
        
        return $this;
    }


    /**
     * Create current directory context
     * @param Loco_fs_File optional directory
     * @return bool
     * @throws Loco_error_WriteException
     */
     public function mkdir( Loco_fs_File $here = null ) {
        if( is_null($here) ){
            $here = $this->file;
        }
        $this->authorize();
        $fs = $this->fs;
        // may have bypassed definition of FS_CHMOD_DIR
        $mode = defined('FS_CHMOD_DIR') ? FS_CHMOD_DIR : 0755;
        // find first ancestor that exists while building tree
        $stack = array();
        /* @var $parent Loco_fs_Directory */
        while( $parent = $here->getParent() ){
            array_unshift( $stack, $this->mapPath( $here->getPath() ) );
            if( $parent->exists() ){
                // have existent directory, now build full path
                foreach( $stack as $path ){
                    if( ! $fs->mkdir($path,$mode) ){
                        Loco_error_AdminNotices::debug( sprintf('mkdir(%s,%03o) failed via "%s" method;',var_export($path,1),$mode,$fs->method) );
                        throw new Loco_error_WriteException( __('Failed to create directory','loco-translate') );
                    }
                }
                return true;
            }
            $here = $parent;
        }
        // refusing to create directory when the entire path is missing. e.g. "/bad"
        throw new Loco_error_WriteException( __('Failed to build directory path','loco-translate') );
    }


    /**
     * Check whether write operations are permitted, or throw
     * @throws Loco_error_WriteException
     * @return Loco_fs_FileWriter
     */
    public function authorize(){
        if( $this->disabled() ){
            throw new Loco_error_WriteException( __('File modification is disallowed by your WordPress config','loco-translate') );
        }
        $opts = Loco_data_Settings::get();
        // deny system file changes (fs_protect = 2)
        if( 1 < $opts->fs_protect && $this->file->getUpdateType() ){
            throw new Loco_error_WriteException( __('Modification of installed files is disallowed by the plugin settings','loco-translate') );
        }
        // deny POT modification (pot_protect = 2)
        // this assumes that templates all have .pot extension, which isn't guaranteed. UI should prevent saving of wrongly files like "default.po"
        if( 'pot' === $this->file->extension() &&  1 < $opts->pot_protect ){
            throw new Loco_error_WriteException( __('Modification of POT (template) files is disallowed by the plugin settings','loco-translate') );
        }
        return $this;
    } 


    /**
     * Check if file system modification is banned at WordPress level
     * @return bool
     */
    public function disabled(){
        // WordPress >= 4.8
        if( function_exists('wp_is_file_mod_allowed') ){
            $context = apply_filters( 'loco_file_mod_allowed_context', 'download_language_pack', $this->file );
            return ! wp_is_file_mod_allowed( $context );
        }
        // fall back to direct constant check
        return (bool) loco_constant('DISALLOW_FILE_MODS');
    }

}
