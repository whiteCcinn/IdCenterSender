<?php

// Main invoke example:

$root = dirname(__DIR__);
include_once $root . '/cckeyid/IdCenterSender.php';

echo \cckeyid\IdCenterSender::getInstance()->ck_get_new_id(1);

echo PHP_EOL;

print_r(\cckeyid\IdCenterSender::getInstance(true)->ck_get_new_id(4));
