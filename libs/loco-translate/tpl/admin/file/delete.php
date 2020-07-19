<?php
/**
 * Confirmation form for deleting a file
 */
$this->extend('../layout');
?> 

    <form action="" method="post" enctype="application/x-www-form-urlencoded" id="loco-del">
        <div class="notice inline notice-danger">
            <h3>
                <span class="has-icon icon-trash"> </span>
                <span><?php esc_html_e('Confirm delete','loco-translate')?></span>
            </h3>
            <p>
                <?php
                echo __('Are you sure you want to <strong>permanently</strong> delete the following file?','loco-translate')?> 
            </p>
            <p>
                <code><?php $info->e('relpath')?></code>
            </p><?php
            if( $params->has('deps') ):?> 
            <p>
                <strong><?php $params->e('warn')?></strong><?php
                foreach( $deps as $info ):?> 
                <div><?php $info->e('name')?></div><?php
                endforeach?> 
            </p><?php
            endif?> 
            <p class="submit">
                <button type="submit" class="button button-danger" disabled><?php esc_html_e('Delete Permanently','default')?></button>
            </p>
        </div>
        <?php
        /* @var Loco_mvc_HiddenFields $hidden */
        $hidden->_e();?> 
    </form>
