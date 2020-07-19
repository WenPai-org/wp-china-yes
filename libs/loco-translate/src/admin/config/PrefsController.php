<?php
/**
 *  User-level plugin preferences
 */
class Loco_admin_config_PrefsController extends Loco_admin_config_BaseController {


    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $this->set( 'title', __('User options','loco-translate') );
        
        // user preference options
        $opts = Loco_data_Preferences::get();
        $this->set( 'opts', $opts );
        
        // handle save action 
        $nonce = $this->setNonce('save-prefs');
        try {
            if( $this->checkNonce($nonce->action) ){
                $post = Loco_mvc_PostParams::get();
                if( $post->has('opts') ){
                    $opts->populate( $post->opts )->persist();
                    Loco_error_AdminNotices::success( __('Settings saved','loco-translate') );
                }
            }
        }
        catch( Loco_error_Exception $e ){
            Loco_error_AdminNotices::add($e);
        }
    }



    /**
     * {@inheritdoc}
     */
    public function render(){
        
        $title = __('Plugin settings','loco-translate');
        $breadcrumb = new Loco_admin_Navigation;
        $breadcrumb->add( $title );
        
        return $this->view('admin/config/prefs', compact('breadcrumb') ); 
    }

}
