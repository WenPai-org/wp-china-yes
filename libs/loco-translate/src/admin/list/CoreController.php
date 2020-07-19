<?php
/**
 * Dummy controller skips "core" list view, rendering the core projects directly as a single bundle.
 * Route: loco-core -> loco-core-view
 */
class Loco_admin_list_CoreController extends Loco_admin_RedirectController {


    /**
     * {@inheritdoc}
     */
    public function getLocation(){
        return Loco_mvc_AdminRouter::generate('core-view');
    }    
    
}