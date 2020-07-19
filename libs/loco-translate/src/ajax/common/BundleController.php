<?php
/**
 * Common functions for all Ajax actions that operate on a bundle 
 */
abstract class Loco_ajax_common_BundleController extends Loco_mvc_AjaxController {
    

    /**
     * @return Loco_package_Bundle
     */
    protected function getBundle(){
        if( $id = $this->get('bundle') ){
            // type may be passed as separate argument    
            if( $type = $this->get('type') ){
                return Loco_package_Bundle::createType( $type, $id );
            }
            // else embedded in standalone bundle identifier
            // TODO standardize this across all Ajax end points 
            return Loco_package_Bundle::fromId($id);
        }
        // else may have type embedded in bundle
        throw new Loco_error_Exception('No bundle identifier posted');
    }



    /**
     * @param Loco_package_Bundle
     * @return Loco_package_Project
     */
    protected function getProject( Loco_package_Bundle $bundle ){
        $project = $bundle->getProjectById( $this->get('domain') );
        if( ! $project ){
            throw new Loco_error_Exception('Failed to find translation project');
        }
        return $project;
    }
    
}