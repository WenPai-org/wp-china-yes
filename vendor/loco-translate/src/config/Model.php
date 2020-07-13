<?php
/**
 * Generic configuration model serializer for portable Loco configs
 */
abstract class Loco_config_Model {

    /**
     * @var LocoConfigDocument
     */
    private $dom;    
    
    /**
     * Root directory for calculating relative file paths
     * @var string
     */
    private $base;
    
    /**
     * registry of location contants that may have been overridden
     * @var array
     */
    private $dirs;

    /**
     * @return Iterator
     */
    abstract public function query( $query, $context = null );

    /**
     * @return LocoConfigDocument
     */
    abstract public function createDom();


    /**
     * 
     */    
    final public function __construct(){
        $this->dirs = array();
        $this->dom = $this->createDom();
        $this->setDirectoryPath( loco_constant('ABSPATH') );
    }
    
    
    /**
     * @return void
     */
    public function setDirectoryPath( $path, $key = null ){
        $path = untrailingslashit($path);
        if( is_null($key) ){
            $this->base = $path;
        }
        else {
            $this->dirs[$key] = $path;
        }
    }
    
    
    /**
     * @return LocoConfigDocument
     */
    public function getDom(){
        return $this->dom;
    }


    /**
     * Evaluate a name constant pointing to a file location
     * @param string one of 'LOCO_LANG_DIR', 'WP_LANG_DIR', 'WP_PLUGIN_DIR', 'WPMU_PLUGIN_DIR', 'WP_CONTENT_DIR', or 'ABSPATH'
     */
    public function getDirectoryPath( $key = null ){
        if( is_null($key) ){
            $value = $this->base;
        }
        else if( isset($this->dirs[$key]) ){
            $value = $this->dirs[$key];
        }
        else {
            $value = untrailingslashit( loco_constant($key) );
        }

        return $value;
    }


    /**
     * @return LocoConfigElement
     */
    public function createFileElement( Loco_fs_File $file ){
        $node = $this->dom->createElement( $file->isDirectory() ? 'directory' : 'file' );
        if( $path = $file->getPath() ) {
            // Calculate relative path to the config file itself
            $relpath = $file->getRelativePath( $this->base );
            // Map to a configured base path if target is not under our root. This makes XML more portable
            // matching order is most specific first, resulting in shortest path
            if( $relpath && ( Loco_fs_File::abs($relpath) || '..' === substr($relpath,0,2) || $this->base === $this->getDirectoryPath('ABSPATH') ) ){
                $bases = array( 'LOCO_LANG_DIR', 'WP_LANG_DIR', 'WP_PLUGIN_DIR', 'WPMU_PLUGIN_DIR', 'WP_CONTENT_DIR', 'ABSPATH' );
                foreach( $bases as $key ){
                    if( ( $base = $this->getDirectoryPath($key) ) && $base !== $this->base ){
                        $base .= '/';
                        $len = strlen($base);
                        if( substr($path,0,$len) === $base ){
                            $node->setAttribute('base',$key);
                            $relpath = substr( $path, $len );
                            break;
                        }
                    } // @codeCoverageIgnore
                }
            }
            $path = $relpath;
        }
        $this->setFileElementPath( $node, $path );
        return $node;
    }



    /**
     * @param LocoConfigElement
     * @param string
     * @return LocoConfigText
     */
    protected function setFileElementPath( $node, $path ){
        return $node->appendChild( $this->dom->createTextNode($path) );
    }


    
    /**
     * @param LocoConfigElement
     * @return Loco_fs_File
     */
    public function evaluateFileElement( $el ){
        $path = $el->textContent;

        switch( $el->nodeName ){
        case 'directory':
            $file = new Loco_fs_Directory($path);
            break;
        case 'file':
            $file = new Loco_fs_File($path);
            break;
        case 'path':
            $file = new Loco_fs_File($path);
            if( $file->isDirectory() ){
                $file = new Loco_fs_Directory($path);
            }
            break;
        default:
            throw new InvalidArgumentException('Cannot evaluate file element from <'.$el->nodeName.'>');
        }
        
        if( $el->hasAttribute('base') ){
            $key = $el->getAttribute('base');
            $base = $this->getDirectoryPath($key);
            $file->normalize( $base );
        }
        else {
            $file->normalize( $this->base );
        }
        
        return $file;
    }



    /**
     * @param LocoConfigElement
     * @return bool
     */
    public function evaulateBooleanAttribute( $el, $attr ){
        if( ! $el->hasAttribute($attr) ){
            return false;
        }
        $value = (string) $el->getAttribute($attr);
        return 'false' !== $value && 'no' !== $value && '' !== $value;
    }     

}

