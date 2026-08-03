<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Security;
use App\Model\KacakKontrolModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($userId <= 0 || empty($_SESSION['firma_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Gate::allows('kacak_islemleri') && !Gate::allows('kacak/list') && !Gate::isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bu işlem için yetkiniz bulunmuyor.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$kacakId = (int) Security::decrypt((string) ($_GET['token'] ?? ''));
$model = new KacakKontrolModel();

// getRecord firma ve soft-delete kontrolünü de uygular.
if ($kacakId <= 0 || !$model->getRecord($kacakId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Kayıt bulunamadı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$fotograflar = array_map(static function (array $foto): array {
    return [
        'token' => Security::encrypt($foto['id']),
        'tur' => $foto['tur'] ?? 'saha',
        'ad' => $foto['orijinal_ad'] ?? 'Fotoğraf',
        'pdf' => strtolower(pathinfo((string) ($foto['dosya_yolu'] ?? ''), PATHINFO_EXTENSION)) === 'pdf',
    ];
}, $model->getPhotos($kacakId));

echo json_encode(['success' => true, 'data' => $fotograflar], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
