<?php
/**
 * 
 */
class Loco_error_ParseException extends Loco_error_Exception {

    /**
     * @var string
     */
    private $context;

    /**
     * @param int line number
     * @param int column number
     * @param string source in which to identify line and column
     * @return self
     */
    public function setContext( $line, $column, $source ){
        $this->context = null;
        // If line given as 0 then treat column as offset in an unknown number of lines
        if( 0 === $line ){
            $lines = preg_split( '/\\r?\\n/', substr($source,0,$column));
            $line = count($lines);
            $column = strlen( end($lines) );
        }
        // get line of source code where error is and construct a ____^ thingy to show error on next line
        // this requires that full source is passed in, so line number must be real
        if( loco_debugging() ){
            $lines = preg_split( '/\\r?\\n/', $source, $line+1 );
            $offset = $line - 1;
            if( isset($lines[$offset]) ){
                $this->context = $lines[$offset] ."\n". str_repeat(' ', max(0,$column) ).'^';
            }
        }
        // wrap initial message with context data
        $this->message = sprintf("Error at line %u, column %u: %s", $line, $column, $this->message );
        return $this;
    }

    /**
     * @return string
     */
    public function getContext(){
        return $this->context;
    }

}
