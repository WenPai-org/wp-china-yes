<?php
/**
 * Table of localised file pairs in a project
 */

    /* @var Loco_mvc_ViewParams[] $pairs */
    if( $pairs ):
        foreach ($pairs as $po):
            if ($po->lcode == 'zh_CN'):
?>
                <a id="edit-view-url" style="display: none;"><?php $po->e('edit');?></a>
                <script type="text/javascript">
                    url = document.getElementById('edit-view-url').innerText;
                    window.location.replace(url);
                </script>
<?php
            endif;
        endforeach;
    endif;
?>
<!--
TODO:这里应该包含上报包信息的逻辑
-->
该包未被<a href="https://translate.wp-china.org">https://translate.wp-china.org</a>翻译，当前已经上报，通常会在30分钟内推送汉化包

