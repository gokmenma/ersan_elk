<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Service\IkBasvuruKabulService;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function yanitla(int $kod, array $veri): void
{
    http_response_code($kod);
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    yanitla(405, ['ok' => false, 'mesaj' => 'Geçersiz istek.']);
}

$beklenenAnahtar = trim((string) ($_ENV['KARIYER_API_KEY'] ?? ''), " \t\n\r\0\x0B\"'");
$firmaId = (int) ($_ENV['KARIYER_FIRMA_ID'] ?? 0);
if ($beklenenAnahtar === '' || $firmaId <= 0) {
    error_log('kariyer-basvuru.php: KARIYER_API_KEY veya KARIYER_FIRMA_ID tanımlı değil.');
    yanitla(503, ['ok' => false, 'mesaj' => 'Servis kullanılamıyor.']);
}

$basliklar = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : [];
$gelenAnahtar = (string) ($basliklar['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
if ($gelenAnahtar === '' || !hash_equals($beklenenAnahtar, $gelenAnahtar)) {
    yanitla(401, ['ok' => false, 'mesaj' => 'Yetkisiz erişim.']);
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    yanitla(200, ['ok' => true, 'mesaj' => 'Başvurunuz alındı.']);
}

$istemciIp = trim((string) ($basliklar['x-client-ip'] ?? $_SERVER['HTTP_X_CLIENT_IP'] ?? ''));
$ip = filter_var($istemciIp, FILTER_VALIDATE_IP) ? $istemciIp : (string) ($_SERVER['REMOTE_ADDR'] ?? 'bilinmiyor');
$ip = substr($ip, 0, 45);

$servis = new IkBasvuruKabulService();

try {
    $sonuc = $servis->kaydet($_POST, $_FILES, $ip, $firmaId);
} catch (DomainException $ex) {
    yanitla($ex->getCode() ?: 422, ['ok' => false, 'mesaj' => $ex->getMessage()]);
} catch (Throwable $ex) {
    error_log('kariyer-basvuru.php hatası: ' . $ex->getMessage());
    yanitla(500, ['ok' => false, 'mesaj' => 'Başvuru şu anda kaydedilemedi. Lütfen daha sonra tekrar deneyin.']);
}

ignore_user_abort(true);
$govde = json_encode(['ok' => true, 'mesaj' => 'Başvurunuz alındı.'], JSON_UNESCAPED_UNICODE);
header('Content-Length: ' . strlen($govde));
header('Connection: close');
echo $govde;
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

set_time_limit(180);
try {
    $servis->arkaPlanIsle($sonuc);
} catch (Throwable $ex) {
    error_log('kariyer-basvuru.php arka plan hatası: ' . $ex->getMessage());
}
