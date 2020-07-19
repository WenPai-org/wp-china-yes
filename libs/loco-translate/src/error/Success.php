<?php
/**
 * Success message. Not really an exception obviously, but compatible with Loco_error_AdminNotices
 */
class Loco_error_Success extends Loco_error_Exception {

    /**
     * {@inheritdoc}
     */
    public function getType(){
        return 'success';
    }


    /**
     * {@inheritdoc}
     */
    public function getTitle(){
        return __('OK','loco-translate');
    }


    /**
     * {@inheritdoc}
     */
    public function getLevel(){
        return Loco_error_Exception::LEVEL_NOLOG;
    }

}
