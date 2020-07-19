<?php
/**
 * 
 */
class Loco_fs_LocaleDirectory extends Loco_fs_Directory {  

    /**
     * Get location identifier which signifies the type if translation storage.
     * 
     * - "plugin": bundled inside a plugin (official/author)
     * - "theme":  bundled inside a theme (official/author)
     * - "wplang": under the global languages directory and probably installed by auto-updates
     * - "custom": Loco protected directory
     * - "other":  anywhere else
     * 
     * @return string 
     */
    public function getTypeId(){
        // paths must be compared with trailing slashes so "/foo" doesn't match "/foo-bar"
        $path = trailingslashit( $this->normalize() );
        
        // anything under Loco's protected directory is our location for custom overrides
        $prefix = trailingslashit( loco_constant('LOCO_LANG_DIR') );
        if( substr($path,0,strlen($prefix) ) === $prefix ){
            return 'custom';
        }

        // standard subdirectories of WP_LANG_DIR are under WordPress auto-update control
        $prefix = trailingslashit( loco_constant('WP_LANG_DIR') );
        if( substr($path,0,strlen($prefix) ) === $prefix ){
            if( $path === $prefix || $path === $prefix.'plugins/' || $path === $prefix.'themes/' ){
                return 'wplang';
            }
        }
        else {
            // anything under a registered theme directory is bundled
            $dirs = Loco_fs_Locations::getThemes();
            if( $dirs->check($path) ){
                return 'theme';
            }
            // anything under a registered plugin directory is bundled
            $dirs = Loco_fs_Locations::getPlugins();
            if( $dirs->check($path) ){
                return 'plugin';
            }
        }
        
        // anything else, which includes subdirectories of WP_LANG_DIR etc..
        return 'other';
    }


    /**
     * Get translated version of getTypeId
     * @param string id
     * @return string
     */
    public function getTypeLabel( $id ){
        switch( $id ){
        case 'theme':
        case 'plugin': 
            // Translators: Refers to bundled plugin or theme translation files - i.e. those supplied by the author
            return _x('Author','File location','loco-translate');
        case 'wplang': 
            // Translators: Refers to system-installed translation files - i.e. those under WP_LANG_DIR  
            return _x('System','File location','loco-translate');
        case 'custom': 
            // Translators: Refers to translation files in Loco's custom/protected directory
            return _x('Custom','File location','loco-translate');
        case 'other':
            // Translators: Refers to translation files in an alternative location that isn't Author, System or Custom.
            return _x('Other','File location','loco-translate');
        }
        
        throw new InvalidArgumentException('Invalid location type: '.$id );
    }        


}