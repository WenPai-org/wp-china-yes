<?php
/*
Plugin Name: Loco Translate
Plugin URI: https://wordpress.org/plugins/loco-translate/
Description: Translate themes and plugins directly in WordPress
Author: Tim Whitlock
Version: 2.4.0
Author URI: https://localise.biz/wordpress/plugin
Text Domain: loco-translate
Domain Path: /languages/
*/

// disallow execution out of context
if( ! function_exists('is_admin') ){
    return;
}


/**
 * Get absolute path to Loco primary plugin file
 * @return string
 */
function loco_plugin_file(){
    return __FILE__;
}


/**
 * Get version of this plugin
 * @return string
 */
function loco_plugin_version(){
    return '2.4.0';
}


/**
 * Get Loco plugin handle, used by WordPress to identify plugin as a relative path
 * @return string probably "loco-translate/loco.php"
 */
function loco_plugin_self(){
    static $handle;
    isset($handle) or $handle = plugin_basename(__FILE__);
    return $handle;
}


/**
 * Get absolute path to plugin root directory
 * @return string __DIR__
 */
function loco_plugin_root(){
    static $root;
    isset($root) or $root = dirname(__FILE__);
    return $root;
}


/**
 * Check whether currently running in debug mode
 * @return bool
 */
function loco_debugging(){
    return apply_filters('loco_debug', WP_DEBUG );
}


/**
 * Whether currently processing an Ajax request
 * @return bool
 */
function loco_doing_ajax(){
    return defined('DOING_AJAX') && DOING_AJAX;
}


/**
 * Evaluate a constant by name
 * @param string
 * @return mixed
 */
function loco_constant( $name ){
    $value = defined($name) ? constant($name) : null;
    // constant values will only be modified in tests
    if( defined('LOCO_TEST') && LOCO_TEST ){
        $value = apply_filters('loco_constant', $value, $name );
        $value = apply_filters('loco_constant_'.$name, $value );
    }
    return $value;
}


/**
 * Runtime inclusion of any file under plugin root
 * @param string PHP file path relative to __DIR__
 * @return mixed return value from included file
 */
function loco_include( $relpath ){
    $path = loco_plugin_root().'/'.$relpath;
    if( ! file_exists($path) ){
        throw new Loco_error_Exception('File not found: '.$path);
    }
    return include $path;
}


/**
 * Require dependant library once only
 * @param string PHP file path relative to ./lib
 * @return void
 */
function loco_require_lib( $path ){
    require_once loco_plugin_root().'/lib/'.$path;
}


/**
 * Check PHP extension required by Loco and load polyfill if needed
 * @param string
 * @return bool
 */
function loco_check_extension( $name ) {
    static $cache = array();
    if ( ! isset( $cache[$name] ) ) {
        if ( extension_loaded($name) ) {
            $cache[ $name ] = true;
        }
        else {
            Loco_error_AdminNotices::warn( sprintf( __('Loco requires the "%s" PHP extension. Ask your hosting provider to install it','loco-translate'), $name ) );
            $class = 'Loco_compat_'.ucfirst($name).'Extension.php';
            $cache[$name] = class_exists($class);
        }
    }
    return $cache[ $name ];
}


/**
 * Class autoloader for Loco classes under src directory.
 * e.g. class "Loco_foo_FooBar" wil be found in "src/foo/FooBar.php"
 * Also does autoload for polyfills under "src/compat" if $name < 20 chars
 * 
 * @internal 
 * @param string
 * @return void
 */
function loco_autoload( $name ){
    if( 'Loco_' === substr($name,0,5) ){
        loco_include( 'src/'.strtr( substr($name,5), '_', '/' ).'.php' );
    }
    else if( strlen($name) < 20 ){
        $path = loco_plugin_root().'/src/compat/'.$name.'.php';
        if( file_exists($path) ){
            require $path;
        }
    }
}

spl_autoload_register( 'loco_autoload', false );


// provide safe directory for custom translations that won't be deleted during auto-updates
if( ! defined('LOCO_LANG_DIR') ){
    define( 'LOCO_LANG_DIR', trailingslashit(loco_constant('WP_LANG_DIR')).'loco' );
}


// text domain loading helper for custom file locations. disable by setting constant empty
if( LOCO_LANG_DIR ){
    new Loco_hooks_LoadHelper;
}


// initialize hooks for admin screens
if( is_admin() ){
    new Loco_hooks_AdminHooks;
}
