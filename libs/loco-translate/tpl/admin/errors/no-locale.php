<?php
/*
 * Full screen error when there are no installed files for a given locale
 */

$this->extend('../layout'); 
?> 
    
    <div class="notice inline notice-error">
        <h3 class="has-icon">
            <?php esc_html_e('No files found for this language','loco-translate')?> 
        </h3>
        <p>
            It may not be installed properly.
            See <a href="https://codex.wordpress.org/Installing_WordPress_in_Your_Language">Installing WordPress in your language</a>.
        </p>
    </div>
