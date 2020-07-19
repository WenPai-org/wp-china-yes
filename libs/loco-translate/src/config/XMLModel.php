<?php
/**
 * Holds a bundle definition in a DOM document
 */
class Loco_config_XMLModel extends Loco_config_Model {
    
    /**
     * @var DOMXpath
     */
    private $xpath;
    

    /**
     * {@inheritdoc}
     */    
    public function createDom(){
        $dom = new DOMDocument('1.0','utf-8');
        $dom->formatOutput = true;
        $dom->registerNodeClass('DOMElement','LocoConfig_DOMElement');
        $this->xpath = new DOMXPath($dom);
        return $dom;
    }


    /**
     * {@inheritdoc}
     * @return LocoConfigNodeListIterator
     */
    public function query( $query, $context = null ){
        $list = $this->xpath->query( $query, $context );
        return new LocoConfigNodeListIterator( $list );
    }


    
    /**
     * @return void
     */
    public function loadXml( $source ){
        
        if( ! $source ){
            throw new Loco_error_XmlParseException( __('XML supplied is empty','loco-translate') );
        }
    
        $dom = $this->getDom();
    
        // parse with silent errors, clearing after
        $used_errors = libxml_use_internal_errors(true);

        $result = $dom->loadXML( $source, LIBXML_NONET );
        unset( $source );
        
        // fetch errors and ensure clean for next run.
        $errors = libxml_get_errors();
        $used_errors || libxml_use_internal_errors(false);
        libxml_clear_errors();

        // Throw exception if error level exceeds current tolerance
        if( $errors ){
            /* @var $error LibXMLError */
            foreach( $errors as $error ){
                if( $error->level >= LIBXML_ERR_FATAL ){
                    $e = new Loco_error_XmlParseException( trim($error->message) );
                    //$e->setContext( $error->line, $error->column, $source );
                    throw $e;
                } // @codeCoverageIgnoreStart
            }
        }
        // @codeCoverageIgnoreEnd
        
        // Not currently validating against a DTD, but may as well pre-empt generic model loading errors
        if( ! $dom->documentElement || 'bundle' !== $dom->documentElement->nodeName ){
            throw new Loco_error_XmlParseException('Expected <bundle> document element');
        }
        
        $this->xpath = new DOMXPath($dom);
    }


    /**
     * {@inheritdoc}
     * Overridden to avoid empty text nodes in XML files, preferring <file>.</file> to <file />
     */
    protected function setFileElementPath( $node, $path ){
        if( ! $path && '0' !== $path ){
            $path = '.';
        }
        return parent::setFileElementPath( $node, $path );
    }


}



/**
 * @internal
 */
class LocoConfig_DOMElement extends DOMElement implements IteratorAggregate, Countable {
    public function getIterator(){
        return new LocoConfigNodeListIterator( $this->childNodes );
    }
    public function count(){
        return $this->childNodes->length;
    }
}



/**
 * @internal
 * Cos NodeList doesn't iterate
 */
class LocoConfigNodeListIterator implements Iterator, Countable, ArrayAccess {
    
    /**
     * @var DOMNodeList
     */
    private $nodes;

    /**
     * @var int
     */
    private $i;

    /**
     * @var int
     */
    private $n;
    
        
    public function __construct( DOMNodeList $nodes ){
        $this->nodes = $nodes;
        $this->n = $nodes->length;
    }
    
    public function count(){
        return $this->n;
    }
    
    public function rewind(){
        $this->i = -1;
        $this->next();
    }
    
    public function key(){
        return $this->i;
    }
    
    public function current(){
        return $this->nodes->item( $this->i );
    }
    
    public function valid(){
        return is_int($this->i);
    }
    
    public function next(){
        while( true ){
            $this->i++;
            if( $child = $this->nodes->item($this->i) ){
                break;
            }
            $this->i = null;
            break;
        }
    }
    
    public function offsetExists( $i ){
        return $i >= 0 && $i < $this->n;
    }
    
    public function offsetGet( $i ){
        return $this->nodes->item($i);
    }

    /**
     * @codeCoverageIgnore
     */
    public function offsetSet( $i, $value ){
        throw new Exception('Read only');
    }

    /**
     * @codeCoverageIgnore
     */
    public function offsetUnset( $i ){
        throw new Exception('Read only');
    }
    
}
