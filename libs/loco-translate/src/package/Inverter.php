<?php
/**
 * Bundle inverter utility class.
 */
abstract class Loco_package_Inverter {
    
    /**
     * Get all Gettext files that are not configured and valid in the given bundle
     * @return array
     */
    public static function export( Loco_package_Bundle $bundle ){
        // search paths for inverted bundle will exclude global ignore paths, 
        // plus anything known to the current configuration which we'll add now.
        $finder = $bundle->getFileFinder();
        
        /* @var $project Loco_package_Project */
        foreach( $bundle as $project ){
            if( $file = $project->getPot() ){
                // excluding all extensions in case POT is actually a PO/MO pair
                foreach( array('pot','po','mo') as $ext ){
                    $file = $file->cloneExtension($ext); 
                    if( $path = realpath( $file->getPath() ) ){
                        $finder->exclude( $path );
                    }
                }
            }
            foreach( $project->findLocaleFiles('po') as $file ){
                if( $path = realpath( $file->getPath() ) ){
                    $finder->exclude( $path );
                }
            }
            foreach( $project->findLocaleFiles('mo') as $file ){
                if( $path = realpath( $file->getPath() ) ){
                    $finder->exclude( $path );
                }
            }
        }
        // Do a deep scan of all files that haven't been seen, or been excluded:
        // This will include files in global directories and inside the bundle.
        return $finder->setRecursive(true)->followLinks(false)->group('po','mo','pot')->exportGroups();
    }    
    
    
    /**
     * Compile anything found under bundle root that isn't configured in $known
     * @return Loco_package_Bundle
     */
    public static function compile( Loco_package_Bundle $bundle ){
        
        $found = self::export($bundle);
        
        // done with original bundle now
        $bundle = clone $bundle;
        $bundle->clear();
        

        // first iteration groups found files into common locations that should hopefully indicate translation sets
        $groups = array();
        $templates = array();
        $localised = array();
        $root = $bundle->getDirectoryPath();

        /* @var $list Loco_fs_FileList */
        foreach( $found as $ext => $list ){
            /* @var $file Loco_fs_LocaleFile */
            foreach( $list as $file ){
                // printf("Found: %s <br />\n", $file );
                // This file is NOT known to be part of a configured project
                $dir = $file->getParent();
                $key = $dir->getRelativePath( $root );
                //
                if( ! isset($groups[$key]) ){
                    $groups[$key] = $dir;
                    $templates[$key] = array();
                    $localised[$key] = array();
                }
                // template should define single set of translations unique by directory and file prefix
                if( 'pot' === $ext ){
                    $slug = $file->filename();
                    $templates[$key][$slug] = true;
                }
                // else ideally PO/MO files will correspond to a template by common prefix
                else {
                    $file = new Loco_fs_LocaleFile( $file );
                    $slug = $file->getPrefix();
                    if( $file->getLocale()->isValid() ){
                        $localised[$key][$slug] = true;
                    }
                    // else could be some kind of non-standard template
                    else {
                        $slug = $file->filename();
                        $templates[$key][$slug] = true;
                    }
                }
            }
        }

        unset($found);


        // next iteration matches collected files together into likely project sets
        $unique = array();
        
        /* @var $list Loco_fs_Directory */
        foreach( $groups as $key => $dir ){
            // pair up all projects that match templates neatly to prefixed files
            foreach( $templates[$key] as $slug => $bool ){
                if( isset($localised[$key][$slug]) ){
                    //printf("Perfect match on domain '%s' in %s <br />\n", $slug, $key );
                    $unique[$key][$slug] = $dir;
                    // done with this prefectly matched set
                    $templates[$key][$slug] = null;
                    $localised[$key][$slug] = null;
                }
            }
            // pair up any unprefixed localised files
            if( isset($localised[$key]['']) ){
                $slug = 'unknown';
                // Match to first (hopefully only) template to establish a slug
                foreach( $templates[$key] as $_slug => $bool ){
                    if( $bool ){
                        $slug = $_slug;
                        $templates[$key][$slug] = null;
                        break; // <- not possible to know how multiple POTs might be paired up
                    }
                }
                //printf("Pairing unprefixed files in %s to '%s' <br />\n", $key, $slug );
                $unique[$key][$slug] = $dir;
                // done with unprefixed localised files in this directory
                $localised[$key][''] = null;
            }
            // add any orphaned translations (those with no template matched)
            foreach( $localised[$key] as $slug => $bool ){
                if( $bool ){
                    // printf("Picked up orphoned locales in %s as '%s' <br />\n", $key, $slug );
                    $unique[$key][$slug] = $dir;
                }
            }
            // add any orphaned templates (those with no localised files matched)
            foreach( $templates[$key] as $slug => $bool ){
                if( $bool ){
                    //printf("Picked up orphoned template in %s as '%s' <br />\n", $key, $slug );
                    $unique[$key][$slug] = $dir;
                }
            }
        }

        unset( $groups, $localised, $templates );

               
        // final iteration adds unique projects to bundle
        
        foreach( $unique as $key => $sets ){
            foreach( $sets as $slug => $dir ){
                $name = ucfirst( strtr( $slug, '-_', '  ' ) );
                $domain = new Loco_package_TextDomain( $slug );
                $project = $domain->createProject( $bundle, $name );
                $project->addTargetDirectory($dir);
                $bundle->addProject($project);
            }
            // TODO how to prevent overlapping sets by adding each other's files to exclude lists
        }
        
        
        return $bundle;
    }    
    
    
    
}