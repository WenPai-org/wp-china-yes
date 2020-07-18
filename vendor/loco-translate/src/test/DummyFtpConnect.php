<?php
/**
 * Fake FTP file system.
 * - Hook into WordPress with `new Loco_test_DummyFtpConnect`
 * - Use to write a file with `$file->getWriteContext()->connect( new WP_Filesystem_Debug($creds) )`
 */ 
class Loco_test_DummyFtpConnect extends Loco_hooks_Hookable {
    
    public function filter_filesystem_method(){
        return 'debug';
    }
    
}


/**
 * Dummy FTP file system.
 * WARNING: this actually modifies files - it just does it while simulating a remote connection
 * - All operations performed "direct" when authorized, else they fail.
 */
class WP_Filesystem_Debug extends WP_Filesystem_Base {
    
    private $authed;

    /**
     * @var WP_Error
     */
    public $errors;


    public function __construct( array $opt ) {
        $this->options = $opt;
        $this->method = 'ftp';
    }


    /**
     * Dummy FTP connect: requires username=foo password=xxx
     */
    public function connect() {
        $this->authed = false;
        $this->errors = new WP_Error;
        // @codeCoverageIgnoreStart
        if( empty($this->options['hostname']) ){
            $this->errors->add( 'bad_hostname', 'Debug: empty hostname');
            return false;
        }
        if( empty($this->options['username']) ){
            $this->errors->add( 'bad_username', 'Debug: empty username');
            return false;
        }
        if( $this->options['username'] !== 'foo' ) {
            $this->errors->add( 'bad_username', 'Debug: username expected to be "foo"');
            return false;
        }
        if( empty($this->options['password']) ){
            $this->errors->add( 'bad_username', 'Debug: empty password');
            return false;
        }
        if( $this->options['password'] !== 'xxx' ) {
            $this->errors->add( 'bad_password', 'Debug: password expected to be "xxx"' );
            return false;
        }
        // @codeCoverageIgnoreEnd
        $this->authed = true;
        return true;
    }


    /**
     * @return WP_Filesystem_Debug
     */
    public function disconnect(){
        $this->authed = false;
        $this->options = array();
        return $this;
    }



    /**
     * {@inheritdoc}
     * Dummy function allows exact path to be returned, subject to debugging filters
     */
    public function find_folder( $path ){
        if( WP_CONTENT_DIR === $path ){
            return loco_constant('WP_CONTENT_DIR');
        }
        return false;
    }
    
    
    /**
     * @internal
     * Proxies supposed remote call to *real* direct call, as long as instance is authorized.
     * Deliberately not extending WP_Filesystem_Direct for safety.
     */
    private function _call( $method, array $args ){
        if( $this->authed ){
            $real = Loco_api_WordPressFileSystem::direct();
            return call_user_func_array( array($real,$method), $args );
        }
        return false;
    }
    

    /**
     * {@inheritdoc}
     */
    public function is_writable( $file ){
        return $this->_call( __FUNCTION__, func_get_args() );
    }


    /**
     * {@inheritdoc}
     */
    public function chmod( $file, $mode = false, $recursive = false ){
        return $this->_call( __FUNCTION__, func_get_args() );
    }


    /**
     * {@inheritdoc}
     */
    public function copy( $source, $destination, $overwrite = false, $mode = false ){
        return $this->_call( __FUNCTION__, func_get_args() );
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function put_contents( $path, $data, $mode = false ){
        return $this->_call( __FUNCTION__, func_get_args() );
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function delete( $file, $recursive = false, $type = false ){
        return $this->_call( __FUNCTION__, func_get_args() );
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function mkdir( $path, $chmod = false, $chown = false, $chgrp = false ){
        return $this->_call( __FUNCTION__, func_get_args() );
    }
    
    
}

