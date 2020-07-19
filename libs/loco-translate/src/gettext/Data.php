<?php

loco_require_lib('compiled/gettext.php');

/**
 * Wrapper for array forms of parsed PO data
 */
class Loco_gettext_Data extends LocoPoIterator implements JsonSerializable {

    /**
     * Normalize file extension to internal type
     * @param Loco_fs_File
     * @return string "po", "pot" or "mo"
     * @throws Loco_error_Exception
     */
    public static function ext( Loco_fs_File $file ){
        $ext = rtrim( strtolower( $file->extension() ), '~' );
        if( 'po' === $ext || 'pot' === $ext || 'mo' === $ext ){
            return $ext;
        }
        // translators: Error thrown when attempting to parse a file that is not PO, POT or MO
        throw new Loco_error_Exception( sprintf( __('%s is not a Gettext file'), $file->basename() ) );
    }


    /**
     * @param Loco_fs_File
     * @return Loco_gettext_Data
     */
    public static function load( Loco_fs_File $file ){
        $type = strtoupper( self::ext($file) );
        // catch parse errors so we can inform user of which file is bad
        try {
            if( 'MO' === $type ){
                return self::fromBinary( $file->getContents() );
            }
            else {
                return self::fromSource( $file->getContents() );
            }
        }
        catch( Loco_error_ParseException $e ){
            $path = $file->getRelativePath( loco_constant('WP_CONTENT_DIR') );
            Loco_error_AdminNotices::debug( sprintf('Failed to parse %s as a %s file',$path,$type) );
            throw new Loco_error_ParseException( sprintf('Invalid %s file: %s',$type,basename($path)) );
        }
    }


    /**
     * Like load but just pulls header, saving a full parse. PO only
     * @param Loco_fs_File
     * @return Loco_gettext_Data
     * @throws InvalidArgumentException
     */
    public static function head( Loco_fs_File $file ){
        if( 'mo' === self::ext($file) ){
            throw new InvalidArgumentException('PO only');
        }
        $po = new LocoPoParser( $file->getContents() );
        return new Loco_gettext_Data( $po->parse(1) );
    }


    /**
     * @param string assumed PO source
     * @return Loco_gettext_Data
     */
    public static function fromSource( $src ){
        $p = new LocoPoParser($src);
        return new Loco_gettext_Data( $p->parse() );
    }


    /**
     * @param string assumed MO bytes
     * @return Loco_gettext_Data
     */
    public static function fromBinary( $bin ){
        $p = new LocoMoParser($bin);
        return new Loco_gettext_Data( $p->parse() );
    }


    /**
     * Create a dummy/empty instance with minimum content to be a valid PO file.
     * @return Loco_gettext_Data
     */
    public static function dummy(){
        return new Loco_gettext_Data( array( array('source'=>'','target'=>'Language:') ) );
    }


    /**
     * Ensure PO source is UTF-8. 
     * Required if we want PO code when we're not parsing it. e.g. source view
     * @param string
     * @return string
     */
    public static function ensureUtf8( $src ){
        loco_check_extension('mbstring');
        $src = loco_remove_bom($src,$cs);
        if( ! $cs ){
            // read PO header, requiring partial parse
            try {
                $cs = LocoPoHeaders::fromSource($src)->getCharset();
            }
            catch( Loco_error_ParseException $e ){
                Loco_error_AdminNotices::debug( $e->getMessage() );
            }
            // fall back on detection which will only work for latin1
            if( ! $cs ){
                $cs = mb_detect_encoding($src,array('UTF-8','ISO-8859-1'),true);
            }
        }
        if( $cs && 'UTF-8' !== $cs ){
            $src = mb_convert_encoding($src,'UTF-8',array($cs) );
        }
        return $src;
    }


    /**
     * Compile messages to binary MO format
     * @return string MO file source
     * @throws Loco_error_Exception
     */
    public function msgfmt(){
        if( 2 !== strlen("\xC2\xA3") ){
            throw new Loco_error_Exception('Refusing to compile MO file. Please disable mbstring.func_overload'); // @codeCoverageIgnore
        }
        $mo = new LocoMo( $this, $this->getHeaders() );
        $opts = Loco_data_Settings::get();
        if( $opts->gen_hash ){
            $mo->enableHash();
        }
        if( $opts->use_fuzzy ){
            $mo->useFuzzy();
        }
        return $mo->compile();
    }


    /**
     * Get final UTF-8 string for writing to file
     * @param bool whether to sort output, generally only for extracting strings
     * @return string
     */
    public function msgcat( $sort = false ){
        // set maximum line width, zero or >= 15
        $this->wrap( Loco_data_Settings::get()->po_width );
        // concat with default text sorting if specified
        $po = $this->render( $sort ? array( 'LocoPoIterator', 'compare' ) : null );
        // Prepend byte order mark only if configured
        if( Loco_data_Settings::get()->po_utf8_bom ){
            $po = "\xEF\xBB\xBF".$po;
        }
        return $po;
    }


    /**
     * Split JavaScript messages out of document, based on file reference mapping
     * @return array
     */
    public function splitJs(){
        // TODO take file extension from config
        $messages = $this->splitRefs( array('js'=>'js','jsx'=>'js') );
        return isset($messages['js']) ? $messages['js'] : array();
    }
    


    /**
     * Compile JED flavour JSON
     * @param string text domain for JED metadata
     * @param LocoPoMessage[] pre-compiled messages
     * @return string
     */
    public function jedize( $domain, array $po ){
        $head = $this->getHeaders();
        // start locale_data with JED header
        $data = array( '' => array (
            'domain' => $domain,
            'lang' => $head['language'],
            'plural-forms' => $head['plural-forms'],
        ) );
        /* @var LocoPoMessage $msg */
        foreach( $po as $msg ){
            $data[ $msg->getKey() ] = $msg->getMsgstrs();
        }
        // pretty formatting for debugging
        $json_options = 0;
        if( Loco_data_Settings::get()->jed_pretty ){
            $json_options |= loco_constant('JSON_PRETTY_PRINT') | loco_constant('JSON_UNESCAPED_SLASHES') | loco_constant('JSON_UNESCAPED_UNICODE');
        }
        return json_encode( array (
            'translation-revision-date' => $head['po-revision-date'],
            'generator' => $head['x-generator'],
            'domain' => $domain,
            'locale_data' => array (
                $domain => $data,
            ),
        ), $json_options );
    }


    /**
     * @return array
     */
    public function jsonSerialize(){
        $po = $this->getArrayCopy();
        // exporting headers non-scalar so js doesn't have to parse them
        try {
            $headers = $this->getHeaders();
            if( count($headers) && '' === $po[0]['source'] ){
                $po[0]['target'] = $headers->getArrayCopy();
            }
        }
        // suppress header errors when serializing
        // @codeCoverageIgnoreStart
        catch( Exception $e ){ }
        // @codeCoverageIgnoreEnd
        return $po;
    }


    /**
     * Export to JSON for JavaScript editor
     * @return string
     */
    public function exportJson(){
        return json_encode( $this->jsonSerialize() );
    }


    /**
     * Create a signature for use in comparing source strings between documents
     * @return string
     */
    public function getSourceDigest(){
        $data = $this->getHashes();
        return md5( implode("\1",$data) );
    }

    
    /**
     * @param Loco_Locale
     * @param array custom headers
     * @return Loco_gettext_Data
     */
    public function localize( Loco_Locale $locale, array $custom = null ){
        $date = gmdate('Y-m-d H:i').'+0000'; // <- forcing UCT
        $headers = $this->getHeaders();
        // headers that must always be set if absent
        $defaults = array (
            'Project-Id-Version' => '',
            'Report-Msgid-Bugs-To' => '',
            'POT-Creation-Date' => $date,
        );
        // headers that must always override when localizing
        $required = array (
            'PO-Revision-Date' => $date,
            'Last-Translator' => '',
            'Language-Team' => $locale->getName(),
            'Language' => (string) $locale,
            'Plural-Forms' => $locale->getPluralFormsHeader(),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
            'X-Generator' => 'Loco https://localise.biz/',
            'X-Loco-Version' => sprintf('%s; wp-%s', loco_plugin_version(), $GLOBALS['wp_version'] ),
        );
        // set user's preferred Last-Translator credit if configured
        if( function_exists('get_current_user_id') && get_current_user_id() ){
            $prefs = Loco_data_Preferences::get();
            $credit = (string) $prefs->credit;
            if( '' === $credit ){
                $credit = $prefs->default_credit();
            }
            // filter credit with current user name and email
            $user = wp_get_current_user();
            $credit = apply_filters( 'loco_current_translator', $credit, $user->get('display_name'), $user->get('email') );
            if( '' !== $credit ){
                $required['Last-Translator'] = $credit;
            }
        }
        // only set absent or empty headers from default list
        foreach( $defaults as $key => $value ){
            if( ! $headers[$key] ){
                $headers[$key] = $value;
            }
        }
        // add required headers with custom ones overriding
        if( is_array($custom) ){
            $required = array_merge( $required, $custom );
        }
        foreach( $required as $key => $value ){
            $headers[$key] = $value;
        }
        // avoid non-empty POT placeholders that won't have been set from $defaults
        if( 'PACKAGE VERSION' === $headers['Project-Id-Version'] ){
            $headers['Project-Id-Version'] = '';
        }
        // header message must be un-fuzzied if it was formerly a POT file
        return $this->initPo();
    }


    /**
     * @return Loco_gettext_Data
     */
    public function templatize(){
        $date = gmdate('Y-m-d H:i').'+0000'; // <- forcing UCT
        $headers = $this->getHeaders();
        $required = array (
            'Project-Id-Version' => 'PACKAGE VERSION',
            'Report-Msgid-Bugs-To' => '',
            'POT-Creation-Date' => $date,
            'PO-Revision-Date' => 'YEAR-MO-DA HO:MI+ZONE',
            'Last-Translator' => 'FULL NAME <EMAIL@ADDRESS>',
            'Language-Team' => '',
            'Language' => '',
            'Plural-Forms' => 'nplurals=INTEGER; plural=EXPRESSION;',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
            'X-Generator' => 'Loco https://localise.biz/',
            'X-Loco-Version' => sprintf('%s; wp-%s', loco_plugin_version(), $GLOBALS['wp_version'] ),
        );
        foreach( $required as $key => $value ){
            $headers[$key] = $value;
        }

        return $this->initPot();
    }


    /**
     * Remap proprietary base path when PO file is moving to another location.
     * 
     * @param Loco_fs_File the file that was originally extracted to (POT)
     * @param Loco_fs_File the file that must now target references relative to itself
     * @param string vendor name used in header keys
     * @return bool whether base header was alterered
     */
    public function rebaseHeader( Loco_fs_File $origin, Loco_fs_File $target, $vendor ){
        $base = $target->getParent();
        $head = $this->getHeaders();
        $key = 'X-'.$vendor.'-Basepath';
        if( $key = $head->normalize($key) ){
            $oldRelBase = $head[$key];    
            $oldAbsBase = new Loco_fs_Directory($oldRelBase);
            $oldAbsBase->normalize( $origin->getParent() );
            $newRelBase = $oldAbsBase->getRelativePath($base);
            // new base path is relative to $target location 
            $head[$key] = $newRelBase;
            return true;
        }
        return false;
    }


    /**
     * @param string date format as Gettext states "YEAR-MO-DA HO:MI+ZONE"
     * @return int
     */
    public static function parseDate( $podate ){
        if( method_exists('DateTime', 'createFromFormat') ){
            $objdate = DateTime::createFromFormat('Y-m-d H:iO', $podate);
            if( $objdate instanceof DateTime ){
                return $objdate->getTimestamp();
            }
        }
        return strtotime($podate);
    }

} 
