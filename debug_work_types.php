<?php
require_once __DIR__ . '/Autoloader.php';
use App\Model\TanimlamalarModel;
@session_start();
$_SESSION['firma_id'] = 1;

$model = new TanimlamalarModel();
$isTurleri = $model->getByGrup('is_turu');
$turler = [];
foreach ($isTurleri as $item) {
    $turler[] = $item->tur_adi;
}
echo json_encode(array_unique($turler), JSON_PRETTY_PRINT);
