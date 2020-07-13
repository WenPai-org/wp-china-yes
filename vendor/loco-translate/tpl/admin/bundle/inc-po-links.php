<?php
/**
 * Links above PO files table
 */
?> 
<nav class="above-list-table"><?php
    /* @var $link Loco_mvc_ViewParams */
    foreach( $nav as $link ):?> 
    <a class="has-icon icon-<?php $link->e('icon')?>" href="<?php $link->e('href')?>"><?php $link->e('name')?></a><?php
    endforeach;
    echo $this->render('../common/inc-table-filter');
    ?> 
</nav>