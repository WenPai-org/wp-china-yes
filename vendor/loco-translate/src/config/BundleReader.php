<?php
/**
 * Loads Loco configuration file into a bundle definition
 */
class Loco_config_BundleReader {

    /**
     * @var Loco_package_Bundle
     */
    private $bundle;

    
    /**
     * Constructor initializes empty dom
     */
    public function __construct( Loco_package_Bundle $bundle ){
        $this->bundle = $bundle;
    }


    /**
     * @param Loco_fs_File loco.xml file
     * @return Loco_package_Bundle
     */
    public function loadXml( Loco_fs_File $file ){
        $this->bundle->setDirectoryPath( $file->dirname() );
        $model = new Loco_config_XMLModel;
        $model->loadXml( $file->getContents() );
        return $this->loadModel( $model );
    }


    /**
     * @return Loco_package_Bundle
     */
    public function loadJson( Loco_fs_File $file ){
        $this->bundle->setDirectoryPath( $file->dirname() );
        return $this->loadArray( json_decode( $file->getContents(), true ) );
    }


    /**
     * @return Loco_package_Bundle
     */
    public function loadArray( array $raw ){
        $model = new Loco_config_ArrayModel;
        $model->loadArray( $raw );
        return $this->loadModel( $model );
    }



    /**
     * Agnostic construction of Bundle from any configuration format
     * @return Loco_package_Bundle
     */
    public function loadModel( Loco_config_Model $model ){
        
        // Base directory required to resolve relative paths
        $bundle = $this->bundle;
        $model->setDirectoryPath( $bundle->getDirectoryPath() );

        $dom = $model->getDom();
        $bundleElement = $dom->documentElement;
        if( ! $bundleElement || 'bundle' !== $bundleElement->nodeName ){
            throw new InvalidArgumentException('Expected root bundle element');
        }

        // Set bundle meta data if configured
        // note that bundles have no inherent slug as it can change according to plugin/theme directory naming
        if( $bundleElement->hasAttribute('name') ){
            $bundle->setName( $bundleElement->getAttribute('name') );
        }
        
        // Bundle-level path exclusions
        foreach( $model->query('exclude/*',$bundleElement) as $fileElement ){
            $bundle->excludeLocation( $model->evaluateFileElement($fileElement) );
        }

        /* @var $domainElement LocoConfigElement */
        foreach( $model->query('domain',$bundleElement) as $domainElement ){
            $slug = $domainElement->getAttribute('name') or $slug = $bundle->getSlug();
            // bundle may not have a handle set (most likely only in tests)
            if( ! $bundle->getHandle() ){
               $bundle->setHandle( $slug );
            }
            // Text Domain may also be declared by bundle author
            $domain = new Loco_package_TextDomain( $slug );
            $declared = $bundle->getHeaderInfo();
            if( $declared && $declared->TextDomain === $slug ){
                $domain->setCanonical( true );
            }
            /* @var $projectElement LocoConfigElement */
            foreach( $model->query('project',$domainElement) as $projectElement ){
    
                $name = $projectElement->getAttribute('name') or $name = $bundle->getName();
                $project = new Loco_package_Project( $bundle, $domain, $name );
                if( $projectElement->hasAttribute('slug') ){
                    $project->setSlug( $projectElement->getAttribute('slug') );
                }
                
                // <source>
                foreach( $model->query('source',$projectElement) as $sourceElement ){
                    // sources may be <file>, <directory> or pass in special <path> if it could be either 
                    foreach( $model->query('file',$sourceElement) as $fileElement ){
                        $project->addSourceFile( $model->evaluateFileElement($fileElement) );
                    }                
                    foreach( $model->query('directory',$sourceElement) as $fileElement ){
                        $project->addSourceDirectory( $model->evaluateFileElement($fileElement) );
                    }
                    foreach( $model->query('path',$sourceElement) as $fileElement ){
                        $project->addSourceLocation( $model->evaluateFileElement($fileElement) );
                    }
                    foreach( $model->query('exclude/*', $sourceElement) as $fileElement ){
                        $project->excludeSourcePath( $model->evaluateFileElement($fileElement) );
                    }
                }
                // Avoid having no source locations
                if( ! $project->hasSourceFiles() ){
                    if( $bundle->isSingleFile() ){
                        $project->addSourceFile( $bundle->getBootstrapPath() );
                    }
                    else {
                        $project->addSourceDirectory( $bundle->getDirectoryPath() );
                    }
                }
                
                // <target>
                foreach( $model->query('target',$projectElement) as $targetElement ){
                    // targets support only directory paths:
                    foreach( $model->query('directory',$targetElement) as $fileElement ){
                        $project->addTargetDirectory( $model->evaluateFileElement($fileElement) );
                    }
                    foreach( $model->query('exclude/*', $targetElement) as $fileElement ){
                        $project->excludeTargetPath( $model->evaluateFileElement($fileElement) );
                    }
                }
                // Avoid having no target locations ..
                if( 0 === count($project->getConfiguredTargets() ) ){
                    // .. unless the inherited root is a global location
                    if( $bundle->isTheme() || ( $bundle->isPlugin() && ! $bundle->isSingleFile() ) ){
                        $project->addTargetDirectory( $bundle->getDirectoryPath() );
                    }
                }
                
                // <template>
                // configure POT file, should only be one
                foreach( $model->query('template',$projectElement) as $templateElement ){
                    if( $model->evaulateBooleanAttribute( $templateElement, 'locked') ){
                        $project->setPotLock( true );
                    }
                    foreach( $model->query('file',$templateElement) as $fileElement ){
                        $project->setPot( $model->evaluateFileElement( $fileElement ) );
                        break 2;
                    }
                }
                
                // add project last for additional configs to be appended
                $bundle->addProject( $project );
            }
        }        
        
        return $bundle;
    }    






    
}