<?php
/**
 * Bundle is set up fully from self-declared metadata
 */
$this->extend('../setup');
$this->start('header');
?> 

    <div class="notice inline notice-success">
        <h3 class="has-icon">
            <?php esc_html_e('Bundle auto-configured','loco-translate')?> 
        </h3>
        <p>
            <?php esc_html_e("This bundle's configuration has been automatically detected and seems to be fully compatible",'loco-translate')?>.
            <?php esc_html_e("You can make changes in the Advanced tab if you need to override the current settings",'loco-translate')?>.
        </p>
        <?php echo $this->render('inc-nav')?> 
    </div>
