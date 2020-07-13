<?php
/**
 * Bundle diagnostics.
 */
class Loco_package_Debugger implements IteratorAggregate {
    
    /**
     * @var array
     */
    private $messages;

    /**
     * @var array
     */
    private $counts;
    

    /**
     * Run immediately on construct
     */
    public function __construct( Loco_package_Bundle $bundle ){
        
        $this->messages = array();
        $this->counts = array(
            'success' => 0,
            'warning' => 0,
            'debug'   => 0,
            'info'    => 0,
        );
        
        // config storage type
        switch( $bundle->isConfigured() ){
        case 'db':
            $this->info("Custom configuration saved in database");
            break;
        case 'meta':
            $this->good("Configuration auto-detected from file headers");
            break;
        case 'file':
            $this->good("Official configuration provided by author");
            break;
        case 'internal':
            $this->info("Configuration built-in to Loco");
            break;
        case '':
            $this->warn("Cannot auto-detect configuration");
            break;
        default:
            throw new Exception('Unexpected isConfigured() return value');
        }

        $base = $bundle->getDirectoryPath();
        // $this->devel('Bundle root is %s',$base);

        // self-declarations provided by author in file headers
        $native = $bundle->getHeaderInfo();
        if( $value = $native->TextDomain ){
            $this->info('WordPress says primary text domain is "%s"', $value);
            // WordPress 4.6 changes mean this header could be a fallback and not actually declared by author
            if( $bundle->isPlugin() ){
                $map = array ( 'TextDomain' => 'Text Domain' );
                $raw = get_file_data( $bundle->getBootstrapPath(), $map, 'plugin' );
                if( empty($raw['TextDomain']) ){
                    $this->warn('Author doesn\'t define the TextDomain header, WordPress guessed it');
                }
            }
            // Warn if WordPress-assumed text domain is not configured. plugin/theme headers won't be translated
            $domains = $bundle->getDomains();
            if( ! isset($domains[$value]) && ! isset($domains['*']) ){
                $this->warn('Expected text domain "%s" is not configured', $value );
            }
        }
        else {
            $this->warn("Author doesn't define the TextDomain header");
        }
        if( $value = $native->DomainPath ){
            $this->good('Primary domain path declared by author as "%s"', $value );
        }
        else if( is_dir($base.'/languages') ){
            $this->info('Standard "languages" folder found, although DomainPath not declared');
        }
        else {
            $this->warn("Author doesn't define the DomainPath header");
        }
        
        // check validity of single-file plugins
        if( $bundle->isSingleFile() && ! $bundle->getBootstrapPath() ){
            $this->warn('Plugin is a single file, but bootstrap file is unknown');
        }
        
        // collecting only configured domains to match against source code
        $domains = array();
        $templates = array();
        
        // show each known subset
        if( $count = count($bundle) ){
            /* @var $project Loco_package_Project */
            foreach( $bundle as $project ){
                $id = $project->getId();
                $domain = (string) $project->getDomain();
                if( '*' === $domain ){
                    $this->devel('Wildcard text domain configured for %s', $project );
                    $domain = '';
                }
                $domains[$domain] = true;
                // Domain path[s] within bundle directory
                $targets = array();
                /* @var $dir Loco_fs_Directory */
                foreach( $project->getConfiguredTargets() as $dir ){
                    $targets[] = $dir->getRelativePath($base);
                }
                if( $targets ){
                    $this->info('%u domain path[s] configured for "%s" -> %s', count($targets), $id, json_encode($targets,JSON_UNESCAPED_SLASHES) );
                }
                else {
                    $this->warn('No domain paths configured for "%s"', $id );
                }
                // POT template file  
                if( $potfile = $project->getPot() ){
                    if( $potfile->exists() ){
                        $this->good('Template file for "%s" exists at "%s"', $id, $potfile->getRelativePath($base) );
                        try {
                            $data = Loco_gettext_Data::load($potfile);
                            $templates[$domain][] = $data;
                        }
                        catch( Exception $e ){
                            $this->warn('Template file for "%s" is invalid format', $id );
                        }
                    }
                    else {
                        $this->warn('Template file for "%s" does not exist (%s)', $id, $potfile->getRelativePath($base) );
                    }
                }
                else {
                    $this->warn('No template file configured for "%s"', $domain );
                    if( $potfile = $project->guessPot() ){
                        $this->devel('Possible non-standard name for "%s" template at "%s"', $id, $potfile->getRelativePath($base) );
                        $project->setPot( $potfile ); // <- adding so that invert ignores it
                    }
                }
            }
            $default = $bundle->getDefaultProject();
            if( ! $default ){
                $this->warn('%u subsets configured, but failed to establish the default/primary', $count );
            }
        }
        else {
            $default = $bundle->createDefault();
            $domain = (string) $default->getDomain();
            $this->devel( 'Suggested text domain: "%s"', $domain );
        }
        
        // files picked up with no context as to what they're for
        if( $bundle->isTheme() || ( $bundle->isPlugin() && ! $bundle->isSingleFile() ) ){
            $unknown = $bundle->invert();
            if( $n = count($unknown) ){
                /* @var $project Loco_package_Project */
                foreach( $unknown as $project ){
                    $domain = (string) $project->getDomain();
                    // should only have one target due the way the inverter groups results
                    /* @var $dir Loco_fs_Directory */
                    foreach( $project->getConfiguredTargets() as $dir ){
                        $reldir = $dir->getRelativePath($base) or $stub = '.';
                        $this->warn('Unconfigured files found in "%s", possible domain name: "%s"', $reldir, $domain );
                    }
                }
            }
        }
        
        // source code extraction across entire bundle
        $tmp = clone $bundle;
        $tmp->exchangeArray( array() );
        $project = $tmp->createDefault( (string) $default->getDomain() );
        $extr = new Loco_gettext_Extraction( $tmp );
        $extr->addProject( $project );
        
        if( $total = $extr->getTotal() ){
            // real count excludes additional metadata
            $realCounts = $extr->getDomainCounts();
            $counts = $extr->includeMeta()->getDomainCounts();
            // $this->good("%u string[s] can be extracted from source code for %s", $total, $this->implodeKeys($counts) );
            foreach( array_intersect_key($counts, $domains) as $domain => $count ){
                if( isset($realCounts[$domain]) ){
                    $count = $counts[$domain];
                    $realCount = $realCounts[$domain];
                    $str = _n( 'One string extracted from source code for "%2$s"', '%s strings extracted from source code for "%s"', $realCount, 'loco-translate' );
                    $this->good( $str.' (%s including metadata)', number_format($realCount), $domain?$domain:'*', number_format($count) );
                }
                else {
                    $this->warn('No strings extracted from source code for "%s"', $domain?$domain:'*' );
                }
                // check POT agrees with extracted count, but only if domain has single POT (i.e. not split across files on purpose)
                if( isset($templates[$domain]) && 1 === count($templates[$domain]) ){
                    $data = current( $templates[$domain] );
                    if( ! $extr->getTemplate($domain)->equalSource($data) ){
                        $meta = Loco_gettext_Metadata::create( new Loco_fs_DummyFile(''), $data );
                        $this->devel('Template is not in sync with source code (%s in file)', $meta->getTotalSummary() );
                    }
                }
            }
            // with extracted strings we can check for domain mismatches
            if( $missing = array_diff_key($domains, $realCounts) ){
                $num = count($missing);
                $str = _n( 'Configured domain has no extractable strings', '%u configured domains have no extractable strings', $num, 'loco-translate' );
                $this->warn( $str.': %2$s', $num, $this->implodeKeys($missing) );
            }
            if( $extra = array_diff_key($realCounts,$domains) ){
                
                $this->info('%u unconfigured domain[s] found in source code: %s', count($extra), $this->implodeKeys($extra) );
                /*/ debug other domains extracted
                foreach( $extra as $name => $count ){
                    $this->devel(' > %s (%u)', $name, $count );
                }*/
                // extracted domains could prove that declared domain is wrong
                if( $missing ){
                    foreach( array_keys($extra) as $name ){
                        $flat = preg_replace('/[^a-z0-9]/','', strtolower($name) );
                        foreach( array_keys($missing) as $decl ){
                            if( preg_replace('/[^a-z0-9]/','', strtolower($decl) ) === $flat ){
                                $this->devel('"%s" might be a mistake. Should it be "%s"?', $decl, $name );
                            }
                        }
                    }
                }
            }
                            
        }
        else {
            $this->warn("No strings can be extracted from source code");
        }
        
    }


    /**
     * @internal
     * Implements IteratorAggregate for looping over messages
     * @return ArrayIterator
     */
    public function getIterator(){
        return new ArrayIterator( $this->messages );
    }


    /**
     * Add a success notice
     * @return Loco_package_Debugger
     */
    private function good( $text ){
        $args = func_get_args();
        $text = call_user_func_array('sprintf', $args );
        return $this->add( new Loco_error_Success($text) );
    }


    /**
     * Add a warning notice
     * @return Loco_package_Debugger
     */
    private function warn( $text ){
        $args = func_get_args();
        $text = call_user_func_array('sprintf', $args );
        return $this->add( new Loco_error_Warning($text) );
    }


    /**
     * Add an information notice (not good, or bad)
     * @return Loco_package_Debugger
     */
    private function info( $text ){
        $args = func_get_args();
        $text = call_user_func_array('sprintf', $args );
        return $this->add( new Loco_error_Notice($text) );
    }


    /**
     * Add a developer notice. probably something helpful for fixing a problem
     * @return Loco_package_Debugger
     */
    private function devel( $text ){
        $args = func_get_args();
        $text = call_user_func_array('sprintf', $args );
        return $this->add( new Loco_error_Debug($text) );
    }


    /**
     * @return Loco_package_Debugger
     */
    private function add( Loco_error_Exception $error ){
        $this->counts[ $error->getType() ]++;
        $this->messages[] = $error;
        return $this;
    }


    /**
     * Print all diagnostic messages suitable for CLI
     * @codeCoverageIgnore
     */
    public function dump( $prefix = '' ){
        /* @var $notice Loco_error_Exception */
        foreach( $this as $notice ){
            printf("%s[%s] %s\n", $prefix, $notice->getType(), $notice->getMessage() );
        }
    }


    /**
     * Get number of bad things discovered
     * @return int
     */
    public function countWarnings(){
        return $this->counts['warning'];
    }


    /**
     * Utility for printing "x", "y" & "z"
     * @return string
     */
    private function implodeNames( array $names ){
        $last = array_pop($names);
        if( $names ){
            return '"'.implode('", "',$names).'" & "'.$last.'"';
        }
        if( is_string($last) ){
            return '"'.$last.'"';
        }
        return '';
    }
    
    
    /**
     * @internal
     * @return string
     */
    private function implodeKeys( array $assoc ){
        return $this->implodeNames( array_keys($assoc) );
    }


}