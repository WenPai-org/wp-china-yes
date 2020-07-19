<?php
/**
 * Bundle is configured by official author
 */
$this->extend('../setup');
$this->start('header');
?> 

    <div class="notice inline notice-success">
        <h3 class="has-icon">
            <?php esc_html_e('Official configuration','wp-china-yes')?>
        </h3>
        <p>
            <?php esc_html_e("This bundle's configuration is provided by the author",'wp-china-yes')?>.
            <?php esc_html_e("You can make changes in the Advanced tab if you need to override the current settings",'wp-china-yes')?>.
        </p>
        <?php echo $this->render('inc-nav')?> 
    </div>
