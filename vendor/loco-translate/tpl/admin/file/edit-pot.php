<?php
/**
 * POT file editor
 */
$this->extend('editor');
$this->start('header');
?>

    <h3 class="has-lang">
        <span><?php esc_html_e('Template file','loco-translate')?>:</span>
        <span class="loco-meta">
            <span><?php echo esc_html_x('Updated','Modified time','loco-translate')?>:</span>
            <span id="loco-po-modified"><?php $params->date('modified')?></span>
            &ndash;
            <span id="loco-po-status"></span>
        </span>
    </h3>


    <div id="loco-auto" title="Error">
        <p>Template files cannot be translated</p>
    </div>
