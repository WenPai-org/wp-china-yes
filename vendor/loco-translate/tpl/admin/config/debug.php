<?php
/**
 * System diagnostics
 */
$this->extend('../layout');
?> 
    
        <div class="panel" id="loco-versions">
            <h3>
                Versions
                <a href="#loco-versions" class="loco-anchor" aria-hidden="true"></a>
            </h3>
            <dl><?php
            foreach( $versions as $key => $value ): if( $value ):?> 
                <dt>
                    <?php echo $versions->escape($key)?>:
                </dt>
                <dd>
                    <code class="path"><?php $versions->e($key)?></code>
                </dd><?php
            endif; endforeach?> 
            </dl>
        </div>
    
        <div class="panel" id="loco-unicode">
            <h3>
                Unicode
                <a href="#loco-unicode" class="loco-anchor" aria-hidden="true"></a>
            </h3>
            <dl>
                <dt>UTF-8 rendering:</dt>
                <dd><?php echo $encoding->OK?> <span id="loco-utf8-check"><?php echo $encoding->tick?></span></dd>

                <dt>Multibyte support:</dt>
                <dd><?php echo $encoding->mbstring?></dd>
            </dl>
        </div>
    
        <div class="panel" id="loco-ajax">
            <h3>
                Ajax
                <a href="#loco-ajax" class="loco-anchor" aria-hidden="true"></a>
            </h3>
            <dl>
                <dt>Endpoint:</dt>
                <dd><code id="loco-ajax-url" class="path">/wp-admin/admin-ajax.php</code></dd>
                
                <dt>JSON decoding:</dt>
                <dd><?php echo $encoding->json?></dd>
    
                <dt class="jsonly">Ajax test result:</dt>
                <dd class="jsonly" id="loco-ajax-check"><span class="inline-spinner"> </span></dd>
            </dl>
        </div>

        <div class="panel" id="loco-apis">
            <h3>
                Translation APIs
                <a href="#loco-apis" class="loco-anchor" aria-hidden="true"></a>
            </h3>
            <dl><?php
                /* @var Loco_mvc_ViewParams[] $apis */
                foreach( $apis as $api ):?> 
                <dt><?php $api->e('name')?>:</dt>
                <dd class="jsonly" id="loco-api-<?php $api->e('id')?>"><span class="inline-spinner"> </span></dd><?php
                endforeach?> 
            </dl>
        </div>

        <div class="panel" id="loco-sizes">
            <h3>
                Limits
                <a href="#loco-sizes" class="loco-anchor" aria-hidden="true"></a>
            </h3>
            <dl><?php
            foreach( $memory as $key => $value ):?> 
                <dt>
                    <?php echo $memory->escape($key)?>:
                </dt>
                <dd>
                    <?php $memory->e($key)?> 
                </dd><?php
            endforeach?> 
            </dl>
        </div>
        
        <div class="panel" id="loco-files">
            <h3>
                Filesystem
                <a href="#loco-files" class="loco-anchor" aria-hidden="true"></a>
            </h3>
            <dl>
                <dt>Custom languages directory:</dt>
                <dd><code class="path"><?php $fs->e('langdir')?></code></dd>
    
                <dt>Directory writable:</dt>
                <dd><?php echo $fs->writable?'Yes':'No'?></dd>
    
                <dt>File mods disallowed:</dt>
                <dd><?php echo $fs->disabled?'Yes':'No'?></dd>
                
                <dt>File mod safety level:</dt>
                <dd><?php $fs->e('fs_protect')?></dd>
                
            </dl>
        </div>

        <div class="panel" id="loco-debug">
            <h3>
                Debug settings
                <a href="#loco-debug" class="loco-anchor" aria-hidden="true"></a>
            </h3>
            <dl><?php
            foreach( $debug as $key => $value ):?> 
                <dt>
                    <?php echo $debug->escape($key)?>:
                </dt>
                <dd>
                    <?php $debug->e($key)?> 
                </dd><?php
            endforeach?> 
            </dl>
        </div>
