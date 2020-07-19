<?php
/**
 * Bundle is set up fully from self-declared metadata
 */
$this->extend('../setup');
$this->start('header');
?> 

    <div class="notice inline notice-success">
        <h3 class="has-icon">
            <?php esc_html_e('Bundle auto-configured','wp-china-yes')?> 
        </h3>
        <p>
            <?php esc_html_e("This bundle's configuration has been automatically detected and seems to be fully compatible",'wp-china-yes')?>.
            <?php esc_html_e("You can make changes in the Advanced tab if you need to override the current settings",'wp-china-yes')?>.
        </p>
        <?php echo $this->render('inc-nav')?> 
    </div>
