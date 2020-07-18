<?php
/**
 * Handles execution of Ajax actions and rendering of JSON
 */
class Loco_mvc_AjaxRouter extends Loco_hooks_Hookable {
    
    /**
     * Current ajax controller
     * @var Loco_mvc_AjaxController
     */
    private $ctrl;

    /**
     * @var Loco_output_Buffer
     */
    private $buffer;

    /**
     * Generate a GET request URL containing required routing parameters
     * @param string
     * @param array
     * @return string
     */
    public static function generate( $route, array $args = array() ){
        // validate route autoload if debugging
        if( loco_debugging() ){
            class_exists( self::routeToClass($route) );
        }
        $args += array (
            'route' => $route,
            'action' => 'loco_ajax',
            'loco-nonce' => wp_create_nonce($route),
        );
        return admin_url('admin-ajax.php','relative').'?'.http_build_query($args,null,'&');
    }


    /**
     * Create a new ajax router and starts buffering output immediately
     */
    public function __construct(){
        $this->buffer = Loco_output_Buffer::start();
        parent::__construct();
    }


    /**
     * "init" action callback.
     * early-ish hook that ensures controllers can initialize
     */
    public function on_init(){
        try {
            $class = self::routeToClass( $_REQUEST['route'] );
            // autoloader will throw error if controller class doesn't exist
            $this->ctrl = new $class;
            $this->ctrl->_init( $_REQUEST );
            // hook name compatible with AdminRouter, plus additional action for ajax hooks to set up
            do_action('loco_admin_init', $this->ctrl );
            do_action('loco_ajax_init', $this->ctrl );
        }
        catch( Loco_error_Exception $e ){
            $this->ctrl = null;
            // throw $e; // <- debug
        }
    }

    
    /**
     * @param string
     * @return string
     */
    private static function routeToClass( $route ){
        $route = explode( '-', $route );
        // convert route to class name, e.g. "foo-bar" => "Loco_ajax_foo_BarController"
        $key = count($route) - 1;
        $route[$key] = ucfirst( $route[$key] );
        return 'Loco_ajax_'.implode('_',$route).'Controller';
    }


    /**
     * Common ajax hook for all Loco admin JSON requests
     * Note that tests call renderAjax directly.
     * @codeCoverageIgnore
     */
    public function on_wp_ajax_loco_json(){
        $json = $this->renderAjax();
	    $this->exitScript( $json, array (
	        'Content-Type' => 'application/json; charset=UTF-8',
	    ) );
    }


    /**
     * Additional ajax hook for download actions that won't be JSON
     * Note that tests call renderDownload directly.
     * @codeCoverageIgnore
     */
    public function on_wp_ajax_loco_download(){
        $data = $this->renderDownload();
        if( is_string($data) ){
            $path = ( $this->ctrl ? $this->ctrl->get('path') : '' ) or $path = 'error.json';
            $file = new Loco_fs_File( $path );
            $ext = $file->extension();
        }
        else if( $data instanceof Exception ){
            $data = sprintf('%s in %s:%u', $data->getMessage(), basename($data->getFile()), $data->getLine() );
            $ext = null;
        }
        else {
            $data = (string) $data;
            $ext = null;
        }
        $mimes = array (
            'mo'   => 'application/x-gettext-translation',
            'po'   => 'application/x-gettext',
            'pot'  => 'application/x-gettext',
            'xml'  => 'text/xml',
            'json' => 'application/json',
        );
        $headers = array();
	    if( $ext && isset($mimes[$ext]) ){
            $headers['Content-Type'] = $mimes[$ext].'; charset=UTF-8';
            $headers['Content-Disposition'] = 'attachment; filename='.$file->basename();
        }
        else {
	        $headers['Content-Type'] = 'text/plain; charset=UTF-8';
        }
        $this->exitScript( $data, $headers );
    }


	/**
	 * Exit script before WordPress shutdown, avoids hijacking of exit via wp_die_ajax_handler.
	 * Also gives us a final chance to check for output buffering problems.
	 * @codeCoverageIgnore
	 * @param string
	 * @param array
	 */
    private function exitScript( $str, array $headers ){
	    try {
            do_action('loco_admin_shutdown');
	    	Loco_output_Buffer::clear();
	    	$this->buffer = null;
		    Loco_output_Buffer::check();
		    $headers['Content-Length'] = strlen($str);
		    foreach( $headers as $name => $value ){
			    header( $name.': '.$value, true );
		    }
	    }
	    catch( Exception $e ){
		    Loco_error_AdminNotices::add( Loco_error_Exception::convert($e) );
		    $str = $e->getMessage();
	    }
    	echo $str;
    	exit(0);
    }


    /**
     * Execute Ajax controller to render JSON response body
     * @return string
     */
    public function renderAjax(){
        try {
            // respond with deferred failure from initAjax
            if( ! $this->ctrl ){
                $route = isset($_REQUEST['route']) ? $_REQUEST['route'] : '';
                throw new Loco_error_Exception( sprintf( __('Ajax route not found: "%s"','loco-translate'), $route ) );
            }
            // else execute controller to get json output
            $json = $this->ctrl->render();
            if( is_null($json) || '' === $json ){
                throw new Loco_error_Exception( __('Ajax controller returned empty JSON','loco-translate') );
            }
        }
        catch( Loco_error_Exception $e ){
            $json = json_encode( array( 'error' => $e->jsonSerialize(), 'notices' => Loco_error_AdminNotices::destroyAjax() ) );
        }
        catch( Exception $e ){
            $e = Loco_error_Exception::convert($e);
            $json = json_encode( array( 'error' => $e->jsonSerialize(), 'notices' => Loco_error_AdminNotices::destroyAjax() ) );
        }
        $this->buffer->discard();
        return $json;
    }


    /**
     * Execute ajax controller to render something other than JSON
     * @return string|Exception
     */
    public function renderDownload(){
        try {
            // respond with deferred failure from initAjax
            if( ! $this->ctrl ){
                throw new Loco_error_Exception( __('Download action not found','loco-translate') );
            }
            // else execute controller to get raw output
            $data = $this->ctrl->render();
            if( is_null($data) || '' === $data ){
                throw new Loco_error_Exception( __('Download controller returned empty output','loco-translate') );
            }
        }
        catch( Exception $e ){
            $data = $e;
        }
	    $this->buffer->discard();
        return $data;
    }

}