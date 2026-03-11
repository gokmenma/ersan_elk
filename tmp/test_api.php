<?php
require "Autoloader.php";
use App\Helper\Security;
$_POST = [
    'action' => 'hesap-hareketleri-ajax-list',
    'cari_id' => Security::encrypt('1'),
    'draw' => 1,
    'start' => 0,
    'length' => 50
];
ob_start();
require 'views/cari/api.php';
$out = ob_get_clean();
echo $out;
