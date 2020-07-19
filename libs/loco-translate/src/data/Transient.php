<?php
/**
 * 
 */
abstract class Loco_data_Transient extends Loco_data_Serializable {

    /**
     * Lifespan to persist object in transient cache
     * @var int seconds
     */
    private $ttl = 0;
    
    /**
     * Get short suffix for use as end of cache key.
     * DB allows 191 characters including "_transient_timeout_loco_" prefix, leaving 167 bytes
     * @return string
     */
    abstract public function getKey();

    /**
     * Persist object in WordPress cache
     * @param int
     * @param bool
     * @return Loco_data_Transient
     */
    public function persist(){
        $key = 'loco_'.$this->getKey();
        set_transient( $key, $this->getSerializable(), $this->ttl );
        $this->clean();
        return $this;
    }


    /**
     * Retrieve and unserialize this object from WordPress transient cache
     * @return bool whether object existed in cache
     */
    public function fetch(){
        $key = 'loco_'.$this->getKey();
        $data = get_transient( $key );
        try {
            $this->setUnserialized($data);
            return true;
        }
        catch( InvalidArgumentException $e ){
            return false;
        }
    }
    
    
    /**
     * @param int
     * @return self
     */
    public function setLifespan( $ttl ){
        $this->ttl = (int) $ttl;
        return $this;
    }
    
    
    /**
     * Set keep-alive interval
     * @param int
     * @return self
     */
    public function keepAlive( $timeout ){
        $time = $this->getTimestamp();
        // legacy objects (with ttl=0) had no timestamp, so will always be touched.
        // make dirty if this number of seconds has elapsed since last persisted.
        if( time() > ( $time + $timeout ) ){
            $this->touch()->persistLazily();
        }
        return $this;
    }
    

}