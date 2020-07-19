<?php
/**
 * Hookable objects automatically bind Wordpress filters and actions instance methods.
 * - Filter methods take the form `public function filter_{hook}()`
 * - Actions methods take the form `public function on_{hook}`
 */
abstract class Loco_hooks_Hookable {

    /**
     * Registry of tags to be deregistered when object removed from memory
     * @var array
     */
    private $hooks;


    /**
     * Constructor register hooks immediately
     */
    public function __construct(){

        $ref = new ReflectionClass( $this ); 
        $reg = array();
        foreach( $ref->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ){
            $func = $method->name;
            // support filter_{filter_hook} methods
            if( 0 === strpos($func,'filter_' ) ) {
                $hook = substr( $func, 7 );
            }
            // support on_{action_hook} methods
            else if( 0 === strpos($func,'on_' ) ){
                $hook = substr( $func, 3 );
            }
            else {
                continue;
            }
            // this goes to 11 so we run after system defaults
            $priority = 11;
            // support @priority tag in comment block (uncomment if needed)
            /*if( ( $docblock = $method->getDocComment() ) && ( $offset = strpos($docblock,'@priority ') ) ){
                preg_match( '/^\d+/', substr($docblock,$offset+10), $r ) and
                $priority = (int) $r[0];
            }*/
            // call add_action or add_filter with required arguments and hook is registered
            // add_action actually calls add_filter, although unsure how long that's been the case.
            $num_args = $method->getNumberOfParameters();
            add_filter( $hook, array( $this, $func ), $priority, $num_args );
            // register hook for destruction so object can be removed from memory
            $reg[] = array( $hook, $func, $priority );
        }

        $this->hooks = $reg;
    }


    /**
     * Deregister active hooks.
     * We can't use __destruct because instances persist in WordPress hook registry
     */
    public function unhook(){
        if( is_array($this->hooks) ){
            foreach( $this->hooks as $r ){
                remove_filter( $r[0], array($this,$r[1]), $r[2] );
            }
        }
        $this->hooks = null;
    }

}