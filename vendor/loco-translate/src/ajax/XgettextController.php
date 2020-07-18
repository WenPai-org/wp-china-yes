<?php
/**
 * Ajax "xgettext" route, for initializing new template file from source code
 */
class Loco_ajax_XgettextController extends Loco_ajax_common_BundleController {


    /**
     * {@inheritdoc}
     */
    public function render(){

        $this->validate();
        $bundle = $this->getBundle();
        $project = $this->getProject( $bundle );
        
        // target location may not be next to POT file at all
        $base = loco_constant('WP_CONTENT_DIR');
        $target = new Loco_fs_Directory( $this->get('path') );
        $target->normalize( $base );
        if( $target->exists() && ! $target->isDirectory() ){
            throw new Loco_error_Exception('Target is not a directory');
        }
        
        // basename should be posted from front end
        $name = $this->get('name');
        if( ! $name ){
            throw new Loco_error_Exception('Front end did not post $name');
        }

        // POT file shouldn't exist currently
        $potfile = new Loco_fs_File( $target.'/'.$name );
        $api = new Loco_api_WordPressFileSystem;
        $api->authorizeCreate($potfile);
        // Do extraction and grab only given domain's strings
        $ext = new Loco_gettext_Extraction( $bundle );
        $domain = $project->getDomain()->getName();
        $data = $ext->addProject($project)->includeMeta()->getTemplate( $domain );
        
        // additional headers to set in new POT file
        $head = $data->getHeaders();
        $head['Project-Id-Version'] = $project->getName();
        
        // write POT file to disk returning byte length
        $potsize = $potfile->putContents( $data->msgcat(true) );
        
        // set response data for debugging
        if( loco_debugging() ){
            $this->set( 'debug', array (
                'potname' => $potfile->basename(),
                'potsize' => $potsize,
                'total' => $ext->getTotal(),
            ) );
        }

        // push recent items on file creation
        // TODO push project and locale file
        Loco_data_RecentItems::get()->pushBundle( $bundle )->persist();
        
        // put flash message into session to be displayed on redirected page
        try {
            Loco_data_Session::get()->flash('success', __('Template file created','loco-translate') );
            Loco_data_Session::close();
        }
        catch( Exception $e ){
            Loco_error_AdminNotices::debug( $e->getMessage() );
        }
        
        // redirect front end to bundle view. Discourages manual editing of template
        $type = strtolower( $bundle->getType() );   
        $href = Loco_mvc_AdminRouter::generate( sprintf('%s-view',$type), array(
            'bundle' => $bundle->getHandle(),
        ) );
        $hash = '#loco-'.$project->getId();
        $this->set( 'redirect', $href.$hash );
        
        return parent::render();
    }
    
    
}