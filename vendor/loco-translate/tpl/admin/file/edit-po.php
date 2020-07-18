<?php
/**
 * PO file editor
 */
$this->extend('editor');
$this->start('header');

echo $this->render('../common/inc-po-header');


/* @var Loco_mvc_ViewParams $js */
$help = apply_filters('loco_external','https://localise.biz/wordpress/plugin/manual/providers');

// inline modal for auto-translate. Note that modal will be placed outside of #loco.wrap element
if( $js->apis ):?> 
<div id="loco-auto" class="loco-batch" title="<?php esc_html_e('Auto-translate this file','loco-translate');?>">
    <form action="#">
        <fieldset>
            <select name="api" id="auto-api"><?php foreach( $js->apis as $api ):?> 
                <option value="<?php self::e($api['id'])?>"><?php self::e($api['name'])?></option><?php 
                endforeach?> 
            </select>
        </fieldset>
        <fieldset>
            <p>
                <label for="auto-existing">
                    <input type="checkbox" id="auto-existing" name="existing" />
                    <?php esc_html_e('Overwrite existing translations','loco-translate')?> 
                </label>
            </p>
            <p>
                <label for="auto-fuzzy">
                    <input type="checkbox" id="auto-fuzzy" name="fuzzy" />
                    <?php esc_html_e('Mark new translations as Fuzzy','loco-translate')?> 
                </label>
            </p>
            <blockquote id="loco-job-progress">
                Initializing...
            </blockquote>
            <p>
                <button type="submit" class="button button-primary has-icon icon-translate">
                    <span><?php esc_html_e('Translate','loco-translate')?></span>
                </button>
                <a href="<?php self::e($help)?>" class="button button-link has-icon icon-help" target="_blank"><?php
                    esc_html_e('Help','loco-translate');
                ?></a>
            </p>
        </fieldset>
    </form>
</div><?php



// inline modal for when no APIs are configured.
else:?> 
<div id="loco-auto" class="loco-alert" title="<?php esc_html_e('No translation APIs configured','loco-translate');?>">
    <p>
        <?php esc_html_e('Add automatic translation services in the plugin settings.','loco-translate')?>
    </p>
    <nav>
        <a href="<?php $this->route('config-apis')->e('href')?>" class="has-icon icon-cog"><?php
            esc_html_e('Settings','loco-translate');
        ?></a>
        <a href="<?php self::e($help)?>" class="has-icon icon-help" target="_blank"><?php
            esc_html_e('Help','loco-translate');
        ?></a>
    </nav>
</div><?php
endif;
