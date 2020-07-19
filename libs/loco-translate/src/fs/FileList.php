<?php
/**
 * Simple list of file paths
 */
class Loco_fs_FileList extends ArrayIterator implements Loco_fs_FileListInterface {

    /**
     * Hash map for ensuring files only added once
     * @var array
     */
    private $unique = array();
    
    /**
     * Construct with initial list if files
     * @param Loco_fs_File[]
     */
    public function __construct( $a = array() ){
        parent::__construct( array() );
        foreach( $a as $file ){
            $this->add( $file );
        }
    }


    /**
     * Use instead of clone because that does weird things to ArrayIterator instances.
     * Note that this does NOT clone individual file members.
     * @return Loco_fs_FileList
     */
    public function copy(){
        return new Loco_fs_FileList( $this->getArrayCopy() );
    }
    

    /**
     * Like getArrayCopy, but exports string paths
     * @return array
     */
    public function export(){
        $a = array();
        foreach( $this as $file ){
            $a[] = (string) $file;
        }
        return $a;
    }


    /**
     * @internal
     * @return string
     */
    public function __toString(){
        return implode( "\n", $this->getArrayCopy() );
    }


    /**
     * Generate a unique key for file
     * @param Loco_fs_File
     * @return string
     */
    private function hash( Loco_fs_File $file ){
        $path = $file->normalize();
        // if file is real, we must resolve its real path
        if( $file->exists() && ( $real = realpath($path) ) ){
            $path = $real;
        }
        return $path;
    }


    /**
     * {@inheritDoc}
     */
    public function offsetSet( $index, $value ){
        throw new Exception('Use Loco_fs_FileList::add');
    }


    /**
     * {@inheritDoc}
     */
    public function add( Loco_fs_File $file ){
        $hash = $this->hash( $file );
        if( isset($this->unique[$hash]) ){
            return false;
        }
        $this->unique[$hash] = true;
        parent::offsetSet( null, $file );
        return true;
    }


    /**
     * Check if given file is already in list
     * @param Loco_fs_File
     * @return bool
     */
    public function has( Loco_fs_File $file ){
        $hash = $this->hash( $file );
        return isset($this->unique[$hash]);
    }


    /**
     * Get a copy of list with only files not contained in passed list
     * @param Loco_fs_FileList
     * @return Loco_fs_FileList
     */
    public function diff( Loco_fs_FileList $not_in ){
        $list = new Loco_fs_FileList;
        foreach( $this as $file ){
            $not_in->has($file) || $list->add( $file );
        }
        return $list;
    }



    /**
     * Merge another list of the SAME TYPE uniquely on top of current one
     * @param Loco_fs_FileList
     * @return Loco_fs_FileList
     */
    public function augment( loco_fs_FileList $list ){
        foreach( $list as $file ){
            $this->add( $file );
        }
        return $this;
    }
    
}
