<?php
/**
 * User preferences screen
 */

$this->extend('../layout');
/* @var Loco_data_Preferences $opts */
$help = apply_filters('loco_external','https://localise.biz/wordpress/plugin/manual/settings');
?> 

    <form action="" method="post" enctype="application/x-www-form-urlencoded">
        <input type="hidden" name="<?php $nonce->e('name')?>" value="<?php $nonce->e('value')?>" />
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Translator credit','wp-china-yes')?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span><?php esc_html_e('Translator credit','wp-china-yes')?></span>
                            </legend>
                            <p>
                                <input type="text" size="64" name="opts[credit]" id="loco--credit" value="<?php echo esc_attr($opts->credit)?>" placeholder="<?php echo esc_attr($opts->default_credit())?>" />
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="submit">
            <input type="submit" class="button-primary" value="<?php esc_html_e('Save settings','wp-china-yes')?>" />
            <a class="button button-link" href="<?php self::e($help)?>#user" target="_blank"><?php esc_html_e('Documentation','wp-china-yes')?></a>
        </p>
    </form>
