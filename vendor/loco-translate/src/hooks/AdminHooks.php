<?php
/**
 * Common hooks for all admin contexts
 */
class Loco_hooks_AdminHooks extends Loco_hooks_Hookable {

    /**
     * @var Loco_mvc_AdminRouter
     */
    private $router;


    /**
     * "admin_notices" callback, 
     * If this is hooked and not unhooked then auto-hooks using annotations have failed.
     */
    public static function print_hook_failure(){
        echo '<div class="notice error"><p><strong>Error:</strong> Loco Translate failed to start up</p></div>';
    }


    /**
     * {@inheritdoc}
     */
    public function __construct(){
        // renders failure notice if plugin failed to start up admin hooks.
        add_action( 'admin_notices', array(__CLASS__,'print_hook_failure') );
        // initialize hooks
        parent::__construct();
        // Ajax router will be called directly in tests
        // @codeCoverageIgnoreStart
        if( loco_doing_ajax() ){
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
            // initialize Ajax router before hook fired so we can handle output buffering
            if( 'loco_' === substr($action,0,5)  && isset($_REQUEST['route']) ){
                $this->router = new Loco_mvc_AjaxRouter;
                Loco_package_Listener::create();
            }
        }
        // @codeCoverageIgnoreEnd
        // page router required on all pages as it hooks in the menu
        else {
            $this->router = new Loco_mvc_AdminRouter;
            // we don't know we will render a page yet, but we need to listen for text domain hooks as early as possible
            if( isset($_GET['page']) && 'loco' === substr($_GET['page'],0,4) ){
                Loco_package_Listener::create();
                // trigger post-upgrade process if required
                Loco_data_Settings::get()->migrate();
            }
        }
    }


	/**
	 * "admin_init" callback.
	 */
    public function on_admin_init(){
    	// This should fire just before WP_Privacy_Policy_Content::privacy_policy_guide is called
        // View this content at /wp-admin/privacy-policy-guide.php#wp-privacy-policy-guide-loco-translate
	    if( function_exists('wp_add_privacy_policy_content') ) {
	    	$url = apply_filters('loco_external','https://localise.biz/wordpress/plugin/privacy');
		    wp_add_privacy_policy_content(
		    	__('Loco Translate','loco-translate'),
			    esc_html( __("This plugin doesn't collect any data from public website visitors.",'loco-translate') ).'<br />'. 
                wp_kses( 
                    sprintf( __('Administrators and auditors may wish to review Loco\'s <a href="%s">plugin privacy notice</a>.','loco-translate'), esc_url($url) ),
                    array('a'=>array('href'=>true)), array('https')
                )
		    );
	    }
    }


    /**
     * "admin_menu" callback.
     */
    public function on_admin_menu(){
        // This earliest we need translations, and admin user locale should be set by now
        if( $this->router ){
            $domainPath = dirname( loco_plugin_self() ).'/languages';
            load_plugin_textdomain( 'loco-translate', false, $domainPath );
        }
        // Unhook failure notice that would fire if this hook was not successful
        remove_action( 'admin_notices', array(__CLASS__,'print_hook_failure') );
    }


    /**
     * plugin_action_links action callback
     * @param string[]
     * @param string
     * @return string[]
     */
    public function on_plugin_action_links( $links, $plugin = '' ){
         try {
             if( $plugin && current_user_can('loco_admin') && Loco_package_Plugin::get_plugin($plugin) ){
                // coerce links to array
                if( ! is_array($links) ){
                    $links = $links && is_string($links) ? (array) $links : array();
                }
                // ok to add "translate" link into meta row
                $href = Loco_mvc_AdminRouter::generate('plugin-view', array( 'bundle' => $plugin) );
                $links[] = '<a href="'.esc_attr($href).'">'.esc_html__('Translate','loco-translate').'</a>';
             }
         }
         catch( Exception $e ){
             // $links[] = esc_html( 'Debug: '.$e->getMessage() );
         }
         return $links;
    }


    /**
     * Purge in-memory caches that may be persisted by object caching plugins
     */
    private function purge_wp_cache(){
        global $wp_object_cache;
        if( function_exists('wp_cache_delete') && method_exists($wp_object_cache,'delete') ){
            wp_cache_delete('plugins','loco');
        }
    }


    /**
     * pre_update_option_{$option} filter callback for $option = "active_plugins"
     * @param array active plugins
     * @return array
     */
    public function filter_pre_update_option_active_plugins( $value = null ){
        $this->purge_wp_cache();
        return $value;
    }


    /**
     * pre_update_site_option_{$option} filter callback for $option = "active_sitewide_plugins"
     * @param array active sitewide plugins
     * @return array
     */
    public function filter_pre_update_site_option_active_sitewide_plugins( $value = null ){
        $this->purge_wp_cache();
        return $value;
    }



    /**
     * deactivate_plugin action callback
     *
    public function on_deactivate_plugin( $plugin, $network = false ){
        if( loco_plugin_self() === $plugin ){
            // TODO flush all our transient cache entries
            // "DELETE FROM ___ WHERE `option_name` LIKE '_transient_loco_%' OR `option_name` LIKE '_transient_timeout_loco_%'";
        }
    }*/



    /*public function filter_all( $hook ){
        error_log( $hook, 0 );
    }*/
    
}
