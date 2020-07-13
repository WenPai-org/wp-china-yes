<?php
/**
 * For buffering accidental output caused by themes and other plugins.
 * Also used in template rendering.
 */
class Loco_output_Buffer {
    
    /**
     * The output buffering level opened by this instance
     * @var int usually 1 unless another buffer was opened before this one.
     */    
    private $ob_level;

    /**
     * Content buffered while our buffer was buffering
     * @var string
     */    
    private $output = '';

    /**
     * @return string
     */    
    public function __toString(){
         return $this->output;
    }


    /**
     * @return Loco_output_Buffer
     */
    public static function start(){
        $buffer = new Loco_output_Buffer;
        return $buffer->open();
    }


    /**
     * @internal
     * Ensure buffers closed if something terminates before we close gracefully
     */
    public function __destruct(){
        $this->close();
    }


    /**
     * @return Loco_output_Buffer
     */
    public function open(){
        self::check();
        if( ! ob_start() ){
            throw new Loco_error_Exception('Failed to start output buffering');
        }
        $this->ob_level = ob_get_level();
        return $this;
    }


    /**
     * @return Loco_output_Buffer
     */
    public function close(){
        if( is_int($this->ob_level) ){
            // collect output from our nested buffers
            $this->output = self::collect( $this->ob_level );
            $this->ob_level = null;
        }
        return $this;
    }


	/**
	 * Trash all open buffers, logging any junk output collected
	 * @return void
	 */
    public function discard(){
    	$this->close();
	    if( '' !== $this->output ){
		    self::log_junk( $this->output );
		    $this->output = '';
	    }
    }


    /**
     * Collect output buffered to a given level
     * @param int highest buffer to flush, 0 being the root
     * @return string
     */
    public static function collect( $min ){
        $last = 0;
        $output = '';
        while( $level = ob_get_level() ){
            // @codeCoverageIgnoreStart
            if( $level === $last ){
                throw new Loco_error_Exception('Failed to close output buffer');
            }
            // @codeCoverageIgnoreEnd
            if( $level < $min ){
                break;
            }
            // output is appended inside out:
            $output = ob_get_clean().$output;
            $last = $level;
        }
        return $output;
    }


    /**
     * Forcefully destroy all open buffers and log any bytes already buffered.
     * @return void
     */
    public static function clear(){
        $junk = self::collect(0);
	    if( '' !== $junk ){
		    self::log_junk($junk);
	    }
    }


    /**
     * Check output has not already been flushed.
     * @throws Loco_error_Exception
     */
    public static function check(){
	    if( headers_sent($file,$line) && 'cli' !== PHP_SAPI ){
		    $file = str_replace( trailingslashit( loco_constant('ABSPATH') ), '', $file );
		    throw new Loco_error_Exception( sprintf( __('Loco interrupted by output from %s:%u','loco-translate'), $file, $line ) );
	    }
    }


	/**
	 * Debug collection of junk output
	 * @param string
	 */
    private static function log_junk( $junk ){
    	$bytes = strlen($junk);
		$message = sprintf("Cleared %s of buffered output", Loco_mvc_FileParams::renderBytes($bytes) );
		Loco_error_AdminNotices::debug( $message );
		do_action( 'loco_buffer_cleared', $junk );
	}

}
