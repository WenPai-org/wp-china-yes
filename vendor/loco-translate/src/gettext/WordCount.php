<?php
/**
 * Experimental PO/POT file word counter.
 * Word counts are approximate, including numbers and sprintf tokens.
 * Currently only used for source words in latin script, presumed to be in English.
 */
class Loco_gettext_WordCount implements Countable {

    /**
     * @var LocoPoIterator
     */
    private $po;

    /**
     * Source Words: Cached count of "msgid" fields, presumed en_US
     * @var int
     */
    private $sw;



    /**
     * Create counter for a pre-parsed PO/POT file.
     */
    public function __construct( Loco_gettext_Data $po ){
        $this->po = $po;
    }



    /**
     * @internal
     */
    private function countField( $f ){
        $n = 0;
        foreach( $this->po as $r ){
            $n += self::simpleCount( $r[$f] );
        }
        return $n;
    }



    /**
     * Default count function returns source words (msgid) in current file.
     * @return int
     */
    public function count(){
        $n = $this->sw;
        if( is_null($n) ){
            $n = $this->countField('source');
            $this->sw = $n;
        }
        return $n;
    }



    /**
     * Very simple word count, only suitable for latin characters, and biased toward English.
     * @param string
     * @return int
     */
    public static function simpleCount( $str ){
        $n = 0;
        if( is_string($str) && '' !== $str ){

            // TODO should we strip PHP string formatting?
            // e.g. "Hello %s" currently counts as 2 words.
            // $str = preg_replace('/%(?:\\d+\\$)?(?:\'.|[-+0 ])*\\d*(?:\\.\\d+)?[suxXbcdeEfFgGo%]/', '', $str );

            // Strip HTML (but only if open and close tags detected, else "< foo" would be stripped to nothing
            if( false !== strpos($str,'<') && false !== strpos($str,'>') ){
                $str = strip_tags($str);
            }

            // always html-decode, else escaped punctuation will be counted as words
            $str = html_entity_decode( $str, ENT_QUOTES, 'UTF-8');

            // Collapsing apostrophe'd words into single units:
            // Simplest way to handle ambiguity of "It's Tim's" (technically three words in English)
            $str = preg_replace('/(\\w+)\'(\\w)(\\W|$)/u', '\\1\\2\\3', $str );
            
            // Combining floating numbers into single units
            // e.g. "£1.50" and "€1,50" should be one word each
            $str = preg_replace('/\\d[\\d,\\.]+/', '0', $str );

            // count words by standard Unicode word boundaries
            $words = preg_split( '/\\W+/u', $str, -1, PREG_SPLIT_NO_EMPTY );
            $n += count($words);

            /*/ TODO should we exclude some words (like numbers)?
            foreach( $words as $word ){
                if( ! ctype_digit($word) ){
                    $n++;
                }
            }*/
        }
        return $n;
    }

}
