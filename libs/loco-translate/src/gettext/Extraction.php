<?php

loco_require_lib('compiled/gettext.php');

/**
 * String extraction from source code.
 */
class Loco_gettext_Extraction {

    /**
     * @var Loco_package_Bundle
     */    
    private $bundle;

    /**
     * @var LocoExtracted
     */
    private $extracted;

    /**
     * Extra strings to be pushed into domains
     * @var array
     */
    private $extras;

    /**
     * List of files skipped due to memory limit
     * @var Loco_fs_FileList
     */
    private $skipped;

    /**
     * Size in bytes of largest file encountered
     * @var int
     */
    private $maxbytes = 0;


    /**
     * Initialize extractor for a given bundle
     * @param Loco_package_Bundle
     */
    public function __construct( Loco_package_Bundle $bundle ){
        loco_check_extension('ctype');
        loco_check_extension('mbstring');
        if( ! loco_check_extension('tokenizer') ){
            throw new Loco_error_Exception('String extraction not available without required extension');
        }
        $this->bundle = $bundle;
        $this->extracted = new LocoExtracted;
        $this->extracted->setDomain('default');
        $this->extras = array();
        if( $default = $bundle->getDefaultProject() ){
            $domain = (string) $default->getDomain();
            // wildcard stands in for empty text domain, meaning unspecified or dynamic domains will be included.
            // note that strings intended to be in "default" domain must specify explicitly, or be included here too.
            if( '*' === $domain ){
                $domain = '';
                $this->extracted->setDomain('');
            }
            // pull bundle's default metadata. these are translations that may not be encountered in files
            $extras = array();
            $header = $bundle->getHeaderInfo();
            foreach( $bundle->getMetaTranslatable() as $prop => $notes ){
                if( $source = $header->__get($prop) ){
                    if( is_string($source) ){
                        $extras[] = array( $source, $notes );
                    }
                }
            }
            if( $extras ){
                $this->extras[$domain] = $extras;
            }
        }
    }


    /**
     * @param Loco_package_Project
     * @return Loco_gettext_Extraction
     */
    public function addProject( Loco_package_Project $project ){
        $base = $this->bundle->getDirectoryPath();
        // skip files larger than configured maximum
        $opts = Loco_data_Settings::get();
        $max = wp_convert_hr_to_bytes( $opts->max_php_size );
        // *attempt* to raise memory limit to WP_MAX_MEMORY_LIMIT
        if( function_exists('wp_raise_memory_limit') ){
            wp_raise_memory_limit('loco');
        }
        /* @var $file Loco_fs_File */
        foreach( $project->findSourceFiles() as $file ){
            $type = $opts->ext2type( $file->extension() );
            $extr = loco_wp_extractor($type);
            if( 'js' !== $type ) {
                // skip large files for PHP, because token_get_all is hungry
                $size = $file->size();
                $this->maxbytes = max( $this->maxbytes, $size );
                if( $size > $max ){
                    $list = $this->skipped or $list = ( $this->skipped = new Loco_fs_FileList() );
                    $list->add( $file );
                    continue;
                }
                // extract headers from theme PHP files in
                if( $project->getBundle()->isTheme() ){
                    $extr->headerize( array (
                        'Template Name' => 'Name of the template',
                    ), (string) $project->getDomain() );
                }
            }
            $this->extracted->extractSource( $extr, $file->getContents(), $file->getRelativePath( $base ) );
        }
        return $this;
    }


    /**
     * Add metadata strings deferred from construction. Note this will alter domain counts
     * @return Loco_gettext_Extraction
     */
    public function includeMeta(){
        foreach( $this->extras as $domain => $extras ){
            foreach( $extras as $args ){
                $this->extracted->pushMeta( $args[0], $args[1], $domain );
            }
        }
        $this->extras = array();
        return $this;
    }


    /**
     * Get number of unique strings across all domains extracted (excluding additional metadata)
     * @return array { default: x, myDomain: y }
     */
    public function getDomainCounts(){
        return $this->extracted->getDomainCounts();
    }


    /**
     * Pull extracted data into POT, filtering out any unwanted domains 
     * @param string
     * @return Loco_gettext_Data
     */
    public function getTemplate( $domain ){
        $data = new Loco_gettext_Data( $this->extracted->filter($domain) );
        return $data->templatize();
    }


    /**
     * Get total number of strings extracted from all domains, excluding additional metadata
     * @return int
     */
    public function getTotal(){
        return $this->extracted->count();
    }


    /**
     * Get list of files skipped, or null if none were skipped
     * @return Loco_fs_FileList | null
     */
    public function getSkipped(){
        return $this->skipped;
    }


    /**
     * Get size in bytes of largest file encountered, even if skipped.
     * This is the value required of the max_php_size plugin setting to extract all files
     * @return int
     */
    public function getMaxPhpSize(){
        return $this->maxbytes;
    }

}
