<?php
/**
 * pre-msginit function. Prepares arguments for creating a new PO language file
 */
class Loco_admin_init_InitPoController extends Loco_admin_bundle_BaseController {
    
    
    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $this->enqueueStyle('poinit');
        //
        $bundle = $this->getBundle();
        $this->set('title', __('New language','loco-translate').' &lsaquo; '.$bundle );
    }



    /**
     * {@inheritdoc}
     */
    public function getHelpTabs(){
        return array (
            __('Overview','default') => $this->viewSnippet('tab-init-po'),
        );
    }



    /**
     * Sort to the left the best option for saving new translation files
     * @return Loco_mvc_ViewParams
     */
    private function sortPreferred( array $choices ){
        usort( $choices, array(__CLASS__,'_onSortPreferred') );
        $best = current( $choices );
        if( $best && ! $best['disabled'] ){
            return $best;
        }
    }

    

    /**
     * @internal
     */
    public static function _onSortPreferred( Loco_mvc_ViewParams $a, Loco_mvc_ViewParams $b ){
        $x = self::scoreFileChoice($a);
        $y = self::scoreFileChoice($b);
        return $x === $y ? 0 : ( $x > $y ? -1 : 1 );
    }
    
    
    /**
     * Score an individual file choice for sorting preferred
     * @return int
     */
    private static function scoreFileChoice( Loco_mvc_ViewParams $p ){
        $score = 0;
        if( $p['writable'] ){
            $score++;
        }
        if( $p['disabled'] ){
            $score -= 2;
        }
        if( $p['systype'] ){
            $score--;
        }
        return $score;
    }



    /**
     * @internal 
     */
    public static function _onSortLocationKeys( $a, $b ){
        static $order = array('custom' => 4, 'wplang' => 3, 'theme' => 2, 'plugin' => 2, 'other' => 1 );
        $x = $order[$a];
        $y = $order[$b];
        return $x === $y ? 0 : ( $x > $y ? -1 : 1 );
    }



    /**
     * {@inheritdoc}
     */
    public function render(){
        
        $breadcrumb = $this->prepareNavigation();
        // "new" tab is confusing when no project-scope navigation
        // $this->get('tabs')->add( __('New PO','loco-translate'), '', true );
        
        // bundle mandatory, but project optional
        $bundle = $this->getBundle();

        try {
            $project = $this->getProject();
            $slug = $project->getSlug();
            $domain = (string) $project->getDomain();
            $subhead = sprintf( __('Initializing new translations in "%s"','loco-translate'), $slug?$slug:$domain );
        }
        catch( Loco_error_Exception $e ){
            $project = null;
            $subhead = __('Initializing new translations in unknown set','loco-translate');
        }

        $title = __('New language','loco-translate');
        $this->set('subhead', $subhead );
        
        // navigate up to bundle listing page 
        $breadcrumb->add( $title );
        $this->set( 'breadcrumb', $breadcrumb );
        
        // default locale is a placeholder
        $locale = new Loco_Locale('zxx');
        $content_dir = untrailingslashit( loco_constant('WP_CONTENT_DIR') );
        $copying = false;
        
        // Permit using any provided file a template instead of POT
        if( $potpath = $this->get('path') ){
            $potfile = new Loco_fs_LocaleFile($potpath);
            $potfile->normalize( $content_dir );
            if( ! $potfile->exists() ){
                throw new Loco_error_Exception('Forced template argument must exist');
            }
            $copying = true;
            // forced source could be a POT (although UI would normally prevent it)
            if( $potfile->getSuffix() ){
                $locale = $potfile->getLocale();
                $this->set('sourceLocale', $locale );
            }
        }
        // else project not configured. UI should prevent this by not offering msginit
        else if( ! $project ){
            throw new Loco_error_Exception('Cannot add new language to unconfigured set');
        }
        // else POT file may or may not be known, and may or may not exist
        else {
            $potfile = $project->getPot();
        }


        $locales = array();
        $installed = array();
        $api = new Loco_api_WordPressTranslations;
        // pull installed list first, this will include en_US and any non-standard languages installed
        foreach( $api->getInstalledCore() as $tag ){
            $locale = Loco_Locale::parse($tag);
            if( $locale->isValid() ){
                $tag = (string) $tag;
                // We may not have names for these, so just the language tag will show
                $installed[$tag] = new Loco_mvc_ViewParams( array(
                    'value' => $tag,
                    'icon'  => $locale->getIcon(),
                    'label' => $locale->ensureName($api),
                ) );
            }
        }
        // pull the same list of "available" languages as used in WordPress settings
        /* @var $locale Loco_Locale */
        foreach( $api->getAvailableCore() as $tag => $locale ){
            if( ! array_key_exists($tag,$installed) ){
                $locales[$tag] = new Loco_mvc_ViewParams( array(
                    'value' => $tag,
                    'icon'  => $locale->getIcon(),
                    'label' => $locale->ensureName($api),
                ) );
            }
        }

        // two locale lists built for "installed" and "available" dropdowns
        $this->set( 'locales', $locales );
        $this->set( 'installed', $installed );

        // Critical that user selects the correct save location:
        if( $project ){
            $filechoice = $project->initLocaleFiles( $locale );
        }
        // without configured project we will only allow save to same location
        else {
            $filechoice = new Loco_fs_FileList;
        }


        // show information about POT file if we are initializing from template
        if( $potfile && $potfile->exists() ){
            $meta = Loco_gettext_Metadata::load($potfile);
            $total = $meta->getTotal();
            $summary = sprintf( _n('One string found in %2$s','%s strings found in %s',$total,'loco-translate'), number_format($total), $potfile->basename() );
            $this->set( 'pot', new Loco_mvc_ViewParams( array(
                'name' => $potfile->basename(),
                'path' => $meta->getPath(false),
            ) ) );
            // if copying an existing PO file, we can fairly safely establish the correct prefixing
            if( $copying ){
                $poname = ( $prefix = $potfile->getPrefix() ) ? sprintf('%s-%s.po',$prefix,$locale) : sprintf('%s.po',$locale);
                $pofile = new Loco_fs_LocaleFile( $poname );
                $pofile->normalize( $potfile->dirname() );
                $filechoice->add( $pofile );
            }
            /// else if POT is in a folder we don't know about, we may as well add to the choices
            // TODO this means another utility function in project for prefixing rules on individual location
        }
        // else no template exists, so we prompt to extract from source
        else {
            $this->set( 'ext', new Loco_mvc_ViewParams( array(
                'link' => Loco_mvc_AdminRouter::generate( $this->get('type').'-xgettext', $_GET ),
                'text' => __('Create template','loco-translate'),
            ) ) );
            // if forcing source extraction show brief description of source files
            if( $this->get('extract') ){
                // Tokenizer required for string extraction
                if( ! loco_check_extension('tokenizer') ){
                    return $this->view('admin/errors/no-tokenizer');
                }
                $nfiles = count( $project->findSourceFiles() );
                $summary = sprintf( _n('1 source file will be scanned for translatable strings','%s source files will be scanned for translatable strings',$nfiles,'loco-translate'), number_format_i18n($nfiles) );
            }
            // else prompt for template creation before continuing
            else {
                $this->set( 'skip', new Loco_mvc_ViewParams( array(
                    'link' => Loco_mvc_AdminRouter::generate( $this->get('_route'), $_GET + array( 'extract' => '1' ) ),
                    'text' => __('Skip template','loco-translate'),
                ) ) );
                // POT could still be defined, it might just not exist yet
                if( $potfile ){
                    $this->set('pot', Loco_mvc_FileParams::create($potfile) );
                }
                // else offer assignment of a new file
                else {
                    $this->set( 'conf', new Loco_mvc_ViewParams( array(
                        'link' => Loco_mvc_AdminRouter::generate( $this->get('type').'-conf', array_intersect_key($_GET,array('bundle'=>'')) ),
                        'text' => __('Assign template','loco-translate'),
                    ) ) );
                }
                return $this->view('admin/init/init-prompt');
            }
        }
        $this->set( 'summary', $summary );
        
        // group established locations into types (official, etc..)
        // there is no point checking whether any of these file exist, because we don't know what language will be chosen yet.
        $sortable = array();
        $locations = array();
        $fs_protect = Loco_data_Settings::get()->fs_protect;
        $fs_failure = null;
        /* @var Loco_fs_File $pofile */
        foreach( $filechoice as $pofile ){
            $parent = new Loco_fs_LocaleDirectory( $pofile->dirname() );
            $systype = $parent->getUpdateType();
            $typeId = $parent->getTypeId();
            if( ! isset($locations[$typeId]) ){
                $locations[$typeId] = new Loco_mvc_ViewParams( array(
                    'label' => $parent->getTypeLabel( $typeId ),
                    'paths' => array(),
                ) );
            }
            // folder may be unwritable (requiring connect to create file) or may be denied under security settings
            try {
                $context = $parent->getWriteContext()->authorize();
                $writable = $context->writable();
                $disabled = false;
            }
            catch( Loco_error_WriteException $e ){
                $fs_failure = $e->getMessage();
                $writable = false;
                $disabled = true;
            }
            $choice = new Loco_mvc_ViewParams( array (
                'checked' => '',
                'writable' => $writable,
                'disabled' => $disabled,
                'systype' => $systype,
                'parent' => Loco_mvc_FileParams::create( $parent ),
                'hidden' => $pofile->getRelativePath($content_dir),
                'holder' => str_replace( (string) $locale, '<span>&lt;locale&gt;</span>', $pofile->basename() ),
            ) );
            // may need to show system file warnings
            if( $systype && $fs_protect ){
                $choice['syswarn'] = true;
            }
            $sortable[] = $choice;
            $locations[$typeId]['paths'][] = $choice;
        }

        // display locations in runtime preference order
        uksort( $locations, array(__CLASS__,'_onSortLocationKeys') );
        $this->set( 'locations', $locations );

        // pre-select best (safest/writable) option
        if( $preferred = $this->sortPreferred( $sortable ) ){
            $preferred['checked'] = 'checked';
        }
        // else show total lock message. probably file mods disallowed
        else if( $fs_failure ){
            $this->set('fsLocked', $fs_failure );
        }
        
        // hidden fields to pass through to Ajax endpoint
        $this->set('hidden', new Loco_mvc_HiddenFields( array(
            'action' => 'loco_json',
            'route' => 'msginit',
            'loco-nonce' => $this->setNonce('msginit')->value,
            'type' => $bundle->getType(),
            'bundle' => $bundle->getHandle(),
            'domain' => $project ? $project->getId() : '',
            'source' => $potpath,
        ) ) );
        
        $this->set('help', new Loco_mvc_ViewParams( array(
            'href' => apply_filters('loco_external','https://localise.biz/wordpress/plugin/manual/msginit'),
            'text' => __("What's this?",'loco-translate'),
        ) ) );

        // file system prompts will be handled when paths are selected (i.e. we don't have one yet)
        $this->prepareFsConnect( 'create', '' );
        
        $this->enqueueScript('poinit');
        return $this->view( 'admin/init/init-po', array() );
    }

    
}