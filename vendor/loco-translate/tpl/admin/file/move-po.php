<?php
/**
 * Confirmation form for moving localized files to a new location.
 */
$this->extend('move');
$this->start('source');

/* @var Loco_mvc_ViewParams $current */
/* @var Loco_mvc_ViewParams[] $locations */
?> 

    <div class="notice inline notice-generic">
        <h2>
            <?php self::e( __('Choose a new location for these translations','loco-translate') );?> 
        </h2>
        <table class="form-table">
            <tbody class="loco-paths"><?php
                foreach( $locations as $typeId => $location ):?> 
                <tr class="compact">
                    <td>
                        <p class="description"><?php $location->e('label')?>:</p>
                    </td>
                    <td><?php
                        /* @var Loco_mvc_FileParams $choice */
                        foreach( $location['paths'] as $choice ):?> 
                        <p><?php
                            if( $choice->active ):?> 
                            <label>
                                <input type="radio" name="dest" value="" disabled checked /><?php
                            else:?> 
                            <label>
                                <input type="radio" name="dest" value="<?php $choice->e('path')?>" /><?php
                            endif?>
                                <code class="path"><?php $choice->e('path')?></code>
                            </label>
                        </p><?php
                        endforeach?> 
                    </td>
                </tr><?php
                endforeach?> 
            </tbody>
        </table>
    </div>
