<?php
/**
 * Pseudo-bundle view, lists all files available in a single locale
 */
class Loco_admin_bundle_LocaleController extends Loco_mvc_AdminController {
    
    /**
     * @var Loco_Locale
     */
    private $locale;


    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $tag = $this->get('locale');
        $locale = Loco_Locale::parse($tag);
        if( $locale->isValid() ){
            $api = new Loco_api_WordPressTranslations;
            $this->set('title', $locale->ensureName($api) );
            $this->locale = $locale;
            $this->enqueueStyle('locale')->enqueueStyle('fileinfo');
        }
    }


    /**
     * {@inheritdoc}
     */
    public function getHelpTabs(){
        return array (
            __('Overview','default') => $this->viewSnippet('tab-locale-view'),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function render(){

        // locale already parsed during init (for page title)
        $locale = $this->locale;
        if( ! $locale || ! $locale->isValid() ){
            throw new Loco_error_Exception('Invalid locale argument');
        }

        // language may not be "installed" but we still want to inspect available files
        $api = new Loco_api_WordPressTranslations;
        $installed = $api->isInstalled($locale);
        
        $tag = (string) $locale;
        $package = new Loco_package_Locale( $locale );

        // Get PO files for this locale
        $files = $package->findLocaleFiles();
        $translations = array();
        $modified = 0;
        $npofiles = 0;
        $nfiles = 0;

        // source locale means we want to see POT instead of translations
        if( 'en_US' === $tag ){
            $files = $package->findTemplateFiles()->augment($files);
        }
        
        /* @var Loco_fs_File */
        foreach( $files as $file ){
            $nfiles++;
            if( 'pot' !== $file->extension() ){
                $npofiles++;
            }
            $modified = max( $modified, $file->modified() );
            $project = $package->getProject($file);
            // do similarly to Loco_admin_bundle_ViewController::createFileParams
            $meta = Loco_gettext_Metadata::load($file);
            $dir = new Loco_fs_LocaleDirectory( $file->dirname() );
            // arguments for deep link into project
            $slug = $project->getSlug();
            $domain = $project->getDomain()->getName();
            $bundle = $project->getBundle();
            $type = strtolower( $bundle->getType() );
            $args = array(
                // 'locale' => $tag,
                'bundle' => $bundle->getHandle(),
                'domain' => $project->getId(),
                'path' => $meta->getPath(false),
            );
            // append data required for PO table row, except use bundle data instead of locale data
            $translations[$type][] = new Loco_mvc_ViewParams( array (
                // bundle info
                'title' => $project->getName(),
                'domain' => $domain,
                'short' => ! $slug || $project->isDomainDefault() ? $domain : $domain.'→'.$slug,
                // file info
                'meta' => $meta,
                'name' => $file->basename(),
                'time' => $file->modified(),
                'type' => strtoupper( $file->extension() ),
                'todo' => $meta->countIncomplete(),
                'total' => $meta->getTotal(),
                // author / system / custom / other
                'store' => $dir->getTypeLabel( $dir->getTypeId() ),
                // links
                'view' =>   Loco_mvc_AdminRouter::generate( $type.'-file-view', $args ),
                'info' =>   Loco_mvc_AdminRouter::generate( $type.'-file-info', $args ),
                'edit' =>   Loco_mvc_AdminRouter::generate( $type.'-file-edit', $args ),
                'move' =>   Loco_mvc_AdminRouter::generate( $type.'-file-move', $args ),
                'delete' => Loco_mvc_AdminRouter::generate( $type.'-file-delete', $args ),
                'copy' =>   Loco_mvc_AdminRouter::generate( $type.'-msginit', $args ),
            ) );
        }
        
        $title = __( 'Installed languages', 'loco-translate' );
        $breadcrumb = new Loco_admin_Navigation;
        $breadcrumb->add( $title, Loco_mvc_AdminRouter::generate('lang') );
        //$breadcrumb->add( $locale->getName() );
        $breadcrumb->add( $tag );

        // It's unlikely that an "installed" language would have no files, but could happen if only MO on disk
        if( 0 === $nfiles ){
            return $this->view('admin/errors/no-locale', compact('breadcrumb','locale') );
        }
        
        // files may be available for language even if not installed (i.e. no core files on disk)
        if( ! $installed || ! isset($translations['core']) && 'en_US' !== $tag ){
            Loco_error_AdminNotices::warn( __('No core translation files are installed for this language','loco-translate') )
                ->addLink('https://codex.wordpress.org/Installing_WordPress_in_Your_Language', __('Documentation','loco-translate') );
        }

        // Translated type labels and "See all <type>" links
        $types = array(
            'core' => new Loco_mvc_ViewParams( array(
                'name' => __('WordPress Core','loco-translate'),
                'text' => __('See all core translations','loco-translate'), 
                'href' => Loco_mvc_AdminRouter::generate('core') 
            ) ),
            'theme' => new Loco_mvc_ViewParams( array(
                'name' => __('Themes','loco-translate'),
                'text' => __('See all themes','loco-translate'), 
                'href' => Loco_mvc_AdminRouter::generate('theme') 
            ) ),
            'plugin' => new Loco_mvc_ViewParams( array(
                'name' => __('Plugins','loco-translate'),
                'text' => __('See all plugins','loco-translate'), 
                'href' => Loco_mvc_AdminRouter::generate('plugin') 
            ) ),
        );
        
        $this->set( 'locale', new Loco_mvc_ViewParams( array(
            'code' => $tag,
            'name' => $locale->getName(),
            'attr' => 'class="'.$locale->getIcon().'" lang="'.$locale->lang.'"',
        ) ) );

        return $this->view( 'admin/bundle/locale', compact('breadcrumb','translations','types','npofiles','modified') );
    }

    
}
