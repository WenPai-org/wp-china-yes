<?php
/**
 * Listing of all files within a locale, grouped by bundle
 */

$this->extend('../layout');
?> 

    <div class="notice inline notice-info">
        <h3 class="has-lang">
            <span <?php echo $locale->attr?>><code><?php $locale->e('code')?></code></span> 
            <span><?php $locale->e('name')?></span>
            <span class="loco-meta">
                <span><?php echo esc_html_x('Updated','Modified time','loco-translate')?>:</span>
                <span><?php $params->date('modified')?></span>
            </span>
        </h3>
    </div>

    <?php
    foreach( $translations as $t => $group ): $type = $types[$t];?> 
    <div class="loco-projects">
        <h3>
            <?php $type->e('name')?> 
        </h3><?php
        echo $this->render('../common/inc-table-filter');
        ?> 
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th data-sort-type="s">
                        <?php esc_html_e('Bundle name','loco-translate')?> 
                    </th><?php
                    if( $npofiles ):?> 
                    <th colspan="2" data-sort-type="n">
                        <?php esc_html_e('Translation progress','loco-translate')?> 
                    </th>
                    <th data-sort-type="n">
                        <?php esc_html_e('Pending','loco-translate')?> 
                    </th><?php
                    endif?> 
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
                /* @var Loco_mvc_ViewParams $po */
                foreach( $group as $po ): ?> 
                <tr>
                    <td class="has-row-actions" data-sort-value="<?php $po->e('lname')?>">
                        <a href="<?php $po->e('edit')?>" class="row-title">
                            <?php $po->e('title')?> 
                        </a>
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
                        </nav>
                    </td><?php
                    if( $npofiles ):
                    if( 'PO' === $po->type ):?> 
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
                        -- <!-- no pending for template -->
                    </td><?php
                    endif;
                    endif?> 

                    <td data-sort-value="<?php $po->e('name')?>">
                         <a href="<?php $po->e('info')?>"><?php $po->e('name')?></a>
                    </td>
                    <td data-sort-value="<?php $po->f('time','%u')?>">
                        <time datetime="<?php $po->date('time','Y-m-d H:i:s')?>"><?php $po->date('time')?></time>
                    </td>
                    <td>
                        <?php $po->e('store')?> 
                    </td>
                </tr><?php
                endforeach;?> 
            </tbody>
        </table>
        <p>
            <a href="<?php $type->e('href')?>" class="button button-link has-raquo"><?php $type->e('text')?></a>
        </p>
    </div><?php
    endforeach;