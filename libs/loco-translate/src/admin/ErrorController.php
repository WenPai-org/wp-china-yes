<?php
/**
 * 
 */
class Loco_admin_ErrorController extends Loco_mvc_AdminController {
    
    
    public function renderError( Exception $e ){
        $this->set('error', Loco_error_Exception::convert($e) );
        return $this->render();
    }


    public function render(){
        $e = $this->get('error');
        if( $e ){
            /* @var Loco_error_Exception $e */
            $file = Loco_mvc_FileParams::create( new Loco_fs_File( $e->getRealFile() ) ); 
            $file['line'] = $e->getRealLine();
            $this->set('file', $file );
            if( loco_debugging() ){
                $trace = array();
                foreach( $e->getRealTrace() as $raw ) {
                    $frame  = new Loco_mvc_ViewParams($raw);
                    if( $frame->has('file') ){
                        $frame['file'] = Loco_mvc_FileParams::create( new Loco_fs_File($frame['file']) )->relpath;
                    }
                    $trace[] = $frame;
                }
                $this->set('trace',$trace);
            }
        }
        else {
            $e = new Loco_error_Exception('Unknown error');
            $this->set('error', $e );
        }
        return $this->view( $e->getTemplate() );
    }
    
}


