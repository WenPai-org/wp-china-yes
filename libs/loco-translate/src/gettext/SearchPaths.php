<?php
/**
 * A file finder built from search path references in a PO/POT file
 */
class Loco_gettext_SearchPaths extends Loco_fs_FileFinder {
    
    
    /**
     * Look up a relative file reference against search paths
     * @param string relative file path reference
     * @return Loco_fs_File
     */
    public function match( $ref ){
        $excluded = new Loco_fs_Locations( $this->getExcluded() );
        /* @var Loco_fs_Directory */
        foreach( $this->getRootDirectories() as $base ){
            $file = new Loco_fs_File($ref);
            $path = $file->normalize( (string) $base );
            if( $file->exists() && ! $excluded->check($path) ){
                return $file;
            }
        }
    }



    /**
     * Build search paths from a given PO/POT file that references other files
     * @return Loco_gettext_SearchPaths
     */
    public function init( Loco_fs_File $pofile, LocoHeaders $head = null ){
        if( is_null($head) ){
            loco_require_lib('compiled/gettext.php');
            $head = LocoPoHeaders::fromSource( $pofile->getContents() );
        }
        $ninc = 0;
        foreach( array('Poedit') as $vendor ){
            $key = 'X-'.$vendor.'-Basepath';
            if( ! $head->has($key) ){
                continue;
            }
            $dir = new Loco_fs_Directory( $head[$key] );   
            $base = $dir->normalize( $pofile->dirname() );
            // base should be absolute, with the following search paths relative to it
            $i = 0;
            while( true ){
                $key = sprintf('X-%s-SearchPath-%u', $vendor, $i++);
                if( ! $head->has($key) ){
                    break;
                }
                // map search path to given base
                $include = new Loco_fs_File( $head[$key] );
                $include->normalize( $base );
                if( $include->exists() ){
                    if( $include->isDirectory() ){
                        $this->addRoot( (string) $include );
                        $ninc++;
                    }
                    /*else {
                        TODO force specific file in Loco_fs_FileFinder
                    }*/
                }
            }
            // exclude from search paths
            $i = 0;
            while( true ){
                $key = sprintf('X-%s-SearchPathExcluded-%u', $vendor, $i++);
                if( ! $head->has($key) ){
                    break;
                }
                // map excluded path to given base
                $exclude = new Loco_fs_File( $head[$key] );
                $exclude->normalize($base);
                if( $exclude->exists() ){
                     $this->exclude( (string) $exclude );
                }
                // TODO implement wildcard exclusion
            }
        }

        // Add po file location if no proprietary headers used
        if( ! $ninc ){
            $this->addRoot( $pofile->dirname() );
        }

        return $this;
    }
    
    
}