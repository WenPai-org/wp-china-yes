<?php
/**
 *  Site-wide Loco options (plugin settings)
 */
class Loco_admin_config_SettingsController extends Loco_admin_config_BaseController {


    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        
        // set current plugin options and defaults for placeholders
        $opts = Loco_data_Settings::get();
        $this->set( 'opts', $opts );
        $this->set( 'dflt', Loco_data_Settings::create() );
        
        // roles and capabilities
        $perms = new Loco_data_Permissions;

        // handle save action 
        $nonce = $this->setNonce('save-config');
        try {
            if( $this->checkNonce($nonce->action) ){
                $post = Loco_mvc_PostParams::get();
                if( $post->has('opts') ){
                    $opts->populate( $post->opts )->persist();
                    $perms->populate( $post->has('caps') ? $post->caps : array() );
                    // done update
                    Loco_error_AdminNotices::success( __('Settings saved','loco-translate') );
                    // remove saved params from session if persistent options unset
                    if( ! $opts['fs_persist'] ){
                        $session = Loco_data_Session::get();
                        if( isset($session['loco-fs']) ){
                            unset( $session['loco-fs'] );
                            $session->persist();
                        }
                    }
                }
            }
        }
        catch( Loco_error_Exception $e ){
            Loco_error_AdminNotices::add($e);
        }

        $this->set('caps', $caps = new Loco_mvc_ViewParams );
        // there is no distinct role for network admin, so we'll fake it for UI
        if( is_multisite() ){
            $caps[''] = new Loco_mvc_ViewParams( array(
                'label' => __('Super Admin','default'),
                'name' => 'dummy-admin-cap',
                'attrs' => 'checked disabled'
            ) );
        }
        foreach( $perms->getRoles() as $id => $role ){
            $caps[$id] = new Loco_mvc_ViewParams( array(
                'value' => '1',
                'label' => $perms->getRoleName($id),
                'name' => 'caps['.$id.'][loco_admin]',
                'attrs' => $perms->isProtectedRole($role) ? 'checked disabled ' : ( $role->has_cap('loco_admin') ? 'checked ' : '' ),
            ) );
        }
        // allow/deny warning levels
        $this->set('verbose', new Loco_mvc_ViewParams( array(
            0 => __('Allow','loco-translate'),
            1 => __('Allow (with warning)','loco-translate'),
            2 => __('Disallow','loco-translate'),
        ) ) );
    }



    /**
     * {@inheritdoc}
     */
    public function render(){
        
        $title = __('Plugin settings','loco-translate');
        $breadcrumb = new Loco_admin_Navigation;
        $breadcrumb->add( $title );
        
        return $this->view('admin/config/settings', compact('breadcrumb') ); 
    }
    
}
