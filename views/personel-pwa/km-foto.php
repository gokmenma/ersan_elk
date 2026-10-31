<?php

session_start();

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Helper\MedyaServis;
use App\Model\AracKmBildirimModel;

if (!isset($_SESSION['personel_id'])) {
    http_response_code(403);
    exit('Oturum bulunamadı.');
}

$personelId = (int) $_SESSION['personel_id'];
$bildirimId = (int) ($_GET['id'] ?? 0);
session_write_close();

if ($bildirimId <= 0) {
    http_response_code(400);
    exit('Geçersiz istek.');
}

$resimYolu = (new AracKmBildirimModel())->getPersonelResimYolu($bildirimId, $personelId);

if ($resimYolu === null) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

$dosya = AracKmBildirimModel::resimDosyasiniBul($resimYolu);

if ($dosya === null) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

MedyaServis::gonder($dosya, 86400);
exit;
