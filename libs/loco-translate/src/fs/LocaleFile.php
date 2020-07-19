<?php
/**
 * A file with metadata about the locale it relates to
 */
class Loco_fs_LocaleFile extends Loco_fs_File {
    
    /**
     * @var Loco_Locale
     */
    private $locale;
    
    /**
     * @var string
     */
    private $suffix;
    
    /**
     * @var string
     */
    private $prefix;


    /**
     * Lazy handling of localized path info
     * @return array [ prefix, suffix ]
     */
    public function split(){
        if( is_null($this->suffix) ){
            $parts = explode( '-', $this->filename() );
            $this->suffix = array_pop( $parts );
            $this->prefix = implode( '-', $parts );
            // handle situations where suffixless name is wrongly taken as the prefix
            // e.g. "de.po" is valid but "hello.po" is not. 
            // There are still some  ambiguous situations, e.g. "foo-bar.po" is valid, but nonsense
            if( ! $this->prefix && ! $this->getLocale()->isValid() ){
                $this->prefix = $this->suffix;
                $this->suffix = '';
                $this->locale = null;
            }
        }
        return array( $this->prefix, $this->suffix );
    }
    
    
    /**
     * @return Loco_Locale
     */
    public function getLocale(){
        if( ! $this->locale ){
            if( $tag = $this->getSuffix() ){
                $this->locale = Loco_Locale::parse($tag);
            }
            else {
                $this->locale = new Loco_Locale('');
            }
        }
        return $this->locale;
    }


    /**
     * @param Loco_locale
     * @return Loco_fs_LocaleFile
     */
    public function cloneLocale( Loco_locale $locale ){
        $this->split();
        $path = (string) $locale;
        if( $str = $this->prefix ){
            $path = $str.'-'.$path;
        }
        if( $str = $this->extension() ){
            $path .= '.'.$str;
        }
        if( $dir = $this->getParent() ){
            $path = $dir->getPath().'/'.$path;
        }
        return new Loco_fs_LocaleFile($path);
    }

    

    /**
     * Get prefix (or stem) from name that comes before locale suffix.
     * @return string
     */
    public function getPrefix(){
        $info = $this->split();
        return $info[0];
    }
    

    /**
     * Get suffix (or locale code) from name that comes after "-" separator
     * @return string
     */
    public function getSuffix(){
        $info = $this->split();
        return $info[1];
    }


    /**
     * Test if file is suffix only, e.g. "en_US.po"
     * @return bool
     */
    public function hasSuffixOnly(){
        $info = $this->split();
        return $info[1] && ! $info[0];
    }


    /**
     * Test if file is prefix only, e.g. "incorrect.po"
     * @return bool
     */
    public function hasPrefixOnly(){
        $info = $this->split();
        return $info[0] && ! $info[1];
    }
        
}
