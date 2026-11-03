<?php
require_once dirname(__DIR__, 2) . '/layouts/session.php';
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Service\DosyaYonetimiService;
use App\Service\Gate;

if (!Gate::allows('dosya_yonetimi_goruntule') && !Gate::isSuperAdmin()) {
    http_response_code(403);
    exit('Bu dosyayı görüntüleme yetkiniz yok.');
}

try {
    $servis = new DosyaYonetimiService();
    $servis->akit($_GET['id'] ?? '', isset($_GET['inline']) && $_GET['inline'] === '1', isset($_GET['onizleme']) && $_GET['onizleme'] === '1');
} catch (DomainException $e) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
} catch (Throwable $e) {
    error_log('dosya-yonetimi/indir: ' . $e->getMessage());
    http_response_code(500);
    exit('Dosya açılamadı.');
}
