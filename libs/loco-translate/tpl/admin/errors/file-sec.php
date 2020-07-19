<?php
/**
 * File security issue
 */
$this->extend('../layout');

?>

<div class="notice inline notice-error">
    <h3 class="has-icon">
        <?php esc_html_e('File access disallowed','loco-translate')?> 
    </h3>
    <p>
        <?php esc_html_e("Access to this file is blocked for security reasons",'loco-translate')?>:
        <strong><?php $params->e('reason')?></strong>
    </p>
</div>