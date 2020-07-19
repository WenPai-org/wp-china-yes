<?php
/**
 * @codeCoverageIgnore
 */
class Loco_admin_DebugController extends Loco_mvc_AdminController {

    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $this->set('title','DEBUG');
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function render(){
        
        // debug package listener
        $themes = array();
        /* @var $bundle Loco_package_Bundle */
        foreach( Loco_package_Listener::singleton()->getThemes() as $bundle ){
            $themes[] = array (
                'id' => $bundle->getId(),
                'name' => $bundle->getName(),
                'default' => $bundle->getDefaultProject()->getSlug(),
                'count' => count($bundle),
            );
        }
        $this->set('themes', $themes );

        $plugins = array();
        /* @var $bundle Loco_package_Bundle */
        foreach( Loco_package_Listener::singleton()->getPlugins() as $bundle ){
            $plugins[] = array (
                'id' => $bundle->getId(),
                'name' => $bundle->getName(),
                'default' => $bundle->getDefaultProject()->getSlug(),
                'count' => count($bundle),
            );
        }
        
        
        // $this->set( 'plugins', Loco_package_Plugin::get_plugins() );
        // $this->set('installed', wp_get_installed_translations('plugins') );
        // $this->set('active', get_option( 'active_plugins', array() ) );
        // $this->set('langs',get_available_languages());

        /*$plugins = get_plugins();
        $plugin_info = get_site_transient( 'update_plugins' );
        foreach( $plugins as $plugin_file => $plugin_data ){
            if ( isset( $plugin_info->response[$plugin_file] ) ) {
                $plugins[$plugin_file]['____'] = $plugin_info->response[$plugin_file];
            }
        }*/
        
        /*/ inspect session and test flash messages
        $session = Loco_data_Session::get();
        $session->flash( 'success', microtime() );
        $this->set('session', $session->getArrayCopy() );
        Loco_data_Session::close();*/
        
        // try some notices
        Loco_error_AdminNotices::add( new Loco_error_Success('This is a sample success message') );
        Loco_error_AdminNotices::add( new Loco_error_Warning('This is a sample warning') );
        Loco_error_AdminNotices::add( new Loco_error_Exception('This is a sample error') );
        Loco_error_AdminNotices::add( new Loco_error_Debug('This is a sample debug message') );
        //*/
        
        return $this->view('admin/debug');
        
    }
    
}


