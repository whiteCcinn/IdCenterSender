<?php

// Main invoke example:

include_once '../cckeyid/IdCenterSender.php';

echo \cckeyid\IdCenterSender::getInstance()->ck_get_new_id(1);

echo PHP_EOL;

print_r(\cckeyid\IdCenterSender::getInstance(true)->ck_get_new_id(4));
