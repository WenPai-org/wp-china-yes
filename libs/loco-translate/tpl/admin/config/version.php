<?php
/**
 * Plugin version information
 */
$this->extend('../layout');


    if( $params->has('update') ):?> 
    <div class="notice inline notice-warning">
        <h3 class="has-icon">
            <?php self::e( __('Version %s','loco-translate'), $version )?> 
        </h3>
        <p>
            <?php esc_html_e( __('A newer version of Loco Translate is available for download','loco-translate') )?>.
        </p>
        <p class="submit">
            <a class="button button-primary" href="<?php echo $update_href?>"><?php self::e(__('Upgrade to %s','loco-translate'), $update )?></a>
            <a class="button button-link has-icon icon-ext" href="https://wordpress.org/plugins/loco-translate/installation/" target="_blank"><?php esc_html_e( __('Install manually','loco-translate') )?></a>
        </p>
    </div><?php

    elseif( $params->has('devel') ):?> 
    <div class="notice inline notice-debug">
        <h3 class="has-icon">
            <?php self::e( __('Version %s','loco-translate'), $version )?> 
        </h3>
        <p>
            <?php esc_html_e("You're running a development snapshot of Loco Translate",'loco-translate')?> 
        </p>
    </div><?php

    else:?> 
    <div class="notice inline notice-success">
        <h3 class="has-icon">
            <?php self::e( __('Version %s','loco-translate'), $version)?> 
        </h3>
        <p>
            <?php esc_html_e("You're running the latest version of Loco Translate",'loco-translate')?> 
        </p>
    </div><?php
    endif;
