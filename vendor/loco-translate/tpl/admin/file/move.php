<?php
/**
 * Confirmation form for moving any file or group of file siblings.
 */
$this->extend('../layout');
?> 

<form action="" method="post" enctype="application/x-www-form-urlencoded" id="loco-move"><?php
    /* @var Loco_mvc_HiddenFields $hidden */
    $hidden->_e();
    echo $source?> 
    <div class="notice inline notice-info">
        <h2>
            <?php self::e( __('Confirm relocation','loco-translate') );?> 
        </h2>
        <p>
            <?php self::e(_n('The following file will be moved/renamed to the new location:','The following files will be moved/renamed to the new location:',count($files),'loco-translate'));
            /* @var Loco_fs_File[] $files */
            foreach( $files as $file ):
                echo '<div>',$params->escape( $file->basename() ),'</div>';
            endforeach?>
        </p>
        <p class="submit">
            <button type="submit" class="button button-primary" disabled><?php esc_html_e('Move files','loco-translate')?></button><?php
            if( $params->has('advanced') ):?> 
            <a href="<?php $params->e('advanced')?>" class="button button-link"><?php esc_html_e('Advanced','loco-translate')?></a><?php
            endif?> 
        </p>
    </div>
</form>

