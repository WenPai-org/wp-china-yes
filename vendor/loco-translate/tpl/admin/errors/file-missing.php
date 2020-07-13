<?php
/**
 * File path not found
 */
$this->extend('../layout');

?>

    <div class="notice inline notice-error">
        <h3 class="has-icon">
            <?php esc_html_e('File not found','loco-translate')?> 
        </h3>
        <p>
            <?php esc_html_e("Either this file is missing or the server doesn't have permission to access it",'loco-translate')?>:
        </p>
        <p>
            <code class="path"><?php $params->e('path')?></code>
        </p>
    </div>