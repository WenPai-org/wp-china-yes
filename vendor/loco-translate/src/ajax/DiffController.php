<?php
/**
 * Ajax "diff" route, for rendering PO/POT file diffs
 */
class Loco_ajax_DiffController extends Loco_mvc_AjaxController {


    /**
     * {@inheritdoc}
     */
    public function render(){
        $post = $this->validate();
        
        // require x2 valid files for diffing
        if( ! $post->lhs || ! $post->rhs ){
            throw new InvalidArgumentException('Path parameters required');
        }
        
        $dir = loco_constant('WP_CONTENT_DIR');
        $lhs = new Loco_fs_File( $post->lhs ); $lhs->normalize($dir);
        $rhs = new Loco_fs_File( $post->rhs ); $rhs->normalize($dir);
        
        // avoid diffing non Gettext source files
        $exts = array_flip( array( 'pot', 'pot~', 'po', 'po~' ) );

        /* @var $file Loco_fs_File */
        foreach( array($lhs,$rhs) as $file ){
            if( ! $file->exists() ){
                throw new InvalidArgumentException('File paths must exist');
            }
            if( ! $file->underContentDirectory() ){
                throw new InvalidArgumentException('Files must be under '.basename($dir) );
            }
            $ext = $file->extension();
            if( ! isset($exts[$ext]) ){
                throw new InvalidArgumentException('Disallowed file extension');
            }
        }
        
        // OK to diff files as HTML table
        $renderer = new Loco_output_DiffRenderer;
        $emptysrc = $renderer->_startDiff().$renderer->_endDiff();
        $tablesrc = $renderer->renderFiles( $rhs, $lhs );

        if( $tablesrc === $emptysrc ){
            // translators: Where %s is a file name
            $message = __('Revisions are identical, you can delete %s','loco-translate');
            $this->set( 'error', sprintf( $message, $rhs->basename() ) );
        }
        else {
            $this->set( 'html', $tablesrc );
        }
        
        return parent::render();
    }


}
