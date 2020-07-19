<?php
/**
 * Test case that doesn't need any WordPress bootstrapping
 */
abstract class Loco_test_TestCase extends PHPUnit_Framework_TestCase {
    
    
    public function tearDown(){
        Loco_error_AdminNotices::destroy();
    }
    
    
    protected function normalizeHtml( $src ){
            
        $dom = new DOMDocument('1.0','UTF-8');
        $dom->preserveWhitespace = false;
        $dom->formatOutput = false;
        $dom->loadXML( '<?xml version="1.0" encoding="utf-8"?><root>'.$src.'</root>' );
        $dom->normalizeDocument();
        $src = $dom->saveXML();
        
        return trim( preg_replace( '/>\s+</', '><', $src ) );
    }

    
    public function assertSameHtml( $expect, $actual, $message = null ){
        return $this->assertSame( $this->normalizeHtml($expect), $this->normalizeHtml($actual), $message );
    }
    
    
}