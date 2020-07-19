<?php
/**
 * List of bundles
 */
?> 

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th data-sort-type="s">
                    <?php esc_html_e('Bundle name', 'loco-translate')?> 
                </th>
                <th data-sort-type="s">
                    <?php esc_html_e('Text domain','loco-translate')?> 
                </th>
                <th data-sort-type="n">
                    <?php esc_html_e('Last modified','loco-translate')?> 
                </th>
                <th data-sort-type="n">
                    <?php esc_html_e('Sets','loco-translate')?> 
                </th>
            </tr>
        </thead>
        <tbody><?php
            /* @var $bundle Loco_pages_ViewParams */ 
            foreach( $bundles as $bundle ):?> 
            <tr id="loco-<?php $bundle->e('id')?>">
                <td data-sort-value="<?php $bundle->e('name')?>">
                    <a href="<?php $bundle->e('view')?>"><?php $bundle->e('name')?></a>
                </td>
                <td>
                    <?php $bundle->e('dflt')?> 
                </td>
                <td data-sort-value="<?php $bundle->f('time','%u')?>">
                    <time datetime="<?php $bundle->date('time','c')?>"><?php $bundle->time ? $bundle->date('time') : print '--'?></time>
                </td>
                <td data-sort-value="<?php $bundle->f('size','%u')?>">
                    <?php $bundle->n('size')?> 
                </td>
            </tr><?php
            endforeach;?> 
        </tbody>
    </table>