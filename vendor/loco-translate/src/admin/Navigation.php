<?php
/**
 * Generic navigation helper.
 */
class Loco_admin_Navigation extends ArrayIterator {

    
    /**
     * @return Loco_admin_Navigation
     */
    public function add( $name, $href = null, $active = false ){
        $this[] = new Loco_mvc_ViewParams( compact('name','href','active') );
        return $this;
    }


    /* not currently used
     * @return Loco_admin_Navigation
     *
    public function addRoute( $name, $action ){
        $href = Loco_mvc_AdminRouter::generate( $action );
        return $this->add( $name, $href );
    }*/
    
    

    /**
     * Create a breadcrumb trail for a given view below a bundle
     * @return Loco_admin_Navigation
     */
    public static function createBreadcrumb( Loco_package_Bundle $bundle ){
        $nav = new Loco_admin_Navigation;

        // root link depends on bundle type
        $type = strtolower( $bundle->getType() );
        if( 'core' !== $type ){
            $link = new Loco_mvc_ViewParams( array(
                'href' => Loco_mvc_AdminRouter::generate($type),
            ) );
            if( 'theme' === $type ){
                $link['name'] = __('Themes','loco-translate');
            }
            else {
                $link['name'] = __('Plugins','loco-translate');
            }
            $nav[] = $link;
        }
        
        // Add actual bundle page, href may be unset to show as current page if needed
        $nav->add (
            $bundle->getName(),
            Loco_mvc_AdminRouter::generate( $type.'-view', array( 'bundle' => $bundle->getHandle() ) )
        );
        
        // client code will add current page
        return $nav;
    }



    /**
     * @return Loco_mvc_ViewParams
     *
    public function getSecondLast(){
        $i = count($this);
        if( $i > 1 ){
            return $this[ $i-2 ];
        }
    }*/


}