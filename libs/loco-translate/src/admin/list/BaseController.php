<?php
/**
 * Common controller for listing of all bundle types
 */
abstract class Loco_admin_list_BaseController extends Loco_mvc_AdminController {
    
    private $bundles = array();


    /**
     * build renderable bundle variables
     * @return Loco_mvc_ViewParams
     */
    protected function bundleParam( Loco_package_Bundle $bundle ){
        $handle = $bundle->getHandle();
        // compatibility will be 'ok', 'warn' or 'error' depending on severity
        if( $default = $bundle->getDefaultProject() ){
            $compat = $default->getPot() instanceof Loco_fs_File;
        }
        else {
            $compat = false;
        }
        //$info = $bundle->getHeaderInfo();
        return new Loco_mvc_ViewParams( array (
            'id'   => $bundle->getId(),
            'name' => $bundle->getName(),
            'dflt' => $default ? $default->getDomain() : '--',
            'size' => count( $bundle ),
            'save' => $bundle->isConfigured(),
            'type' => $type = strtolower( $bundle->getType() ),
            'view' => Loco_mvc_AdminRouter::generate( $type.'-view', array( 'bundle' => $handle ) ),
            'time' => $bundle->getLastUpdated(),
        ) );
    }
    

    /**
     * Add bundle to enabled or disabled list, depending on whether it is configured
     */
    protected function addBundle( Loco_package_Bundle $bundle ){
        $this->bundles[] = $this->bundleParam($bundle);
    }
    

    /**
     * {@inheritdoc}
     */
    public function getHelpTabs(){
        return array (
            __('Overview','default') => $this->viewSnippet('tab-list-bundles'),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function render(){

        // breadcrumb is just the root
        $here = new Loco_admin_Navigation( array (
            new Loco_mvc_ViewParams( array( 'name' => $this->get('title') ) ),
        ) );
        
        /*/ tab between the types of bundles
        $types = array (
            '' => __('Home','loco-translate'),
            'theme'  => __('Themes','loco-translate'),
            'plugin' => __('Plugins','loco-translate'),
        );
        $current = $this->get('_route');
        $tabs = new Loco_admin_Navigation;
        foreach( $types as $type => $name ){
            $href = Loco_mvc_AdminRouter::generate($type);
            $tabs->add( $name, $href, $type === $current );
        }
        */
        
        return $this->view( 'admin/list/bundles', array (
            'bundles' => $this->bundles,
            'breadcrumb' => $here,
        ) );
    }

    
}