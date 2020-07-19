<?php
/**
 * Information page when a file has no backups.
 */
$this->extend('../layout');
$help = esc_url( apply_filters('loco_external','https://localise.biz/wordpress/plugin/manual/settings') );

?> 

    <div class="notice inline notice-warning">
        <h3><?php 
            esc_html_e('No previous file revisions','loco-translate')?> 
        </h3>
        <p><?php
        if( $enabled ):
            esc_html_e('Backup files will be written when you save translations from Loco Translate editor','loco-translate');
        else:
            esc_html_e('File backups are disabled in your plugin settings','loco-translate');
        endif?>.
        </p>
        <p class="submit">
            <a href="<?php echo $help?>#po" target="_blank"><?php esc_html_e('Documentation','loco-translate')?></a>
            <span>|</span>
            <a href="<?php $this->route('config')->e('href')?>"><?php esc_html_e('Settings','loco-translate')?></a>
        </p>
    </div>
