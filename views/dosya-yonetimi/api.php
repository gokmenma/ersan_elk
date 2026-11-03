<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\PermissionPolicyModel;
use App\Service\DosyaYonetimiService;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$izinliAksiyonlar = [
    'agac', 'liste', 'ozet', 'klasor-icerik', 'paylasim-listele', 'klasor-rolleri',
    'yukle', 'dosya-yeniden-adlandir', 'dosya-tasi', 'klasor-yeniden-adlandir', 'klasor-tasi',
    'dosya-sil', 'klasor-sil', 'paylasim-olustur', 'paylasim-iptal', 'klasor-olustur', 'klasor-rolleri-kaydet',
    'klasor-sirala', 'erisim-listele', 'erisim-kullanicilar', 'erisim-ver', 'erisim-kaldir',
];
if (!in_array($action, $izinliAksiyonlar, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tanımsız aksiyon.']);
    exit;
}

if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Oturumunuz sonlanmış. Lütfen tekrar giriş yapın.']);
    exit;
}

Gate::authorizeApiPolicy('dosya-yonetimi/api', $action);

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
        case 'liste':
            $veri = $servis->liste($_POST);
            echo json_encode($veri, JSON_UNESCAPED_UNICODE);
            exit;
        case 'klasor-icerik':
            $veri = $servis->klasorIcerik($_POST['klasor_id'] ?? null, (string) ($_POST['arama'] ?? ''), $_POST['bolum'] ?? null);
            break;
        case 'klasor-olustur':
            $veri = $servis->klasorOlustur($_POST['parent_id'] ?? null, (string) ($_POST['ad'] ?? ''), (string) ($_POST['alan'] ?? 'kisisel'));
            break;
        case 'klasor-yeniden-adlandir':
            $servis->klasorYenidenAdlandir($_POST['id'] ?? null, (string) ($_POST['ad'] ?? ''));
            break;
        case 'klasor-tasi':
            $servis->klasorTasi($_POST['id'] ?? null, $_POST['hedef_id'] ?? null);
            break;
        case 'klasor-sirala':
            $servis->klasorSirala((array) ($_POST['idler'] ?? []));
            break;
        case 'klasor-sil':
            $servis->klasorSil($_POST['id'] ?? null);
            break;
        case 'klasor-rolleri':
            $veri = $servis->klasorRolleri($_POST['id'] ?? null);
            break;
        case 'klasor-rolleri-kaydet':
            $servis->klasorRolleriKaydet($_POST['id'] ?? null, (array) ($_POST['roller'] ?? []));
            break;
        case 'yukle':
            $yuklenenler = [];
            $dosyalar = $_FILES['dosyalar'] ?? null;
            if (!$dosyalar || !is_array($dosyalar['name'] ?? null)) {
                throw new DomainException('Dosya seçilmedi.');
            }
            $hatalar = [];
            foreach (array_keys($dosyalar['name']) as $i) {
                try {
                    $yuklenenler[] = $servis->dosyaYukle($_POST['klasor_id'] ?? null, [
                        'name' => $dosyalar['name'][$i],
                        'tmp_name' => $dosyalar['tmp_name'][$i],
                        'size' => $dosyalar['size'][$i],
                        'error' => $dosyalar['error'][$i],
                    ], (string) ($_POST['aciklama'] ?? ''));
                } catch (DomainException $e) {
                    $hatalar[] = $dosyalar['name'][$i] . ': ' . $e->getMessage();
                }
            }
            if (!$yuklenenler && $hatalar) {
                throw new DomainException(implode("\n", $hatalar));
            }
            $veri = ['yuklenen' => count($yuklenenler), 'hatalar' => $hatalar];
            break;
        case 'dosya-yeniden-adlandir':
            $servis->dosyaYenidenAdlandir($_POST['id'] ?? null, (string) ($_POST['ad'] ?? ''));
            break;
        case 'dosya-tasi':
            $servis->dosyaTasi($_POST['id'] ?? null, $_POST['hedef_id'] ?? null);
            break;
        case 'dosya-sil':
            $servis->dosyaSil($_POST['id'] ?? null);
            break;
        case 'paylasim-olustur':
            $sonuc = $servis->paylasimOlustur(
                $_POST['id'] ?? null,
                (int) ($_POST['gun'] ?? 7),
                isset($_POST['sifre']) ? (string) $_POST['sifre'] : null,
                isset($_POST['limit']) && $_POST['limit'] !== '' ? (int) $_POST['limit'] : null
            );
            $veri = ['url' => DosyaYonetimiService::paylasimUrl($sonuc['token']), 'gun' => $sonuc['gun']];
            break;
        case 'paylasim-listele':
            $veri = $servis->paylasimListele($_POST['id'] ?? null);
            break;
        case 'paylasim-iptal':
            $servis->paylasimIptal($_POST['id'] ?? null);
            break;
        case 'erisim-kullanicilar':
            $veri = $servis->erisimKullanicilari();
            break;
        case 'erisim-listele':
            $veri = $servis->erisimListele((string) ($_POST['tur'] ?? ''), $_POST['id'] ?? null);
            break;
        case 'erisim-ver':
            $servis->erisimVer((string) ($_POST['tur'] ?? ''), $_POST['id'] ?? null, (int) ($_POST['user_id'] ?? 0), (string) ($_POST['seviye'] ?? 'goruntule'));
            break;
        case 'erisim-kaldir':
            $servis->erisimKaldir($_POST['id'] ?? null);
            break;
    }

    echo json_encode(['status' => 'success', 'data' => $veri], JSON_UNESCAPED_UNICODE);
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('dosya-yonetimi/api [' . $action . ']: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'İşlem sırasında bir hata oluştu.']);
}
