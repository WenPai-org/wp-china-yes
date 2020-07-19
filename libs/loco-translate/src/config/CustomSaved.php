<?php
/**
 * Bundle configuration saved as a WordPress site option.
 */
class Loco_config_CustomSaved extends Loco_data_Option {
    
    /**
     * @var Loco_package_Bundle
     */
    private $bundle;
    
    
    /**
     * {@inheritdoc}
     */
    public function getKey(){
        return strtolower( $this->bundle->getType() ).'_config__'.$this->bundle->getHandle();
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function persist(){
        $writer = new Loco_config_BundleWriter( $this->bundle );
        $this->exchangeArray( $writer->toArray() );
        return parent::persist(); 
    }


    /**
     * @return Loco_config_CustomSaved
     */
    public function setBundle( Loco_package_Bundle $bundle ){
        $this->bundle = $bundle;
        return $this;
    }


    /**
     * Modify currently set bundle according to saved config data
     * @return Loco_package_Bundle 
     */
    public function configure(){
        $this->bundle->clear();
        $reader = new Loco_config_BundleReader( $this->bundle );
        $reader->loadArray( $this->getArrayCopy() );
        return $this->bundle;
    }

}