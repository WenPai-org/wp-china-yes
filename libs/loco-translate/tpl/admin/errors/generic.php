<?php
/*
 * Generic admin page error template
 */

$this->extend('../layout');

/* @var Loco_mvc_View $this */
/* @var Loco_error_Exception $error */
?> 

    <h1><?php echo esc_html( $error->getTitle() )?></h1>

    <div class="notice inline notice-error">
        <h3 class="has-icon">
            <?php self::e( $error->getMessage() )?> 
        </h3><?php
        /* @var Loco_mvc_FileParams $file */
        if( $params->has('file') && $file->line ):?> 
        <p>
            <code class="path"><?php $file->e('relpath')?>#<?php $file->e('line')?></code>
        </p><?php
        endif?> 
    </div>

    <?php
    /* @var Loco_mvc_ViewParams[] $trace */
    if( $this->has('trace') ):
    echo "<!-- DEBUG:\n";
    foreach( $trace as $f ):
    echo '      ',($f->has('class')?$f['class'].'::':''), $f->e('function'),'  ', $f->e('file'),':',$f->e('line'), "\n";
    endforeach;
    echo "    -->\n";
    endif;
    