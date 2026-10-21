<?php
declare(strict_types=1);

use App\Helper\Security;
use App\Model\MobilePersonelModel;
use App\Service\Gate;

require_once dirname(__DIR__, 3) . '/Autoloader.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user_id']) || empty($_SESSION['firma_id']) || (!Gate::allowsAny(['personel_listesi', 'arac_takip', 'arac_km_onaylari', 'yonetici_onayi', 'nobet/list', 'talepler', 'avans_talepleri', 'izin_talepleri', 'ariza_talepleri']) && !Gate::isSuperAdmin())) { http_response_code(403); exit; }

$rawId = (string) ($_GET['id'] ?? '');
$personelId = ctype_digit($rawId) ? (int) $rawId : (int) Security::decrypt($rawId);
$row = $personelId > 0 ? (new MobilePersonelModel())->mobileDirectoryDetail($personelId, (int) $_SESSION['firma_id']) : null;
$candidates = [
    trim((string) ($row['personel_resim_yolu'] ?? '')),
    trim((string) ($row['resim_yolu'] ?? ''))
];
$projectRoot = realpath(dirname(__DIR__, 3));
$file = false;
if ($projectRoot) {
    foreach ($candidates as $cand) {
        if ($cand === '') continue;
        $tryFile = realpath($projectRoot . '/' . ltrim(str_replace('\\', '/', $cand), '/'));
        if ($tryFile && str_starts_with($tryFile, $projectRoot . DIRECTORY_SEPARATOR) && is_file($tryFile)) {
            $file = $tryFile;
            break;
        }
    }
}
if (!$file || !$projectRoot || !is_file($file)) { http_response_code(404); exit; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
if (!str_starts_with($mime, 'image/')) { http_response_code(415); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($file);
