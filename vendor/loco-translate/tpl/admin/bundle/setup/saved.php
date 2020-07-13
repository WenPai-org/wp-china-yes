<?php
/**
 * Bundle is saved in database, but can be reset 
 */
$this->extend('../setup');
$this->start('header');
?> 

    <div class="notice inline notice-info">
        <h3 class="has-icon">
            <?php esc_html_e('Bundle configuration saved','loco-translate')?> 
        </h3>
        <p>
            <?php esc_html_e("This bundle's configuration is saved in the WordPress database",'loco-translate')?>.
        </p>
        <form action="" method="post" enctype="application/x-www-form-urlencoded">
            <p class="submit">
                <input type="submit" name="reset-setup" class="button button-danger" value="<?php esc_html_e('Reset config','loco-translate')?>" />
                <a href="<?php $tabs[2]->e('href')?>" class="button button-link has-icon icon-wrench"><?php esc_html_e('Edit config','loco-translate')?></a>
            </p>
            <?php $reset->_e()?> 
        </form>
    </div>
