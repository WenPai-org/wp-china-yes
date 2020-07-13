<?php
/**
 * File system write error.
 * Generally thrown from Loco_fs_FileWriter
 */
class Loco_error_WriteException extends Loco_error_Exception {


    /**
     * {@inheritdoc}
     */
    public function getTitle(){
        return __('Permission denied','loco-translate');
    }

}
