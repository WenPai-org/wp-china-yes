<?php
/**
 * Buffers translations requested via __, _x, _n and _nx for exporting in a raw form.
 * This is used for JavaScript localization because wp_localize_script is weak.
 */
class Loco_hooks_TranslateBuffer extends Loco_hooks_Hookable {
    
    /**
     * Temporary buffer of raw translation lookupkeys
     * @var array
     */
    private $buffer = array();
    
    /**
     * `gettext` filter callback
     */
    public function filter_gettext( $msgstr, $msgid, $domain ){
        $this->buffer[$domain][$msgid] = null;
        return $msgstr;
    }    


    /**
     * `gettext_with_context` filter callback
     */
    public function filter_gettext_with_context( $msgstr, $msgid, $msgctxt, $domain ){
        $this->buffer[$domain][$msgctxt."\x04".$msgid] = null;
        return $msgstr;
    }


    /**
     * `ngettext` filter callback
     */
    public function filter_ngettext( $msgstr, $msgid, $msgid_plural, $number, $domain ){
        $this->buffer[$domain][$msgid] = null;
        return $msgstr;
    }


    /**
     * `ngettext_with_context` filter callback
     */
    function filter_ngettext_with_context( $msgstr, $msgid, $msgid_plural, $number, $msgctxt, $domain ){
        $this->buffer[$domain][$msgctxt."\x04".$msgid] = null;
        return $msgstr;
    }



    /**
     * Export all captured translations in a raw form and reset buffer
     * @param string the specific domain listened for
     * @return array
     */
    public function flush( $domain ){
        $export = array();
        if( isset($this->buffer[$domain]) ){
            // what we captures was just a unique namespace
            $captured = $this->buffer[$domain];
            unset($this->buffer[$domain]);
            // process raw data for all that actually exist
            // this survives on WordPress internals not changing :-/
            $loaded = get_translations_for_domain($domain);
            if( $loaded instanceof Translations && is_array($loaded->entries) ){
                $entries = array_intersect_key( $loaded->entries, $captured );
                /* @var $entry Translation_Entry */
                foreach( $entries as $key => $entry ){
                    $export[$key] = $entry->translations;
                }
            }
        }

        return $export;
    }


}
