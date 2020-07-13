<?php
/**
 * Translation set relocation tool.
 * Moves PO/MO pair and all related files to a new location
 */
class Loco_admin_file_MoveController extends Loco_admin_file_BaseController {

    
    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $file = $this->get('file');
        /* @var Loco_fs_File $file */
        if( $file->exists() && ! $file->isDirectory() ){
            $files = new Loco_fs_Siblings($file);
            // nonce action will be specific to file for extra security
            $path = $file->getPath();
            $action = 'move:'.$path;
            // set up view now in case of late failure
            $fields = new Loco_mvc_HiddenFields( array() );
            $fields->setNonce( $action );
            $fields['auth'] = 'move';
            $fields['path'] = $this->get('path');
            $this->set('hidden',$fields );
            // attempt move if valid nonce posted back
            while( $this->checkNonce($action) ){
                // Chosen location should be valid as a posted "dest" parameter
                if( ! Loco_mvc_PostParams::get()->has('dest') ){
                    Loco_error_AdminNotices::err('No destination posted');
                    break;
                }
                $target = new Loco_fs_LocaleFile( Loco_mvc_PostParams::get()->dest );
                $ext = $target->extension();
                // primary file extension should only be permitted to change between po and pot
                if( $ext !== $file->extension() && 'po' !== $ext && 'pot' !== $ext ){
                    Loco_error_AdminNotices::err('Invalid file extension, *.po or *.pot only');
                    break;
                }
                $target->normalize( loco_constant('WP_CONTENT_DIR') );
                $target_dir = $target->getParent()->getPath();
                // Primary file gives template remapping, so all files are renamed with same stub.
                // this can only be one of three things: (en -> en) or (foo-en -> en) or (en -> foo-en)
                // suffix will then consist of file extension, plus any other stuff like backup file date.
                $target_base = $target->filename();
                $source_snip = strlen( $file->filename() );
                // buffer all files to move to preempt write failures
                $movable = array();
                $api = new Loco_api_WordPressFileSystem;
                foreach( $files->expand() as $source ){
                    $suffix = substr( $source->basename(), $source_snip ); // <- e.g. "-backup.po~"
                    $target = new Loco_fs_File( $target_dir.'/'.$target_base.$suffix );
                    // permit valid change of file extension on primary source file (po/pot)
                    if( $source === $files->getSource() && $target->extension() !== $ext ){
                        $target = $target->cloneExtension($ext);
                    }
                    if( ! $api->authorizeMove($source,$target) ) {
                        Loco_error_AdminNotices::err('Failed to authorize relocation of '.$source->basename() );
                        break 2;
                    }
                    $movable[] = array($source,$target);
                }
                // commit moves. If any fail we'll have separated the files, which is bad
                $count = 0;
                $total = count($movable);
                foreach( $movable as $pair ){
                    try {
                        $pair[0]->move( $pair[1] );
                        $count++;
                    }
                    catch( Loco_error_Exception $e ){
                        Loco_error_AdminNotices::add($e);
                    }
                }
                // flash messages for display after redirect
                try {
                    if( $count ) {
                        Loco_data_Session::get()->flash( 'success', sprintf( _n( 'File moved', '%u files moved', $total, 'loco-translate' ), $total ) );
                    }
                    if( $total > $count ){
                        $diff = $total - $count;
                        Loco_data_Session::get()->flash( 'error', sprintf( _n( 'One file could not be moved', '%u files could not be moved', $diff, 'loco-translate' ), $diff ) );
                    }
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
                break;
            }
        }
        // set page title before render sets inline title
        $bundle = $this->getBundle();
        $this->set('title', sprintf( __('Move %s','loco-translate'), $file->basename() ).' &lsaquo; '.$bundle->getName() );
    }


    /**
     * {@inheritdoc}
     */
    public function render(){
        $file = $this->get('file');
        if( $fail = $this->getFileError($file) ){
            return $fail;
        }
        // relocation requires knowing text domain and locale
        try {
            $project = $this->getProject();
        }
        catch( Loco_error_Exception $e ){
            Loco_error_AdminNotices::warn($e->getMessage());
            $project = null;
        }
        $files = new Loco_fs_Siblings($file);
        $file = new Loco_fs_LocaleFile( $files->getSource() );
        $locale = $file->getLocale();
        // switch between canonical move and custom file path mode
        $custom = is_null($project) || $this->get('custom') || 'po' !== $file->extension() || ! $locale->isValid();
        // common page elements:
        $this->set('files',$files->expand() );
        $this->set('title', sprintf( __('Move %s','loco-translate'), $file->filename() ) );
        $this->enqueueScript('move');
        // set info for existing file location
        $content_dir = loco_constant('WP_CONTENT_DIR');
        $current = $file->getRelativePath($content_dir);
        $parent = new Loco_fs_LocaleDirectory( $file->dirname() );
        $typeId = $parent->getTypeId();
        $this->set('current', new Loco_mvc_ViewParams(array(
            'path' => $parent->getRelativePath($content_dir),
            'type' => $parent->getTypeLabel($typeId),
        )) );
        // moving files will require deletion permission on current file location
        // plus write permission on target location, but we don't know what that is yet.
        $fields = $this->prepareFsConnect('move',$current);
        $fields['path'] = '';
        $fields['dest'] = '';
        // custom file move template (POT mode)
        if( $custom ){
            $this->get('hidden')->offsetSet('custom','1');
            $this->set('file', Loco_mvc_FileParams::create($file) );
            return $this->view('admin/file/move-pot');
        }
        // establish valid locations for translation set, which may include current:
        $filechoice = $project->initLocaleFiles($locale);
        // start with current location so always first in list
        $locations = array();
        $locations[$typeId] = new Loco_mvc_ViewParams( array(
            'label' => $parent->getTypeLabel($typeId),
            'paths' => array( new Loco_mvc_ViewParams( array(
                'path' => $current,
                'active' => true,
            ) ) )
        ) );
        /* @var Loco_fs_File $pofile */
        foreach( $filechoice as $pofile ){
            $relpath = $pofile->getRelativePath($content_dir);
            if( $current === $relpath ){
                continue;
            }
            // initialize location type (system, etc..)
            $parent = new Loco_fs_LocaleDirectory( $pofile->dirname() );
            $typeId = $parent->getTypeId();
            if( ! isset($locations[$typeId]) ){
                $locations[$typeId] = new Loco_mvc_ViewParams( array(
                    'label' => $parent->getTypeLabel($typeId),
                    'paths' => array(),
                ) );
            }
            $choice = new Loco_mvc_ViewParams( array(
                'path' => $relpath,
            ) );
            $locations[$typeId]['paths'][] = $choice;
        }
        $this->set('locations', $locations );
        $this->set('advanced', $_SERVER['REQUEST_URI'].'&custom=1' );
        return $this->view('admin/file/move-po');
    }

}