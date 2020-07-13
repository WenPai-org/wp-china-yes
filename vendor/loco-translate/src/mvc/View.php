<?php /** @noinspection PhpMultipleClassesDeclarationsInOneFile */

/**
 * View renderer
 */
class Loco_mvc_View implements IteratorAggregate {

    /**
     * @var Loco_mvc_ViewParams
     */
    private $scope;
    
    /**
     * View that is decorating current view
     * @var Loco_mvc_View
     */
    private $parent;
    
    /**
     * Current template as full path to PHP file
     * @var string
     */
    private $template;
    
    /**
     * Current working directory for finding templates by relative path
     * @var string
     */
    private $cwd;

    /**
     * Name of current output buffer
     * @var string
     */
    private $block;


    /**
     * @internal
     * @param array
     */
    public function __construct( array $args = array() ){
        $this->scope = new Loco_mvc_ViewParams( $args );
        $this->cwd = loco_plugin_root().'/tpl';
    }
    
    
    /**
     * Change base path for template paths
     * @param string path relative to current directory
     * @return Loco_mvc_View 
     */
    public function cd( $path ){
        if( $path && '/' === substr($path,0,1) ){
            $this->cwd = untrailingslashit( loco_plugin_root().'/tpl'.$path );
        }
        else {
            $this->cwd = untrailingslashit( $this->cwd.'/'.$path );
        }
        return $this;
    }    
    

    /**
     * @internal
     * Clean up if something abruptly stopped rendering before graceful end
     */
    public function __destruct(){
        if( $this->block ){
            ob_end_clean();
        }
    }


    /**
     * Render error screen HTML
     * @param Loco_error_Exception
     * @return string
     */
    public static function renderError( Loco_error_Exception $e ){
        $view = new Loco_mvc_View;
        try {
            $view->set( 'error', $e );
            return $view->render( $e->getTemplate() );
        }
        catch( Exception $e ){
            return '<h1>'.esc_html( $e->getMessage() ).'</h1>';
        }
    }


    /**
     * Make this view a child of another template. i.e. decorate this with that.
     * Parent will have access to original argument scope, but separate from now on
     * @param string
     * @return Loco_mvc_View the parent view
     */
    private function extend( $tpl ){
        $this->parent = new Loco_mvc_View;
        $this->parent->cwd = $this->cwd;
        $this->parent->setTemplate( $tpl );
        return $this->parent;
    }


    /**
     * After start is called any captured output will be placed in the named variable
     * @param string
     * @return void
     */
    private function start( $name ){
        $this->stop();
        $this->scope[$name] = null;
        $this->block = $name;
    }


    /**
     * When stop is called, buffered output is saved into current variable for output by parent template, or at end of script.
     * @return void
     */
    private function stop(){
        $content = ob_get_contents();
        ob_clean();
        if( $b = $this->block ){
            if( isset($this->scope[$b]) ){
                $content = $this->scope[$b].$content;
            }
            $this->scope[$b] = new _LocoViewBuffer($content);
            $this->block = null;
        }
        $this->block = '_trash';
    }


    /**
     * {@inheritDoc}
     */
    public function getIterator(){
        return $this->scope;
    }


    /**
     * @internal
     * @param string
     * @return mixed
     */
    public function __get( $prop ){
        return $this->has($prop) ? $this->get($prop) : null;
    }


    /**
     * @param string
     * @return bool
     */
    public function has( $prop ){
        return array_key_exists($prop,$this->scope);
    }


    /**
     * Get property after checking with self::has
     * @param string
     * @return mixed
     */
    public function get( $prop ){
        return $this->scope[$prop];
    }


    /**
     * Set a view argument
     * @param string
     * @param mixed
     * @return Loco_mvc_View
     */
    public function set( $prop, $value ){
        $this->scope[$prop] = $value;
        return $this;
    }



    /**
     * Main entry to rendering complete template
     * @param string template name excluding extension
     * @param array extra arguments to set in view scope
     * @param Loco_mvc_View parent view rendering this view
     * @return string
     */
    public function render( $tpl, array $args = null, Loco_mvc_View $parent = null ){
        if( $this->block ){
            return $this->fork()->render( $tpl, $args, $this );
        }
        $this->setTemplate($tpl);
        if( $parent && $this->template === $parent->template ){
            throw new Loco_error_Exception('Avoiding infinite loop');
        }
        if( is_array($args) ){
            foreach( $args as $prop => $value ){
                $this->set($prop, $value);
            }
        }
        ob_start();
        $content = $this->buffer();
        ob_end_clean();
        return $content;
    }



    /**
     * Do actual render of currently validated template path
     * @return string content not captured in sub-blocks
     */
    private function buffer(){
        $this->start('_trash');
        $this->execTemplate( $this->template );
        $this->stop();
        $this->block = null;
        // decorate via parent view if there is one
        if( $this->parent ){
            $this->parent->scope = clone $this->scope;
            $this->parent->set('_content', $this->_trash );
            return $this->parent->buffer();
        }
        // else at the root of view chain
        return (string) $this->_trash;
    }



    /**
     * Set current template
     * @param string path tro template, excluding file extension
     */
    public function setTemplate( $tpl ){
        $file = new Loco_fs_File( $tpl.'.php' );
        $file->normalize( $this->cwd );
        if( ! $file->exists() ){
            $debug = str_replace( loco_plugin_root().'/', '', $file->getPath() );
            throw new Loco_error_Exception( 'Template not found: '.$debug );
        }
        $this->cwd = $file->dirname();
        $this->template = $file->getPath();
    }


    /**
     * @return Loco_mvc_View
     */
    private function fork(){
        $view = new Loco_mvc_View;
        $view->cwd = $this->cwd;
        $view->scope = clone $this->scope;
        
        return $view;
    }


    /**
     * Do actual runtime template include
     * @param string
     * @return void
     */
    private function execTemplate( $template ){
        $params = $this->scope;
        extract( $params->getArrayCopy() );
        include $template;
    }


    /**
     * Link generator
     * @param string page route, e.g. "config"
     * @param array optional page arguments
     * @return Loco_mvc_ViewParams
     */
    public function route( $route, array $args = array() ){
        return new Loco_mvc_ViewParams( array (
            'href' => Loco_mvc_AdminRouter::generate( $route, $args ),
        ) );
    }


    /**
     * Shorthand for `echo esc_html( sprintf( ...`
     * @param string
     * @return string
     */
    private static function e( $text ){
        if( 1 < func_num_args() ){
            $args = func_get_args();
            $text = call_user_func_array( 'sprintf', $args );
        }
        echo htmlspecialchars( $text, ENT_COMPAT, 'UTF-8' );
        return '';
    }

}



/**
 * @internal
 */
class _LocoViewBuffer {
    
    private $s;

    public function __construct( $s ){
        $this->s = $s;
    }

    public function __toString(){
        return $this->s;
    }    
    
}
