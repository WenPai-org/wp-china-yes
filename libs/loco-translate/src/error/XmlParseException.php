<?php
/**
 * 
 */
class Loco_error_XmlParseException extends Loco_error_Exception {

    public function getTitle(){
        return __('XML parse error','loco-translate');
    }
    
}
