<?php
/**
 * File information for ony type of file. Extended by specific views for supported types
 */
$this->extend('../layout');
echo $header;
?> 

  
    <?php
    if( ! $file->existent ):?> 
    <div class="notice inline notice-error">
        <h3 class="has-icon">
            <?php esc_html_e("File doesn't exist",'loco-translate')?> 
        </h3>
        <p>
            <code><?php $file->e('relpath')?></code>
        </p>
    </div><?php    
    elseif( $file->writable ):?> 
    <div class="notice inline notice-success">
        <h3 class="has-icon">
            <?php // Translators: Where %s is the type of file, e.g. "po"
            self::e( __('%s file is writeable','loco-translate'), $file->type )?> 
        </h3>
        <p>
            <?php esc_html_e('You can update these translations directly from the editor to the file system','loco-translate')?>.
        </p>
        <p>
            <code><?php $file->ls()?></code>
        </p>
    </div><?php
    else:?> 
    <div class="notice inline notice-locked">
        <h3 class="has-icon">
            <?php esc_html_e('Write protected','loco-translate')?> 
        </h3>
        <p>
            <?php esc_html_e("This file can't be updated directly by the web server",'loco-translate')?>.
        </p>
        <p>
            <?php // Translators: Where %s is the name (or number) of an operating system user
            self::e( __("To make changes you'll have to connect to the remote file system, or make the file writeable by %s",'loco-translate'), $params->httpd )?>.
        </p>
        <p>
            <code><?php $file->ls()?></code>
        </p>
    </div><?php
    endif;


    if( ! $dir->existent ):?> 
    <div class="notice inline notice-error">
        <h3 class="has-icon">
            <?php esc_html_e("Directory doesn't exist",'loco-translate')?> 
        </h3>
        <p>
            <?php // Translators: "either" meaning that the file itself can't exist without a containing directory
            esc_html_e("The containing directory for this file doesn't exist either",'loco-translate')?>.
        </p>
        <p>
            <code><?php $dir->e('relpath')?></code>
        </p>
    </div><?php

    elseif( $dir->writable ):?> 
    <div class="notice inline notice-success">
        <h3 class="has-icon">
            <?php esc_html_e('Directory is writeable','loco-translate')?> 
        </h3>
        <p>
            <?php // Translators: Where %s is the name (or number) of an operating system user
            self::e( __('The containing directory is writeable by %s, so you can add new files in the same location','loco-translate'), $params->httpd )?>.
        </p>
        <p>
            <code><?php $dir->ls()?></code>
        </p><?php
        if( ! $file->deletable ):?> 
        <p>
            <span class="has-icon icon-warn"></span>
            <?php esc_html_e('Note that the file may not be deletable due to additional ownership permissions','loco-translate')?>.
        </p><?php
        endif;?> 
    </div><?php

    else:?> 
    <div class="notice inline notice-locked">
        <h3 class="has-icon">
            <?php esc_html_e('Write protected','loco-translate')?> 
        </h3>
        <p>
            <?php esc_html_e("This directory can't be written to directly by the web server",'loco-translate')?>.
        </p>
        <p>
            <?php // Translators: Where %s is the name (or number) of an operating system user
            self::e( __("To create new files here you'll have to connect to the remote file system, or make the directory writeable by %s",'loco-translate'), $params->httpd )?>.
        </p>
        <p>
            <code><?php $dir->ls()?></code>
        </p>
    </div><?php
    endif;
    
    if( $file->autoupdate ):?> 
    <div class="notice inline notice-info">
        <h3 class="has-icon"><?php 
            esc_html_e('WordPress updates','loco-translate')?> 
        </h3>
        <p><?php 
            esc_html_e("Files in this location can be modified or deleted by WordPress automatic updates",'loco-translate')?>.
            <a target="_blank" href="<?php 
                echo esc_url( apply_filters('loco_external','https://localise.biz/wordpress/plugin/faqs/files-deleted') )?>"><?php
                esc_html_e("What's this?",'loco-translate');
            ?></a>
        </p>
    </div><?php
    endif;

    if( $params->has('debug') ):?> 
    <div class="notice inline notice-debug">
        <h3 class="has-icon">Developer notes</h3>
        <div><?php
        foreach( $debug as $prop => $raw ):?> 
        <p><?php $debug->e($prop)?></p><?php
        endforeach?> 
    </div><?php
    endif;
