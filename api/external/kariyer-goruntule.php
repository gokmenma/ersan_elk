<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    yanitla(405, ['ok' => false]);
}

$beklenenAnahtar = trim((string) ($_ENV['KARIYER_API_KEY'] ?? ''), " \t\n\r\0\x0B\"'");
$firmaId = (int) ($_ENV['KARIYER_FIRMA_ID'] ?? 0);
if ($beklenenAnahtar === '' || $firmaId <= 0) {
    error_log('kariyer-goruntule.php: KARIYER_API_KEY veya KARIYER_FIRMA_ID tanımlı değil.');
    yanitla(503, ['ok' => false]);
}

$basliklar = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : [];
$gelenAnahtar = (string) ($basliklar['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
if ($gelenAnahtar === '' || !hash_equals($beklenenAnahtar, $gelenAnahtar)) {
    yanitla(401, ['ok' => false]);
}

try {
    $model = new IkIlanModel();
    $kaydedildi = $model->goruntulemeKaydet(
        strtolower(trim((string) ($_POST['token'] ?? ''))),
        strtolower(trim((string) ($_POST['ziyaretci'] ?? ''))),
        $firmaId
    );
    yanitla($kaydedildi ? 200 : 404, ['ok' => $kaydedildi]);
} catch (Throwable $ex) {
    error_log('kariyer-goruntule.php hatası: ' . $ex->getMessage());
    yanitla(500, ['ok' => false]);
}
