<?php
/**
 * File is actually a directory
 */
$this->extend('../layout');

?>

    <div class="notice inline notice-error">
        <h3 class="has-icon">
            <?php esc_html_e('File is a directory','loco-translate')?> 
        </h3>
        <p>
            <?php esc_html_e("This page was expecting a file, but the path is actually a directory",'loco-translate')?>:
        </p>
        <p>
            <code><?php $info->e('relpath')?></code>
        </p>
    </div>