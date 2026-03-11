<?php
require "Autoloader.php";
use App\Helper\Security;

// 1. Encrypt ID
$id = '1';
$enc = Security::encrypt($id);
echo "ENC: $enc\n";

// 2. Decrypt ID
$dec = Security::decrypt($enc);
echo "DEC: $dec\n";

// 3. Simulate $_POST from DataTables
$_POST = [
    'action' => 'hesap-hareketleri-ajax-list',
    'cari_id' => $enc,
    'draw' => 1,
    'start' => 0,
    'length' => 50,
    'search' => ['value' => '', 'regex' => false]
];

// Execute logic from api.php inline
$model = new \App\Model\CariHareketleriModel();
$dec_post = Security::decrypt($_POST["cari_id"]);
echo "DEC POST: " . var_export($dec_post, true) . "\n";

$_POST['cari_id'] = $dec_post;
$res = $model->ajaxList($_POST);

echo "Ajax List Data Count: " . count($res['data']) . "\n";
print_r($res['data']);
