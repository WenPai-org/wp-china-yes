<?php
/**
 * Binary MO hex view
 */
$this->extend('view');
$this->start('source');
?> 

     <div class="notice inline notice-info">
         <p>
             <?php esc_html_e('File is in binary MO format','loco-translate')?>.
         </p>
     </div>
     
    <div class="panel">
        <pre><?php
            // crude hex dump
            // TODO make dynamic - flowing to width + clicking bytes highlights right-hand character ranges
         
            $i = 0;
            $r = 0;
            $cols = 24;
            $line = array();
            $bytes = strlen($bin);
            // establish formatting of row offset, nbased on largest row number
            $rowfmt = sprintf( '%%0%uX | ', strlen( sprintf( '%02X', $cols * floor( $bytes / $cols ) ) ) );
            
            for( $b = 0; $b < $bytes; $b++ ){
                $c = substr($bin,$b,1);
                $n = ord($c);
                // print byte offset
                if( ! $line ){
                    printf( $rowfmt, $b );
                }
                // print actual byte
                printf('%02X ', $n );
                // add printable to line
                if( $n === 9 ){
                    $line[] = ' '; // <- tab?
                }
                else if ( $n < 32 || $n > 126 ) {
                    $line[] = '.'; // <- unprintable
                }
                else {
                    $line[] = $params->escape($c); // <- printable
                }
                // wrap at cols, and print plain text
                if( ++$i === $cols ){
                    echo '  ', implode('', $line ), "\n";
                    $line = array();
                    $i = 0;
                    $r++;
                }
            }
            if( $line ){
                if( $r ){
                   echo str_repeat( '   ', $cols - $i );
                }
                echo '  ', implode('', $line ), "\n";
            }
            ?></pre>
    </div>        