<?php
/**
 * Bundle is set up internally
 */
$this->extend('../../layout');
?> 

    <div class="notice inline notice-success">
        <h3 class="has-icon">
            <?php esc_html_e('Bundle auto-configured','wp-china-yes')?> 
        </h3>
        <p>
            <?php esc_html_e("This bundle's configuration is built into Loco",'wp-china-yes')?>.
            <?php esc_html_e("You can make changes in the Advanced tab if you need to override the current settings",'wp-china-yes')?>.
        </p>
        <p class="submit">
            <a href="<?php $tabs[2]->e('href')?>" class="button button-link has-icon icon-wrench"><?php esc_html_e('Advanced configuration','wp-china-yes')?></a>
        </p>
    </div>
    