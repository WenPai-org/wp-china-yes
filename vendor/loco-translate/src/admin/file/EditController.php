<?php
/**
 * PO editor view
 */
class Loco_admin_file_EditController extends Loco_admin_file_BaseController {


    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $this->enqueueStyle('editor');
        //
        $file = $this->get('file');
        $bundle = $this->getBundle();
        // translators: %1$s is the file name, %2$s is the bundle name
        $this->set('title', sprintf( __('Editing %1$s in %2$s','loco-translate'), $file->basename(), $bundle ) );
    }


    /**
     * {@inheritdoc}
     */
    public function getHelpTabs(){
        return array (
            __('Overview','default') => $this->viewSnippet('tab-file-edit'),
        );
    }


    /**
     * @param bool whether po files is in read-only mode
     * @return array
     */
    private function getNonces( $readonly ){
        $nonces = array();
        foreach( $readonly ? array('fsReference') : array('sync','save','fsReference','apis') as $name ){
            $nonces[$name] = wp_create_nonce($name);
        }
        return $nonces;
    }


    /**
     * @param bool whether po files is in read-only mode
     * @return array
     */
    private function getApiProviders( $readonly ){
        return $readonly ? null : array_values( array_filter(Loco_api_Providers::export(),array(__CLASS__,'filterApiProvider') ) );
    }


    /**
     * @internal
     * @param string[]
     * @return bool
     */
    private static function filterApiProvider( array $api ){
        return (bool) $api['key'];
    }


    /**
     * {@inheritdoc}
     */
    public function render(){
        
        // file must exist for editing
        $file = $this->get('file');
        if( $fail = $this->getFileError($file) ){
            return $fail; 
        }
        
        // editor will be rendered
        $this->enqueueScript('editor');
        
        // Parse file data into JavaScript for editor
        try {
            $this->set('modified', $file->modified() );
            $data = Loco_gettext_Data::load( $file );
        }
        catch( Exception $e ){
            Loco_error_AdminNotices::add( Loco_error_Exception::convert($e) );
            $data = Loco_gettext_Data::dummy();
        }

        $head = $data->getHeaders();

        // default is to permit editing of any file
        $readonly = false;

        // Establish if file belongs to a configured project
        try {
            $bundle = $this->getBundle();
            $project = $this->getProject();
        }
        // Fine if not, this just means sync isn't possible.
        catch( Loco_error_Exception $e ){
            Loco_error_AdminNotices::add( $e );
            Loco_error_AdminNotices::debug( sprintf("Sync is disabled because this file doesn't relate to a known set of translations", $bundle ) );
            $project = null;
        }
            
        // Establish PO/POT edit mode
        $potfile = null;
        $locale = $this->getLocale();
        if( $locale instanceof Loco_Locale ){
            // alternative POT file may be forced by PO headers
            if( $value = $head['X-Loco-Template'] ){
                $potfile = new Loco_fs_File($value);
                $potfile->normalize( $bundle->getDirectoryPath() );
            }
            // else use project-configured template, assuming there is one
            // no way to get configured POT if invalid project
            else if( $project ){
                $potfile = $project->getPot();
                // Handle situation where project defines a localised file as the official template
                if( $potfile && $potfile->equal($file) ){
                    $locale = null;
                    $potfile = null;
                }
            }
            if( $potfile ){
                // Validate template file as long as it exists
                if( $potfile->exists() ){
                    try {
                        $potdata = Loco_gettext_Data::load( $potfile );
                        if( ! $potdata->equalSource($data) ){
                            Loco_error_AdminNotices::debug( sprintf( __("Translations don't match template. Run sync to update from %s",'loco-translate'), $potfile->basename() ) );
                        }
                    }
                    catch( Exception $e ){
                        // translators: Where %s is the name of the invalid POT file
                        Loco_error_AdminNotices::warn( sprintf( __('Translation template is invalid (%s)','loco-translate'), $potfile->basename() ) );
                        $potfile = null;
                    }
                }
                // else template doesn't exist, so sync will be done to source code
                else {
                    // Loco_error_AdminNotices::debug( sprintf( __('Template file not found (%s)','loco-translate'), $potfile->basename() ) );
                    $potfile = null;
                }
            }
            if( $locale ){
                // allow PO file to dictate its own Plural-Forms
                try {
                    $locale->setPluralFormsHeader( $head['Plural-Forms'] );
                }
                catch( InvalidArgumentException $e ){
                    // ignore invalid Plural-Forms
                }
                // fill in missing PO headers now locale is fully resolved
                $data->localize($locale);
                
                // If MO file will be compiled, check for library/config problems
                if ( 2 !== strlen( "\xC2\xA3" ) ) {
                    Loco_error_AdminNotices::warn('Your mbstring configuration will result in corrupt MO files. Please ensure mbstring.func_overload is disabled');
                }
            }
        }
        
        $settings =  Loco_data_Settings::get();
        
        if( is_null($locale) ){
            // notify if template is locked (save and sync will be disabled)
            if( $project && $project->isPotLocked() ){
                $this->set('fsDenied', true );
                $readonly = true;
            }
            // translators: Warning when POT file is opened in the file editor. It can be disabled in settings.
            else if( 1 === $settings->pot_protect ){
                Loco_error_AdminNotices::warn( __("This is NOT a translation file. Manual editing of source strings is not recommended.",'loco-translate') )
                 ->addLink( Loco_mvc_AdminRouter::generate('config').'#loco--pot-protect', __('Settings','loco-translate') )
                 ->addLink( apply_filters('loco_external','https://localise.biz/wordpress/plugin/manual/templates'), __('Documentation','loco-translate') );
            }
        }
        
        // back end expects paths relative to wp-content
        $wp_content = loco_constant('WP_CONTENT_DIR');
        
        $this->set( 'js', new Loco_mvc_ViewParams( array(
            'podata' => $data->jsonSerialize(),
            'powrap' => (int) $settings->po_width,
            'multipart' => (bool) $settings->ajax_files,
            'locale' => $locale ? $locale->jsonSerialize() : null,
            'potpath' => $locale && $potfile ? $potfile->getRelativePath($wp_content) : null,
            'popath' => $this->get('path'),
            'readonly' => $readonly,
            'project' => $project ? array (
                'bundle' => $bundle->getId(),
                'domain' => (string) $project->getId(),
            ) : null,
            'nonces' => $this->getNonces($readonly),
            'apis' => $locale ? $this->getApiProviders($readonly) : null,
        ) ) );
        $this->set( 'ui', new Loco_mvc_ViewParams( array(
             // Translators: button for adding a new string when manually editing a POT file
             'add'      => _x('Add','Editor','loco-translate'),
             // Translators: button for removing a string when manually editing a POT file
             'del'      => _x('Remove','Editor','loco-translate'),
             'help'     => __('Help','loco-translate'),
             // Translators: Button that saves translations to disk
             'save'     => _x('Save','Editor','loco-translate'),
             // Translators: Button that runs in-editor sync/operation
             'sync'     => _x('Sync','Editor','loco-translate'),
             // Translators: Button that reloads current screen
             'revert'   => _x('Revert','Editor','loco-translate'),
             // Translators: Button that opens window for auto-translating
             'auto'     => _x('Auto','Editor','loco-translate'),
             // Translators: Button for downloading a PO, MO or POT file
             'download' => _x('Download','Editor','loco-translate'),
             // Translators: Placeholder text for text filter above editor
             'filter'   => __('Filter translations','loco-translate'),
             // Translators: Button that toggles invisible characters
             'invs'     => _x('Toggle invisibles','Editor','loco-translate'),
             // Translators: Button that toggles between "code" and regular text editing modes
             'code'     => _x('Toggle code view','Editor','loco-translate'),
        ) ) );

        // Download form params
        $hidden = new Loco_mvc_HiddenFields( array(
            'path'   => '',
            'source' => '',
            'route'  => 'download',
            'action' => 'loco_download',
        ) );
        $this->set( 'dlFields', $hidden->setNonce('download') );
        $this->set( 'dlAction', admin_url('admin-ajax.php','relative') );

        // Remote file system required if file is not directly writable
        $this->prepareFsConnect( 'update', $this->get('path') );
        
        // set simpler title for breadcrumb
        $this->set('title', $file->basename() );
        
        // ok to render editor as either po or pot
        $tpl = $locale ? 'po' : 'pot';
        return $this->view( 'admin/file/edit-'.$tpl, array() );
    }
    
    
    
    
}