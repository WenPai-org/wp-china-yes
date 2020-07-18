<?php
/**
 * 
 */
class Loco_api_WordPressTranslations {

    /**
     * Cache of whether network access is allowed
     * @var bool
     */
    private $enabled;

    /**
     * Cache of core locale objects
     * @var array
     */
    private $locales;

    /**
     * Cache of data returned from get_available_languages (not cached by WP)
     * @var array
     */    
    private $installed;

    /**
     * Hash map of installed languages indexed by tag
     * @var array
     */
    private $installed_hash;


    /**
     * Wrap wp_get_available_translations
     * @return array
     */
    private function wp_get_available_translations(){
        if( ! function_exists('wp_get_available_translations') ){
            require_once ABSPATH.'wp-admin/includes/translation-install.php';
        }
        // WordPress will raise Warning if offline, and will cache result otherwise.
        return wp_get_available_translations();
    }


    /**
     * Get fully fledged locale objects from available core translation data
     * @return array<Loco_Locale>
     */
    public function getAvailableCore(){
        $locales = $this->locales;
        if( is_null($locales) ){
            $locales = array();
            // get official locales from API if we have network
            if( $cached = $this->wp_get_available_translations() ){
                $english_name = 'english_name';
                $native_name = 'native_name';
            }
            // else fall back to bundled data cached
            else {
                $english_name = 0;
                $native_name = 1;
                $cached = Loco_data_CompiledData::get('locales');
                // debug so we can see on front end that data was offline
                // $locales['en-debug'] = ( new Loco_Locale('en','','debug') )->setName('OFFLINE DATA');
            }
            foreach( $cached as $tag => $raw ){
                $locale = Loco_Locale::parse($tag);
                if( $locale->isValid() ){
                    $locale->setName( $raw[$english_name], $raw[$native_name] );
                    $locales[ (string) $tag ] = $locale;
                }
                /* Skip invalid language tags, e.g. "pt_PT_ao90" should be "pt_PT_ao1990"
                 * No point fixing invalid tags, because core translation files won't match. 
                else {
                    Loco_error_AdminNotices::debug( sprintf('Invalid locale: %s', $tag) );
                }*/
            }
            $this->locales = $locales;
        }
        return $locales;
    }



    /**
     * Wrap get_available_languages
     * @return array
     */
    public function getInstalledCore(){
        // wp-includes/l10n.php should always be included at runtime
        if( ! is_array($this->installed) ){
            $this->installed = get_available_languages();
            // en_US is implicitly installed
            if( ! in_array('en_US',$this->installed) ){
                array_unshift( $this->installed, 'en_US' );
            }
        }
        return $this->installed;
    }


    /**
     * @return array
     */
    private function getInstalledHash(){
        if( ! is_array($this->installed_hash) ){
            $this->installed_hash = array_flip( $this->getInstalledCore() );
        }
        return $this->installed_hash;
    }


    /**
     * Check if a given locale is installed
     * @return bool
     */
    public function isInstalled( $locale ){
        return array_key_exists( (string) $locale, $this->getInstalledHash() );
    }


    /**
     * Get WordPress locale data by strictly well-formed language tag
     * @return Loco_Locale
     */
    public function getLocale( $tag ){
        $all = $this->getAvailableCore();
        return isset($all[$tag]) ? $all[$tag] : null;
    }


    /**
     * Check whether remote API may be disabled for whatever reason, usually debugging.
     * @return bool
     */
    public function hasNetwork(){
        if( is_null($this->enabled) ){
            $this->enabled = (bool) apply_filters('loco_allow_remote', true );
        }
        return $this->enabled;
    }
    
}

