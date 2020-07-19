<?php
/**
 * Dummy filter methods for testing Hookable base class
 */
class Loco_test_TestFilters extends Loco_hooks_Hookable {
    
    
    /**
     * Test filter returns arguments exactly as passed
     * @return array
     */    
    public function filter_loco_test_passthru_arguments( $foo ){
        return func_get_args();
    }
 
 
    /**
     * Test filter increments passed number by +1
     * @return int
     */
    public function filter_loco_test_increment_by_one( $n ){
        return ++$n;
    }
    
    
}