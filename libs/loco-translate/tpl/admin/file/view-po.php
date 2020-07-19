<?php
/**
 * PO source view
 */
$this->extend('view');
$this->start('source');

echo $this->render('../common/inc-po-header');
echo $this->render('msgcat');
