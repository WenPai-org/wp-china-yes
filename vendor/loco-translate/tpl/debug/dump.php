<?php
/**
 * Debug snippet: dumps current argument scope 
 */
 
?><dl class="debug"><?php
foreach( $params as $prop => $value ): if( '_' !== substr($prop,0,1) ):?> 
    <dt><?php echo esc_html($prop)?></dt>
    <dd><?php echo esc_html( json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) )?></dd><?php
endif; endforeach?> 
</dl>