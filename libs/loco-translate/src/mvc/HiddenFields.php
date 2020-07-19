<?php
/**
 * 
 */
class Loco_mvc_HiddenFields extends Loco_mvc_ViewParams {
    
    
    
    /**
     * @internal
     * Echo all hidden fields to output buffer
     */
    public function _e(){
        foreach( $this as $name => $value ){
            echo '<input type="hidden" name="',$this->escape($name),'" value="',$this->escape($value),'" />';
        }
    }


    /**
     * Add a nonce field 
     * @param string action passed to wp_create_nonce
     * @return Loco_mvc_HiddenFields
     */
    public function setNonce( $action ){
        $this['loco-nonce'] = wp_create_nonce( $action );
        return $this;
    }


    /**
     * Load postdata fields
     * @param Loco_mvc_PostParams post data
     * @return Loco_mvc_HiddenFields
     */
    public function addPost( Loco_mvc_PostParams $post ){
        foreach( $post->getSerial() as $pair ){
            $this[ $pair[0] ] = isset($pair[1]) ? $pair[1] : '';
        }
        return $this;
    }
    
} 
