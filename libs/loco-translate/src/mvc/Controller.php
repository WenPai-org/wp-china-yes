<?php
/**
 * 
 */
abstract class Loco_mvc_Controller extends Loco_hooks_Hookable {
    
    /**
     * Execute controller and return renderable output
     * @return string
     */
    abstract public function render();

    /**
     * Get view parameter
     * @param string
     * @return mixed
     */
    abstract public function get( $prop );

    /**
     * Set view parameter
     * @param string
     * @param mixed
     * @return Loco_mvc_Controller
     */
    abstract public function set( $prop, $value );


    /**
     * Default authorization check
     * @return Loco_mvc_Controller
     */
    public function auth(){
        if( is_user_logged_in() ){
            // default capability check. child classes should override
            if( current_user_can('loco_admin') ){
                return $this;
            }
        }
        $this->exitForbidden();
    }


    /**
     * Emulate permission denied screen as performed in wp-admin/admin.php
     */
    protected function exitForbidden(){
        do_action( 'admin_page_access_denied' );
        wp_die( __( 'You do not have sufficient permissions to access this page.','default' ), 403 );
    } // @codeCoverageIgnore
    



    /**
     * Set a nonce for the current page for when it submits a form
     * @return Loco_mvc_ViewParams
     */
    public function setNonce( $action ){
        $name = 'loco-nonce';
        $value = wp_create_nonce( $action );
        $nonce = new Loco_mvc_ViewParams( compact('name','value','action') );
        $this->set('nonce', $nonce );
        return $nonce;
    }


    /**
     * Check if a valid nonce has been sent in current request.
     * Fails if nonce is invalid, but returns false if not sent so scripts can exit accordingly.
     * @throws Loco_error_Exception
     * @param string action for passing to wp_verify_nonce
     * @return bool true if data has been posted and nonce is valid
     */
    public function checkNonce( $action ){
        $posted = false;
        $name = 'loco-nonce';
        if( isset($_REQUEST[$name]) ){
            $value = $_REQUEST[$name];
            if( wp_verify_nonce( $value, $action ) ){
                $posted = true;
            }
            else {
                throw new Loco_error_Exception('Failed security check for '.$name);
            }
        }
        return $posted;
    }


    /**
     * Filter callback for `translations_api'
     * Ensures silent failure of translations_api when network disabled, see $this->getAvailableCore
     */
    public function filter_translations_api( $value = false ){
        if( apply_filters('loco_allow_remote', true ) ){
            return $value;
        }
        // returning error here has the safe effect as returning empty translations list
        return new WP_Error( -1, 'Translations API blocked by loco_allow_remote filter' );
    }
    

    /**
     * Filter callback for `pre_http_request`
     * Ensures fatal error if we failed to handle offline mode earlier.
     */
    public function filter_pre_http_request( $value = false ){
        if( apply_filters('loco_allow_remote', true ) ){
            return $value;
        }
        // little point returning WP_Error error because WordPress will just show "unexpected error" 
        throw new Loco_error_Exception('HTTP request blocked by loco_allow_remote filter' );
    }


}
