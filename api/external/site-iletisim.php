<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\SiteMesajModel;
use App\Service\SiteMesajBildirimService;

Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

const SITE_MESAJ_SAATLIK_SINIR = 5;

function yanitla(int $kod, array $veri): void
{
    http_response_code($kod);
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    yanitla(405, ['ok' => false, 'mesaj' => 'Geçersiz istek.']);
}

$beklenen = trim((string) ($_ENV['SITE_API_KEY'] ?? ''), " \t\n\r\0\x0B\"'");
if ($beklenen === '') {
    error_log('site-iletisim.php: SITE_API_KEY tanımlı değil.');
    yanitla(503, ['ok' => false, 'mesaj' => 'Servis kullanılamıyor.']);
}

$basliklar = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : [];
$gelen = (string) ($basliklar['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
if ($gelen === '' || !hash_equals($beklenen, $gelen)) {
    yanitla(401, ['ok' => false, 'mesaj' => 'Yetkisiz erişim.']);
}

$girdi = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($girdi)) {
    yanitla(400, ['ok' => false, 'mesaj' => 'Geçersiz istek.']);
}

$metin = static fn(string $alan, int $limit): string => trim(mb_substr(str_replace("\r", '', (string) ($girdi[$alan] ?? '')), 0, $limit, 'UTF-8'));

$ip = trim((string) ($girdi['ip'] ?? ''));
$ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';

$veri = [
    'ad_soyad' => $metin('ad_soyad', 150),
    'telefon' => preg_replace('/[^0-9+\s()-]/', '', $metin('telefon', 30)) ?? '',
    'email' => $metin('email', 190),
    'konu' => $metin('konu', 200),
    'mesaj' => $metin('mesaj', 3000),
    'ip_adresi' => $ip,
];

$hatalar = [];
if (mb_strlen($veri['ad_soyad'], 'UTF-8') < 3) {
    $hatalar['ad_soyad'] = 'Lütfen adınızı ve soyadınızı yazın.';
}
if ($veri['telefon'] === '' && $veri['email'] === '') {
    $hatalar['telefon'] = 'Telefon veya e-posta bilgilerinden en az birini yazın.';
}
if ($veri['telefon'] !== '' && strlen((string) preg_replace('/\D/', '', $veri['telefon'])) < 10) {
    $hatalar['telefon'] = 'Geçerli bir telefon numarası yazın.';
}
if ($veri['email'] !== '' && !filter_var($veri['email'], FILTER_VALIDATE_EMAIL)) {
    $hatalar['email'] = 'Geçerli bir e-posta adresi yazın.';
}
if (mb_strlen($veri['mesaj'], 'UTF-8') < 10) {
    $hatalar['mesaj'] = 'Mesajınız en az 10 karakter olmalıdır.';
}
if (empty($girdi['kvkk'])) {
    $hatalar['kvkk'] = 'Devam etmek için KVKK aydınlatma metnini onaylayın.';
}
if ($hatalar) {
    yanitla(422, ['ok' => false, 'mesaj' => 'Lütfen işaretli alanları kontrol edin.', 'hatalar' => $hatalar]);
}

try {
    $Mesaj = new SiteMesajModel();
    if ($ip !== '' && $Mesaj->denemeSayisi($ip, 60) >= SITE_MESAJ_SAATLIK_SINIR) {
        yanitla(429, ['ok' => false, 'mesaj' => 'Çok fazla deneme yaptınız. Lütfen daha sonra tekrar deneyin.']);
    }
    $Mesaj->ekle($veri);
    if ($ip !== '') {
        $Mesaj->denemeKaydet($ip, 'kayit');
    }
    (new SiteMesajBildirimService())->yeniMesaj($veri['ad_soyad'], $veri['konu'], $veri['mesaj']);
    yanitla(200, ['ok' => true, 'mesaj' => 'Mesajınız bize ulaştı. En kısa sürede sizinle iletişime geçeceğiz.']);
} catch (Throwable $ex) {
    error_log('site-iletisim.php hatası: ' . $ex->getMessage());
    yanitla(500, ['ok' => false, 'mesaj' => 'Mesajınız şu anda gönderilemedi. Lütfen daha sonra tekrar deneyin.']);
}
