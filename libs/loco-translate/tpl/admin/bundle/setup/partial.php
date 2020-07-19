<?php
/**
 * Bundle is set up from self-declared metadata, but has some missing bits
 */
$this->extend('../setup');
$this->start('header');
?> 

    <div class="notice inline notice-warning">
        <h3 class="has-icon icon-info">
            <?php esc_html_e('Partially configured bundle','wp-china-yes')?> 
        </h3>
        <p>
            <?php esc_html_e("This bundle's configuration has been automatically detected, but isn't fully complete",'wp-china-yes')?>.
        </p>
        <?php echo $this->render('inc-nav')?> 
    </div>
    