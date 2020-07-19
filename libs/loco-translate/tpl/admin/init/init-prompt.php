<?php
/**
 * Notice before creating a PO that there is no POT
 */
$this->extend('../layout');
$help = apply_filters('loco_external','https://localise.biz/wordpress/plugin/manual/templates');
?> 
    <div class="notice inline notice-warning">
        <h3 class="has-icon">
            <?php esc_html_e('Template missing','loco-translate')?> 
        </h3><?php
        if( $params->has('pot') ):?> 
        <p>
            <?php esc_html_e("This bundle's template file doesn't exist yet. We recommend you create it before adding languages",'loco-translate')?>.
        </p><?php
        else:?> 
        <p>
            <?php esc_html_e("This bundle doesn't define a translations template file",'loco-translate')?>.
        </p><?php
        endif?> 
        <p>
            <a href="<?php $ext->e('link')?>" class="button button-link has-icon icon-add"><?php $ext->e('text')?></a>
            <a href="<?php $skip->e('link')?>" class="button button-link has-icon icon-next"><?php $skip->e('text')?></a><?php
            if( $this->has('conf') ):?> 
            <a href="<?php $conf->e('link')?>" class="button button-link has-icon icon-wrench"><?php $conf->e('text')?></a><?php
            endif?> 
            <a class="button button-link has-icon icon-help" href="<?php echo esc_url($help)?>" target="_blank"><?php esc_html_e('About templates','loco-translate')?></a>
        </p>
    </div><?php

    $help = apply_filters('loco_external','https://localise.biz/wordpress/plugin/faqs/template-missing');
    ?> 
    <div class="notice inline notice-info">
        <h3 class="has-icon"><?php esc_html_e('Suggestion','loco-translate')?></h3>
        <p>
            <?php esc_html_e('You can use existing translations as a template using the Copy feature.','loco-translate')?> 
        </p>
        <p>
            <a class="button button-link has-icon icon-back" href="<?php $tabs[0]->e('href')?>"><?php esc_html_e('Back','default')?></a>
            <a class="button button-link has-icon icon-help" href="<?php echo esc_url($help)?>" target="_blank"><?php esc_html_e('See FAQ','loco-translate')?></a>
        </p>
    </div>