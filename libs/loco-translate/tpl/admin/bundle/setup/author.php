<?php
/**
 * Bundle is configured by official author
 */
$this->extend('../setup');
$this->start('header');
?> 

    <div class="notice inline notice-success">
        <h3 class="has-icon">
            <?php esc_html_e('Official configuration','loco-translate')?> 
        </h3>
        <p>
            <?php esc_html_e("This bundle's configuration is provided by the author",'loco-translate')?>.
            <?php esc_html_e("You can make changes in the Advanced tab if you need to override the current settings",'loco-translate')?>.
        </p>
        <?php echo $this->render('inc-nav')?> 
    </div>
