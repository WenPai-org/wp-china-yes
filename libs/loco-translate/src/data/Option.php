<?php
/**
 * Data object persisted as a WordPress "option"
 */
abstract class Loco_data_Option extends Loco_data_Serializable {

    
    /**
     * Get short suffix for use as end of option_name field.
     * DB allows 191 characters including "loco_" prefix, leaving 185 bytes
     * @return string
     */
    abstract public function getKey();

    /**
     * Persist object in WordPress options database
     * @return bool
     */
    public function persist(){
        $key = 'loco_'.$this->getKey();
        return update_option( $key, $this->getSerializable(), false );
    }


    /**
     * Retrieve and unserialize this object from WordPress options table
     * @return bool whether object existed in cache
     */
    public function fetch(){
        $key = 'loco_'.$this->getKey();
        if( $data = get_option($key) ){
            try {
                $this->setUnserialized($data);
                return true;
            }
            catch( InvalidArgumentException $e ){
                // suppress validation error
                // @codeCoverageIgnore
            }
        }
        return false;
    }
    
    
    /**
     * Delete option from WordPress
     */
    public function remove(){
        $key = 'loco_'.$this->getKey();
        return delete_option( $key );
    }
    
}