<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helper\Security;
use App\Model\IkIlanModel;

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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    yanitla(405, ['ok' => false, 'mesaj' => 'Geçersiz istek.']);
}

$beklenenAnahtar = trim((string) ($_ENV['KARIYER_API_KEY'] ?? ''), " \t\n\r\0\x0B\"'");
$firmaId = (int) ($_ENV['KARIYER_FIRMA_ID'] ?? 0);
if ($beklenenAnahtar === '' || $firmaId <= 0) {
    error_log('kariyer-ilanlar.php: KARIYER_API_KEY veya KARIYER_FIRMA_ID tanımlı değil.');
    yanitla(503, ['ok' => false, 'mesaj' => 'Servis kullanılamıyor.']);
}

$basliklar = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : [];
$gelenAnahtar = (string) ($basliklar['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
if ($gelenAnahtar === '' || !hash_equals($beklenenAnahtar, $gelenAnahtar)) {
    yanitla(401, ['ok' => false, 'mesaj' => 'Yetkisiz erişim.']);
}

try {
    $model = new IkIlanModel();

    if (isset($_GET['token'])) {
        $ilan = $model->tokenIleBul(strtolower(trim((string) $_GET['token'])));
        if (!$ilan || (int) $ilan->firma_id !== $firmaId) {
            yanitla(404, ['ok' => false, 'mesaj' => 'İlan bulunamadı.']);
        }
        $sorular = json_decode((string) ($ilan->sorular ?? ''), true);
        $sorular = is_array($sorular) ? array_values(array_filter(array_map('strval', $sorular), static fn($s) => trim($s) !== '')) : [];
        yanitla(200, ['ok' => true, 'ilan' => [
            'baslik' => $ilan->baslik,
            'departman' => $ilan->departman,
            'gorev' => $ilan->gorev,
            'calisma_yeri' => $ilan->calisma_yeri,
            'aciklama' => $ilan->aciklama,
            'aranan_nitelikler' => $ilan->aranan_nitelikler,
            'token' => strtolower(trim((string) $_GET['token'])),
            'yayin_tarihi' => $ilan->created_at,
            'gecerlilik_tarihi' => $ilan->gecerlilik_tarihi,
            'firma_adi' => $ilan->firma_adi,
            'sorular' => $sorular,
        ]]);
    }

    $ilanlar = [];
    foreach ($model->yayindakiListe($firmaId) as $ilan) {
        $token = (string) Security::decrypt((string) $ilan->token_sifreli);
        if ($token === '') {
            continue;
        }
        $ilanlar[] = [
            'baslik' => $ilan->baslik,
            'departman' => $ilan->departman,
            'gorev' => $ilan->gorev,
            'calisma_yeri' => $ilan->calisma_yeri,
            'aciklama' => $ilan->aciklama,
            'aranan_nitelikler' => $ilan->aranan_nitelikler,
            'token' => $token,
            'yayin_tarihi' => $ilan->created_at,
        ];
    }
    yanitla(200, ['ok' => true, 'ilanlar' => $ilanlar]);
} catch (Throwable $ex) {
    error_log('kariyer-ilanlar.php hatası: ' . $ex->getMessage());
    yanitla(500, ['ok' => false, 'mesaj' => 'Bir hata oluştu.']);
}
