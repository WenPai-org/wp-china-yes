<?php
/**
 * Developer notice
 */
class Loco_error_Debug extends Loco_error_Exception {
    
    /**
     * {@inheritdoc}
     */
    public function getType(){
        return 'debug';
    }


    /**
     * {@inheritdoc}
     */
    public function getTitle(){
        return __('Debug','loco-translate');
    }


    /**
     * {@inheritdoc}
     */
    public function getLevel(){
        return Loco_error_Exception::LEVEL_DEBUG;
    }
    
    
    /**
     * Log debugging message to file without raising admin notice
     * @param string
     * @codeCoverageIgnore
     */
    public static function trace( $message ){
        if( 1 < func_get_args() ){
            $message = call_user_func_array('sprintf',func_get_args());
        }
        $debug = new Loco_error_Debug($message);
        $debug->setCallee(1);
        $debug->log();
    }

}