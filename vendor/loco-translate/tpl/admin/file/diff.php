<?php
/**
 * File revisions and rollback UI
 */
$this->extend('../layout');
$dfmt = _x( 'j M @ H:i', 'revision date short format', 'default' );
?> 

    <div class="revisions loading" id="loco-ui">
        <form class="revisions-control-frame" action="" method="post" enctype="application/x-www-form-urlencoded">
            <div class="loco-clearfix">
                <div class="revisions-previous jshide">
                    <button type="button" class="button" disabled><?php echo esc_attr_x( 'Previous', 'Button label for a previous revision' ); ?></button>
                </div>
                <div class="revisions-next jshide">
                    <button type="button" class="button" disabled><?php echo esc_attr_x( 'Next', 'Button label for a next revision' ); ?></button>
                </div>
            </div>
            <div class="revisions-meta loco-clearfix">
                <div class="diff-meta diff-right">
                    <span>Current revision saved <?php $master->e('reltime')?></span><br />
                    <time><?php $master->date('mtime',$dfmt)?></time><br />
                    <button type="button" class="button disabled" disabled>Restore</button>
                </div><?php
                /* @var $file Loco_mvc_FileParams */
                foreach( $files as $i => $file ):?> 
                <div class="diff-meta jshide">
                    <span><?php $file->e('name')?></span><br />
                    <time><?php $file->date('potime',$dfmt)?></time><br />
                    <button type="submit" class="button button-primary" name="backup" value="<?php $file->e('relpath')?>"><?php esc_html_e('Restore','loco-translate')?></button>
                    <button type="submit" class="button button-danger" name="delete" value="<?php $file->e('relpath')?>"><?php esc_html_e('Delete','loco-translate')?></button>
                </div><?php
                endforeach?> 
            </div><?php
            /* @var $hidden Loco_mvc_HiddenFields */
            $hidden->_e();?> 
        </form>
        <div class="revisions-diff-frame jsonly">
            <div class="revisions-diff">
                <div class="loading-indicator"><span class="spinner"></span></div>
                <div class="diff"></div>
             </div>
        </div>
    </div>
    
    <?php /*
    <!--hr />
    
    <h3>Advanced</h3>

    <form action="" method="post" enctype="application/x-www-form-urlencoded">

        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>
                        <?php esc_html_e('Revision saved','loco-translate')?> 
                    </th>
                    <th>
                        <?php esc_html_e('File info','loco-translate')?> 
                    </th>
                </tr>
            </thead>
            <tbody><?php
                foreach( $files as $i => $file ):?> 
                <tr>
                    <td>
                        <label>
                            <input type="radio" name="backup" value="<?php $file->e('relpath')?>" />
                            <?php $file->date('mtime')?> 
                        </label>
                    </td>
                    <td><code class="path"><?php $file->ls()?></code></td>
                </tr><?php
                endforeach?> 
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button button-danger"><?php esc_html_e('Restore selected','default')?></button>
        </p>
        <?php
        $hidden->_e();?> 
    </form-->
    */
