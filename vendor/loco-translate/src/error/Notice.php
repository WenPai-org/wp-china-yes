<?php
/**
 * Generic, non-critical informational notice
 * Not to be confused with an error notice. This is for onscreen messages, and won't be logged.
 */
class Loco_error_Notice extends Loco_error_Exception {
    
    /**
     * {@inheritdoc}
     */
    public function getType(){
        return 'info';
    }


    /**
     * {@inheritdoc}
     */
    public function getTitle(){
        return __('Notice','loco-translate');
    }


    /**
     * {@inheritdoc}
     */
    public function getLevel(){
        return Loco_error_Exception::LEVEL_NOLOG;
    }

}