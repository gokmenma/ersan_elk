<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\SiteProjeModel;

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
    error_log('site-projeler.php: SITE_API_KEY tanımlı değil.');
    yanitla(503, ['ok' => false, 'mesaj' => 'Servis kullanılamıyor.']);
}

$basliklar = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : [];
$gelen = (string) ($basliklar['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
if ($gelen === '' || !hash_equals($beklenen, $gelen)) {
    yanitla(401, ['ok' => false, 'mesaj' => 'Yetkisiz erişim.']);
}

try {
    $projeler = [];
    foreach ((new SiteProjeModel())->yayindakiListe() as $p) {
        $projeler[] = [
            'slug' => $p->slug,
            'baslik' => $p->baslik,
            'kategori' => $p->kategori,
            'musteri' => $p->musteri,
            'konum' => $p->konum,
            'proje_yili' => $p->proje_yili !== null ? (int) $p->proje_yili : null,
            'kisa_aciklama' => $p->kisa_aciklama,
            'aciklama' => $p->aciklama,
            'gorsel' => $p->gorsel,
            'sira' => (int) $p->sira,
        ];
    }
    yanitla(200, ['ok' => true, 'projeler' => $projeler]);
} catch (Throwable $ex) {
    error_log('site-projeler.php hatası: ' . $ex->getMessage());
    yanitla(500, ['ok' => false, 'mesaj' => 'Bir hata oluştu.']);
}
