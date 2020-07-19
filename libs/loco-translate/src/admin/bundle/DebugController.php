<?php
/**
 * Bundle debugger.
 * Shows bundle diagnostics and highlights problems
 */
class Loco_admin_bundle_DebugController extends Loco_admin_bundle_BaseController {
    

    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $bundle = $this->getBundle();
        $this->set('title', 'Debug: '.$bundle );
    }

    
    /**
     * {@inheritdoc}
     */
    public function render(){

        $this->prepareNavigation()->add( __('Bundle diagnostics','loco-translate') );

        $bundle = $this->getBundle();
        $debugger = new Loco_package_Debugger($bundle);
        $this->set('notices', $notices = new Loco_mvc_ViewParams );
        /* @var $notice Loco_error_Exception */
        foreach( $debugger as $notice ){
            $notices[] = new Loco_mvc_ViewParams( array(
                'style' => 'notice inline notice-'.$notice->getType(),
                'title' => $notice->getTitle(),
                'body' => $notice->getMessage(),
            ) );
        }
        
        $meta = $bundle->getHeaderInfo();
        $this->set('meta', new Loco_mvc_ViewParams( array(
            'vendor' => $meta->getVendorHost(),
            'author' => $meta->getAuthorCredit(),
        ) ) );
        
        if( count($bundle) ){
            $writer = new Loco_config_BundleWriter( $bundle );
            $this->set( 'xml', $writer->toXml() );
        }

        return $this->view('admin/bundle/debug');
    }
    
}