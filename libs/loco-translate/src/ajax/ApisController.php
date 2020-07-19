<?php
/**
 * Ajax "apis" route, for handing off Ajax requests to hooked API integrations.
 */
class Loco_ajax_ApisController extends Loco_mvc_AjaxController {

    /**
     * {@inheritdoc}
     */
    public function render(){
        $post = $this->validate();
        
        // Fire an event so translation apis can register their hooks as lazily as possible
        do_action('loco_api_ajax');
        
        // API client id should be posted
        $hook = (string) $post->hook;

        // API client must be hooked in using loco_api_providers filter
        // this normally filters on Loco_api_Providers::export() but should do the same with an empty array.
        $config = null;
        foreach( apply_filters('loco_api_providers', array() ) as $candidate ){
            if( is_array($candidate) && array_key_exists('id',$candidate) && $candidate['id'] === $hook ){
                $config = $candidate;
                break;
            }
        }
        if( is_null($config) ){
            throw new Loco_error_Exception('API not registered: '.$hook );
        }
        
        // Get input texts to translate via registered hook. shouldn't be posted if empty.
        $sources = $post->sources;
        if( ! is_array($sources) || ! $sources ){
            throw new Loco_error_Exception('Empty sources posted to '.$hook.' hook');
        }
        
        // The front end sends translations detected as HTML separately. This is to support common external apis.
        // $isHtml = 'html' === $post->type;
        
        // We need a locale too, which should be valid as it's the same one loaded into the front end.
        $locale = Loco_Locale::parse( (string) $post->locale );
        if( ! $locale->isValid() ){
            throw new Loco_error_Exception('Invalid locale');
        }

        // Check if hook is registered, else sources will be returned as-is
        $action = 'loco_api_translate_'.$hook;
        if( ! has_filter($action) ){
            throw new Loco_error_Exception('API not hooked. Use `add_filter('.var_export($action,1).',...)`');
        }

        // This is effectively a filter whereby the returned array should be a translation of the input array
        // TODO might be useful for translation hooks to know the PO file this comes from 
        $targets = apply_filters( $action, $sources, $locale, $config );
        if( count($targets) !== count($sources) ){
            Loco_error_AdminNotices::warn('Number of translations does not match number of source strings');
        }
    
        // Response data doesn't need anything except the translations
        $this->set('targets',$targets);

        return parent::render();
    }


}