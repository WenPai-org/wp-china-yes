<?php
/**
 * Object representing a file's permission bits
 */
class Loco_fs_FileMode {
    
    /**
     * inode protection mode
     * @var int
     */
    private $i;


    /**
     * Instantiate from integer file mode
     */
    public function __construct( $mode ){
        $this->i = (int) $mode;
    }


    /**
     * @return string
     */
    public function __toString(){
        return sprintf('%03o', $this->i & 07777 );
    }


    /**
     * rwx style friendly formatting
     * @return string
     */
    public function format(){
        $mode = $this->i;
        $setuid = $mode & 04000;
        $setgid = $mode & 02000;
        $sticky = $mode & 01000;
        return 
            $this->type().
            
            ( $mode & 0400 ? 'r' : '-' ).
            ( $mode & 0200 ? 'w' : '-' ).
            ( $mode & 0100 ? ($setuid?'s':'x') : ($setuid?'S':'-') ).

            ( $mode & 0040 ? 'r' : '-' ).
            ( $mode & 0020 ? 'w' : '-' ).
            ( $mode & 0010 ? ($setgid?'s':'x') : ($setgid?'S':'-') ).

            ( $mode & 0004 ? 'r' : '-' ).
            ( $mode & 0002 ? 'w' : '-' ).
            ( $mode & 0001 ? ($sticky?'t':'x') : ($sticky?'T':'-') );
    }



    /**
     * File type bit field:
     * http://man7.org/linux/man-pages/man2/stat.2.html
     */
    public function type(){
        $mode = $this->i & 0170000;
        switch( $mode ){
        case 0010000:
            return '-';
        case 0040000:
            return 'd';
        case 0120000:
            return 'l';
        case 0140000:
            return 's';
        case 0060000:
            return 'c';
        default:
            return '-';
        }
    }
    
}