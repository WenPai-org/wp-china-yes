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

该包还未被<a href="https://translate.wp-china.org">https://translate.wp-china.org</a>翻译<br/>
若你确定该包处于WordPress官方仓库中，则24小时内就会收到该包的翻译推送，若未收到请<a href="https://wp-china.org/forums/forum/104">反馈问题</a>
