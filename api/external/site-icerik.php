<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\SiteAyarModel;
use App\Model\SiteIstatistikModel;
use App\Model\SiteReferansModel;
use App\Model\SiteSssModel;

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
    error_log('site-icerik.php: SITE_API_KEY tanımlı değil.');
    yanitla(503, ['ok' => false, 'mesaj' => 'Servis kullanılamıyor.']);
}

$basliklar = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : [];
$gelen = (string) ($basliklar['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
if ($gelen === '' || !hash_equals($beklenen, $gelen)) {
    yanitla(401, ['ok' => false, 'mesaj' => 'Yetkisiz erişim.']);
}

try {
    $referanslar = [];
    foreach ((new SiteReferansModel())->yayindakiListe() as $r) {
        $referanslar[] = ['kurum' => $r->kurum, 'kisi' => $r->kisi, 'unvan' => $r->unvan, 'yorum' => $r->yorum, 'logo' => $r->logo];
    }
    $sss = [];
    foreach ((new SiteSssModel())->yayindakiListe() as $s) {
        $sss[] = ['soru' => $s->soru, 'cevap' => $s->cevap];
    }
    $istatistikler = [];
    foreach ((new SiteIstatistikModel())->yayindakiListe() as $i) {
        $istatistikler[] = ['etiket' => $i->etiket, 'deger' => (int) $i->deger, 'sonek' => (string) $i->sonek];
    }
    $ayarlar = array_filter((new SiteAyarModel())->tumu(), static fn($d) => $d !== '');
    yanitla(200, ['ok' => true, 'referanslar' => $referanslar, 'sss' => $sss, 'istatistikler' => $istatistikler, 'ayarlar' => (object) $ayarlar]);
} catch (Throwable $ex) {
    error_log('site-icerik.php hatası: ' . $ex->getMessage());
    yanitla(500, ['ok' => false, 'mesaj' => 'Bir hata oluştu.']);
}
