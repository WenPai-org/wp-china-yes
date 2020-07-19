<?php
/**
 * File revisions and rollback
 */
class Loco_admin_file_DiffController extends Loco_admin_file_BaseController {


    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $this->enqueueStyle('podiff');

        $pofile = $this->get('file');
        if( $pofile->exists() && ! $pofile->isDirectory() ){
            $path = $pofile->getPath();
            $action = 'restore:'.$path;
            // set up view now in case of late failure
            $fields = new Loco_mvc_HiddenFields( array() );
            $fields->setNonce( $action );
            $this->set( 'hidden', $fields );
            // attempt rollback if valid nonce posted back with backup path
            if( $this->checkNonce($action) ){
                try {
                    $post = Loco_mvc_PostParams::get();
                    $api = new Loco_api_WordPressFileSystem;
                    // Restore
                    if( $path = $post->backup ){
                        $target = new Loco_fs_File( $path );
                        $target->normalize( loco_constant('WP_CONTENT_DIR') );
                        // parse PO. we'll need it for MO compile anyway
                        $source = $target->getContents();
                        $data = Loco_gettext_Data::fromSource( $source );
                        // backup current master before restoring
                        $backups = new Loco_fs_Revisions($pofile);
                        if( $num_backups = Loco_data_Settings::get()->num_backups ){
                            $api->authorizeCopy($pofile);
                            $backups->create();
                        }
                        // authorize master for file modification
                        $api->authorizeUpdate($pofile);
                        // recompile binary if it exists
                        $mofile = $pofile->cloneExtension('mo');
                        if( $mofile->exists() ){
                            $mofile->putContents( $data->msgfmt() );
                        }
                        // replacing source file last in case of failures
                        $pofile->putContents( $source );
                        Loco_error_AdminNotices::success( __('File restored','loco-translate') );
                        // prune to configured level after success
                        $backups->prune( $num_backups );
                        $backups = null;
                    }
                    // Delete
                    else if( $path = $post->delete ){
                        $target = new Loco_fs_File( $path );
                        $target->normalize( loco_constant('WP_CONTENT_DIR') );
                        $api->authorizeDelete( $target );
                        $target->unlink();
                        Loco_error_AdminNotices::success( __('File deleted','loco-translate') );
                    }
                    else {
                        throw new Loco_error_Exception('Nothing selected');
                    }
                }
                catch( Loco_error_Exception $e ){
                    Loco_error_AdminNotices::add( $e );
                }
            }
        }
        
        $bundle = $this->getBundle();
        $this->set('title', sprintf( __('Restore %s','loco-translate'), $pofile->basename() ).' &lsaquo; '.$bundle->getName() );
    }



    /**
     * {@inheritdoc}
     */
    public function render(){
        
        $file = $this->get('file');
        if( $fail = $this->getFileError($file) ){
            return $fail;
        }
        
        $info = Loco_mvc_FileParams::create($file);
        $info['mtime'] = $file->modified();
        $this->set( 'master', $info );
        $this->set( 'title', sprintf( __('Restore %s','loco-translate'), $info->name ) );        
        
        $enabled = Loco_data_Settings::get()->num_backups;
        $this->set( 'enabled', $enabled );

        $files = array();
        $wp_content = loco_constant('WP_CONTENT_DIR');
        $paths = array( $file->getRelativePath($wp_content) );
        
        $podate = 'pot' === $file->extension() ? 'POT-Creation-Date' : 'PO-Revision-Date'; 
        $backups = new Loco_fs_Revisions($file);

        foreach( $backups->getPaths() as $path ){
            $tmp = new Loco_fs_File( $path );
            $info = Loco_mvc_FileParams::create($tmp);
            // time file was snapshotted is actually the time the next version was updated
            // $info['mtime'] = $backups->getTimestamp($path);
            // pull "real" update time, meaning when the revision was last updated as current version
            try {
                $head = Loco_gettext_Data::head($tmp)->getHeaders();
                if( $value = $head->trimmed($podate) ){
                    $info['potime'] = Loco_gettext_Data::parseDate($value);
                }
                else {
                    throw new Loco_error_Exception('Backup has no '.$podate.' field');
                }
            }
            catch( Exception $e ){
                Loco_error_AdminNotices::debug( $e->getMessage() );
                continue;
            }
            $paths[] = $tmp->getRelativePath($wp_content);
            $files[] = $info;
        }

        // no backups = no restore
        if( ! $files ){
            return $this->view('admin/errors/no-backups');
        }

        /*/ warn if current backup settings aren't enough to restore without losing older revisions
        $min = count($files) + 1;
        if( $enabled < $min ){
            $notice = Loco_error_AdminNotices::info('We recommend enabling more backups before restoring');
            $notice->addLink( apply_filters('loco_external','https://localise.biz/wordpress/plugin/manual/settings#po'), __('Documentation','loco-translate') )
                   ->addLink( Loco_mvc_AdminRouter::generate('config').'#loco--num-backups', __('Settings') );
        }*/

        // restore permissions required are create and delete on current location
        $this->prepareFsConnect( 'update', $this->get('path') );
        
        // prepare revision arguments for JavaScript
        $this->set( 'js', new Loco_mvc_ViewParams( array(
            'paths' => $paths,
            'nonces' => array (
                'diff' => wp_create_nonce('diff'),
            )
        ) ) );
       
        $this->enqueueScript('podiff');
        return $this->view('admin/file/diff', compact('files','backups') );
    }

            
}
