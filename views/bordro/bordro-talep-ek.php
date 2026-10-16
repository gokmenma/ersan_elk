<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\BordroYayinGuvenlik;
use App\Helper\Security;
use App\Model\BordroYayinModel;
use App\Service\Gate;

header('Cache-Control: no-store, private, max-age=0');
try {
    if (empty($_SESSION['user']->id) || empty($_SESSION['firma_id']) || !Gate::allows('bordro/list')) {
        http_response_code(403); exit('Yetkisiz erişim.');
    }
    $ek = (new BordroYayinModel())->talepEki(
        (int) $_SESSION['firma_id'],
        BordroYayinGuvenlik::id($_GET['token'] ?? null)
    );
    $root = realpath(dirname(__DIR__, 2) . '/uploads/bordro-itiraz');
    $path = realpath(dirname(__DIR__, 2) . '/' . ltrim((string) $ek['dosya_yolu'], '/'));
    if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) throw new DomainException('Dosya bulunamadı.');
    $binary = Security::decryptFile((string) file_get_contents($path));
    header('Content-Type: ' . $ek['mime_tipi']);
    header('Content-Length: ' . strlen($binary));
    header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode((string) $ek['dosya_adi']));
    echo $binary;
} catch (DomainException $e) {
    http_response_code(404); echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
} catch (Throwable $e) {
    error_log('Bordro itiraz eki yönetim: ' . $e->getMessage());
    http_response_code(500); echo 'Dosya görüntülenemedi.';
}
