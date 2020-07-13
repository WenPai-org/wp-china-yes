<?php
/**
 * Bundle is set up internally
 */
$this->extend('../../layout');
?> 

    <div class="notice inline notice-success">
        <h3 class="has-icon">
            <?php esc_html_e('Bundle auto-configured','loco-translate')?> 
        </h3>
        <p>
            <?php esc_html_e("This bundle's configuration is built into Loco",'loco-translate')?>.
            <?php esc_html_e("You can make changes in the Advanced tab if you need to override the current settings",'loco-translate')?>.
        </p>
        <p class="submit">
            <a href="<?php $tabs[2]->e('href')?>" class="button button-link has-icon icon-wrench"><?php esc_html_e('Advanced configuration','loco-translate')?></a>
        </p>
    </div>
    