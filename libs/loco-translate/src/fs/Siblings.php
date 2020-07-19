<?php
/**
 * Manages a POT or PO/MO pair and its on-disk dependants
 */
class Loco_fs_Siblings {

    /**
     * @var Loco_fs_File
     */
    private $po;

    /**
     * @var Loco_fs_File
     */
    private $mo;

    
    public function __construct( Loco_fs_File $file ){
        $ext = $file->extension();
        if( 'pot' === $ext ){
            $this->po = $file;
        }
        else if( 'po' === $ext ){
            $this->po = $file;
            $this->mo = $file->cloneExtension('mo');
        }
        else if( 'mo' === $ext ){
            $this->mo = $file;
            $this->po = $file->cloneExtension('po');
        }
        else {
            throw new InvalidArgumentException('Unexpected file extension: '.$ext);
        }
    }


    /**
     * Get all dependant files (including self) that actually exist on disk
     * @return Loco_fs_File[]
     */
    public function expand(){
        $siblings = array();
        // Source and binary pair
        foreach( array( $this->po, $this->mo ) as $file ){
            if( $file && $file->exists() ){
                $siblings[] = $file;
            }
        }
        // Revisions / backup files:
        $revs = new Loco_fs_Revisions( $this->po );
        foreach( $revs->getPaths() as $path ){
            $siblings[] = new Loco_fs_File( $path );
        }
        // JSON exports, unless in POT mode:
        if( 'po' === $this->po->extension() ){
            $name = $this->po->filename();
            $finder = new Loco_fs_FileFinder( $this->po->dirname() );
            $regex = '/^'.preg_quote($name,'/').'-[0-9a-f]{32}$/';
            /* @var $file Loco_fs_File */
            foreach( $finder->group('json')->exportGroups() as $files ) {
                foreach( $files as $file ){
                    $match = $file->filename();
                    if( $match === $name || preg_match($regex,$match) ) {
                        $siblings[] = $file;
                    }
                }
            }
        }

        return $siblings;
    }


    /**
     * @return Loco_fs_File
     */
    public function getSource(){
        return $this->po;
    }


    /**
     * @return Loco_fs_File
     */
    public function getBinary(){
        return $this->mo;
    }

}
