<?php
/**
 * Table of localised file pairs in a project
 */

    /* @var Loco_mvc_ViewParams[] $pairs */
    if( $pairs ):?> 

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th data-sort-type="s">
                        <?php esc_html_e('Language','loco-translate')?> 
                    </th>
                    <th colspan="2" data-sort-type="n">
                        <?php esc_html_e('Translation progress','loco-translate')?> 
                    </th>
                    <th data-sort-type="n">
                        <?php esc_html_e('Pending','loco-translate')?> 
                    </th>
                    <th data-sort-type="s">
                        <?php esc_html_e('File info','loco-translate')?> 
                    </th>
                    <th data-sort-type="n">
                        <?php esc_html_e('Last modified','loco-translate')?> 
                    </th>
                    <th data-sort-type="s">
                        <?php esc_html_e('Folder','loco-translate')?> 
                    </th>
                </tr>
            </thead>
            <tbody><?php
                foreach( $pairs as $po ): $ispo = (bool) $po->lcode;?> 
                <tr>
                    <td class="has-row-actions" data-sort-value="<?php $po->e('lname')?>">
                        <a href="<?php $po->e('edit')?>" class="row-title"><?php
                            if( $ispo ):?> 
                            <span <?php echo $po->lattr?>><code><?php $po->e('lcode')?></code></span>
                            <span><?php $po->e('lname')?></span><?php
                            else:?> 
                            <span class="icon icon-file"></span>
                            <span><?php esc_html_e('Template file','loco-translate')?></span><?php
                            endif?> 
                        </a><?php
                        if( $domain ):?> 
                        <nav class="row-actions">
                            <span>
                                <a href="<?php $po->e('edit')?>"><?php esc_html_e('Edit','loco-translate')?></a> |
                            </span>
                            <span>
                                <a href="<?php $po->e('view')?>"><?php esc_html_e('View','loco-translate')?></a> |
                            </span>
                            <span>
                                <a href="<?php $po->e('info')?>"><?php esc_html_e('Info','loco-translate')?></a> |
                            </span>
                            <span>
                                <a href="<?php $po->e('copy')?>"><?php esc_html_e('Copy','loco-translate')?></a> |
                            </span>
                            <span class="trash">
                                <a href="<?php $po->e('delete')?>"><?php esc_html_e('Delete','loco-translate')?></a>
                            </span>
                        </nav><?php
                        endif?> 
                    </td><?php

                    if( $ispo ):?> 
                    <td data-sort-value="<?php echo $po->meta->getPercent()?>">
                        <?php $po->meta->printProgress()?> 
                    </td>
                    <td title="of <?php $po->n('total')?>">
                        <?php echo $po->meta->getPercent()?>%
                    </td>
                    <td data-sort-value="<?php $po->f('todo','%u')?>">
                        <?php $po->n('todo')?> 
                    </td><?php

                    else:?> 
                    <td data-sort-value="-1">
                        -- <!-- no progress for template -->
                    </td>
                    <td>
                        <!-- no percentage for template -->
                    </td>
                    <td data-sort-value="-1">
                        -- <!-- no pendingfor template -->
                    </td><?php
                    endif?> 

                    <td data-sort-value="<?php $po->e('name')?>">
                         <a href="<?php $po->e('info')?>"><?php $po->e('name')?></a>
                    </td>
                    <td data-sort-value="<?php $po->f('time','%u')?>">
                        <time datetime="<?php $po->date('time','c')?>"><?php $po->date('time')?></time>
                    </td>
                    <td>
                        <?php $po->e('store')?> 
                    </td>
                </tr><?php
                endforeach;?> 
            </tbody>
        </table><?php
    else:?> 
        <table class="wp-list-table widefat fixed striped">
            <tr>
                <td><?php self::e( __('No translations found for "%s"','loco-translate'), $domain )?></td>
            </tr>
        </table><?php    
    endif;
