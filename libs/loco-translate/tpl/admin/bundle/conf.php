<?php
/**
 * Bundle configuration form
 */
$this->extend('../layout');
?> 

    <form action="" method="post" enctype="application/x-www-form-urlencoded" id="loco-conf"><?php

        /* @var $p Loco_mvc_ViewParams */
        foreach( $conf as $i => $p ): $id = sprintf('loco-conf-%u',$i)?> 
        <div id="<?php echo $id?>">
            
            <a href="#" tabindex="-1" class="has-icon icon-del"><span class="screen-reader-text">remove</span></a>
            <input type="hidden" name="<?php echo $p->prefix?>[removed]" value="" /> 
            
            <?php
            // display package name, and slug if it differs.
            if( $p->name === $p->short ):?> 
            <h2><?php $p->e('name')?></h2><?php
            else:?> 
            <h2><?php $p->e('name')?> <span>(<?php $p->e('short')?>)</span></h2><?php
            endif;?> 
            
            <table class="form-table">
                <tbody>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $id?>-name"><?php esc_html_e('Project name','loco-translate')?></label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $p->prefix?>[name]" value="<?php $p->e('name')?>" id="<?php echo $id?>-name" class="regular-text" />
                            <p class="description">
                                <?php // Translators: Help tip for "Project name" field in advanced bundle config
                                esc_html_e('Descriptive name for this set of translatable strings','loco-translate')?> 
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $id?>-domain"><?php esc_html_e('Text domain','loco-translate')?></label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $p->prefix?>[domain]" value="<?php $p->e('domain')?>" id="<?php echo $id?>-domain" class="regular-text" />
                            <p class="description">
                                <?php // Translators: Help tip for "Text domain" field in advanced bundle config
                                esc_html_e('The namespace into which WordPress will load translated strings','loco-translate')?> 
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $id?>-slug"><?php esc_html_e('File prefix','loco-translate')?></label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $p->prefix?>[slug]" value="<?php $p->e('slug')?>" id="<?php echo $id?>-slug" class="regular-text" />
                            <p class="description">
                                <?php // Translators: Help tip for "File prefix" field in advanced bundle config
                                esc_html_e("Usually the same as the text domain, but don't leave blank unless you mean to",'loco-translate')?> 
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $id?>-template"><?php esc_html_e('Template file','loco-translate')?></label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $p->prefix?>[template][path]" id="<?php echo $id?>-template" class="regular-text" value="<?php echo $p->escape( $p->template['path'] )?>" />
                            <label>
                                <input type="checkbox" value="1" name="<?php echo $p->prefix?>[template][locked]" <?php empty($p->template['locked']) || print('checked');?> />
                                <?php esc_html_e('Locked','loco-translate')?> 
                            </label>
                            <p class="description">
                                <?php // Translators: Help tip for "Template file" field in advanced bundle config
                                esc_html_e('Relative path from bundle root to the official POT file','loco-translate')?> 
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $id?>-target"><?php esc_html_e('Domain path','loco-translate')?></label>
                        </th>
                        <td class="twin">
                            <div>
                                <span class="description"><?php esc_html_e('Include','loco-translate')?>:</span>
                                <textarea name="<?php echo $p->prefix?>[target][path]" id="<?php echo $id?>-target" rows="2" cols="30" class="large-text"><?php echo $p->escape( $p->target['path'] )?></textarea>
                            </div>
                            <div>
                                <span class="description"><?php esc_html_e('Exclude','loco-translate')?>:</span>
                                <textarea name="<?php echo $p->prefix?>[target][exclude][path]" id="<?php echo $id?>-xtarget" rows="2" cols="30" class="large-text"><?php echo $p->escape( $p->target['exclude']['path'] )?></textarea>
                            </div>
                            <p class="description">
                                <?php // Translators: Help tip for "Domain path" field in advanced bundle config
                                esc_html_e('Folders within the bundle that contain author-supplied translations','loco-translate')?>. (<?php esc_html_e('no wildcards','loco-translate')?>)
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="<?php echo $id?>-source"><?php esc_html_e('Source file paths','loco-translate')?></label>
                        </th>
                        <td class="twin">
                            <div>
                                <span class="description"><?php esc_html_e('Include','loco-translate')?>:</span>
                                <textarea name="<?php echo $p->prefix?>[source][path]" id="<?php echo $id?>-source" rows="2" cols="30" class="large-text"><?php echo $p->escape( $p->source['path'] )?></textarea>
                            </div>  
                            <div>
                                <span class="description"><?php esc_html_e('Exclude','loco-translate')?>:</span>
                                <textarea name="<?php echo $p->prefix?>[source][exclude][path]" id="<?php echo $id?>-xsource" rows="2" cols="30" class="large-text"><?php echo $p->escape( $p->source['exclude']['path'] )?></textarea>
                            </div>
                            <p class="description">
                                <?php // Translators: Help tip for "Source file paths" field in advanced bundle config
                                esc_html_e('Files and folders within the bundle that contain localized PHP code','loco-translate')?>. (<?php esc_html_e('no wildcards','loco-translate')?>)
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div><?php 
        endforeach;?> 


        <footer id="loco-form-foot">
            <table class="form-table">
                <tbody>
                    <tr valign="top">
                        <th scope="row">
                            <label for="all-excl"><?php esc_html_e('Blocked paths','loco-translate')?>:</label>
                        </th>
                        <td>
                            <textarea name="exclude[path]" id="all-excl" rows="3" cols="30" class="large-text"><?php echo $params->escape($excl['path'])?></textarea>
                            <p class="description">
                                <?php // Translators: Help tip for "Blocked paths" field in advanced bundle config
                                esc_html_e('Folders within the bundle that will never be searched for files','loco-translate')?>. (<?php esc_html_e('no wildcards','loco-translate')?>)
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit" >
                <input type="submit" class="button-primary" value="<?php esc_html_e('Save config','loco-translate')?>" />
                <button type="button" class="button" disabled id="loco-add-butt"><?php esc_html_e('Add set','loco-translate')?></button><?php
                if( $params->parent ):?> 
                <a class="button button-link has-icon icon-cog" href="<?php $parent->e('href')?>"><?php esc_html_e('Parent theme','loco-translate')?></a><?php
                endif?> 
                <a class="button button-link has-icon icon-download" href="<?php $params->e('xmlUrl')?>"><?php esc_html_e('XML','loco-translate')?></a>
            </p>
        </footer>

        <input type="hidden" name="<?php $nonce->e('name')?>" value="<?php $nonce->e('value')?>" />
        <input type="hidden" name="name" value="<?php $params->e('name')?>" />

    </form>


    <?php if( 'db' === $saved ):?> 
    <form action="" method="post" id="loco-reset">
        <p class="submit">
            <input type="submit" name="unconf" class="button button-danger" value="<?php esc_html_e('Reset config','loco-translate')?>" />
            <input type="hidden" name="<?php $nonce->e('name')?>" value="<?php $nonce->e('value')?>" />
        </p>
    </form><?php
    endif;
