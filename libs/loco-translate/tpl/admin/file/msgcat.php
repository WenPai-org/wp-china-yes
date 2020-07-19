<?php
    
    echo $this->render('../common/inc-table-filter');?> 
    
    <div class="panel loco-loading" id="loco-po">
        <ol class="msgcat"><?php
            foreach( $lines as $i => $line ):?> 
            <li id="po-l<?php printf('%u',$i+1)?>"><?php
            // may be totally blank line
            if( '' === $line ){
                echo '<span class="po-none"></span>';
                continue;
            }
            // may be a comment line
            if( '#' === substr($line,0,1) ){
                // may be able to parse out references
                $symbol = (string) substr($line,1,1);
                if( '' !== $symbol ){
                    $line = substr($line,2);
                    if( ':' === $symbol ){
                        echo '<span class="po-refs">#:',preg_replace('/\\S+:\d+/', '<a href="#\\0" class="po-text">\\0</a>', $params->escape($line) ),'</span>';
                    }
                    // parse out flags and formatting directives
                    else if( ',' === $symbol ){
                        echo '<span class="po-flags">#,<span class="po-text">',preg_replace('/[-a-z]+/', '<em>\\0</em>', $params->escape($line) ),'</span></span>';
                    }
                    // else treat as normal comment even if empty
                    else {
                        echo '<span class="po-comment">#',$symbol,'<span class="po-text">',$params->escape($line),'</span></span>';
                    }
                }
                // else probably an empty comment
                else {
                    echo '<span class="po-comment">',$params->escape($line),'</span>';
                }
                continue;
            }
            // grab keyword if there is one before quoted string
            if( preg_match('/^(msg[_a-z0-9\\[\\]]+)(\\s+)/', $line, $r ) ){
                echo '<span class="po-word">',$params->escape($r[1]),'</span><span class="po-space">',$params->escape($r[2]),'</span>';
                $line = substr( $line, strlen($r[0]) );
            }
            // remainder of line (or whole line) should be a quoted string
            if( preg_match('/^"(.*)"\s*$/', $line, $r ) ){
                echo '<span class="po-string">&quot;<span class="po-text">',$params->escape($r[1]),'</span>&quot;</span>';
                continue;
            }
            
            // else print whatever junk is left of line
            echo '<span class="po-junk">',$params->escape($line),'</span>';
            
            ?></li><?php
            endforeach?>
        </ol>
    </div>
