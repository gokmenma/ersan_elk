<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\AparatHareketModel;
use App\Model\AparatStokModel;
use App\Model\PermissionPolicyModel;
use App\Service\AparatStokService;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once dirname(__DIR__, 3) . '/Autoloader.php';
date_default_timezone_set('Europe/Istanbul');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function aparatMobilYanit(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function aparatMobilEkipId(string $sifreli): int
{
    try {
        $deger = Security::decrypt($sifreli);
        return is_numeric($deger) ? (int) $deger : 0;
    } catch (Throwable) {
        return 0;
    }
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
    aparatMobilYanit(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
}
if ((int) ($_SESSION['firma_id'] ?? 0) <= 0) {
    aparatMobilYanit(['success' => false, 'message' => 'Geçerli bir firma seçilmemiş.'], 400);
}

$superAdmin = Gate::isSuperAdmin();
if (!Gate::allows('aparat_takip') && !$superAdmin) {
    aparatMobilYanit(['success' => false, 'message' => 'Aparat takip yetkiniz bulunmuyor.'], 403);
}

$action = (string) ($_GET['action'] ?? 'overview');
if ((new PermissionPolicyModel())->isReady()) {
    Gate::authorizeApiPolicy('mobile-spa/aparat-takip', $action);
}

$depoYetkisi = Gate::allows('aparat_depo') || $superAdmin;
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$userId = (int) $_SESSION['user_id'];

if ($method === 'POST') {
    $gelen = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $bilinen = (string) ($_SESSION['csrf_token'] ?? '');
    if ($gelen === '' || $bilinen === '' || !hash_equals($bilinen, $gelen)) {
        aparatMobilYanit(['success' => false, 'message' => 'Güvenlik doğrulamasının süresi doldu. Lütfen sayfayı yenileyin.'], 419);
    }
}

session_write_close();

$Stok = new AparatStokModel();
$Hareket = new AparatHareketModel();

try {
    if ($method === 'GET' && $action === 'overview') {
        $matris = $Stok->matris();
        $kritik = $Stok->kritikStoklar();
        $uyeler = $Stok->ekipUyeHaritasi();

        $kritikHarita = [];
        foreach ($kritik['ekipler'] as $k) {
            foreach ($k['eksikler'] as $e) {
                $kritikHarita[$k['ekip_id']][$e['aparat_tip_id']] = $e['seviye'];
            }
        }
        $depoKritik = [];
        foreach ($kritik['depo'] as $e) {
            $depoKritik[$e['aparat_tip_id']] = $e['seviye'];
        }

        $tipler = [];
        foreach ($matris['tipler'] as $t) {
            if ((int) $t['is_active'] !== 1) {
                continue;
            }
            $tipler[] = [
                'id' => (int) $t['id'],
                'name' => (string) $t['ad'],
                'code' => (string) $t['kod'],
                'depot_limit' => (int) $t['depo_kritik_stok'],
                'team_limit' => (int) $t['ekip_kritik_stok'],
            ];
        }
        $aktifTipIds = array_column($tipler, 'id');

        $ozet = ['depot' => 0, 'teams' => 0, 'field' => 0, 'critical' => (int) $kritik['toplam']];
        $depo = ['counts' => [], 'critical' => []];
        $ekipler = [];

        foreach ($matris['satirlar'] as $satir) {
            $adetler = [];
            foreach ($aktifTipIds as $tipId) {
                $adetler[(string) $tipId] = (int) ($satir['adetler'][$tipId] ?? 0);
            }

            if ($satir['sahip_tipi'] === 'depo') {
                $ozet['depot'] = (int) $satir['toplam'];
                $depo['counts'] = $adetler;
                foreach ($depoKritik as $tipId => $seviye) {
                    $depo['critical'][(string) $tipId] = $seviye;
                }
                continue;
            }
            if ($satir['sahip_tipi'] === 'saha') {
                $ozet['field'] = (int) $satir['toplam'];
                continue;
            }
            if ($satir['sahip_tipi'] !== 'ekip') {
                continue;
            }

            $ekipId = (int) $satir['sahip_id'];
            $ozet['teams'] += (int) $satir['toplam'];
            $ekipKritik = [];
            foreach ($kritikHarita[$ekipId] ?? [] as $tipId => $seviye) {
                $ekipKritik[(string) $tipId] = $seviye;
            }

            $ekipler[] = [
                'id' => Security::encrypt((string) $ekipId),
                'name' => ($uyeler[$ekipId] ?? '') !== '' ? (string) $uyeler[$ekipId] : 'Personel atanmamış',
                'code' => (string) $satir['ekip_kodu'],
                'region' => (string) ($satir['bolge'] ?? ''),
                'staffed' => ($uyeler[$ekipId] ?? '') !== '',
                'total' => (int) $satir['toplam'],
                'counts' => $adetler,
                'critical' => $ekipKritik,
            ];
        }

        $etiketler = ['depo_giris' => 'Depoya Giriş', 'depo_cikis' => 'Ekibe Verildi', 'depo_iade' => 'İade Alındı'];
        $hareketler = [];
        foreach ($Hareket->sonDepoHareketleri(10) as $h) {
            $ekipId = (int) ($h['ekip_id'] ?? 0);
            $hareketler[] = [
                'type' => (string) $h['hareket_tipi'],
                'label' => $etiketler[$h['hareket_tipi']],
                'apparatus' => (string) $h['aparat_adi'],
                'quantity' => abs((int) $h['adet']),
                'team' => $ekipId > 0 ? (string) (($uyeler[$ekipId] ?? '') !== '' ? $uyeler[$ekipId] : $h['ekip_adi']) : '',
                'user' => (string) ($h['kullanici_adi'] ?? ''),
                'note' => (string) ($h['aciklama'] ?? ''),
                'date' => (string) $h['tarih'],
            ];
        }

        aparatMobilYanit([
            'success' => true,
            'summary' => $ozet,
            'types' => $tipler,
            'depot' => $depo,
            'teams' => $ekipler,
            'movements' => $hareketler,
            'can_move' => $depoYetkisi,
        ]);
    }

    if ($method === 'POST' && $action === 'move') {
        if (!$depoYetkisi) {
            aparatMobilYanit(['success' => false, 'message' => 'Depo işlemi yetkiniz bulunmuyor.'], 403);
        }

        $veri = json_decode((string) file_get_contents('php://input'), true);
        $veri = is_array($veri) ? $veri : [];

        $tur = (string) ($veri['type'] ?? '');
        if (!in_array($tur, ['depo_giris', 'depo_cikis', 'depo_iade'], true)) {
            aparatMobilYanit(['success' => false, 'message' => 'Geçersiz işlem türü.'], 422);
        }

        $ekipId = $tur === 'depo_giris' ? 0 : aparatMobilEkipId((string) ($veri['team_id'] ?? ''));
        if ($tur !== 'depo_giris' && $ekipId <= 0) {
            aparatMobilYanit(['success' => false, 'message' => 'Ekip seçmelisiniz.'], 422);
        }

        $sonuc = (new AparatStokService())->havuzHareketi($tur, [
            'aparat_tip_id' => (int) ($veri['type_id'] ?? 0),
            'adet' => (int) ($veri['quantity'] ?? 0),
            'ekip_id' => $ekipId,
            'aciklama' => mb_substr(trim((string) ($veri['note'] ?? '')), 0, 255),
        ], $userId);

        aparatMobilYanit([
            'success' => true,
            'message' => $sonuc['negatif'] ? 'Kaydedildi. Dikkat: stok eksiye düştü.' : 'Kaydedildi.',
        ]);
    }

    aparatMobilYanit(['success' => false, 'message' => 'Geçersiz istek.'], 400);
} catch (Throwable $e) {
    error_log('Mobil aparat takip API hatası [' . $action . ']: ' . $e->getMessage());
    $mesaj = get_class($e) === Exception::class && $e->getMessage() !== '' ? $e->getMessage() : 'İşlem sırasında bir hata oluştu.';
    aparatMobilYanit(['success' => false, 'message' => $mesaj], 422);
}
