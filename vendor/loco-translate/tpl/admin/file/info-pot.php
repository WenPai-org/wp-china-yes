<?php
/**
 * File info for a template file (POT)
 */
$this->extend('info');
$this->start('header');
/* @var Loco_mvc_FileParams $file */
/* @var Loco_gettext_Metadata $meta */
?> 

    <div class="notice inline notice-info">
        <h3><?php esc_html_e('Template file','loco-translate')?></h3>
        <dl>
            <dt><?php self::e( __('File size','loco-translate') )?>:</dt>
            <dd><?php $file->e('size')?></dd>

            <dt><?php esc_html_e('File modified','loco-translate')?>:</dt>
            <dd><time><?php $file->date('mtime')?></time></dd>

            <dt><?php esc_html_e('Last extracted','loco-translate')?>:</dt>
            <dd><time><?php $params->date('potime')?></time></dd>
            
            <dt><?php echo esc_html_x('Source text','Editor','loco-translate')?>:</dt>
            <dd><?php echo esc_html( $meta->getTotalSummary() )?> <span>(<?php echo sprintf( _n('1 word','%s words', $words, 'loco-translate'), number_format_i18n($words) )?>)</span></dd>
        </dl>
   </div>
    
    <?php 
    if( 'POT' !== $file->type && ! $params->isTemplate ):?> 
    <div class="notice inline notice-debug">
        <h3 class="has-icon">
            Unconventional file name
        </h3>
        <p>
            Template files should have the extension ".pot".<br />
            If this is intended to be a translation file it should end with a language code.
        </p>
    </div><?php
    endif; 