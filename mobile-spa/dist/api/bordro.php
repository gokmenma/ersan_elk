<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\BordroDonemModel;
use App\Model\BordroParametreModel;
use App\Model\BordroPersonelModel;
use App\Model\MenuModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function bordroApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? ($_SESSION['user']->id ?? 0));
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);

if ($userId <= 0 || $firmaId <= 0) {
    bordroApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
}

$menuModel = new MenuModel();
$hasAccess = Gate::allowsAny(['bordro/list', 'bordro/parametreler', 'bordro/raporlar'])
    || $menuModel->userCanAccessMenuLink($userId, 'bordro/list');

if (!$hasAccess) {
    bordroApiResponse(['success' => false, 'message' => 'Bordro sayfasını görüntüleme yetkiniz bulunmuyor.'], 403);
}

function verifyBordroCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals((string) $sessionToken, (string) $token)) {
        bordroApiResponse(['success' => false, 'message' => 'Geçersiz güvenlik doğrulaması (CSRF). Lütfen sayfayı yenileyin.'], 419);
    }
}

$decryptId = static function ($value): int {
    if (!is_string($value) || trim($value) === '') {
        return 0;
    }
    try {
        return (int) Security::decrypt(trim($value));
    } catch (\Throwable $e) {
        return 0;
    }
};

$personnelInitials = static function (string $name): string {
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts === []) {
        return '?';
    }
    $selected = count($parts) > 1 ? [$parts[0], $parts[count($parts) - 1]] : [$parts[0]];
    return implode('', array_map(static fn(string $part): string => mb_strtoupper(mb_substr($part, 0, 1)), $selected));
};

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'init');

// JSON gövdesi varsa $_POST ve $_GET'e aktar
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    if ($rawInput !== false && $rawInput !== '') {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            foreach ($json as $k => $v) {
                if (!isset($_POST[$k])) {
                    $_POST[$k] = $v;
                }
            }
            if (!empty($json['action'])) {
                $action = (string) $json['action'];
            }
        }
    }
}

// 1. GET İŞLEMLERİ (Başlatma, Liste ve Detay)
if ($method === 'GET') {
    if ($action === 'init' || $action === 'list') {
        try {
            $donemModel = new BordroDonemModel();
            $bordroModel = new BordroPersonelModel();
            $parametreModel = new BordroParametreModel();

            $allDonemler = $donemModel->getAllDonemsForFilter();
            $selectedId = 0;
            if (!empty($_GET['donem'])) {
                $selectedId = $decryptId($_GET['donem']);
            }

            $selectedDonem = null;
            $selectedDonemToken = '';
            $donemOptions = [];
            foreach ($allDonemler as $donem) {
                $dId = (int) $donem->id;
                $encToken = Security::encrypt((string) $dId);
                $isClosed = (int) ($donem->kapali_mi ?? 0) === 1;
                $donemOptions[] = [
                    'id' => $encToken,
                    'raw_id' => $dId,
                    'name' => (string) ($donem->donem_adi ?? 'Dönem'),
                    'closed' => $isClosed,
                    'start_date' => (string) ($donem->baslangic_tarihi ?? ''),
                    'end_date' => (string) ($donem->bitis_tarihi ?? ''),
                ];

                if (($selectedId > 0 && $dId === $selectedId) || ($selectedId === 0 && $selectedDonem === null)) {
                    $selectedDonem = $donem;
                    $selectedDonemToken = $encToken;
                }
            }

            $items = [];
            $summary = [
                'personel' => 0,
                'alacak' => 0.0,
                'kesinti' => 0.0,
                'net' => 0.0,
                'banka' => 0.0,
                'sodexo' => 0.0,
                'elden' => 0.0,
                'diger' => 0.0,
                'hesaplanan' => 0,
                'hesaplanmayan' => 0,
            ];

            $selectedDonemData = null;
            $closed = false;

            if ($selectedDonem && (int) $selectedDonem->firma_id === $firmaId) {
                $closed = (int) ($selectedDonem->kapali_mi ?? 0) === 1;
                $selectedDonemData = [
                    'id' => $selectedDonemToken ?: Security::encrypt((string) $selectedDonem->id),
                    'raw_id' => (int) $selectedDonem->id,
                    'name' => (string) ($selectedDonem->donem_adi ?? ''),
                    'closed' => $closed,
                    'start_date' => (string) ($selectedDonem->baslangic_tarihi ?? ''),
                    'end_date' => (string) ($selectedDonem->bitis_tarihi ?? ''),
                ];

                $personeller = $bordroModel->getPersonellerByDonem((int) $selectedDonem->id);
                $asgariNet = (float) ($parametreModel->getGenelAyar('asgari_ucret_net', $selectedDonem->baslangic_tarihi) ?? 0);

                foreach ($personeller as $personel) {
                    $calc = $bordroModel->hesaplaOrtakGosterimDegerleri($personel, $selectedDonem, $asgariNet);
                    $name = trim((string) ($personel->adi_soyadi ?? ''));
                    $encPersonelId = Security::encrypt((string) $personel->personel_id);
                    $hasPhoto = !empty($personel->resim_yolu) && is_file(dirname(__DIR__, 3) . '/' . ltrim(str_replace('\\', '/', $personel->resim_yolu), '/'));
                    $isCalculated = !empty($personel->hesaplama_tarihi);

                    $net = (float) ($calc['netAlacagi'] ?? 0);
                    $alacak = (float) ($calc['toplamAlacagi'] ?? 0);
                    $kesinti = (float) ($calc['kesintiHaricIcra'] ?? 0);
                    $icra = (float) ($calc['icraKesintisi'] ?? 0);
                    $banka = (float) ($calc['bankaOdemesi'] ?? 0);
                    $sodexo = (float) ($calc['sodexoOdemesi'] ?? 0);
                    $elden = (float) ($calc['eldenOdeme'] ?? 0);
                    $diger = (float) ($personel->diger_odeme ?? 0);
                    $gun = (float) ($calc['calismaGunu'] ?? 0);

                    $items[] = [
                        'token' => Security::encrypt((string) $personel->id),
                        'personel_token' => $encPersonelId,
                        'name' => $name ?: 'İsimsiz Personel',
                        'initials' => $personnelInitials($name ?: 'İP'),
                        'avatar' => $hasPhoto ? '/api/personnel-photo.php?id=' . rawurlencode($encPersonelId) : null,
                        'department' => trim((string) ($personel->departman ?? '')) ?: 'Genel',
                        'job' => trim((string) ($personel->gorev ?? '')) ?: 'Personel',
                        'is_calculated' => $isCalculated,
                        'net' => $net,
                        'alacak' => $alacak,
                        'kesinti' => $kesinti,
                        'icra' => $icra,
                        'banka' => $banka,
                        'sodexo' => $sodexo,
                        'elden' => $elden,
                        'diger' => $diger,
                        'work_days' => $gun,
                    ];

                    $summary['personel']++;
                    if ($isCalculated) {
                        $summary['hesaplanan']++;
                    } else {
                        $summary['hesaplanmayan']++;
                    }
                    $summary['alacak'] += $alacak;
                    $summary['kesinti'] += ($kesinti + $icra);
                    $summary['net'] += $net;
                    $summary['banka'] += $banka;
                    $summary['sodexo'] += $sodexo;
                    $summary['elden'] += $elden;
                    $summary['diger'] += $diger;
                }
            }

            bordroApiResponse([
                'success' => true,
                'donemler' => $donemOptions,
                'selected_donem' => $selectedDonemData,
                'is_closed' => $closed,
                'summary' => $summary,
                'items' => $items,
            ]);
        } catch (\Throwable $e) {
            error_log('Bordro Init Hatası: ' . $e->getMessage());
            bordroApiResponse(['success' => false, 'message' => 'Bordro verileri yüklenirken bir hata oluştu.'], 500);
        }
    }

    if ($action === 'detail' || $action === 'get-detail') {
        $token = (string) ($_GET['bordro_token'] ?? $_GET['token'] ?? '');
        $bordroId = $decryptId($token);
        if ($bordroId <= 0) {
            bordroApiResponse(['success' => false, 'message' => 'Geçersiz bordro kaydı.'], 400);
        }

        $_POST['action'] = 'get-detail';
        $_POST['id'] = $bordroId;
        // Devamında POST bloğu işleyecek
    }
}

// 2. POST İŞLEMLERİ (Detay, Hesaplama, Dağıtım, Dönem Aç/Kapat, vb.)
$allowedActions = [
    'get-detail',
    'maas-hesapla',
    'donem-kapat',
    'donem-ac',
    'odeme-dagit',
    'odeme-reset',
    'odeme-reset-all',
    'personel-gelir-ekle',
    'personel-kesinti-ekle',
];

if (!in_array($action, $allowedActions, true)) {
    bordroApiResponse(['success' => false, 'message' => 'Geçersiz bordro işlemi.'], 400);
}

// get-detail hariç CSRF zorunlu
if ($action !== 'get-detail') {
    verifyBordroCsrf();
}

// Token çözümlemeleri
if (isset($_POST['donem_token'])) {
    $_POST['donem_id'] = $decryptId((string) $_POST['donem_token']);
}
if (isset($_POST['bordro_token'])) {
    $_POST['id'] = $decryptId((string) $_POST['bordro_token']);
}
if (isset($_POST['personel_token'])) {
    $_POST['personel_id'] = $decryptId((string) $_POST['personel_token']);
}
if (isset($_POST['personel_tokens']) && is_array($_POST['personel_tokens'])) {
    $_POST['personel_ids'] = array_values(array_filter(array_map($decryptId, $_POST['personel_tokens'])));
}

// Firma izolasyon kontrolleri
$db = (new \App\Core\Db())->getConnection();

if (!empty($_POST['donem_id'])) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM bordro_donemi WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL');
    $stmt->execute([(int) $_POST['donem_id'], $firmaId]);
    if (!(bool) $stmt->fetchColumn()) {
        bordroApiResponse(['success' => false, 'message' => 'Bu bordro dönemine erişiminiz bulunmuyor.'], 403);
    }
}

if (!empty($_POST['id'])) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM bordro_personel bp INNER JOIN bordro_donemi bd ON bd.id = bp.donem_id WHERE bp.id = ? AND bd.firma_id = ? AND bp.silinme_tarihi IS NULL AND bd.silinme_tarihi IS NULL');
    $stmt->execute([(int) $_POST['id'], $firmaId]);
    if (!(bool) $stmt->fetchColumn()) {
        bordroApiResponse(['success' => false, 'message' => 'Bu bordro kaydına erişiminiz bulunmuyor.'], 403);
    }
}

if (!empty($_POST['personel_ids'])) {
    $ids = array_map('intval', (array) $_POST['personel_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM bordro_personel bp INNER JOIN bordro_donemi bd ON bd.id = bp.donem_id WHERE bp.id IN ($placeholders) AND bp.donem_id = ? AND bd.firma_id = ? AND bp.silinme_tarihi IS NULL");
    $stmt->execute(array_merge($ids, [(int) ($_POST['donem_id'] ?? 0), $firmaId]));
    if ((int) $stmt->fetchColumn() !== count($ids)) {
        bordroApiResponse(['success' => false, 'message' => 'Personel bordro seçimi geçersiz.'], 403);
    }
}

if (!empty($_POST['personel_id'])) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM personel WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL');
    $stmt->execute([(int) $_POST['personel_id'], $firmaId]);
    if (!(bool) $stmt->fetchColumn()) {
        bordroApiResponse(['success' => false, 'message' => 'Bu personele erişiminiz bulunmuyor.'], 403);
    }
}

// Kapalı dönem koruması
if (!in_array($action, ['get-detail', 'donem-ac', 'donem-kapat'], true)) {
    $targetPeriodId = (int) ($_POST['donem_id'] ?? 0);
    if ($targetPeriodId === 0 && !empty($_POST['id'])) {
        $stmt = $db->prepare('SELECT donem_id FROM bordro_personel WHERE id = ? AND silinme_tarihi IS NULL');
        $stmt->execute([(int) $_POST['id']]);
        $targetPeriodId = (int) $stmt->fetchColumn();
    }
    if ($targetPeriodId > 0) {
        $stmt = $db->prepare('SELECT kapali_mi FROM bordro_donemi WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL');
        $stmt->execute([$targetPeriodId, $firmaId]);
        if ((int) $stmt->fetchColumn() === 1) {
            bordroApiResponse(['success' => false, 'message' => 'Kapalı bordro döneminde değişiklik yapılamaz.'], 409);
        }
    }
}

// Merkezi bordro API dosyasını çalıştır
$_POST['action'] = $action;
ob_start();
try {
    require dirname(__DIR__, 3) . '/views/bordro/api.php';
    $rawOutput = ob_get_clean();
} catch (\Throwable $e) {
    ob_end_clean();
    error_log('Bordro API Çalıştırma Hatası: ' . $e->getMessage());
    bordroApiResponse(['success' => false, 'message' => $e->getMessage()], 400);
}

$decoded = json_decode($rawOutput, true);
if (is_array($decoded)) {
    $status = (string) ($decoded['status'] ?? '');
    $isSuccess = in_array($status, ['success', 'warning'], true);
    $decoded['success'] = $isSuccess;
    bordroApiResponse($decoded, $isSuccess ? 200 : 400);
}

echo $rawOutput;
exit;
