<?php
/**
 * Show bundle diagnostics
 */
$this->extend('../layout');
?> 
    <p>
        <?php esc_html_e('This information is for developers to find problems in the bundle setup','loco-translate')?>.
    </p>
    <p data-vendor="<?php $meta->e('vendor')?>">
        <?php echo $meta->author?> 
    </p>
    
    <?php
    /* @var $notice Loco_mvc_ViewParams */
    foreach( $notices as $notice ):?> 
    <div class="<?php $notice->e('style')?>">
        <p>
            <strong class="has-icon"> </strong> 
            <?php $notice->e('body')?> 
        </p>
    </div><?php
    endforeach;
    
    if( $params->has('xml') ):?> 
    <div class="notice inline notice-generic">
        <h4>Current configuration as XML:</h4>
        <pre><?php $params->e('xml')?></pre>
    </div><?php
    endif?> 