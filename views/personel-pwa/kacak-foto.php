<?php
/**
 * Personel PWA - Kaçak fotoğrafı görüntüleme
 * Personel yalnızca kendi bildirdiği veya ekibinde yer aldığı kayıtların belgelerini görebilir.
 */

session_start();

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

use App\Model\KacakKontrolModel;

if (!isset($_SESSION['personel_id'])) {
    http_response_code(403);
    exit('Oturum bulunamadı.');
}

$personelId = (int) $_SESSION['personel_id'];
$fotoId = (int) ($_GET['id'] ?? 0);

if ($fotoId <= 0) {
    http_response_code(400);
    exit('Geçersiz istek.');
}

if (empty($_SESSION['firma_id'])) {
    $personel = (new \App\Model\PersonelModel())->find($personelId);
    if (!$personel) {
        http_response_code(403);
        exit('Personel bulunamadı.');
    }
    $_SESSION['firma_id'] = $personel->firma_id;
}

$Kacak = new KacakKontrolModel();
$foto = $Kacak->getPhoto($fotoId);

if (!$foto) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

$db = $Kacak->getDb();
$stmt = $db->prepare("SELECT id FROM kacak_kontrol
                      WHERE id = ? AND silinme_tarihi IS NULL
                        AND (bildiren_personel_id = ? OR FIND_IN_SET(?, personel_ids))
                      LIMIT 1");
$stmt->execute([(int) $foto['kacak_id'], $personelId, $personelId]);

if (!$stmt->fetchColumn()) {
    http_response_code(403);
    exit('Bu belgeye erişim yetkiniz yok.');
}

$dosya = KacakKontrolModel::rootPath() . '/' . ltrim($foto['dosya_yolu'], '/');

if (!is_file($dosya)) {
    http_response_code(404);
    exit($foto['arsivlendi'] ? 'Bu dosya arşivlenmiştir.' : 'Dosya bulunamadı.');
}

$uzanti = strtolower(pathinfo($dosya, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
];

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . ($mimeMap[$uzanti] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($dosya));
header('Content-Disposition: inline; filename="' . basename($dosya) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');

readfile($dosya);
exit;
