<?php
require_once '../vendor/autoload.php';

use App\Model\EvrakModel;

$evrakModel = new EvrakModel();
$sayi = $evrakModel->yeniEvrakSayisi();

echo "Yeni Evrak Sayısı: " . $sayi;