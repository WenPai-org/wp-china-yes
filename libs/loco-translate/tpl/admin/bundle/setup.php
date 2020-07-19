<?php
/**
 * Bundle setup layout.
 * See setup/*.php for header definitions
 */
$this->extend('../layout');
echo $header;

?> 
    <div class="notice inline notice-generic">
        <h3 class="has-icon">
            <?php esc_html_e('Author details','wp-china-yes')?> 
        </h3>
        <p>
            <?php esc_html_e('If you have trouble translating this bundle, consider asking the author for help','wp-china-yes')?>.
        </p>
        <p>
            <?php echo $credit?> 
        </p>
    </div>
