<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\SiteHizmetModel;

Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    yanitla(405, ['ok' => false, 'mesaj' => 'Geçersiz istek.']);
}

$beklenen = trim((string) ($_ENV['SITE_API_KEY'] ?? ''), " \t\n\r\0\x0B\"'");
if ($beklenen === '') {
    error_log('site-hizmetler.php: SITE_API_KEY tanımlı değil.');
    yanitla(503, ['ok' => false, 'mesaj' => 'Servis kullanılamıyor.']);
}

$basliklar = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : [];
$gelen = (string) ($basliklar['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
if ($gelen === '' || !hash_equals($beklenen, $gelen)) {
    yanitla(401, ['ok' => false, 'mesaj' => 'Yetkisiz erişim.']);
}

$satirlar = static fn(?string $metin): array => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $metin) ?: []), static fn($s) => $s !== ''));

try {
    $hizmetler = [];
    foreach ((new SiteHizmetModel())->yayindakiListe() as $h) {
        $hizmetler[] = [
            'slug' => $h->slug,
            'baslik' => $h->baslik,
            'kisa_aciklama' => $h->kisa_aciklama,
            'aciklama' => $h->aciklama,
            'ozellikler' => $satirlar($h->ozellikler),
            'adimlar' => $satirlar($h->adimlar),
            'gorsel' => $h->gorsel,
            'sira' => (int) $h->sira,
        ];
    }
    yanitla(200, ['ok' => true, 'hizmetler' => $hizmetler]);
} catch (Throwable $ex) {
    error_log('site-hizmetler.php hatası: ' . $ex->getMessage());
    yanitla(500, ['ok' => false, 'mesaj' => 'Bir hata oluştu.']);
}
