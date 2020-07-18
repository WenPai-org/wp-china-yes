<?php
/**
 * 
 */
abstract class Loco_admin_RedirectController extends Loco_mvc_AdminController {


    /**
     * Get full URL for redirecting to.
     * @var string 
     */
    abstract public function getLocation();
    
    
    /**
     * {@inheritdoc}
     */
    public function init(){
        $location = $this->getLocation();
        if( $location && wp_redirect($location) ){
            // @codeCoverageIgnoreStart
            exit;
        }
    }



    /**
     * @internal
     */
    public function render(){
        return 'Failed to redirect';
    }
    
}