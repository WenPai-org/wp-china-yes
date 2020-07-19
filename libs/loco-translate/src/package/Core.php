<?php
/**
 * The "WordPress Core" translations bundle
 */
class Loco_package_Core extends Loco_package_Bundle {

    /**
     * {@inheritdoc}
     */
    public function getSystemTargets(){
        return array (
            untrailingslashit( loco_constant('LOCO_LANG_DIR') ),
	        untrailingslashit( loco_constant('WP_LANG_DIR') )
        );
    }


    /**
     * {@inheritdoc}
     */
    public function getHeaderInfo(){
        return new Loco_package_Header( array (
            'TextDomain' => 'default',
            'DomainPath' => '/wp-content/languages/',
            // dummy author info for core components
            'Name' => __('WordPress core','loco-translate'),
            'Version' => $GLOBALS['wp_version'],
            'Author' => __('The WordPress Team','default'),
            'AuthorURI' => __('https://wordpress.org/','default'),
        ) );
    }


    /**
     * {@inheritdoc}
     */
    public function getMetaTranslatable(){
        return array();
    }


    /**
     * {@inheritdoc}
     */
    public function getType(){
        return 'Core';
    }


    /**
     * {@inheritdoc}
     * Core bundle doesn't need a handle, there is only one.
     */
    public function getId(){
        return 'core';
    }


    /**
     * {@inheritdoc}
     * Core bundle is always configured
     */
    public function isConfigured(){
        $saved = parent::isConfigured() or $saved = 'internal';
        return $saved;
    }


    /**
     * Manually define the core WordPress translations as a single bundle
     * Projects are those included in standard WordPress downloads: [default], "admin", "admin-network" and "continents-cities"
     * @return Loco_package_Core
     */
    public static function create(){
        
        $rootDir = loco_constant('ABSPATH');
        $langDir = loco_constant('WP_LANG_DIR');
        
        $bundle = new Loco_package_Core('core', __('WordPress Core','loco-translate') );
        $bundle->setDirectoryPath( $rootDir );
        
        // Core config may be saved in DB, but not supporting bundled XML
        if( $bundle->configureDb() ){
            return $bundle;
        }
        
        // front end, admin and network admin packages are all part of the "default" domain
        $domain = new Loco_package_TextDomain('default');
        $domain->setCanonical( true );
        // front end subset, has empty name in WP
        // full title is like "4.9.x - Development" but we don't know what version at this point
        list($x,$y) = explode('.',$GLOBALS['wp_version'],3); 
        $project = $domain->createProject( $bundle, sprintf('%u.%u.x - Development',$x,$y) );
        $project->setSlug('')
                ->setPot( new Loco_fs_File($langDir.'/wordpress.pot') )
                ->addSourceDirectory( $rootDir)
                ->excludeSourcePath( $rootDir.'/wp-admin')
                ->excludeSourcePath( $rootDir.'/wp-content')
                ->excludeSourcePath( $rootDir.'/wp-includes/class-pop3.php')
                ->excludeSourcePath( $rootDir.'/wp-includes/js/codemirror')
                ->excludeSourcePath( $rootDir.'/wp-includes/js/crop')
                ->excludeSourcePath( $rootDir.'/wp-includes/js/imgareaselect')
                ->excludeSourcePath( $rootDir.'/wp-includes/js/jcrop')
                ->excludeSourcePath( $rootDir.'/wp-includes/js/jquery')
                ->excludeSourcePath( $rootDir.'/wp-includes/js/mediaelement')
                ->excludeSourcePath( $rootDir.'/wp-includes/js/plupload')
                ->excludeSourcePath( $rootDir.'/wp-includes/js/swfupload')
                ->excludeSourcePath( $rootDir.'/wp-includes/js/thickbox')
                ->excludeSourcePath( $rootDir.'/wp-includes/js/tw-sack.js')
        ;
        // "Administration" project (admin subset)
        $project = $domain->createProject( $bundle, 'Administration');
        $project->setSlug('admin')
                ->setPot( new Loco_fs_File($langDir.'/admin.pot') )
                ->addSourceDirectory( $rootDir.'/wp-admin' )
                ->excludeSourcePath( $rootDir.'/wp-admin/js')
                ->excludeSourcePath( $rootDir.'/wp-admin/css')
                ->excludeSourcePath( $rootDir.'/wp-admin/network')
                ->excludeSourcePath( $rootDir.'/wp-admin/network.php')
                ->excludeSourcePath( $rootDir.'/wp-admin/includes/continents-cities.php')
        ;
        // "Network Admin" package (admin-network subset)
        $project = $domain->createProject($bundle, 'Network Admin');
        $project->setSlug('admin-network')
                ->setPot( new Loco_fs_File($langDir.'/admin-network.pot') )
                ->addSourceDirectory( $rootDir.'/wp-admin/network' )
                ->addSourceFile( $rootDir.'/wp-admin/network.php' )
        ;
        
        // end of "default" domain projects
        $bundle->addDomain( $domain );


        // Continents & Cities is its own text domain)
        $domain = new Loco_package_TextDomain('continents-cities');
        $project = $domain->createProject( $bundle, 'Continents & Cities');
        $project->setPot( new Loco_fs_File( $langDir.'/continents-cities.pot') )
                ->addSourceFile( $rootDir.'/wp-admin/includes/continents-cities.php' )
        ;
        $bundle->addDomain( $domain );
        
        return $bundle;
    }     
    
    
    
    
}