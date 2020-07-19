<?php
/**
 * Bundle configuration page
 */
class Loco_admin_bundle_ConfController extends Loco_admin_bundle_BaseController {

    /**
     * {@inheritdoc}
     */
    public function init(){
        parent::init();
        $this->enqueueStyle('config');
        $this->enqueueScript('config');
        $bundle = $this->getBundle();
        // translators: where %s is a plugin or theme
        $this->set( 'title', sprintf( __('Configure %s','loco-translate'),$bundle->getName() ) );

        $post = Loco_mvc_PostParams::get();
        // always set a nonce for current bundle
        $nonce = $this->setNonce( $this->get('_route').'-'.$this->get('bundle') );
        $this->set('nonce', $nonce );
        try {
            // Save configuration if posted
            if( $post->has('conf') ){
                if( ! $post->name ){
                    $post->name = $bundle->getName();
                }
                $this->checkNonce( $nonce->action );
                $model = new Loco_config_FormModel;
                $model->loadForm( $post );
                // configure bundle from model in full
                $bundle->clear();
                $reader = new Loco_config_BundleReader( $bundle );
                $reader->loadModel( $model );
                $this->saveBundle();
            }
            // Delete configuration if posted
            else if( $post->has('unconf') ){
                $this->resetBundle();
            }
        }
        catch( Exception $e ){
            Loco_error_AdminNotices::warn( $e->getMessage() );
        }

    }

    

    /**
     * {@inheritdoc}
     */
    public function getHelpTabs(){
        return array (
            __('Advanced tab','loco-translate') => $this->viewSnippet('tab-bundle-conf'),
        );
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function render() {

        $parent = null;
        $bundle = $this->getBundle();
        $default = $bundle->getDefaultProject();
        $base = $bundle->getDirectoryPath();


        // parent themes are inherited into bundle, we don't want them in the child theme config
        if( $bundle->isTheme() && ( $parent = $bundle->getParent() ) ){
            $this->set( 'parent', new Loco_mvc_ViewParams( array(
                'name' => $parent->getName(),
                'href' => Loco_mvc_AdminRouter::generate('theme-conf', array( 'bundle' => $parent->getSlug() ) + $_GET ),
            ) ) );
        }

        // render postdata straight back to form if sent
        $data = Loco_mvc_PostParams::get();
        // else build initial data from current bundle state 
        if( ! $data->has('conf') ){
            // create single default set for totally unconfigured bundles
            if( 0 === count($bundle) ){
                $bundle->createDefault('');
            }
            $writer = new Loco_config_BundleWriter($bundle);
            $data = $writer->toForm();
            // removed parent bundle from config form, as they are inherited
            /* @var Loco_package_Project $project */
            foreach( $bundle as $i => $project ){
                if( $parent && $parent->hasProject($project) ){
                    // warn if child theme uses parent theme's text domain (but allowing to render so we don't get an empty form.
                    if( $project === $default ){
                        Loco_error_AdminNotices::warn( __("Child theme declares the same Text Domain as the parent theme",'loco-translate') );
                    }
                    // else safe to remove parent theme configuration as it should be held in its own bundle
                    else {
                        $data['conf'][$i]['removed'] = true;
                    }
                }
            }
        }

        // build config blocks for form
        $i = 0;
        $conf = array();
        foreach( $data['conf'] as $raw ){
            if( empty($raw['removed']) ){
                $slug = $raw['slug'];
                $domain = $raw['domain'] or $domain = 'untitled';
                $raw['prefix'] = sprintf('conf[%u]', $i++ );
                $raw['short'] = ! $slug || ( $slug === $domain ) ? $domain : $domain.'→'.$slug;
                $conf[] = new Loco_mvc_ViewParams( $raw );
            }
        }

        // bundle level configs
        $name = $bundle->getName();
        $excl = $data['exclude'];
        
        
        // access to type of configuration that's currently saved
        $this->set('saved', $bundle->isConfigured() );
        
        // link to author if there are config problems
        $info = $bundle->getHeaderInfo();
        $this->set('author', $info->getAuthorLink() );
        
        // link for downloading current configuration XML file
        $args = array ( 
            'path' => 'loco.xml', 
            'action' => 'loco_download', 
            'bundle' => $bundle->getHandle(), 
            'type' => $bundle->getType()  
        );
        $this->set( 'xmlUrl', Loco_mvc_AjaxRouter::generate( 'DownloadConf', $args ) );
        $this->set( 'manUrl', apply_filters('loco_external','https://localise.biz/wordpress/plugin/manual/bundle-config') );
        
        $this->prepareNavigation()->add( __('Advanced configuration','loco-translate') );
        return $this->view('admin/bundle/conf', compact('conf','base','name','excl') );
    }    
    
    
} 