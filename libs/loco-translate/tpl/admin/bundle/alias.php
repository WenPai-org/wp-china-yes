<?php
/**
 * Special case for viewing Hello Dolly plugin
 * TODO implement package aliasing in a generic fashion as part of bundle configuration.
 */
$this->extend('../layout');
?> 
    
    <div class="notice inline notice-info">
        <h3 class="has-icon">
            <?php esc_attr_e('"Hello Dolly" is part of the WordPress core','loco-translate')?> 
        </h3>
        <p>
            <?php esc_html_e("This plugin doesn't have its own translation files, but can be translated in the default text domain", 'loco-translate')?>.
        </p>
        <p>
            <a href="<?php $params->e('redirect')?>"><?php esc_html_e('Go to WordPress Core','loco-translate')?></a>
        </p>
    </div>
