<?php
/**
 * 
 */
abstract class Loco_mvc_AdminController extends Loco_mvc_Controller {
    
    /**
     * @var Loco_mvc_View
     */
    private $view;
    
    /**
     * Debugging timestamp (microseconds)
     * @var float
     */
    private $bench;

    /**
     * Base url to plugin folder for web access
     * @var string
     */
    private $baseurl;

    /**
     * @var string[]
     */
    private $scripts = array();
    

    /**
     * Pre-init call invoked by router
     * @param mixed[]
     * @return Loco_mvc_AdminController
     */    
    final public function _init( array $args ){
        if( loco_debugging() ){
            $this->bench = microtime( true );
        }
        $this->view = new Loco_mvc_View( $args );
        $this->auth();
        
        // check essential extensions on all pages so admin notices are shown
        foreach( array('json','mbstring') as $ext ){
            loco_check_extension($ext);
        }

        // add contextual help tabs to current screen if there are any
        if( $screen = get_current_screen() ){
            try {
                $this->view->cd('/admin/help');
                $tabs = $this->getHelpTabs();
                // always append common help tabs
                $tabs[ __('Help & support','loco-translate') ] = $this->view->render('tab-support');
                // set all tabs and common side bar
                $i = 0;
                foreach( $tabs as $title => $content ){
                    $id = sprintf('loco-help-%u', $i++ );
                    $screen->add_help_tab( compact('id','title','content') );
                }
                $screen->set_help_sidebar( $this->view->render('side-bar') );
                $this->view->cd('/');
            }
            // avoid critical errors rendering non-critical part of page
            catch( Loco_error_Exception $e ){
                $this->view->cd('/');
                Loco_error_AdminNotices::add( $e );
            }
        }
        
        // helper properties for loading static resources
        $this->baseurl = plugins_url( '', loco_plugin_self() );
        
        // add common admin page resources
        $this->enqueueStyle('admin', array('wp-jquery-ui-dialog') );

        // load colour scheme is user has non-default
        $skin = get_user_option('admin_color');
        if( $skin && 'fresh' !== $skin ){
            $this->enqueueStyle( 'skins/'.$skin );
        }
        
        // core minimized admin.js loaded on all pages before any other Loco scripts
        $this->enqueueScript('admin', array('jquery-ui-dialog') );
        
        $this->init();
        return $this;
    }



    /**
     * Post-construct initializer that may be overridden by child classes
     * @return void
     */
    public function init(){
        
    }


    /**
     * "admin_title" filter, modifies HTML document title if we've set one
     */
    public function filter_admin_title( $admin_title, $title ){
        if( $view_title = $this->get('title') ){
            $admin_title = $view_title.' &lsaquo; '.$admin_title;
        }
        return $admin_title;
    }


    /**
     * "admin_footer_text" filter, modifies admin footer only on Loco pages
     */
    public function filter_admin_footer_text(){
        $url = apply_filters('loco_external', 'https://localise.biz/');
        return '<span id="loco-credit">'.sprintf( '<span>%s</span> <a href="%s" target="_blank">Loco</a>', esc_html(__('Loco Translate is powered by','loco-translate')), esc_url($url) ).'</span>';
    }

    
    /**
     * "update_footer" filter, prints Loco version number in admin footer
     */
    public function filter_update_footer( $text ){
        $html = sprintf( '<span>v%s</span>', loco_plugin_version() );
        if( $this->bench && ( $info = $this->get('_debug') ) ){
            $html .= sprintf('<span>%ss</span>', number_format_i18n($info['time'],2) );
        }
        return $html;
    }


    /**
     * "loco_external" filter callback, campaignizes external links
     */
    public function filter_loco_external( $url ){
        $u = parse_url( $url );
        if( isset($u['host']) && 'localise.biz' === $u['host'] ){
            $query = http_build_query( array( 'utm_medium' => 'plugin', 'utm_campaign' => 'wp', 'utm_source' => 'admin', 'utm_content' => $this->get('_route') ), null, '&' );
            $url = 'https://localise.biz'.$u['path'];
            if( isset($u['query']) ){
                $url .= '?'. $u['query'].'&'.$query;
            }
            else {
                $url .= '?'.$query;
            }
            if( isset($u['fragment']) ){
                $url .= '#'.$u['fragment'];
            }
        }
        return $url;
    }


    /**
     * All admin screens must define help tabs, even if they return empty
     * @return array
     */
    public function getHelpTabs(){
        return array();
    }


    /**
     * {@inheritdoc}
     */
    public function get( $prop ){
        return $this->view->__get($prop);
    }


    /**
     * {@inheritdoc}
     */
    public function set( $prop, $value ){
        $this->view->set( $prop, $value );
        return $this;
    }



    /**
     * Render template for echoing into admin screen
     * @param string template name
     * @param array template arguments
     * @return string
     */
    public function view( $tpl, array $args = array() ){
        /*if( ! $this->baseurl ){
            throw new Loco_error_Debug('Did you mean to call $this->viewSnippet('.json_encode($tpl,JSON_UNESCAPED_SLASHES).') in '.get_class($this).'?');
        }*/
        $view = $this->view;
        foreach( $args as $prop => $value ){
            $view->set( $prop, $value );
        }
        // ensure JavaScript config always present
        if( $view->has('js') ){
            $jsConf = $view->get('js');
            if( ! $jsConf instanceof Loco_mvc_ViewParams ){
                throw new InvalidArgumentException('Bad "js" view parameter');
            }
        }
        else {
            $jsConf = new Loco_mvc_ViewParams;
            $view->set( 'js', $jsConf );
        }
        // deregister legacy scripts in case another plugin tried to hijack them
        wp_deregister_script('loco-js-editor');
        wp_deregister_script('loco-js-min-admin');
        // TODO perhaps do some kind of in-script check validation check
        // $jsConf->offsetSet('$v',loco_plugin_version() );
        // localize script if translations in memory
        if( is_textdomain_loaded('loco-translate') ){
            $strings = new Loco_js_Strings;
            $jsConf['wpl10n'] = $strings->compile();
            $strings->unhook();
            unset( $strings );
            // add currently loaded locale for passing plural equation into js.
            // note that plural rules come from our data, because MO is not trusted.
            $tag = apply_filters( 'plugin_locale', get_locale(), 'loco-translate' );
            $jsConf['wplang'] = Loco_Locale::parse($tag);
        }
        // take benchmark for debugger to be rendered in footer
        if( $this->bench ){
            $this->set('_debug', new Loco_mvc_ViewParams( array( 
                'time' => microtime(true) - $this->bench,
            ) ) );
            // additional debugging info when enabled
            $jsConf['WP_DEBUG'] = true;
        }
        return $view->render( $tpl );
    }


    /**
     * Shortcut to render template without full page arguments as per view
     * @param string
     * @return string
     */
    public function viewSnippet( $tpl ){
        return $this->view->render( $tpl );
    }


    /**
     * Add CSS to head
     * @param string stem name of file, e.g "editor"
     * @param string[] dependencies of this stylesheet
     * @return Loco_mvc_Controller
     */
    public function enqueueStyle( $name, array $deps = array() ){
        $base = $this->baseurl;
        if( ! $base ){
            throw new Loco_error_Exception('Too early to enqueueStyle('.var_export($name,1).')');
        }
        $id = 'loco-translate-css-'.strtr($name,'/','-');
        // css always minified. sass in build env only
        $href = $base.'/pub/css/'.$name.'.css';
        $vers = apply_filters( 'loco_static_version', loco_plugin_version(), $href );
        wp_enqueue_style( $id, $href, $deps, $vers, 'all' );
        return $this;
    }


    /**
     * Add JavaScript to footer
     * @param string stem name of file, e.g "editor"
     * @param string[] dependencies of this script
     * @return Loco_mvc_Controller
     */
    public function enqueueScript( $name, array $deps = array() ){
        $base = $this->baseurl;
        if( ! $base ){
            throw new Loco_error_Exception('Too early to enqueueScript('.var_export($name,1).')');
        }
        // use minimized javascript file. hook into script_loader_src to point at development source
        $href = $base.'/pub/js/min/'.$name.'.js';
        $vers = apply_filters( 'loco_static_version', loco_plugin_version(), $href );
        $id = 'loco-translate-js-'.strtr($name,'/','-');
        wp_enqueue_script( $id, $href, $deps, $vers, true );
        $this->scripts[$id] = $href;
        return $this;
    }


    /**
     * @internal 
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function filter_script_loader_tag( $tag, $handle, $src ){
        if( array_key_exists($handle,$this->scripts) ) {
            $base = $this->baseurl.'/pub/js/';
            $snip = strlen($base);
            if( substr($src,0,$snip) !== $base || false !== strpos($src,'..') ){
                Loco_error_AdminNotices::warn('Another plugin attempted to modify scripts on this page. If you experience problems, please let us know.');
                Loco_error_AdminNotices::debug( $src.' does not belong to this plugin. It could be a hack attempt' );
                // this will lose any legitimate filters we've added ourselves. (likely only under local dev)
                $tag = str_replace($src,$this->scripts[$handle],$tag);
            }
        }
        return $tag;
    }
    

}