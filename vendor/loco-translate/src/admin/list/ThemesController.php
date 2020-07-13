<?php
/**
 * List all bundles of type "theme"
 * Route: loco-theme
 */
class Loco_admin_list_ThemesController extends Loco_admin_list_BaseController {

    


    public function render(){

        $this->set('type', 'theme' );
        $this->set('title', __( 'Translate themes', 'loco-translate' ) );
        
        /* @var $theme WP_Theme */
        foreach( wp_get_themes() as $theme ){
            $bundle = Loco_package_Theme::create( $theme->get_stylesheet() );
            $this->addBundle( $bundle );
        }

        return parent::render();
    }

    
}