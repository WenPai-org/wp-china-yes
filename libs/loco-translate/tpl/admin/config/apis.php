<?php
/**
 * API keys/settings screen
 */

$this->extend('../layout');
/* @var Loco_mvc_ViewParams[] $apis */
/* @var Loco_mvc_ViewParams $nonce */
$help = apply_filters('loco_external','https://localise.biz/wordpress/plugin/manual/providers');
?> 

<form action="" method="post" enctype="application/x-www-form-urlencoded">
    <input type="hidden" name="<?php $nonce->e('name')?>" value="<?php $nonce->e('value')?>" />
    <table class="form-table">
        <tbody><?php
            // GOOGLE
            $api = $apis['google']?> 
            <tr>
                <th scope="row"><?php $api->e('name')?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php esc_html_e(__('API key','wp-china-yes'))?></span>
                        </legend>
                        <p>
                            <label for="loco--google_api_key">
                                <?php esc_html_e(__('API key','wp-china-yes'))?>:
                            </label>
                            <input type="text" size="50" name="api[google][key]" id="loco--google_api_key" value="<?php $api->e('key')?>" spellcheck="false" />
                        </p>
                        <p>
                            <span class="description"><a href="https://cloud.google.com/translate/" target="_blank" tabindex="-1">https://cloud.google.com/translate</a></span>
                        </p>
                    </fieldset>
                </td>
            </tr><?php
            // MICROSOFT
            $api = $apis['microsoft']?> 
            <tr>
                <th scope="row"><?php $api->e('name')?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php esc_html_e(__('API key','wp-china-yes'))?></span>
                        </legend>
                        <p>
                            <label for="loco--microsoft_api_key">
                                <?php esc_html_e(__('API key','wp-china-yes'))?>:
                            </label>
                            <input type="text" size="50" name="api[microsoft][key]" id="loco--microsoft_api_key" value="<?php $api->e('key')?>" spellcheck="false" />
                        </p>
                        <p>
                            <label for="loco--microsoft_api_region">
                                <?php esc_html_e(__('API region','wp-china-yes'))?>:
                            </label>
                            <input type="text" size="20" name="api[microsoft][region]" id="loco--microsoft_api_region" value="<?php $api->e('region')?>" spellcheck="false" placeholder="global" />
                        </p>
                        <p>
                            <span class="description"><a href="https://aka.ms/MicrosoftTranslator" target="_blank" tabindex="-1">https://aka.ms/MicrosoftTranslator</a></span>
                        </p>
                    </fieldset>
                </td>
            </tr><?php
            // YANDEX
            $api = $apis['yandex']?> 
            <tr>
                <th scope="row"><?php $api->e('name')?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php esc_html_e(__('API key','wp-china-yes'))?></span>
                        </legend>
                        <p>
                            <label for="loco--yandex_api_key">
                                <?php esc_html_e(__('API key','wp-china-yes'))?>:
                            </label>
                            <input type="text" size="90" name="api[yandex][key]" id="loco--yandex_api_key" value="<?php $api->e('key')?>" spellcheck="false" />
                        </p>
                        <p>
                            <span class="description"><a href="https://tech.yandex.com/translate/" target="_blank" tabindex="-1">https://tech.yandex.com/translate/</a></span>
                        </p>
                    </fieldset>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="notice inline">
        <p>
            <strong class="has-icon"><?php esc_html_e('Important','wp-china-yes')?>:</strong>
            <span>
                <?php esc_html_e('Third party services are subject to their own terms of use and may incur costs from the provider','wp-china-yes')?>.
                <a href="<?php self::e($help)?>#legal" target="_blank" tabindex="-1"><?php esc_html_e('See full disclaimer','wp-china-yes')?></a>.
            </span>
        </p>
    </div>  

    <p class="submit">
        <input type="submit" class="button-primary" value="<?php esc_html_e('Save settings','wp-china-yes')?>" />
        <a class="button button-link" href="<?php self::e($help)?>" target="_blank"><?php esc_html_e('Documentation','wp-china-yes')?></a>
    </p>
</form>
