<?php
/**
 * Debug
 */
$this->extend('layout');
?>

    <h1>Debug</h1>

    <?php
    echo $this->render('../debug/dump');
    ?> 

    <form>
        <button class="button button-primary button-large has-icon icon-save loco-loading" disabled>Test spinner</button>
    </form>