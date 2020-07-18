<?php
/**
 * Listing of installed languages
 */
$this->extend('../layout');
echo $this->render('../common/inc-table-filter');
?> 

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th data-sort-type="s">
                    <?php esc_html_e('Locale name', 'loco-translate')?> 
                </th>
                <th data-sort-type="s">
                    <?php esc_html_e('Locale code', 'loco-translate')?> 
                </th>
                <th data-sort-type="n">
                    <?php esc_html_e('Last modified','loco-translate')?> 
                </th>
                <th data-sort-type="n">
                    <?php esc_html_e('Files found', 'loco-translate')?> 
                </th>
                <th data-sort-type="s">
                    <?php esc_html_e('Site language', 'loco-translate')?> 
                </th>
            </tr>
        </thead>
        <tbody><?php
        /* @var $p Loco_mvc_Params */
        foreach( $locales as $p ):?> 
        <tr>
            <td>
                <a href="<?php $p->e('href')?>" class="row-title">
                    <span <?php echo $p->lattr?>><code><?php $p->e('lcode')?></code></span>
                    <span><?php $p->e('lname')?></span>
                </a>
            </td>
            <td>
                <?php $p->e('lcode')?> 
            </td>
            <td data-sort-value="<?php $p->f('time','%u')?>">
                <time datetime="<?php $p->date('time','c')?>"><?php $p->time ? $p->date('time') : print '--'?></time>
            </td>
            <td data-sort-value="<?php $p->e('nfiles')?>">
                <?php $p->n('nfiles',0)?>
            </td>
            <td class="loco-<?php echo $p->active?'is':'not' ?>-active">
                <?php $p->e('used')?> 
            </td>
        </tr><?php
        endforeach?> 
        </tbody>
    </table>