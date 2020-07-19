<?php
/**
 * Object represents a Text Domain within a bundle.
 * TODO implement a conflict watcher to warn when domains are shared by multiple bundles?
 */
class Loco_package_TextDomain extends ArrayIterator {
    
    /**
     * Actual Gettext-like name of Text Domain, e.g. "twentyfifteen"
     * @var string
     */
    private $name;
    
    /**
     * Whether this is the officially declared domain for a theme or plugin
     * @var bool
     */
    private $canonical = false;

    
    /**
     * Create new Text Domain from its name
     */
    public function __construct( $name ){
        $this->name = $name;
    }

    
    /**
     * @internal
     */
    public function __toString(){
        return (string) $this->name;
    }


    /**
     * Get name of Text Domain, e.g. "twentyfifteen"
     * @return string
     */
    public function getName(){
        return $this->name;
    }


    /**
     * Create a named project in a given bundle for this Text Domain
     * @return Loco_package_Project
     */
    public function createProject( Loco_package_Bundle $bundle, $name ){
        $proj = new Loco_package_Project( $bundle, $this, $name );
        $this[] = $proj;

        return $proj;
    }


    /**
     * @return Loco_package_TextDomain
     */
    public function setCanonical( $bool ){
        $this->canonical = (bool) $bool;
        return $this;
    }


    /**
     * @return bool
     */
    public function isCanonical(){
        return $this->canonical;
    }
      
}
