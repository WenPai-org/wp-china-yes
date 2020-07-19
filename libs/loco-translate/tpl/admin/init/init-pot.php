<?php
/**
 * Initialize a new POT template file
 */
$this->extend('../layout');
$help = apply_filters('loco_external','https://localise.biz/wordpress/plugin/manual/templates');
?> 

    <div class="notice inline notice-generic">
        <h2><?php $params->e('subhead')?></h2>
        <p>
            <?php esc_html_e('Source files to scan:','wp-china-yes')?> 
            <strong><?php $scan->n('count')?></strong>
            <span>(<?php 
            // Translators: Where %s is the size of a file
            $scan->f( 'size', __('%s on disk','wp-china-yes') );?>, <?php
            // Translators: Where %s is the size of a file
            $scan->f( 'largest', __('largest is %s','wp-china-yes') )?>)</span>
        </p><?php
        if( $n = $scan->skip ):?> 
        <p>
            <em><?php 
            // Translators: Where %2$s is the size of a file
            self::e( _n('Excludes one file over %2$s','Excludes %s files over %2$s',$n,'wp-china-yes'), $n, $scan->large )?>.
            <a class="icon icon-help" href="<?php echo esc_url(apply_filters('loco_external','https://localise.biz/wordpress/plugin/faqs/skipped-files'))?>" target="_blank"><span class="screen-reader-text">Help</span></a>
            </em>
        </p><?php
        endif?> 
        <p>
            <?php esc_html_e('Strings will be extracted to:','wp-china-yes')?> 
            <code class="path"><?php $pot->e('relpath')?></code>
        </p>
        <form action="" method="post" enctype="application/x-www-form-urlencoded" id="loco-potinit"><?php
            
            foreach( $hidden as $name => $value ):?> 
            <input type="hidden" name="<?php echo $name?>" value="<?php $hidden->e($name)?>" /><?php
            endforeach;?> 
        
            <p class="submit">
                <button type="submit" class="button button-large button-primary" disabled><?php esc_html_e('Create template','wp-china-yes')?></button>
                <a href="<?php echo esc_url($help)?>" class="button button-large button-link" target="_blank"><?php 
                    esc_html_e('About templates','wp-china-yes')?></a>
            </p>

        </form>
    </div>
