<?php
/**
 * POT source view
 */
$this->extend('view');
$this->start('source');
?> 
    <h3 class="has-lang">
        <span><?php esc_html_e('Template file','loco-translate')?>:</span>
        <span class="loco-meta">
            <span><?php echo esc_html_x('Updated','Modified time','loco-translate')?>:</span>
            <span id="loco-po-modified"><?php $params->date('modified')?></span>
            &ndash;
            <span id="loco-po-status"><?php echo esc_html( $meta->getTotalSummary() )?></span>
        </span>
    </h3>

    <?php
    echo $this->render('msgcat');
