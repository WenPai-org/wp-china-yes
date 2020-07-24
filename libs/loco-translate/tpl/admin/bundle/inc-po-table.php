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
<p>
<strong>该包还未被<a href="https://translate.wp-china.org">本土翻译系统</a>托管哦~</strong>
</p>
不要担心，若你确定该包正处于WordPress官方应用市场中，则<a href="https://translate.wp-china.org">本土翻译系统</a>会在24小时内监控到该包并为其提供翻译托管，届时你将收到翻译更新推送，若超过24小时未收到推送，请<a href="https://wp-china.org/forums/forum/104">反馈问题</a><br/>
再次感谢你对本土化社区的支持^_^
