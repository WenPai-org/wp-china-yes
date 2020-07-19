<?php
/**
 * pre-xgettext function. Initializes a new PO file for a given locale
 */
class Loco_admin_init_InitPotController extends Loco_admin_bundle_BaseController {
    
    
    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $this->enqueueStyle('poinit');
        //
        $bundle = $this->getBundle();
        $this->set('title', __('New template','loco-translate').' &lsaquo; '.$bundle );
    }


    /**
     * {@inheritdoc}
     */
    public function getHelpTabs(){
        return array (
            __('Overview','default') => $this->viewSnippet('tab-init-pot'),
        );
    }
    
    

    /**
     * {@inheritdoc}
     */
    public function render(){
        
        $breadcrumb = $this->prepareNavigation();
        // "new" tab is confusing when no project-scope navigation
        // $this->get('tabs')->add( __('New POT','loco-translate'), '', true );

        $bundle = $this->getBundle();
        $project = $this->getProject();

        $slug = $project->getSlug();
        $domain = (string) $project->getDomain();
        $this->set('domain', $domain );
        
        // Tokenizer required for string extraction
        if( ! loco_check_extension('tokenizer') ){
            return $this->view('admin/errors/no-tokenizer');
        }
        
        // Establish default POT path whether it exists or not
        $pot = $project->getPot();
        while( ! $pot ){
            $name = ( $slug ? $slug : $domain ).'.pot';
            /* @var $dir Loco_fs_Directory */
            foreach( $project->getConfiguredTargets() as $dir ){
                $pot = new Loco_fs_File( $dir->getPath().'/'.$name );
                break 2;
            }
            // unlikely to have no configured targets, but possible ... so default to standard
            $pot = new Loco_fs_File( $bundle->getDirectoryPath().'/languages/'.$name );
            break;
        }
        
        // POT should actually not exist at this stage. It should be edited instead.
        if( $pot->exists() ){
            throw new Loco_error_Exception( __('Template file already exists','loco-translate') );
        }
        
        // Bundle may deliberately lock template to avoid end-user tampering
        // it makes little sense to do so when template doesn't exist, but we will honour the setting anyway.
        if( $project->isPotLocked() ){
            throw new Loco_error_Exception('Template is protected from updates by the bundle configuration');
        }
        
        // Just warn if POT writing will fail when saved, but still show screen
        $dir = $pot->getParent();
        
        // Avoiding full source scan until actioned, but calculate size to manage expectations
        $bytes = 0;
        $nfiles = 0;
        $nskip = 0;
        $largest = 0;
        $sources = $project->findSourceFiles();
        // skip files larger than configured maximum
        $opts = Loco_data_Settings::get();
        $max = wp_convert_hr_to_bytes( $opts->max_php_size );
        /* @var $sourceFile Loco_fs_File */
        foreach( $sources as $sourceFile ){
            $nfiles++;
            $fsize = $sourceFile->size();
            $largest = max( $largest, $fsize );
            if( $fsize > $max ){
                $nskip += 1;
                // uncomment to log which files are too large to be scanned
                // Loco_error_AdminNotices::debug( sprintf('%s is %s',$sourceFile,Loco_mvc_FileParams::renderBytes($fsize)) );
            }
            else {
                $bytes += $fsize;
            }
        }
        $this->set( 'scan', new Loco_mvc_ViewParams( array (
            'bytes' => $bytes,
            'count' => $nfiles,
            'skip' => $nskip,
            'size' => Loco_mvc_FileParams::renderBytes($bytes),
            'large' => Loco_mvc_FileParams::renderBytes($max),
            'largest' => Loco_mvc_FileParams::renderBytes($largest),
        ) ) );
        
        // file metadata
        $this->set('pot', Loco_mvc_FileParams::create( $pot ) );
        $this->set('dir', Loco_mvc_FileParams::create( $dir ) );
        
        $title = __('New template file','loco-translate');
        $subhead = sprintf( __('New translations template for "%s"','loco-translate'), $project );
        $this->set('subhead', $subhead );
        
        // navigate up to bundle listing page 
        $breadcrumb->add( $title );
        $this->set( 'breadcrumb', $breadcrumb );
        
        // ajax service takes the target directory path
        $content_dir = loco_constant('WP_CONTENT_DIR');
        $target_path = $pot->getParent()->getRelativePath($content_dir);

        // hidden fields to pass through to Ajax endpoint
        $this->set( 'hidden', new Loco_mvc_ViewParams( array(
            'action' => 'loco_json',
            'route' => 'xgettext',
            'loco-nonce' => $this->setNonce('xgettext')->value,
            'type' => $bundle->getType(),
            'bundle' => $bundle->getHandle(),
            'domain' => $project->getId(),
            'path' => $target_path,
            'name' => $pot->basename(),
        ) ) );

        // File system connect required if location not writable
        $relpath = $pot->getRelativePath($content_dir);
        $this->prepareFsConnect('create', $relpath );
        
        $this->enqueueScript('potinit');
        return $this->view( 'admin/init/init-pot' );
    }

    
    
    
}