<?php
/**
 * Exception that throws an exception when rendered
 * Don't use it, it's for testing failures during failures
 */
class Loco_test_RecursiveException extends Loco_error_Exception {
    
    
    /**
     * @override
     */
    public function getTemplate(){
        return LOCO_TEST_DATA_ROOT.'/views/fatal';
    }    

}