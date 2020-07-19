<?php
/**
 * File info for a translation source PO
 */
$this->extend('info');
$this->start('header');
/* @var Loco_mvc_FileParams $file */
/* @var Loco_gettext_Metadata $meta */
?> 

    <div class="notice inline notice-info">
        <h3>
            <a href="<?php $locale->e('href')?>" class="has-lang">
                <span class="<?php $locale->e('icon')?>" lang="<?php $locale->e('lang')?>"><code><?php $locale->e('code')?></code></span>
                <span><?php $locale->e('name')?></span>
            </a>
        </h3>
        <dl>
            <dt><?php self::e( __('File size','wp-china-yes') )?>:</dt>
            <dd><?php $file->e('size')?></dd>

            <dt><?php self::e( __('File modified','wp-china-yes') )?>:</dt>
            <dd><time><?php $file->date('mtime')?></time></dd>

            <dt><?php self::e( __('Last translation','wp-china-yes') )?>:</dt>
            <dd><?php $params->e('author')?> &mdash; <date><?php $params->date('potime')?></date></dd>
            
            <dt><?php self::e( __('Translation progress','wp-china-yes') )?>:</dt>
            <dd>
                <?php self::e( $meta->getProgressSummary() )?> 
            </dd>
            <dd>
                <?php $meta->printProgress()?> 
            </dd>
        </dl>
    </div>
 
    <?php
    if( ! $sibling->existent ):?> 
    <div class="notice inline notice-warning">
        <h3 class="has-icon">
            <?php self::e( __('Binary file missing','wp-china-yes') )?> 
        </h3>
        <p>
            <?php self::e( __("We can't find the binary MO file that belongs with these translations",'wp-china-yes') )?>.
        </p>
    </div><?php
    endif;


    if( $params->has('potfile') ):
    if( $potfile->synced ):?> 
    <div class="notice inline notice-success">
        <h3 class="has-icon">
            <?php self::e( __('In sync with template','wp-china-yes') )?> 
        </h3>
        <p>
            <?php // Translators: Where %s is the name of a template file
            self::e( __('PO file has the same source strings as "%s"','wp-china-yes'), $potfile->name )?>.
        </p>
    </div><?php

    else:?> 
    <div class="notice inline notice-info">
        <h3 class="has-icon">
            <?php self::e( __('Out of sync with template','wp-china-yes') )?> 
        </h3>
        <p>
            <?php // Translators: Where %s is the name of a template file
            self::e( __('PO file has different source strings to "%s". Try running Sync before making any changes.','wp-china-yes'), $potfile->name )?> 
        </p>
    </div><?php
    endif;
    
    // only showing missing template warning when project was matched. Avoids confusion if something went wrong
    elseif( $params->has('project') ):?> 
    <div class="notice inline notice-debug">
        <h3 class="has-icon">
            <?php self::e( __('Missing template','wp-china-yes') )?> 
        </h3>
        <p>
            <?php
            self::e( __('These translations are not linked to a POT file. Sync operations will extract strings directly from source code.','wp-china-yes') )?> 
        </p>
    </div><?php
    endif;
