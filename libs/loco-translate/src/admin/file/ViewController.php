<?php
/**
 * File view / source formatted view.
 */
class Loco_admin_file_ViewController extends Loco_admin_file_BaseController {


    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $this->enqueueStyle('poview');
        //
        $file = $this->get('file');
        $bundle = $this->getBundle();
        $this->set( 'title', 'Source of '.$file->basename().' &lsaquo; '.$bundle->getName() );
    }


    /**
     * {@inheritdoc}
     */
    public function getHelpTabs(){
        return array (
            __('Overview','default') => $this->viewSnippet('tab-file-view'),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function render(){
        
        // file must exist for editing
        /* @var Loco_fs_File $file */
        $file = $this->get('file');
        $name = $file->basename();
        $type = strtolower( $file->extension() );
        $this->set('title', $name );

        if( $fail = $this->getFileError($file) ){
            return $fail; 
        }

        // Establish if file belongs to a configured project
        try {
            $bundle = $this->getBundle();
            $project = $this->getProject();
        }
        catch( Exception $e ){
            $project = null;
        }    
            
        // Parse data before rendering, so we know it's a valid Gettext format
        try {
            $this->set('modified', $file->modified() );
            $data = Loco_gettext_Data::load( $file );
        }
        catch( Loco_error_ParseException $e ){
            Loco_error_AdminNotices::add( Loco_error_Exception::convert($e) );
            $data = Loco_gettext_Data::dummy();
        }

        $this->set( 'meta', Loco_gettext_Metadata::create($file, $data) );

        // binary MO will be hex-formatted in template
        if( 'mo' === $type ){
            $this->set('bin', $file->getContents() );
            return $this->view('admin/file/view-mo' );
        }
        
        // else is a PO or POT file 
        $this->enqueueScript('poview');//->enqueueScript('min/highlight');
        $lines = preg_split('/(?:\\n|\\r\\n?)/', Loco_gettext_Data::ensureUtf8( $file->getContents() ) );
        $this->set( 'lines', $lines );
        
        // ajax parameters required for pulling reference sources
        $this->set('js', new Loco_mvc_ViewParams( array (
            'popath' => $this->get('path'),
            'nonces' => array(
                'fsReference' => wp_create_nonce('fsReference'),
            ),
            'project' => $bundle ? array (
                'bundle' => $bundle->getId(),
                'domain' => $project ? $project->getId() : '',
            ) : null,
        ) ) ); 

        
        // treat as PO if file name has locale
        if( $this->getLocale() ){
            return $this->view('admin/file/view-po' );
        }

        // else view as POT
        return $this->view('admin/file/view-pot' );
    }

}