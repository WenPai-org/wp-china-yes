<?php
/**
 * Initialize a new PO translations file
 */
$this->extend('../layout');

    // warn if doing direct extraction
    /* @var Loco_mvc_ViewParams $ext */
    if( $params->has('ext') ):?> 
    <div class="notice inline notice-info">
        <p>
            <?php esc_html_e("You're creating translations directly from source code",'loco-translate')?>.
            <a href="<?php $ext->e('link')?>"><?php esc_html_e('Create template instead','loco-translate')?></a>.
        </p>
    </div><?php
    endif;


    /*/ warning to show/hide when locations are marked unsafe
    if( $params->has('fsNotice') ):?> 
    <div id="loco-fs-info" class="has-nav notice inline notice-info jshide">
        <p>
            <strong class="has-icon"><?php esc_html_e('Warning','loco-translate')?>:</strong>
            <span><?php $params->e('fsNotice')?>.</span>
        </p>
        <nav>
            <a href="<?php echo $help?>#locations" target="_blank"><?php esc_html_e('Documentation','loco-translate')?></a>
            <span>|</span>
            <a href="<?php $this->route('config')->e('href')?>#loco--fs-protect"><?php esc_html_e('Settings','loco-translate')?></a>
        </nav>
    </div><?php
    endif*/?> 


    <div class="notice inline notice-generic">

        <h2><?php $params->e('subhead')?></h2>
        <p><?php $params->e('summary')?></p>

        <form action="" method="post" enctype="application/x-www-form-urlencoded" id="loco-poinit"><?php
            /* @var Loco_mvc_HiddenFields $hidden */
            $hidden->_e();?> 
            <table class="form-table">
                <tbody class="loco-locales">
                    <tr valign="top">
                        <th scope="row">
                            <label for="loco-select-locale">
                                1. <?php esc_html_e('Choose a language','loco-translate')?>:
                            </label>
                        </th>
                        <td>
                            <fieldset>
                                <label for="loco-use-selector-1">
                                    <span><input type="radio" name="use-selector" value="1" checked id="loco-use-selector-1" /></span>
                                    <?php esc_attr_e('WordPress language','loco-translate')?>:
                                </label>
                                <div>
                                    <span class="lang nolang"></span>
                                    <select id="loco-select-locale" name="select-locale">
                                        <option value=""><?php esc_attr_e('No language selected','loco-translate')?></option>
                                        <optgroup label="<?php esc_attr_e( 'Installed languages', 'loco-translate' )?>"><?php
                                            /* @var Loco_mvc_ViewParams[] $installed */
                                            foreach( $installed as $option ):?> 
                                            <option value="<?php $option->e('value')?>" data-icon="<?php $option->e('icon')?>"><?php $option->e('label')?></option><?php
                                            endforeach;?> 
                                        </optgroup>
                                        <optgroup label="<?php esc_attr_e( 'Available languages', 'loco-translate' )?>"><?php
                                            /* @var Loco_mvc_ViewParams[] $locales */
                                            foreach( $locales as $option ):?> 
                                            <option value="<?php $option->e('value')?>" data-icon="<?php $option->e('icon')?>"><?php $option->e('label')?></option><?php
                                            endforeach;?> 
                                        </optgroup>
                                    </select>
                                </div>
                            </fieldset>
                            <fieldset class="disabled">
                                <label for="loco-user-selector-0">
                                    <span><input type="radio" name="use-selector" value="0" /></span>
                                    <?php esc_attr_e('Custom language','loco-translate')?>:
                                </label>
                                <div>
                                    <span class="lang nolang"></span>
                                    <span class="loco-clearable"><input type="text" maxlength="14" name="custom-locale" value="" /></span>
                                </div>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
                <tbody class="loco-paths">   
                    <tr valign="top">
                        <th scope="row">
                            <label>
                                2. <?php esc_html_e('Choose a location','loco-translate')?>:
                            </label>
                        </th>
                        <td>
                            <a href="<?php $help->e('href')?>#locations" class="has-icon icon-help" target="_blank" tabindex="-1"><?php $help->e('text')?></a>
                        </td>
                    </tr><?php
                    $choiceId = 0;
                    /* @var Loco_mvc_ViewParams[] $locations */
                    foreach( $locations as $typeId => $location ):?> 
                    <tr class="compact">
                        <td>
                            <p class="description"><?php $location->e('label')?>:</p>
                        </td>
                        <td><?php
                        /* @var Loco_mvc_FileParams $choice */
                        /* @var Loco_mvc_FileParams $parent */
                        foreach( $location['paths'] as $choice ): 
                            $parent = $choice['parent']; 
                            $offset = sprintf('%u',++$choiceId);?> 
                            <p><?php
                                if( $choice->disabled ):?> 
                                <label class="for-disabled">
                                    <span class="icon icon-lock"></span>
                                    <input type="radio" name="select-path" class="disabled" disabled /><?php
                                else:?> 
                                <label>
                                    <input type="radio" name="select-path" value="<?php echo $offset?>" <?php echo $choice->checked?> />
                                    <input type="hidden" name="path[<?php echo $offset?>]" value="<?php $choice->e('hidden')?>" /><?php
                                endif?> 
                                    <code class="path"><?php $parent->e('relpath')?>/<?php echo $choice->holder?></code>
                                </label>
                            </p><?php
                        endforeach?> 
                        </td>
                    </tr><?php
                    endforeach;?> 
                </tbody><?php
    
                if( $params->has('sourceLocale') ):?> 
                <tbody>
                    <tr valign="top">
                        <th scope="row" rowspan="3">
                            3. <?php esc_html_e('Template options','loco-translate')?>:
                        </th>
                        <td>
                            <a href="<?php $help->e('href')?>#copy" class="has-icon icon-help" target="_blank" tabindex="-1"><?php $help->e('text')?></a>
                        </td>
                    </tr>
                    <tr valign="top" class="compact">
                        <td>
                            <p>
                                <label>
                                    <input type="radio" name="strip" value="" />
                                    <?php $params->f('sourceLocale', __('Copy target translations from "%s"','loco-translate') )?> 
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="radio" name="strip" value="1" checked />
                                    <?php esc_html_e('Just copy English source strings','loco-translate')?> 
                                </label>
                            </p>
                        </td>
                    </tr>                    
                    <tr valign="top" class="compact">
                        <td>
                            <p>
                                <label>
                                    <input type="checkbox" name="link" value="1" />
                                    <?php esc_html_e('Use this file as template when running Sync','loco-translate')?> 
                                </label>
                            </p>
                        </td>
                    </tr>
                </tbody><?php
                endif?> 
            </table>
    
            <p class="submit">
                <button type="submit" class="button button-large button-primary" disabled><?php esc_html_e('Start translating','loco-translate')?></button>
            </p>
    
        </form>

    </div>