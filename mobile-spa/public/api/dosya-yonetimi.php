<?php

declare(strict_types=1);

use App\Service\DosyaYonetimiService;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function dosyaApiYanit(array $payload, int $durum = 200): never
{
    http_response_code($durum);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$yetkiHaritasi = [
    'agac' => 'dosya_yonetimi_goruntule',
    'ozet' => 'dosya_yonetimi_goruntule',
    'icerik' => 'dosya_yonetimi_goruntule',
    'indir' => 'dosya_yonetimi_goruntule',
    'yukle' => 'dosya_yonetimi_yukle',
    'dosya_adlandir' => 'dosya_yonetimi_duzenle',
    'dosya_tasi' => 'dosya_yonetimi_duzenle',
    'klasor_adlandir' => 'dosya_yonetimi_duzenle',
    'klasor_tasi' => 'dosya_yonetimi_duzenle',
    'dosya_sil' => 'dosya_yonetimi_sil',
    'klasor_sil' => 'dosya_yonetimi_sil',
    'paylasim_olustur' => 'dosya_yonetimi_paylas',
    'paylasim_listele' => 'dosya_yonetimi_paylas',
    'paylasim_iptal' => 'dosya_yonetimi_paylas',
    'klasor_olustur' => 'dosya_yonetimi_klasor_yonet',
    'klasor_roller' => 'dosya_yonetimi_klasor_yonet',
    'klasor_roller_kaydet' => 'dosya_yonetimi_klasor_yonet',
];
$okumaAksiyonlari = ['agac', 'ozet', 'icerik', 'indir', 'paylasim_listele', 'klasor_roller'];

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!isset($yetkiHaritasi[$action])) {
    dosyaApiYanit(['success' => false, 'message' => 'Tanımsız işlem.'], 400);
}
if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
    dosyaApiYanit(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
}
if (!Gate::allows($yetkiHaritasi[$action]) && !Gate::isSuperAdmin()) {
    dosyaApiYanit(['success' => false, 'message' => 'Bu işlem için yetkiniz bulunmuyor.'], 403);
}

$govde = [];
if ($method === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $oturumToken = $_SESSION['csrf_token'] ?? '';
    if ($token === '' || $oturumToken === '' || !hash_equals((string) $oturumToken, (string) $token)) {
        dosyaApiYanit(['success' => false, 'message' => 'Geçersiz güvenlik doğrulaması. Lütfen sayfayı yenileyin.'], 419);
    }
    $ham = file_get_contents('php://input');
    $json = ($ham !== '' && str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) ? json_decode($ham, true) : null;
    $govde = is_array($json) ? $json : $_POST;
} elseif (!in_array($action, $okumaAksiyonlari, true)) {
    dosyaApiYanit(['success' => false, 'message' => 'Geçersiz istek yöntemi.'], 405);
}
$girdi = $method === 'POST' ? $govde : $_GET;

try {
    $servis = new DosyaYonetimiService();
    $veri = [];

    switch ($action) {
        case 'agac':
            $veri = $servis->agac();
            break;
        case 'ozet':
            $veri = $servis->ozet();
            break;
        case 'icerik':
            $veri = $servis->klasorIcerik($girdi['klasor_id'] ?? null, (string) ($girdi['arama'] ?? ''));
            break;
        case 'indir':
            $servis->akit($girdi['id'] ?? null, ($girdi['inline'] ?? '') === '1');
        case 'yukle':
            $dosyalar = $_FILES['dosyalar'] ?? null;
            if (!$dosyalar || !is_array($dosyalar['name'] ?? null)) {
                throw new DomainException('Dosya seçilmedi.');
            }
            $yuklenen = 0;
            $hatalar = [];
            foreach (array_keys($dosyalar['name']) as $i) {
                try {
                    $servis->dosyaYukle($govde['klasor_id'] ?? null, [
                        'name' => $dosyalar['name'][$i],
                        'tmp_name' => $dosyalar['tmp_name'][$i],
                        'size' => $dosyalar['size'][$i],
                        'error' => $dosyalar['error'][$i],
                    ], (string) ($govde['aciklama'] ?? ''));
                    $yuklenen++;
                } catch (DomainException $e) {
                    $hatalar[] = $dosyalar['name'][$i] . ': ' . $e->getMessage();
                }
            }
            if ($yuklenen === 0 && $hatalar) {
                throw new DomainException(implode("\n", $hatalar));
            }
            $veri = ['yuklenen' => $yuklenen, 'hatalar' => $hatalar];
            break;
        case 'dosya_adlandir':
            $servis->dosyaYenidenAdlandir($govde['id'] ?? null, (string) ($govde['ad'] ?? ''));
            break;
        case 'dosya_tasi':
            $servis->dosyaTasi($govde['id'] ?? null, $govde['hedef_id'] ?? null);
            break;
        case 'dosya_sil':
            $servis->dosyaSil($govde['id'] ?? null);
            break;
        case 'klasor_olustur':
            $veri = $servis->klasorOlustur($govde['parent_id'] ?? null, (string) ($govde['ad'] ?? ''));
            break;
        case 'klasor_adlandir':
            $servis->klasorYenidenAdlandir($govde['id'] ?? null, (string) ($govde['ad'] ?? ''));
            break;
        case 'klasor_tasi':
            $servis->klasorTasi($govde['id'] ?? null, $govde['hedef_id'] ?? null);
            break;
        case 'klasor_sil':
            $servis->klasorSil($govde['id'] ?? null);
            break;
        case 'klasor_roller':
            $veri = $servis->klasorRolleri($girdi['id'] ?? null);
            break;
        case 'klasor_roller_kaydet':
            $servis->klasorRolleriKaydet($govde['id'] ?? null, (array) ($govde['roller'] ?? []));
            break;
        case 'paylasim_olustur':
            $sonuc = $servis->paylasimOlustur(
                $govde['id'] ?? null,
                (int) ($govde['gun'] ?? 7),
                isset($govde['sifre']) && $govde['sifre'] !== '' ? (string) $govde['sifre'] : null,
                isset($govde['limit']) && $govde['limit'] !== '' ? (int) $govde['limit'] : null
            );
            $veri = ['url' => DosyaYonetimiService::paylasimUrl($sonuc['token']), 'gun' => $sonuc['gun']];
            break;
        case 'paylasim_listele':
            $veri = $servis->paylasimListele($girdi['id'] ?? null);
            break;
        case 'paylasim_iptal':
            $servis->paylasimIptal($govde['id'] ?? null);
            break;
    }

    dosyaApiYanit(['success' => true, 'data' => $veri]);
} catch (DomainException $e) {
    dosyaApiYanit(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('mobil dosya-yonetimi [' . $action . ']: ' . $e->getMessage());
    dosyaApiYanit(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu.'], 500);
}
