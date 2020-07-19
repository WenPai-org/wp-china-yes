<?php
/**
 * Class containing reasons for total incompatibility with current WordPress environment.
 * It won't be loaded unless total failure occurs
 * 
 * @codeCoverageIgnore
 */
abstract class Loco_compat_Failure {

    /**
     * "admin_notices" callback, renders failure notice if plugin failed to start up admin hooks.
     * If this is hooked and not unhooked then auto-hooks using annotations have failed.
     */
    public static function print_hook_failure(){
        $texts = array( 'Loco Translate failed to start up' );
        /*/ Hooks currently not using annotatons (would be if we enabled @priority tag)
        if( ini_get('opcache.enable') && ( ! ini_get('opcache.save_comments') || ! ini_get('opcache.load_comments') ) ){
            $texts[] = 'Try configuring opcache to preserve comments';
        }*/
        echo '<div class="notice error"><p><strong>Error:</strong> '.implode('. ',$texts).'</p></div>';
    }

}
