<?php
/**
 * Holds a bundle definition in a structure serializable to a native array.
 */
class Loco_config_ArrayModel extends Loco_config_Model {


    /**
     * {@inheritdoc}
     */
    public function createDom(){
        return new LocoConfigDocument( array('#document', array(), array() ) );
    }
    
    
    /**
     * Construct model from serialized JSON
     * @return void
     */
    public function loadJson( $json ){
        $root = json_decode( $json, true );
        if( ! $root || ! is_array($root) ){
            throw new Loco_error_ParseException('Invalid JSON');
        }
        $this->loadArray( $root );
    }
    
    
    /**
     * Construct model from exported array
     * @return void
     */
    public function loadArray( array $root ){
        $dom = $this->getDom();
        $dom->load( array('#document', array(), array($root) ) );
    }



    /**
     * {@inheritdoc}
     * Emulates *very limited* XPath queries used by the XML DOM.
     */
    public function query( $query, $context = null ){
        $match = new LocoConfigNodeList;
        $query = explode('/', $query );
        // absolute path always starts in document
        if( $absolute = empty($query[0]) ){
            $match->append( $this->getDom() );
        }
        // else start with base for relative path
        else if( $context instanceof LocoConfigNode ){
            $match->append( $context );
        }
        while( $query ){
            $name = array_shift($query);
            // self references do nothing
            if( ! $name || '.' === $name ){
                continue;
            }
            // match all current branches to produce new set of parents
            $next = new LocoConfigNodeList;
            foreach( $match as $parent ){
                foreach( $parent->childNodes as $child ){
                    if( $name === $child->nodeName || ( '*' === $name && $child instanceof LocoConfigElement ) || ( 'text()' === $name && $child instanceof LocoConfigText) ){
                        $next->append( $child );
                    }
                }
            }
            $match = $next;
        }
        
        return $match;
    }
    
}





// The following classes are "private" to this file:
// They partially implement the same interfaces as the core DOM classes and are used for code hints.
// Interfaces are deliberately not used as the real DOM classes would not be able to implement them.



/**
 * Node
 */
abstract class LocoConfigNode implements IteratorAggregate {
        
    /**
     * Raw data of internal format
     * @var array 
     */ 
    protected $data;
    
    /**
     * Child nodes once cast to node objects
     * @var LocoConfigNodeList
     */
    protected $children;

    /**
     * @return mixed
     */
    abstract public function export();

    final public function __construct( $data ){
        $this->data = $data;
    }

    protected function get_nodeName(){
        return $this->data[0];
    }
    
    /*protected function get_attributes(){
        return $this->data[1];
    }*/
    
    protected function get_childNodes(){
        return $this->getIterator();
    }
    
    
    public function __get( $prop ){
        $method = array( $this, 'get_'.$prop );
        if( is_callable($method) ){
            return call_user_func( $method );
        }
    }

    
    /** @return LocoConfigNode */
    public function appendChild( LocoConfigNode $child ){
        $children = $this->getIterator();
        $children->append( $child );
        return $child;
    }

    
    /** @return bool */
    public function hasChildNodes(){
        return (bool) count( $this->getIterator() );
    }
    
    
    /**
     * @return LocoConfigNodeList
     */
    public function getIterator(){
        if( ! $this->children ){
            $raw = isset($this->data[2]) ? $this->data[2] : array();
            $this->children = new LocoConfigNodeList( $this->data[2] );
        }
        return $this->children;
    }


    public function get_textContent(){
        $s = '';
        foreach( $this as $child ){
            $s .= $child->get_textContent();
        }
        return $s;
    }
    
}


/**
 * NodeList
 */
class LocoConfigNodeList implements Iterator, Countable, ArrayAccess {
 
    private $nodes;

    private $i;

    private $n;
    
    public function __construct( array $nodes = array() ){
        $this->nodes = $nodes;
        $this->n = count( $nodes );
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
        return $this[ $this->i ];
    }
    
    public function valid(){
        return is_int($this->i);
    }
    
    public function next(){
        if( ++$this->i === $this->n ){
            $this->i = null;
        }
    }
 
    public function offsetExists( $i ){
        return $i >= 0 && $i < $this->n;
    }
    
    public function offsetGet( $i ){
        $node = $this->nodes[$i];
        if( ! $node instanceof LocoConfigNode ){
            if( is_array($node) ){
                $node = new LocoConfigElement( $node );
            }
            else {
                $node = new LocoConfigText( $node );
            }
            $this->nodes[$i] = $node;
        }
        return $node;
    }

    /**
     * @codeCoverageIgnore
     */
    public function offsetSet( $i, $value ){
        throw new Exception('Use append');
    }

    /**
     * @codeCoverageIgnore
     */
    public function offsetUnset( $i ){
        throw new Exception('Read only');
    }
    
    
    public function append( LocoConfigNode $node ){
        $this->nodes[] = $node;
        $this->n++;
    }
    
    
    /**
     * Revert nodes back to raw array form and return for exporting
     * @return array
     */
    public function normalize(){
        foreach( $this->nodes as $i => $node ){
            if( $node instanceof LocoConfigNode ){
                $this->nodes[$i] = $node->export();
            }
        }
        return $this->nodes;
    }
    
}






/**
 * Document
 */
class LocoConfigDocument extends LocoConfigNode {

    /**
     * Rapidly set new data for document
     */    
    public function load( $data ){
        $this->data = $data;
        $this->children = null;
    }
    

    /**
     * @return LocoConfigElement
     */
    public function createElement( $name ){
        return new LocoConfigElement( array( $name, array(), array() ) );
    }


    /**
     * @return LocoConfigText
     */
    public function createTextNode( $text ){
        return new LocoConfigText( $text );
    }


    /**
     * @return LocoConfigElement
     */
    public function get_documentElement(){
        $child = null;
        foreach( $this as $child ){
            break;
        }
        return $child;
    }
    

    /**
     * {@inheritdoc}
     * Override to keep single element root 
     */
    public function export(){
        if( $root = $this->get_documentElement() ){
            return $root->export();
        }
    }
    
}




/**
 * Element
 */
class LocoConfigElement extends LocoConfigNode {

    public function setAttribute( $prop, $value ){
        $this->data[1][$prop] = $value;
    }

    public function removeAttribute( $prop ){
        unset( $this->data[1][$prop] );
    }

    public function getAttribute( $prop ){
        if( isset($this->data[1][$prop]) ){
            return $this->data[1][$prop]; 
        }
        return '';
    }

    public function hasAttribute( $prop ){
        return isset($this->data[1][$prop]);
    }

    /**
     * {@inheritdoc}
     */
    public function export(){
        $raw = $this->data;
        // return any cast elements back to raw data
        if( $this->children ){
            $raw[2] = $this->children->normalize();
        }
        return $raw;
    }    
}



/**
 * Text
 */
class LocoConfigText extends LocoConfigNode {

    protected function get_nodeName(){
        return '#text';
    }
    
    public function hasChildNodes(){
        return false;
    }
    
    public function getIterator(){
        return new ArrayIterator;
    }
    
    public function export(){
        return (string) $this->data;
    }
    
    public function get_nodeValue(){
        return (string) $this->data;
    }
    
    public function get_textContent(){
        return (string) $this->data;
    }
    
}


