<?php
/**
 * File delete function
 */
class Loco_admin_file_DeleteController extends Loco_admin_file_BaseController {
    

    /**
     * Expand single path to all files that will be deleted
     * @param Loco_fs_File primary file being deleted, probably the PO
     * @return array
     */
    private function expandFiles( Loco_fs_File $file ){
        try {
            $siblings = new Loco_fs_Siblings( $file );
        }
        catch( InvalidArgumentException $e ){
            $ext = $file->extension();
            throw new Loco_error_Exception( sprintf('Refusing to delete a %s file', strtoupper($ext) ) );
        }
        return $siblings->expand();
    }



    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $file = $this->get('file');
        
        // set up form for delete confirmation
        if( $file->exists() && ! $file->isDirectory() ){
            // nonce action will be specific to file for extra security
            // TODO could also add file MD5 to avoid deletion after changes made.
            $path = $file->getPath();
            $action = 'delete:'.$path;
            // set up view now in case of late failure
            $fields = new Loco_mvc_HiddenFields( array() );
            $fields->setNonce( $action );
            $this->set( 'hidden', $fields );
            // attempt delete if valid nonce posted back
            if( $this->checkNonce($action) ){
                $api = new Loco_api_WordPressFileSystem;
                // delete dependant files first, so master still exists if others fail
                $files = array_reverse( $this->expandFiles($file) );
                try {
                    /* @var $trash Loco_fs_File */
                    foreach( $files as $trash ){
                        $api->authorizeDelete($trash);
                        $trash->unlink();
                    }
                    // flash message for display after redirect
                    try {
                        $n = count( $files );
                        Loco_data_Session::get()->flash('success', sprintf( _n('File deleted','%u files deleted',$n,'loco-translate'),$n) );
                        Loco_data_Session::close();
                    }
                    catch( Exception $e ){
                        // tolerate session failure
                    }
                    // redirect to bundle overview
                    $href = Loco_mvc_AdminRouter::generate( $this->get('type').'-view', array( 'bundle' => $this->get('bundle') ) );
                    if( wp_redirect($href) ){
                        exit;
                    }
                }
                catch( Loco_error_Exception $e ){
                    Loco_error_AdminNotices::add( $e );
                }
            }
        }
        // set page title before render sets inline title
        $bundle = $this->getBundle();
        $this->set('title', sprintf( __('Delete %s','loco-translate'), $file->basename() ).' &lsaquo; '.$bundle->getName() );
    }



    /**
     * {@inheritdoc}
     */
    public function render(){
        
        $file = $this->get('file');
        if( $fail = $this->getFileError($file) ){
            return $fail;
        }
        
        $files = $this->expandFiles( $file );
        $info = Loco_mvc_FileParams::create($file);
        $this->set( 'info', $info );
        $this->set( 'title', sprintf( __('Delete %s','loco-translate'), $info->name ) );
        
        // warn about additional files that will be deleted along with this
        if( $deps = array_slice($files,1) ){
            $count = count($deps);
            $this->set('warn', sprintf( _n( 'One dependent file will also be deleted', '%u dependent files will also be deleted', $count, 'loco-translate' ), $count ) );
            $infos = array();
            foreach( $deps as $depfile ){
                $infos[] = Loco_mvc_FileParams::create( $depfile );
            }
            $this->set('deps', $infos );
        }
        
        $this->prepareFsConnect( 'delete', $this->get('path') );
        
        $this->enqueueScript('delete');
        return $this->view('admin/file/delete');
    }
    
}