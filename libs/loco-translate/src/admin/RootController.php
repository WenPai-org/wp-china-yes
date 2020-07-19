<?php
/**
 * Highest level Loco admin screen.
 */
class Loco_admin_RootController extends Loco_admin_list_BaseController {

    /**
     * {@inheritdoc}
     */
    public function getHelpTabs(){
        return array (
            __('Overview','default') => $this->viewSnippet('tab-home'),
        );
    }

    
    /**
     * Render main entry home screen
     */
    public function render(){
        
        // translators: home screen title where %s is the version number
        $this->set('title', sprintf( __('Loco Translate %s','loco-translate'), loco_plugin_version() ) );

        // Show currently active theme on home page
        $theme = Loco_package_Theme::create(null);
        $this->set('theme', $this->bundleParam($theme) );
        
        // Show plugins that have currently loaded translations
        $bundles = array();
        foreach( Loco_package_Listener::singleton()->getPlugins() as $bundle ){
            try {
                $bundles[] = $this->bundleParam($bundle);
            }
            catch( Exception $e ){
                // bundle should exist if we heard it. reduce to debug notice
                Loco_error_AdminNotices::debug( $e->getMessage() );
            }
        }
        $this->set('plugins', $bundles );
        

        // Show recently "used' bundles
        $bundles = array();
        $recent = Loco_data_RecentItems::get();
        // filter in lieu of plugin setting
        $maxlen = apply_filters('loco_num_recent_bundles', 10 );
        foreach( $recent->getBundles(0,$maxlen) as $id ){
            try {
                $bundle = Loco_package_Bundle::fromId($id);
                $bundles[] = $this->bundleParam($bundle);
            }
            catch( Exception $e ){
                // possible that bundle ID changed since being saved in recent items list
            }
        }
        $this->set('recent', $bundles );
        

        // current locale and related links
        $locale = Loco_Locale::parse( get_locale() );
        $api = new Loco_api_WordPressTranslations;
        $tag = (string) $locale;
        $this->set( 'siteLocale', new Loco_mvc_ViewParams( array(
            'code' => $tag,
            'name' => ( $name = $locale->ensureName($api) ),
            'attr' => 'class="'.$locale->getIcon().'" lang="'.$locale->lang.'"',
            'link' => '<a href="'.esc_url(Loco_mvc_AdminRouter::generate('lang-view', array('locale'=>$tag) )).'">'.esc_html($name).'</a>',
            //'opts' => admin_url('options-general.php').'#WPLANG',
        ) ) );
        
        // user's "admin" language may differ and is worth showing
        if( function_exists('get_user_locale') ){
            $locale = Loco_Locale::parse( get_user_locale() );
            $alt = (string) $locale;
            if( $tag !== $alt ){
                $this->set( 'adminLocale', new Loco_mvc_ViewParams( array(
                    'name' => ( $name = $locale->ensureName($api) ),
                    'link' => '<a href="'.esc_url(Loco_mvc_AdminRouter::generate('lang-view', array('locale'=>$tag) )).'">'.esc_html($name).'</a>',
                ) ) );
            }
        }
        
        $this->set('title', __('Welcome to Loco Translate','loco-translate') );
        
        return $this->view('admin/root');
    }


}