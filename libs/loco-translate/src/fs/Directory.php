<?php
/**
 * 
 */
class Loco_fs_Directory extends Loco_fs_File {
    
    /**
     * Recursive flag for internal use
     * @var bool
     */
    private $r = false;


    /**
     * {@inheritDoc}
     */
    public function isDirectory(){
        return true;
    }


    /**
     * Set recursive flag for use when traversing directory trees
     * @param bool
     * @return Loco_fs_Directory
     */
    public function setRecursive( $bool ){
        $this->r = (bool) $bool;
        return $this;
    }


    /**
     * @return bool
     */
    public function isRecursive(){
        return $this->r;
    }


    /**
     * Create this directory for real.
     * 
     * @throws Loco_error_WriteException
     * @return Loco_fs_Directory
     */
     public function mkdir(){
         if( ! $this->exists() ){
            $this->getWriteContext()->mkdir();
         }
         return $this;
     }

}
