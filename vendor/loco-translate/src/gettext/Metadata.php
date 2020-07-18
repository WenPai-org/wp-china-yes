<?php

loco_require_lib('compiled/gettext.php');

/**
 * Holds metadata about a PO file, cached as Transient
 */
class Loco_gettext_Metadata extends Loco_data_Transient {

    /**
     * Generate abbreviated stats from parsed array data  
     * @param array in form returned from parser, including header message
     * @return array in form ['t' => total, 'p' => progress, 'f' => fuzzy ];
     */
    public static function stats( array $po ){
        $t = $p = $f = 0;
        /* @var $r array */
        foreach( $po as $i => $r ){
            // skip header
            if( 0 === $i && empty($r['source']) && empty($r['context']) ){
                continue;
            }
            // plural form
            // TODO how should plural forms affect stats? should all forms be complete before 100% can be achieved? should offsets add to total??
            if( isset($r['parent']) && is_int($r['parent']) ){
                continue;
            }
            // singular form
            $t++;
            if( '' !== $r['target'] ){
                $p++;
                if( isset($r['flag']) /*&& LOCO_FLAG_FUZZY === $r['flag']*/ ){
                    $f++;
                }
            }
        }
        return compact('t','p','f');        
    }


    /**
     * {@inheritdoc}
     */
    public function getKey(){
        return 'po_'.md5( $this['rpath'] );
    }


    /**
     * Load metadata from file, using cache if enabled.
     * Note that this does not throw exception, check "valid" key
     * @param Loco_fs_File
     * @param bool
     * @return Loco_gettext_Metadata
     */
    public static function load( Loco_fs_File $po, $nocache = false ){
        $bytes = $po->size();
        $mtime = $po->modified();
        // quick construct of new meta object. enough to query and validate cache 
        $meta = new Loco_gettext_Metadata( array(
            'rpath' => $po->getRelativePath( loco_constant('WP_CONTENT_DIR') ),
        ) );
        // pull from cache if exists and has not been modified
        if( $nocache || ! $meta->fetch() || $bytes !== $meta['bytes'] || $mtime !== $meta['mtime'] ){
            // not available from cache, or cache is invalidated
            $meta['bytes'] = $bytes;
            $meta['mtime'] = $mtime;
            // parse what is hopefully a PO file to get stats
            try {
                $data = Loco_gettext_Data::load($po)->getArrayCopy();
                $meta['valid'] = true;
                $meta['stats'] = self::stats($data);
            }
            catch( Exception $e ){
                $meta['valid'] = false;
                $meta['error'] = $e->getMessage();
            }
        }
        // show cached debug notice as if file was being parsed 
        else if( $meta->offsetExists('error') ){
            Loco_error_AdminNotices::debug($meta['error'].': '.$meta['rpath']);
        }
        // persist on shutdown with a useful TTL and keepalive
        // Maximum lifespan: 10 days. Refreshed if accessed a day after being cached.
        $meta->setLifespan(864000)->keepAlive(86400)->persistLazily();
        
        return $meta;
    }


    /**
     * Construct metadata from previously parsed PO data
     * @param Loco_fs_File
     * @param Loco_gettext_Data
     * @return Loco_gettext_Metadata 
     */
    public static function create( Loco_fs_File $file, Loco_gettext_Data $data ){
        return new Loco_gettext_Metadata( array (
            'valid' => true,
            'bytes' => $file->size(),
            'mtime' => $file->modified(),
            'stats' => self::stats( $data->getArrayCopy() ),
        ) );
    }


    /**
     * Get progress stats as simple array with keys, t=total, p=progress, f:flagged.
     * Note that untranslated strings are never flagged, hence "f" includes all in "p"  
     * @return array in form ['t' => total, 'p' => progress, 'f' => fuzzy ];
     */
    public function getStats(){
        if( isset($this['stats']) ){
            return $this['stats'];
        }
        // fallback to empty stats
        return array( 't' => 0, 'p' => 0, 'f' => 0 );
    }


    /**
     * Get total number of messages, not including header and excluding plural forms
     * @return int
     */
    public function getTotal(){
        $stats = $this->getStats();
        return $stats['t'];
    }


    /**
     * Get number of fuzzy messages, not including header
     * @return int
     */
    public function countFuzzy(){
        $stats = $this->getStats();
        return $stats['f'];
    }


    /**
     * Get progress as a string percentage (minus % symbol)
     * @return string
     */
    public function getPercent(){
        $stats = $this->getStats();
        $n = max( 0, $stats['p'] - $stats['f'] );
        $t = max( $n, $stats['t'] );
        return loco_string_percent( $n, $t );
    }


    /**
     * Get number of strings either untranslated or fuzzy.
     * @return int
     */
    public function countIncomplete(){
        $stats = $this->getStats();
        return max( 0,  $stats['t'] - ( $stats['p'] - $stats['f'] ) );
    }


    /**
     * Get number of strings completely untranslated (excludes fuzzy).
     * @return int
     */
    public function countUntranslated(){
        $stats = $this->getStats();
        return max( 0,  $stats['t'] - $stats['p'] );
    }


    /**
     * Echo progress bar using compiled function
     * @return void
     */
    public function printProgress(){
        $stats = $this->getStats();
        $flagged = $stats['f'];
        $translated = $stats['p'];
        $untranslated = $stats['t'] - $translated;
        
        loco_print_progress( $translated, $untranslated, $flagged );
    }



    /**
     * Get wordy summary of total strings
     */
    public function getTotalSummary(){
        $total = $this->getTotal();
        return sprintf( _n('1 string','%s strings',$total,'loco-translate'), number_format($total) );
    }



    /**
     * Get wordy summary including translation stats
     */
    public function getProgressSummary(){
        $extra = array();
        $stext = sprintf( __('%s%% translated','loco-translate'), $this->getPercent() ).', '.$this->getTotalSummary();
        if( $num = $this->countFuzzy() ){
            $extra[] = sprintf( __('%s fuzzy','loco-translate'), number_format($num) );
        }
        if( $num = $this->countUntranslated() ){
            $extra[] = sprintf( __('%s untranslated','loco-translate'), number_format($num) );
        }
        if( $extra ){
            $stext .= ' ('.implode(', ', $extra).')';
        }
        return $stext;
    }



    public function getPath( $absolute ){
        $path = $this['rpath'];
        if( $absolute && ! Loco_fs_File::abs($path) ){
            $path = trailingslashit( loco_constant('WP_CONTENT_DIR') ).$path;
        }
        return $path;
    }

}
