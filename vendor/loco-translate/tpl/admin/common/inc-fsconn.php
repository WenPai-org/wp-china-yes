<?php
/**
 * Include standard file system connect dialog
 */

    $help = esc_url( apply_filters('loco_external','https://localise.biz/wordpress/plugin/manual/filesystem') );

    // Total file lock prevents any kind of update, regardless of connection
    if( $params->has('fsLocked') ):?> 
    <div class="has-nav notice inline notice-locked">
        <p>
            <strong class="has-icon"><?php esc_html_e('Locked','loco-translate')?>:</strong>
            <span><?php $params->e('fsLocked')?>.</span>
        </p>
        <nav>
            <a href="<?php echo $help?>#wp" target="_blank"><?php esc_html_e('Documentation','loco-translate')?></a>
            <span>|</span>
            <a href="<?php $this->route('config')->e('href')?>#loco--fs-protect"><?php esc_html_e('Settings','loco-translate')?></a>
        </nav>
    </div><?php


    // else specific file may be protected from updates by the bundle config
    elseif( $params->has('fsDenied') ):?>
    <div class="has-nav notice inline notice-locked">
    <p>
        <strong class="has-icon"><?php esc_html_e('Read only','loco-translate')?>:</strong>
        <span><?php esc_html_e('File is protected by the bundle configuration','loco-translate')?>.</span>
    </p>
    </div><?php


    // else render remote connection form
    else:?> 
    <div id="loco-fs-warn" class="has-nav notice inline notice-info jshide">
        <p>
            <strong class="has-icon"><?php esc_html_e('Notice','loco-translate')?>:</strong>
            <span class="loco-msg"><!-- warning to be loaded by ajax --></span>
        </p>
        <nav>
            <a href="<?php echo $help?>#wp" target="_blank"><?php esc_html_e('Documentation','loco-translate')?></a>
            <span>|</span>
            <a href="<?php $this->route('config')->e('href')?>#loco--fs-protect"><?php esc_html_e('Settings','loco-translate')?></a>
        </nav>
    </div>
    <form id="loco-fs" class="has-nav notice inline notice-locked jshide jsonly">
        <p>
            <strong class="has-icon"><?php
                // Translators: When a file or folder cannot be modified due to filesystem permissions
                esc_html_e('Write protected','loco-translate')?>:
            </strong>
            <span class="loco-msg">
                <!-- specific reason to be loaded by ajax -->
            </span>
            <span><?php 
                esc_html_e('Click "Connect" to authenticate with the server','loco-translate')?>.
            </span>
        </p>
        <nav>
            <button type="button" class="button button-small button-primary"><?php esc_html_e('Connect','loco-translate')?></button>
            <a class="button button-small" href="<?php echo $help?>#remote" target="_blank"> ? </a>
        </nav><?php
        $fsFields->_e();?> 
    </form><?php
    endif;
