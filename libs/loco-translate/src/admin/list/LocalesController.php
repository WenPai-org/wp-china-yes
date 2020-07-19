<?php
/**
 * Lists all installed locales.
 * WordPress decides what is "installed" based on presence of core translation files
 */
class Loco_admin_list_LocalesController extends Loco_mvc_AdminController {


    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $this->enqueueStyle('locale');
    }



    /**
     * {@inheritdoc}
     */
    public function getHelpTabs(){
        return array (
            __('Overview','default') => $this->viewSnippet('tab-list-locales'),
        );
    }



    /**
     * {@inheritdoc}
     */
    public function render(){

        $this->set( 'title', __( 'Installed languages', 'loco-translate' ) );
        
        $used = array();
        $locales = array();
        $api = new Loco_api_WordPressTranslations;
        
        $active = get_locale();

        // list which sites have each language as their WPLANG setting
        if( $multisite = is_multisite() ){
            $this->set('multisite',true);
            /* @var WP_Site $site */
            foreach( get_sites() as $site ){
                $id = (int) $site->blog_id;
                $tag = get_blog_option( $id, 'WPLANG') or $tag = 'en_US';
                $name = get_blog_option( $id, 'blogname' );
                $used[$tag][] = $name;
            }
        }
        // else single site shows tick instead of site name
        else {
            $used[$active][] = '✓';
        }

        // add installed languages to file crawler
        $finder = new Loco_package_Locale;

        // Pull "installed" languages (including en_US)
        foreach( $api->getInstalledCore() as $tag ){
            $locale = Loco_Locale::parse($tag);
            if( $locale->isValid() ){
                $tag = (string) $locale;
                $finder->addLocale($locale);
                $args = array( 'locale' => $tag );
                $locales[$tag] = new Loco_mvc_ViewParams( array(
                    'nfiles' => 0,
                    'time' => 0,
                    'lcode' => $tag,
                    'lname' => $locale->ensureName($api),
                    'lattr' => 'class="'.$locale->getIcon().'" lang="'.$locale->lang.'"',
                    'href' => Loco_mvc_AdminRouter::generate('lang-view',$args),
                    'used' => isset($used[$tag]) ? implode( ', ', $used[$tag] ) : ( $multisite ? '--' : '' ),
                    'active' => $active === $tag,
                ) );
            }
        }
        $this->set('locales', $locales );
        
        
        // Count up unique PO files  
        foreach( $finder->findLocaleFiles() as $file ){
            if( preg_match('/(?:^|-)([_a-zA-Z]+).po$/', $file->basename(), $r ) ){
                $locale = Loco_Locale::parse($r[1]);
                if( $locale->isValid() ){
                    $tag = (string) $locale;
                    $locales[$tag]['nfiles']++;
                    $locales[$tag]['time'] = max( $locales[$tag]['time'], $file->modified() );
                }
            }
        }
        
        // POT files are in en_US locale
        $tag = 'en_US';
        foreach( $finder->findTemplateFiles() as $file ){
            $locales[$tag]['nfiles']++;
            $locales[$tag]['time'] = max( $locales[$tag]['time'], $file->modified() );
        }
        

        return $this->view( 'admin/list/locales' );
        
    }

    
}
