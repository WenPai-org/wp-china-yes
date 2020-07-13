<?php
/**
 * Dummy file that just holds content in memory. 
 * Use when you don't want to commit data to disk but you need to pass a typed file object
 */
class Loco_fs_DummyFile extends Loco_fs_File {

    /**
     * @var string
     */
    private $contents = '';

    /**
     * @var int
     */
    private $mtime = 0;

    /**
     * @var int
     */
    private $fmode = 0644;

    /**
     * @var int
     */
    private $uid = 0;

    /**
     * @var int
     */
    private $gid = 0;

    
    public function __construct($path){
        parent::__construct($path);
        $this->mtime = time();
    }


    /**
     * {@inheritdoc}
     */
    public function exists(){
        return false;
    }


    /**
     * {@inheritdoc}
     */
    public function getContents(){
        return $this->contents;
    }


    /**
     * {@inheritdoc}
     */
    public function size(){
        return strlen($this->contents);
    }


    /**
     * {@inheritdoc}
     */
    public function putContents( $contents ){
        $this->contents = (string) $contents;
        return $this;
    }


    /**
     * {@inheritdoc}
     */
    public function modified(){
        return $this->mtime;
    }


    /**
     * Allow forcing of modified stamp for testing purposes
     * @return Loco_fs_File
     */
    public function touch( $modified ){
        $this->mtime = (int) $modified;
        return $this;
    }


    /**
     * {@inheritdoc}
     */
    public function mode(){
        return $this->fmode;
    }


    /**
     * {@inheritdoc}
     */
    public function chmod( $mode, $recursive = false ){
        $this->fmode = (int) $mode;
        return $this;
    }


    /**
     * TODO implement in parent
     */
    public function chown( $uid = null, $gid = null ){
        if( is_int($uid) ){
            $this->uid = $uid;
        }
        if( is_int($gid) ){
            $this->gid = $gid;
        }
        return $this;
    }



    /**
     * {@inheritdoc}
     */
    public function copy( $dest ){
        $copy = clone $this;
        $copy->path = $dest;
        return $copy;
    }


    /**
     * {@inheritdoc}
     */
    public function uid(){
        return $this->uid;
    }


    /**
     * {@inheritdoc}
     */
    public function gid(){
        return $this->gid;
    }
    
    
    
    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function writable(){
        $mode = $this->mode();
        // world writable
        if( $mode & 02 ){
            return true;
        }
        // group writable
        if( ( $mode & 020 ) && $this->gid() === Loco_compat_PosixExtension::getgid() ){
            return true;
        }
        // owner writable
        if( ( $mode & 0200 ) && $this->uid() === Loco_compat_PosixExtension::getuid() ){
            return true;
        }
        // else locked:
        return false;
    }    

}