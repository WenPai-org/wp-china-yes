<?php
/**
 * List all bundles of type "plugin"
 * Route: loco-plugin
 */
class Loco_admin_list_PluginsController extends Loco_admin_list_BaseController {

    
    public function render(){

        $this->set( 'type', 'plugin' );
        $this->set( 'title', __( 'Translate plugins', 'loco-translate' ) );
        
        foreach( Loco_package_Plugin::get_plugins() as $handle => $data ){
            try {
                $bundle = Loco_package_Plugin::create( $handle );
                $this->addBundle($bundle);
            }
            // @codeCoverageIgnoreStart
            catch( Exception $e ){
                $bundle = new Loco_package_Plugin( $handle, $handle );
                $this->addBundle( $bundle );
            }
            // @codeCoverageIgnoreEnd
        }
        
        return parent::render();
    }

    
}