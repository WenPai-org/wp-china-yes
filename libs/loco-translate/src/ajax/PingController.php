<?php
/**
 * Ajax "ping" route, for testing Ajax responses are working.
 */
class Loco_ajax_PingController extends Loco_mvc_AjaxController {
    
    
    /**
     * {@inheritdoc}
     */
    public function render(){
        $post = $this->validate();
        // echo back bytes posted
        if( $post->has('echo') ){
            $this->set( 'ping', $post['echo'] );
        }
        // else just send pong
        else {
            $this->set( 'ping', 'pong' );
        }
        // always send tick symbol to check json serializing of unicode
        $this->set( 'utf8', "\xE2\x9C\x93" );

        return parent::render();
    }
    
    
}