<?php
/**
 * Ajax "download" route, for outputting raw gettext file contents.
 */
class Loco_ajax_DownloadController extends Loco_mvc_AjaxController {

    /**
     * {@inheritdoc}
     */
    public function render(){

        $post = $this->validate();

        // we need a path, but it may not need to exist
        $file = new Loco_fs_File( $this->get('path') );
        $file->normalize( loco_constant( 'WP_CONTENT_DIR') );
        $is_binary = 'mo' === strtolower( $file->extension() );

        // posted source must be clean and must parse as whatever the file extension claims to be
        if( $raw = $post->source ){
            // compile source if target is MO
            if( $is_binary ) {
                $raw = Loco_gettext_Data::fromSource($raw)->msgfmt();
            }
        }
        // else file can be output directly if it exists.
        // note that files on disk will not be parsed or manipulated. they will download strictly as-is
        else if( $file->exists() ){
            $raw = $file->getContents();
        }
        /*/ else if PO exists but MO doesn't, we can compile it on the fly
        else if( ! $is_binary ){

        }*/
        else {
            throw new Loco_error_Exception('File not found and no source posted');
        }

        // Observe UTF-8 BOM setting
        if( ! $is_binary ){
            $has_bom = "\xEF\xBB\xBF" === substr($raw,0,3);
            $use_bom = (bool) Loco_data_Settings::get()->po_utf8_bom;
            // only alter file if valid UTF-8. Deferring detection overhead until required 
            if( $has_bom !== $use_bom && 'UTF-8' === mb_detect_encoding( $raw, array('UTF-8','ISO-8859-1'), true ) ){
                if( $use_bom ){
                    $raw = "\xEF\xBB\xBF".$raw; // prepend
                }
                else {
                    $raw = substr($raw,3); // strip bom
                }
            }
        }


        return $raw;
    }
    
    
}