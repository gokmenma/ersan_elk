<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\MedyaServis;
use App\Model\AracKmBildirimModel;
use App\Service\Gate;

if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
    http_response_code(401);
    exit('Oturum bulunamadı.');
}

$yetkili = Gate::allows('arac_km_onaylari') || Gate::isSuperAdmin();
$superAdmin = Gate::isSuperAdmin();
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
session_write_close();

if (!$yetkili) {
    http_response_code(403);
    exit('Bu fotoğrafa erişim yetkiniz yok.');
}

$bildirimId = (int) ($_GET['id'] ?? 0);
if ($bildirimId <= 0) {
    http_response_code(400);
    exit('Geçersiz istek.');
}

$Model = new AracKmBildirimModel();
$bildirim = $Model->find($bildirimId);

if (!$bildirim || (!$superAdmin && (int) $bildirim->firma_id !== $firmaId)) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

$dosya = AracKmBildirimModel::resimDosyasiniBul((string) ($bildirim->resim_yolu ?? ''));
if ($dosya === null) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

MedyaServis::gonder($dosya, 86400);
exit;
