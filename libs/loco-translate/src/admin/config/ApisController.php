<?php
/**
 *  API keys/settings screen
 */
class Loco_admin_config_ApisController extends Loco_admin_config_BaseController {

    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $this->set( 'title', __('API keys','loco-translate') );

        // collect support API keys
        $apis = array();
        foreach( Loco_api_Providers::builtin() as $api ){
            $apis[ $api['id'] ] = new Loco_mvc_ViewParams($api);
        }
        $this->set('apis',$apis);

        // handle save action
        $nonce = $this->setNonce('save-apis');
        try {
            if( $this->checkNonce($nonce->action) ){
                $post = Loco_mvc_PostParams::get();
                if( $post->has('api') ){
                    // Save only options in post. Avoids overwrite of missing site options
                    $data = array();
                    $filter = array();
                    foreach( $apis as $id => $api ){
                        $fields = $post->api[$id];
                        if( is_array($fields) ){
                            foreach( $fields as $prop => $value ){
                                $apis[$id][$prop] = $value;
                                $prop = $id.'_api_'.$prop;
                                $data[$prop] = $value;
                                $filter[] = $prop;
                            }
                        }
                    }
                    if( $filter ){
                        Loco_data_Settings::get()->populate($data,$filter)->persistIfDirty();
                        Loco_error_AdminNotices::success( __('Settings saved','loco-translate') );
                    }
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
        
        return $this->view('admin/config/apis', compact('breadcrumb') );
    }

}