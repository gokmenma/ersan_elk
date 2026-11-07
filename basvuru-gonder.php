<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/Autoloader.php';

use App\Service\IkBasvuruKabulService;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$yanit = static function (bool $ok, string $mesaj, int $kod = 200): void {
    http_response_code($kod);
    echo json_encode(['ok' => $ok, 'mesaj' => $mesaj], JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $yanit(false, 'Geçersiz istek.', 405);
}

$siteBaglami = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if ($siteBaglami !== '' && !in_array($siteBaglami, ['same-origin', 'none'], true)) {
    $yanit(false, 'Geçersiz istek.', 403);
}
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    $originHost = (string) parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '');
    if (!hash_equals(strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')), strtolower($originHost))) {
        $yanit(false, 'Geçersiz istek.', 403);
    }
}

$sessionCsrf = (string) ($_SESSION['ik_basvuru_csrf'] ?? '');
if ($sessionCsrf === '' || !hash_equals($sessionCsrf, (string) ($_POST['csrf'] ?? ''))) {
    $yanit(false, 'Oturum süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.', 403);
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    $yanit(true, 'Başvurunuz alındı.');
}
if (time() - (int) ($_SESSION['ik_basvuru_form_zamani'] ?? 0) < 4) {
    $yanit(false, 'Lütfen formu doldurduktan sonra tekrar gönderin.', 429);
}

$ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'bilinmiyor'), 0, 45);
$servis = new IkBasvuruKabulService();

try {
    $sonuc = $servis->kaydet($_POST, $_FILES, $ip);
} catch (DomainException $ex) {
    $yanit(false, $ex->getMessage(), $ex->getCode() ?: 422);
} catch (Throwable $ex) {
    error_log('basvuru-gonder.php hatası: ' . $ex->getMessage());
    $yanit(false, 'Başvuru şu anda kaydedilemedi. Lütfen daha sonra tekrar deneyin.', 500);
}

unset($_SESSION['ik_basvuru_csrf']);

ignore_user_abort(true);
$govde = json_encode(['ok' => true, 'mesaj' => 'Başvurunuz alındı.'], JSON_UNESCAPED_UNICODE);
header('Content-Length: ' . strlen($govde));
header('Connection: close');
echo $govde;
if (function_exists('fastcgi_finish_request')) {
    session_write_close();
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
    error_log('basvuru-gonder.php arka plan hatası: ' . $ex->getMessage());
}
