<?php
/**
 * Handles execution and rendering of HTML admin pages.
 */
class Loco_mvc_AdminRouter extends Loco_hooks_Hookable {
    
    /**
     * Current admin page controller
     * @var Loco_mvc_AdminController
     */
    private $ctrl;


    /**
     * admin_menu action callback
     */
    public function on_admin_menu() {

        // lowest capability required to see menu items is "loco_admin"
        // currently also the highest (and only) capability
        $cap = 'loco_admin';
        $user = wp_get_current_user();
        $super = is_super_admin( $user->ID );
        
        // Ensure Loco permissions are set up for the first time, or nobody will have access at all
        if( ! get_role('translator') || ( $super && ! is_multisite() && ! $user->has_cap($cap) ) ){
            Loco_data_Permissions::init();
            $user->get_role_caps(); // <- rebuild
        }

        // rendering hook for all menu items
        $render = array( $this, 'renderPage' );
        
        // main loco pages, hooking only if has permission
        if( $user->has_cap($cap) ){

            $label = __('Loco Translate','loco-translate');
            // translators: Page title for plugin home screen
            $title = __('Loco, Translation Management','loco-translate');
            add_menu_page( $title, $label, $cap, 'loco', $render, 'dashicons-translation' );
            // alternative label for first menu item which gets repeated from top level 
            add_submenu_page( 'loco', $title, __('Home','loco-translate'), $cap, 'loco', $render );

            $label = __('Themes','loco-translate');
            // translators: Page title for theme translations
            $title = __('Theme translations &lsaquo; Loco','loco-translate');
            add_submenu_page( 'loco', $title, $label, $cap, 'loco-theme', $render );

            $label = __('Plugins', 'loco-translate');
            // translators: Page title for plugin translations
            $title = __('Plugin translations &lsaquo; Loco','loco-translate');
            add_submenu_page( 'loco', $title, $label, $cap, 'loco-plugin', $render );

            $label = __('WordPress', 'loco-translate');
            // translators: Page title for core WordPress translations
            $title = __('Core translations &lsaquo; Loco', 'loco-translate');
            add_submenu_page( 'loco', $title, $label, $cap, 'loco-core', $render );

            $label = __('Languages', 'loco-translate');
            // translators: Page title for installed languages page
            $title = __('Languages &lsaquo; Loco', 'loco-translate');
            add_submenu_page( 'loco', $title, $label, $cap, 'loco-lang', $render );
            
            // settings page only for users with manage_options permission in addition to Loco access:
            if( $user->has_cap('manage_options') ){
                $title = __('Plugin settings','loco-translate');
                add_submenu_page( 'loco', $title, __('Settings','loco-translate'), 'manage_options', 'loco-config', $render );
            }
            // but all users need access to user preferences which require standard Loco access permission
            else {
                $title = __('User options','loco-translate');
                add_submenu_page( 'loco', $title, __('Settings','loco-translate'), $cap, 'loco-config-user', $render );
            }
        }
    }


    /**
     * Early hook as soon as we know what screen will be rendered
     * @param WP_Screen
     * @return void
     */
    public function on_current_screen( WP_Screen $screen ){
        $action = isset($_GET['action']) ? $_GET['action'] : null;
        $this->initPage( $screen, $action );
    }


    /**
     * Instantiate admin page controller from current screen.
     * This is called early (before renderPage) so controller can listen on other hooks.
     * 
     * @param WP_Screen
     * @param string 
     * @return Loco_mvc_AdminController|null
     */
    public function initPage( WP_Screen $screen, $action = '' ){
        $class = null;
        $args = array ();
        // suppress error display when establishing Loco page
        $page = self::screenToPage($screen);
        if( is_string($page) ){
            $class = self::pageToClass( $page, $action, $args );
        }
        if( is_null($class) ){
            $this->ctrl = null;
            return null;
        }
        // class should exist, so throw fatal if it doesn't
        $this->ctrl = new $class;
        if( ! $this->ctrl instanceof Loco_mvc_AdminController ){
            throw new Exception( $class.' must inherit Loco_mvc_AdminController');
        }
        // transfer flash messages from session to admin notice buffer
        try {
            $session = Loco_data_Session::get();
            while( $message = $session->flash('success') ){
                Loco_error_AdminNotices::success( $message );
            }
        }
        catch( Exception $e ){
            Loco_error_AdminNotices::debug( $e->getMessage() );
        }
        // catch errors during controller setup
        try {
            $this->ctrl->_init( $_GET + $args );
            do_action('loco_admin_init', $this->ctrl );
        }
        catch( Loco_error_Exception $e ){
            $this->ctrl = new Loco_admin_ErrorController;
            // can't afford an error during an error
            try {
                $this->ctrl->_init( array( 'error' => $e ) );
            }
            catch( Exception $_e ){
                Loco_error_AdminNotices::debug( $_e->getMessage() );
                Loco_error_AdminNotices::add($e);
            }
        }

        return $this->ctrl;
    }


    /**
     * Convert WordPress internal WPScreen $id into route prefix for an admin page controller
     * @param WP_Screen
     * @return string|null
     */
    private static function screenToPage( WP_Screen $screen ){
        // Hooked menu slug is either "toplevel_page_loco" or "{title}_page_loco-{page}"
        // Sanitized {title} prefix is not reliable as it may be localized. instead just checking for "_page_loco"
        // TODO is there a safer WordPress way to resolve this? 
        $id = $screen->id;
        $start = strpos($id,'_page_loco');
        // not one of our pages if token not found
        if( is_int($start) ){
            $page = substr( $id, $start+11 ) or $page = '';
            return $page;
        }
        return null;
    }


    /**
     * Get unvalidated controller class for given route parameters
     * Abstracted from initPage so we can validate routes in self::generate
     * @param string
     * @param string
     * @param array reference
     * @return string|null
     */
    private static function pageToClass( $page, $action, array &$args ){
        $routes = array (
            '' => 'Root',
            'debug' => 'Debug',
            // site-wide plugin configurations
            'config' => 'config_Settings',
            'config-apis' => 'config_Apis',
            'config-user' => 'config_Prefs',
            'config-debug' => 'config_Debug',
            'config-version' => 'config_Version',
            // bundle type listings
            'theme'  => 'list_Themes',
            'plugin' => 'list_Plugins',
            'core'   => 'list_Core',
            'lang'   => 'list_Locales',
            // bundle level views
            '{type}-view' => 'bundle_View',
            '{type}-conf' => 'bundle_Conf',
            '{type}-setup' => 'bundle_Setup',
            '{type}-debug' => 'bundle_Debug',
            'lang-view' => 'bundle_Locale',
            // file initialization
            '{type}-msginit'   => 'init_InitPo',
            '{type}-xgettext'  => 'init_InitPot',
            // file resource views
            '{type}-file-view' => 'file_View',
            '{type}-file-edit' => 'file_Edit',
            '{type}-file-info' => 'file_Info',
            '{type}-file-diff' => 'file_Diff',
            '{type}-file-move' => 'file_Move',
            '{type}-file-delete' => 'file_Delete',
            // test routes that don't actually exist
            'test-no-class' => 'test_NonExistantClass',
        );
        if( ! $page ){
            $page = $action;
        }
        else if( $action ){
            $page .= '-'. $action;
        }
        $args['_route'] = $page;
        // tokenize path arguments
        if( preg_match('/^(plugin|theme|core)-/', $page, $r ) ){
            $args['type'] = $r[1];
            $page = substr_replace( $page, '{type}', 0, strlen($r[1]) );
        }
        if( isset($routes[$page]) ){
            return 'Loco_admin_'.$routes[$page].'Controller';
        }
        // debug routing failures:
        // throw new Exception( sprintf('Failed to get page class from $page=%s',$page) );
        return null;
    }



    /**
     * Main entry point for admin menu callback, establishes page and hands off to controller
     * @return void
     */
    public function renderPage(){
        try {
            // show deferred failure from initPage
            if( ! $this->ctrl ){
                throw new Loco_error_Exception( __('Page not found','loco-translate') );
            }
            // display loco admin page
            echo $this->ctrl->render();
        }
        catch( Exception $e ){
            $ctrl = new Loco_admin_ErrorController;
            try {
                $ctrl->_init( array() );
            }
            catch( Exception $_e ){
                // avoid errors during error rendering
                Loco_error_AdminNotices::debug( $_e->getMessage() );
            }
            echo $ctrl->renderError($e);
        }
        // ensure session always shutdown cleanly after render
        Loco_data_Session::close();
        do_action('loco_admin_shutdown');
    }


    /**
     * Generate a routable link to Loco admin page
     * @param string
     * @param array
     * @return string
     */
    public static function generate( $route, array $args = array() ){
        $url = null;
        $page = null;
        $action = null;
        // empty action targets plugin root
        if( ! $route || 'loco' === $route ){
            $page = 'loco';
        }
        // support direct usage of page hooks
        else if( 'loco-' === substr($route,0,5) && menu_page_url($route,false) ){
            $page = $route;
        }
        // else split action into admin page (e.g. "loco-themes") and sub-action (e.g. "view-theme")
        else {
            $page = 'loco';
            $path = explode( '-', $route );
            if( $sub = array_shift($path) ){
                $page .= '-'.$sub;
                if( $path ){
                    $action = implode('-',$path);
                }
            }
        }
        // sanitize extended route in debug mode only. useful in tests
        if( loco_debugging() ){
            $tmp = array();
            $class = self::pageToClass( (string) substr($page,5), $action, $tmp );
            if( ! $class ){
                throw new UnexpectedValueException( sprintf('Invalid admin route: %s', json_encode($route) ) );
            }
            else {
                class_exists( $class, true ); // <- autoloader will throw if not class found
            }
        }
        // if url found, it should contain the page
        if( $url ){
            unset( $args['page'] );
        }
        // else start with base URL
        else {
            $url = admin_url('admin.php');
            $args['page'] = $page;
        }
        // add action if found
        if( $action ){
            $args['action'] = $action;
        }
        // else ensure not set in args, as it's reserved
        else {
            unset( $args['action'] );
        }
        // append all arguments to base URL        
        if( $query = http_build_query($args,null,'&') ){
            $sep = false === strpos($url, '?') ? '?' : '&';
            $url .= $sep.$query;
        }
        return $url;
    }

}