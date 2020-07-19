<?php
/**
 * Ajax "sync" route.
 * Extracts strings from source (POT or code) and returns to the browser for in-editor merge.
 */
class Loco_ajax_SyncController extends Loco_mvc_AjaxController {

    /**
     * {@inheritdoc}
     */
    public function render(){
        
        $post = $this->validate();
        
        $bundle = Loco_package_Bundle::fromId( $post->bundle );
        $project = $bundle->getProjectById( $post->domain );
        if( ! $project instanceof Loco_package_Project ){
            throw new Loco_error_Exception('No such project '.$post->domain);
        }
        
        $file = new Loco_fs_File( $post->path );
        $base = loco_constant('WP_CONTENT_DIR');
        $file->normalize( $base );        
        
        // POT file always synced with source code (even if a PO being used as POT)
        if( 'pot' === $post->type ){
            $potfile = null;
        }
        // allow post data to force a template file path
        else if( $path = $post->sync ){
            $potfile = new Loco_fs_File($path);
            $potfile->normalize( $base );
        }
        // else use project-configured template if one is defined
        else {
            $potfile = $project->getPot();
        } 
        
        // sync with POT if it exists
        if( $potfile && $potfile->exists() ){
            $this->set('pot', $potfile->basename() );
            try {
                $data = Loco_gettext_Data::load($potfile);
            }
            catch( Exception $e ){
                // translators: Where %s is the name of the invalid POT file
                throw new Loco_error_ParseException( sprintf( __('Translation template is invalid (%s)','loco-translate'), $potfile->basename() ) );
            }
        }
        // else sync with source code
        else {
            $this->set('pot', '' );
            $domain = (string) $project->getDomain();
            $extr = new Loco_gettext_Extraction($bundle);
            $extr->addProject($project);
            // bail if any files were skipped
            if( $list = $extr->getSkipped() ){
                $n = count($list);
                $maximum = Loco_mvc_FileParams::renderBytes( wp_convert_hr_to_bytes( Loco_data_Settings::get()->max_php_size ) );
                $largest = Loco_mvc_FileParams::renderBytes( $extr->getMaxPhpSize() );
                // Translators: Where %2$s is the maximum size of a file that will be included and %3$s is the largest encountered
                $text = _n('One file has been skipped because it\'s %3$s. (Max is %2$s). Check all strings are present before saving.','%s files over %2$s have been skipped. (Largest is %3$s). Check all strings are present before saving.',$n,'loco-translate');
                $text = sprintf( $text, number_format($n), $maximum, $largest );
                // not failing, just warning. Nothing will be saved until user saves editor state
                Loco_error_AdminNotices::warn( $text );
            }
            // OK to return available strings
            $data = $extr->includeMeta()->getTemplate($domain);
        }

        $this->set( 'po', $data->jsonSerialize() );
        
        return parent::render();
    }
    
    
}