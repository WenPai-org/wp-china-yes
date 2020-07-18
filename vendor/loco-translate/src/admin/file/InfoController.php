<?php
/**
 * File info / management view.
 */
class Loco_admin_file_InfoController extends Loco_admin_file_BaseController {


    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $this->enqueueStyle('fileinfo');
        //
        $file = $this->get('file');
        $bundle = $this->getBundle();
        $this->set('title', $file->basename().' &lsaquo; '.$bundle->getName() );
    }


    /**
     * {@inheritdoc}
     */
    public function getHelpTabs(){
        return array (
            __('Overview','default') => $this->viewSnippet('tab-file-info'),
        );
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function render(){
        
        $file = $this->get('file');
        $name = $file->basename();
        $this->set('title', $name );
        
        if( $fail = $this->getFileError($file) ){
            return $fail;
        }
        
        // file info
        $ext = strtolower( $file->extension() );
        $finfo = Loco_mvc_FileParams::create( $file );
        $this->set('file', $finfo );
        $finfo['type'] = strtoupper($ext);
        if( $file->exists() ){
            $finfo['existent'] = true;
            $finfo['writable'] = $file->writable();
            $finfo['deletable'] = $file->deletable();
            $finfo['mtime'] = $file->modified();
            // Notify if file is managed by WordPress
            $api = new Loco_api_WordPressFileSystem;
            if( $api->isAutoUpdatable($file) ){
                $finfo['autoupdate'] = true;
            }
        }
        
        // location info
        $dir = new Loco_fs_LocaleDirectory( $file->dirname() );
        $dinfo = Loco_mvc_FileParams::create( $dir );
        $this->set('dir', $dinfo );
        $dinfo['type'] = $dir->getTypeId();
        if( $dir->exists() && $dir->isDirectory() ){
            $dinfo['existent'] = true;
            $dinfo['writable'] = $dir->writable();
        }
        
        // collect note worthy problems with file headers
        $debugging = loco_debugging();
        $debug = array();
        
        // get the name of the web server for information purposes
        $this->set('httpd', Loco_compat_PosixExtension::getHttpdUser() );
        
        // unknown file template if required
        $locale = null;
        $project = null;
        $tpl = 'admin/file/info-other';

        // we should know the project the file belongs to, but permitting orphans for debugging
        try {
            $project = $this->getProject();
            $template = $project->getPot();
            $isTemplate = $template && $file->equal($template);
            $this->set('isTemplate', $isTemplate );
            $this->set('project', $project );
        }
        catch( Loco_error_Exception $e ){
            $debug[] = $e->getMessage();
            $isTemplate = false;
            $template = null;
        }

        // file will be Gettext most likely            
        if( 'pot' === $ext || 'po' === $ext || 'mo' === $ext ){
            // treat as template until locale verified
            $tpl = 'admin/file/info-pot';
            // don't attempt to pull locale of template file
            if( 'pot' !== $ext && ! $isTemplate ){
                $locale = $file->getLocale();
                $code = (string) $locale;
                if( $locale->isValid() ){
                    // find PO/MO counter parts
                    if( 'po' === $ext ){
                        $tpl = 'admin/file/info-po';
                        $sibling = $file->cloneExtension('mo');
                    }
                    else {
                        $tpl = 'admin/file/info-mo';
                        $sibling = $file->cloneExtension('po');
                    }
                    $info = Loco_mvc_FileParams::create($sibling);
                    $this->set( 'sibling', $info );
                    if( $sibling->exists() ){
                        $info['existent'] = true;
                        $info['writable'] = $sibling->writable();
                    }
                }
            }
            // Do full parse to get stats and headers
            try {
                $data = Loco_gettext_Data::load($file);
                $head = $data->getHeaders();
                $author = $head->trimmed('Last-Translator') or $author = __('Unknown author','loco-translate');
                $this->set( 'author', $author );
                // date headers may not be same as file modification time (files copied to server etc..)
                $podate = $head->trimmed( $locale ? 'PO-Revision-Date' : 'POT-Creation-Date' );
                $potime = Loco_gettext_Data::parseDate($podate) or $potime = $file->modified();
                $this->set('potime', $potime );
                // access to meta stats, normally cached on listing pages
                $meta = Loco_gettext_Metadata::create($file,$data);
                $this->set( 'meta', $meta );
                // allow PO header to specify alternative template for sync
                if( $head->has('X-Loco-Template') ){
                    $altpot = new Loco_fs_File($head['X-Loco-Template']);
                    $altpot->normalize( $this->getBundle()->getDirectoryPath() );
                    if( $altpot->exists() && ( ! $template || ! $template->equal($altpot) ) ){
                        $template = $altpot;
                    }
                }
                // establish whether PO is in sync with POT
                if( $template && ! $isTemplate && 'po' === $ext && $template->exists() ){
                    try {
                        $this->set('potfile', new Loco_mvc_FileParams( array(
                            'synced' => Loco_gettext_Data::load($template)->equalSource($data),
                        ), $template ) );
                    }
                    catch( Exception $e ){
                        // ignore invalid template in this context
                    }
                }
                if( $debugging ){
                    // missing or invalid headers are tollerated but developers should be notified
                    if( $debugging && ! count($head) ){
                        $debug[] = __('File does not have a valid header','loco-translate');
                    }
                    // Language header sanity checks, raising developer (debug) warnings
                    if( $locale ){
                        if( $value = $head['Language'] ){
                            $check = (string) Loco_Locale::parse($value);
                            if( $check !== $code ){
                                $debug[]= sprintf( __('Language header is "%s" but file name contains "%s"','loco-translate'), $value, $code );
                            }
                        }
                        if( $value = $head['Plural-Forms'] ){
                            try {
                                $locale->setPluralFormsHeader($value);
                            }
                            catch( InvalidArgumentException $e ){
                                $debug[] = sprintf('Plural-Forms header is invalid, "%s"',$value);
                            }
                        }
                    }
                    // Other sanity checks
                    if( $project && ( $value = $head['Project-Id-Version'] ) && $value !== $project->getName() ){
                        $debug[] = sprintf('Project-Id-Version header is "%s" but project is "%s"', $value, $project );
                    }
                }
                // Count source text for templates only (assumed English)
                if( 'admin/file/info-pot' === $tpl ){
                    $counter = new Loco_gettext_WordCount($data);
                    $this->set('words', $counter->count() );
                }
            }
            catch( Loco_error_Exception $e ){
                $this->set('error', $e->getMessage() );
                $tpl = 'admin/file/info-other';
            }
        }
        
        if( $debugging && $debug ){
            $this->set( 'debug', new Loco_mvc_ViewParams($debug) );
        }

        return $this->view( $tpl );
    }
    
}
