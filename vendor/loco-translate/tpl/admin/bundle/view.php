<?php
/**
 * Bundle overview
 */
$this->extend('../layout');

/* @var Loco_mvc_ViewParams[] $projects */
/* @var Loco_mvc_ViewParams[] $unknown */
if( $projects ):
foreach( $projects as $p ): ?> 
    <div class="loco-project" id="loco-<?php $p->e('id')?>"><?php
        
        // display package name, and slug if it differs.
        if( $p->name === $p->short ):?> 
        <h2><?php $p->e('name')?></h2><?php
        else:?> 
        <h2><?php $p->e('name')?> <span>(<?php $p->e('short')?>)</span></h2><?php
        endif;
        
        echo $this->render('inc-po-links', array( 'nav' => $p->nav ) );
        echo $this->render('inc-po-table', array( 'pairs' => $p->po, 'domain' => $p->domain ) );
        ?> 
                
    </div><?php
endforeach;
if( $unknown ):?> 
    <div class="loco-project">
        <div class="notice inline notice-info">
            <h2><?php esc_html_e('Additional files found','loco-translate')?></h2>
            <p>
                <?php
                esc_html_e("This bundle isn't fully configured, so we don't know what the following files are for",'loco-translate')?>. <?php
                // Translators: %s is a URL. Keep the <a> tag intact
                echo wp_kses(
                    sprintf( __('Click the <a href="%s">setup</a> tab to complete the bundle configuration','loco-translate'), $tabs[1]->href ),
                    array('a'=>array('href'=>true)), array('http','https')
                );?>.
            </p>
        </div>
        <?php 
        echo $this->render('../common/inc-table-filter');
        echo $this->render('inc-po-table', array( 'pairs' => $unknown, 'domain' => null ) )?> 
    </div><?php
endif;
   
 

// showing incompatibility message if no configured projects available 
else:?> 
<div class="loco-project">
    <div class="notice inline notice-error">
        <h2><?php $params->e('name')?> <span>(<?php esc_html_e('unconfigured','loco-translate')?>)</span></h2>
        <p>
            <?php
            esc_html_e("This bundle isn't automatically compatible and requires configuring before you can use all the functions of Loco Translate",'loco-translate')?>. <?php
            echo wp_kses(
                sprintf( __('Click the <a href="%s">setup</a> tab to complete the bundle configuration','loco-translate'), $tabs[1]->href ),
                array('a'=>array('href'=>true)), array('http','https')
            );?>.
        </p>
    </div>
</div><?php
if( $unknown ):?> 
    <div class="loco-project">
        <?php echo $this->render('inc-po-table', array( 'pairs' => $unknown, 'domain' => null ) )?> 
    </div><?php
endif;
endif;
