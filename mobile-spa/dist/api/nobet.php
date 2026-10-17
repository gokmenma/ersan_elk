<?php

declare(strict_types=1);

use App\Helper\Date;
use App\Helper\Helper;
use App\Helper\Security;
use App\Model\NobetModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Service\PushNotificationService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function nobetApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
    nobetApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
}

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
if ($firmaId <= 0) {
    nobetApiResponse(['success' => false, 'message' => 'Lütfen önce işlem yapılacak firmayı seçin.'], 400);
}

// Yetki kontrolü (Gate, Permission & SuperAdmin)
$canAccess = Gate::allowsAny(['nobet/onay-islemleri', 'yonetici_onayi', 'nobet/list']) || Gate::isSuperAdmin();
if (!$canAccess) {
    nobetApiResponse(['success' => false, 'message' => 'Nöbet onay modülüne erişim yetkiniz bulunmuyor.'], 403);
}

function verifyCsrfToken(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals((string) $sessionToken, (string) $token)) {
        nobetApiResponse(['success' => false, 'message' => 'Güvenlik doğrulaması (CSRF) geçersiz veya süresi dolmuş. Lütfen sayfayı yenileyin.'], 419);
    }
}

function getInitials(string $name): string
{
    $name = trim($name);
    if ($name === '') return '?';
    $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($words) >= 2) {
        $first = mb_substr($words[0], 0, 1, 'UTF-8');
        $last = mb_substr(end($words), 0, 1, 'UTF-8');
        return mb_strtoupper($first . $last, 'UTF-8');
    }
    return mb_strtoupper(mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');
}

function getGunAdi(string $date): string
{
    $gunler = [
        1 => 'Pazartesi',
        2 => 'Salı',
        3 => 'Çarşamba',
        4 => 'Perşembe',
        5 => 'Cuma',
        6 => 'Cumartesi',
        7 => 'Pazar'
    ];
    $n = (int) date('N', strtotime($date));
    return $gunler[$n] ?? '';
}

function getAvatarUrl(int $personelId, ?string $resimYolu = null, ?string $personelResimYolu = null): ?string
{
    if ($personelId <= 0) return null;
    $hasPhoto = false;
    $candidates = [trim((string) $personelResimYolu), trim((string) $resimYolu)];
    $projectRoot = realpath(dirname(__DIR__, 3));
    if ($projectRoot) {
        foreach ($candidates as $cand) {
            if ($cand === '') continue;
            $tryFile = realpath($projectRoot . '/' . ltrim(str_replace('\\', '/', $cand), '/'));
            if ($tryFile && str_starts_with($tryFile, $projectRoot . DIRECTORY_SEPARATOR) && is_file($tryFile)) {
                $hasPhoto = true;
                break;
            }
        }
    }
    if (!$hasPhoto) return null;
    $encId = Security::encrypt((string) $personelId);
    return '/api/personnel-photo.php?id=' . rawurlencode($encId);
}

$nobetModel = new NobetModel();
$personelModel = new PersonelModel();
$systemLogModel = new SystemLogModel();
$userId = (int) ($_SESSION['user_id'] ?? 0);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');

try {
    if ($method === 'POST') {
        verifyCsrfToken();
        $input = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?: [];
        } else {
            $input = $_POST;
        }

        switch ($action) {
            case 'approve': {
                $rawId = $input['id'] ?? '';
                $id = (int) Security::decrypt((string) $rawId);
                if ($id <= 0) {
                    nobetApiResponse(['success' => false, 'message' => 'Geçersiz nöbet kaydı.'], 400);
                }

                $nobet = $nobetModel->find($id);
                if (!$nobet || (int) $nobet->firma_id !== $firmaId) {
                    nobetApiResponse(['success' => false, 'message' => 'Nöbet kaydı bulunamadı.'], 404);
                }

                $result = $nobetModel->onaylaNobet($id);
                if ($result) {
                    $systemLogModel->logAction($userId, 'Nöbet Onayı', "Nöbet onaylandı: #{$id} (Tarih: {$nobet->nobet_tarihi})");
                    nobetApiResponse(['success' => true, 'message' => 'Nöbet başarıyla onaylandı.']);
                }
                nobetApiResponse(['success' => false, 'message' => 'Nöbet onaylanamadı.'], 500);
            }

            case 'unapprove': {
                $rawId = $input['id'] ?? '';
                $id = (int) Security::decrypt((string) $rawId);
                if ($id <= 0) {
                    nobetApiResponse(['success' => false, 'message' => 'Geçersiz nöbet kaydı.'], 400);
                }

                $nobet = $nobetModel->find($id);
                if (!$nobet || (int) $nobet->firma_id !== $firmaId) {
                    nobetApiResponse(['success' => false, 'message' => 'Nöbet kaydı bulunamadı.'], 404);
                }

                $result = $nobetModel->onayiKaldirNobet($id);
                if ($result) {
                    $systemLogModel->logAction($userId, 'Nöbet Onay Kaldırma', "Nöbet onayı geri alındı: #{$id} (Tarih: {$nobet->nobet_tarihi})");
                    nobetApiResponse(['success' => true, 'message' => 'Nöbet onayı kaldırıldı.']);
                }
                nobetApiResponse(['success' => false, 'message' => 'İşlem gerçekleştirilemedi.'], 500);
            }

            case 'delete': {
                $rawId = $input['id'] ?? '';
                $id = (int) Security::decrypt((string) $rawId);
                if ($id <= 0) {
                    nobetApiResponse(['success' => false, 'message' => 'Geçersiz nöbet kaydı.'], 400);
                }

                $nobet = $nobetModel->find($id);
                if (!$nobet || (int) $nobet->firma_id !== $firmaId) {
                    nobetApiResponse(['success' => false, 'message' => 'Nöbet kaydı bulunamadı.'], 404);
                }

                $result = $nobetModel->deleteNobet($id);
                if ($result) {
                    $systemLogModel->logAction($userId, 'Nöbet Silme', "Nöbet silindi: #{$id} (Tarih: {$nobet->nobet_tarihi})");
                    nobetApiResponse(['success' => true, 'message' => 'Nöbet kaydı silindi.']);
                }
                nobetApiResponse(['success' => false, 'message' => 'Nöbet silinemedi.'], 500);
            }

            case 'bulk_approve': {
                $rawIds = $input['ids'] ?? [];
                if (!is_array($rawIds)) {
                    $rawIds = explode(',', (string) $rawIds);
                }
                $ids = [];
                foreach ($rawIds as $rid) {
                    $dec = (int) Security::decrypt((string) $rid);
                    if ($dec > 0) $ids[] = $dec;
                }
                if (empty($ids)) {
                    nobetApiResponse(['success' => false, 'message' => 'Lütfen onaylanacak en az bir nöbet seçin.'], 400);
                }

                $result = $nobetModel->bulkOnaylaNobet($ids);
                if ($result) {
                    $systemLogModel->logAction($userId, 'Toplu Nöbet Onayı', count($ids) . ' adet nöbet onaylandı.');
                    nobetApiResponse(['success' => true, 'message' => count($ids) . ' adet nöbet başarıyla onaylandı.']);
                }
                nobetApiResponse(['success' => false, 'message' => 'Toplu onay işlemi gerçekleştirilemedi.'], 500);
            }

            case 'bulk_unapprove': {
                $rawIds = $input['ids'] ?? [];
                if (!is_array($rawIds)) {
                    $rawIds = explode(',', (string) $rawIds);
                }
                $ids = [];
                foreach ($rawIds as $rid) {
                    $dec = (int) Security::decrypt((string) $rid);
                    if ($dec > 0) $ids[] = $dec;
                }
                if (empty($ids)) {
                    nobetApiResponse(['success' => false, 'message' => 'Lütfen işlem yapılacak en az bir nöbet seçin.'], 400);
                }

                $result = $nobetModel->bulkOnayiKaldirNobet($ids);
                if ($result) {
                    $systemLogModel->logAction($userId, 'Toplu Nöbet Onay Kaldırma', count($ids) . ' adet nöbetin onayı kaldırıldı.');
                    nobetApiResponse(['success' => true, 'message' => count($ids) . ' adet nöbetin onayı kaldırıldı.']);
                }
                nobetApiResponse(['success' => false, 'message' => 'İşlem gerçekleştirilemedi.'], 500);
            }

            case 'bulk_delete': {
                $rawIds = $input['ids'] ?? [];
                if (!is_array($rawIds)) {
                    $rawIds = explode(',', (string) $rawIds);
                }
                $ids = [];
                foreach ($rawIds as $rid) {
                    $dec = (int) Security::decrypt((string) $rid);
                    if ($dec > 0) $ids[] = $dec;
                }
                if (empty($ids)) {
                    nobetApiResponse(['success' => false, 'message' => 'Lütfen silinecek en az bir nöbet seçin.'], 400);
                }

                $result = $nobetModel->bulkSilNobet($ids);
                if ($result) {
                    $systemLogModel->logAction($userId, 'Toplu Nöbet Silme', count($ids) . ' adet nöbet silindi.');
                    nobetApiResponse(['success' => true, 'message' => count($ids) . ' adet nöbet silindi.']);
                }
                nobetApiResponse(['success' => false, 'message' => 'Toplu silme gerçekleştirilemedi.'], 500);
            }

            case 'add': {
                $personelIdRaw = $input['personel_id'] ?? '';
                $personelId = (int) Security::decrypt((string) $personelIdRaw);
                if ($personelId <= 0) {
                    // Sayısal kontrol (raw ID gelmişse güvenlik amacıyla yine kontrol)
                    if (is_numeric($personelIdRaw)) {
                        $personelId = (int) $personelIdRaw;
                    }
                }

                $nobetTarihi = trim((string) ($input['nobet_tarihi'] ?? ''));
                if ($personelId <= 0 || empty($nobetTarihi)) {
                    nobetApiResponse(['success' => false, 'message' => 'Lütfen personel ve nöbet tarihini eksiksiz girin.'], 400);
                }

                $personel = $personelModel->find($personelId);
                if (!$personel) {
                    nobetApiResponse(['success' => false, 'message' => 'Seçilen personel sistemde bulunamadı.'], 404);
                }

                $tip = trim((string) ($input['nobet_tipi'] ?? 'standart'));
                if ($tip === '' || $tip === 'standart') {
                    $gunNum = (int) date('N', strtotime($nobetTarihi));
                    $tip = ($gunNum >= 6) ? 'hafta_sonu' : 'hafta_ici';
                }

                $baslangicSaati = trim((string) ($input['baslangic_saati'] ?? '18:00'));
                $bitisSaati = trim((string) ($input['bitis_saati'] ?? '08:00'));
                if (strlen($baslangicSaati) === 5) $baslangicSaati .= ':00';
                if (strlen($bitisSaati) === 5) $bitisSaati .= ':00';

                $aciklama = trim((string) ($input['aciklama'] ?? ''));
                $yoneticiOnayi = !empty($input['yonetici_onayi']) ? 1 : 0;

                $data = [
                    'personel_id' => $personelId,
                    'nobet_tarihi' => $nobetTarihi,
                    'nobet_tipi' => $tip,
                    'baslangic_saati' => $baslangicSaati,
                    'bitis_saati' => $bitisSaati,
                    'aciklama' => $aciklama !== '' ? $aciklama : null,
                    'yonetici_onayi' => $yoneticiOnayi,
                ];

                $newId = $nobetModel->addNobet($data);
                if ($newId) {
                    $systemLogModel->logAction($userId, 'Nöbet Planlama', "{$personel->adi_soyadi} için {$nobetTarihi} tarihine nöbet planlandı (#{$newId})");
                    nobetApiResponse([
                        'success' => true,
                        'message' => 'Nöbet başarıyla planlandı.',
                        'id' => Security::encrypt((string) $newId),
                    ]);
                }
                nobetApiResponse(['success' => false, 'message' => 'Nöbet kaydedilemedi.'], 500);
            }

            case 'send_reminder': {
                $rawNobetId = $input['nobet_id'] ?? '';
                $rawPersonelId = $input['personel_id'] ?? '';
                $personelId = 0;
                $tarih = '';

                if (!empty($rawNobetId)) {
                    $nobetId = (int) Security::decrypt((string) $rawNobetId);
                    $nobet = $nobetModel->find($nobetId);
                    if ($nobet) {
                        $personelId = (int) $nobet->personel_id;
                        $tarih = (string) $nobet->nobet_tarihi;
                    }
                } elseif (!empty($rawPersonelId)) {
                    $personelId = (int) Security::decrypt((string) $rawPersonelId);
                    $tarih = (string) ($input['tarih'] ?? date('Y-m-d'));
                }

                if ($personelId <= 0) {
                    nobetApiResponse(['success' => false, 'message' => 'Personel veya nöbet kaydı bulunamadı.'], 400);
                }

                $personel = $personelModel->find($personelId);
                if (!$personel) {
                    nobetApiResponse(['success' => false, 'message' => 'Personel bulunamadı.'], 404);
                }

                $pushService = new PushNotificationService();
                $tarihFmt = $tarih !== '' ? date('d.m.Y', strtotime($tarih)) : 'bugünkü';
                $payload = [
                    'title' => '⏰ Nöbet Hatırlatması',
                    'body' => "Sayın {$personel->adi_soyadi}, {$tarihFmt} tarihindeki nöbetinizi hatırlatmak isteriz.",
                    'url' => '/mobile-spa/#/nobet-onay'
                ];

                $sent = $pushService->sendToPersonel($personelId, $payload);
                $systemLogModel->logAction($userId, 'Nöbet Hatırlatma', "{$personel->adi_soyadi} için nöbet hatırlatma bildirimi gönderildi.");

                nobetApiResponse([
                    'success' => true,
                    'message' => $sent ? 'Hatırlatma bildirimi personelin cihazına başarıyla iletildi.' : 'Bildirim oluşturuldu (Personelin cihaz aboneliği bulunamadıysa e-posta iletildi).'
                ]);
            }

            default:
                nobetApiResponse(['success' => false, 'message' => 'Geçersiz işlem parametresi.'], 400);
        }
    }

    // GET İstekleri
    $ay = isset($_GET['ay']) ? (int) $_GET['ay'] : (int) date('m');
    $yil = isset($_GET['yil']) ? (int) $_GET['yil'] : (int) date('Y');
    if ($ay < 1 || $ay > 12) $ay = (int) date('m');
    if ($yil < 2020 || $yil > 2040) $yil = (int) date('Y');

    switch ($action) {
        case 'list': {
            $durum = (string) ($_GET['durum'] ?? 'bekleyen'); // 'bekleyen' | 'onaylanan' | 'tumu'
            $tip = (string) ($_GET['tip'] ?? ''); // '' | 'hafta_ici' | 'hafta_sonu' | 'resmi_tatil'
            $search = trim((string) ($_GET['search'] ?? ''));

            $db = $nobetModel->getDb();

            // 1. İstatistikler (Tüm seçili ay için)
            $statsSql = "SELECT 
                COUNT(*) as total_nobet,
                SUM(CASE WHEN (yonetici_onayi = 0 OR yonetici_onayi IS NULL) THEN 1 ELSE 0 END) as total_bekleyen,
                SUM(CASE WHEN (yonetici_onayi = 1) THEN 1 ELSE 0 END) as total_onaylanan,
                SUM(CASE WHEN (yonetici_onayi = 0 OR yonetici_onayi IS NULL) AND nobet_tipi = 'hafta_sonu' THEN 1 ELSE 0 END) as hafta_sonu_bekleyen,
                SUM(CASE WHEN (yonetici_onayi = 0 OR yonetici_onayi IS NULL) AND nobet_tipi = 'resmi_tatil' THEN 1 ELSE 0 END) as resmi_tatil_bekleyen,
                SUM(CASE WHEN (yonetici_onayi = 0 OR yonetici_onayi IS NULL) AND (nobet_tipi NOT IN ('hafta_sonu', 'resmi_tatil') OR nobet_tipi IS NULL) THEN 1 ELSE 0 END) as hafta_ici_bekleyen
            FROM nobetler 
            WHERE silinme_tarihi IS NULL 
            AND (durum IS NULL OR durum NOT IN ('reddedildi', 'iptal'))
            AND MONTH(nobet_tarihi) = ? AND YEAR(nobet_tarihi) = ?
            AND firma_id = ?";

            $stmtStats = $db->prepare($statsSql);
            $stmtStats->execute([$ay, $yil, $firmaId]);
            $rawStats = $stmtStats->fetch(PDO::FETCH_OBJ);

            $stats = [
                'total_nobet' => (int) ($rawStats->total_nobet ?? 0),
                'total_bekleyen' => (int) ($rawStats->total_bekleyen ?? 0),
                'total_onaylanan' => (int) ($rawStats->total_onaylanan ?? 0),
                'hafta_sonu_bekleyen' => (int) ($rawStats->hafta_sonu_bekleyen ?? 0),
                'resmi_tatil_bekleyen' => (int) ($rawStats->resmi_tatil_bekleyen ?? 0),
                'hafta_ici_bekleyen' => (int) ($rawStats->hafta_ici_bekleyen ?? 0),
            ];

            // 2. Liste Filtreleme
            $whereParts = [
                'n.firma_id = ?',
                'n.silinme_tarihi IS NULL',
                "(n.durum IS NULL OR n.durum NOT IN ('reddedildi', 'iptal'))",
                'MONTH(n.nobet_tarihi) = ?',
                'YEAR(n.nobet_tarihi) = ?'
            ];
            $params = [$firmaId, $ay, $yil];

            if ($durum === 'bekleyen') {
                $whereParts[] = '(n.yonetici_onayi = 0 OR n.yonetici_onayi IS NULL)';
            } elseif ($durum === 'onaylanan') {
                $whereParts[] = 'n.yonetici_onayi = 1';
            }

            if ($tip === 'hafta_sonu') {
                $whereParts[] = "n.nobet_tipi = 'hafta_sonu'";
            } elseif ($tip === 'resmi_tatil') {
                $whereParts[] = "n.nobet_tipi = 'resmi_tatil'";
            } elseif ($tip === 'hafta_ici') {
                $whereParts[] = "(n.nobet_tipi NOT IN ('hafta_sonu', 'resmi_tatil') OR n.nobet_tipi IS NULL)";
            }

            if ($search !== '') {
                $whereParts[] = '(p.adi_soyadi LIKE ? OR p.departman LIKE ? OR n.aciklama LIKE ?)';
                $sParam = '%' . $search . '%';
                $params[] = $sParam;
                $params[] = $sParam;
                $params[] = $sParam;
            }

            $whereSql = implode(' AND ', $whereParts);
            $query = "SELECT n.*, p.adi_soyadi as personel_adi, p.departman, p.resim_yolu as personel_resim, p.personel_resim_yolu
                      FROM nobetler n
                      LEFT JOIN personel p ON n.personel_id = p.id
                      WHERE {$whereSql}
                      ORDER BY n.nobet_tarihi ASC, n.baslangic_saati ASC";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

            $list = [];
            foreach ($rows as $r) {
                $pName = (string) ($r->personel_adi ?: 'Bilinmeyen Personel');
                $list[] = [
                    'id' => Security::encrypt((string) $r->id),
                    'raw_id' => (int) $r->id,
                    'personel_id' => Security::encrypt((string) $r->personel_id),
                    'personel_name' => $pName,
                    'personel_initials' => getInitials($pName),
                    'personel_avatar' => getAvatarUrl((int) $r->personel_id, $r->personel_resim, $r->personel_resim_yolu),
                    'departman' => (string) ($r->departman ?: 'Genel'),
                    'tarih' => (string) $r->nobet_tarihi,
                    'tarih_formatli' => date('d.m.Y', strtotime((string) $r->nobet_tarihi)),
                    'gun_adi' => getGunAdi((string) $r->nobet_tarihi),
                    'baslangic_saati' => substr((string) $r->baslangic_saati, 0, 5),
                    'bitis_saati' => substr((string) $r->bitis_saati, 0, 5),
                    'nobet_tipi' => (string) ($r->nobet_tipi ?: 'hafta_ici'),
                    'aciklama' => (string) ($r->aciklama ?? ''),
                    'yonetici_onayi' => !empty($r->yonetici_onayi),
                    'bildirim_gonderildi' => !empty($r->bildirim_gonderildi),
                ];
            }

            nobetApiResponse([
                'success' => true,
                'ay' => $ay,
                'yil' => $yil,
                'durum' => $durum,
                'tip' => $tip,
                'stats' => $stats,
                'list' => $list,
                'count' => count($list),
            ]);
        }

        case 'calendar': {
            $db = $nobetModel->getDb();
            $sql = "SELECT n.*, p.adi_soyadi as personel_adi, p.departman, p.resim_yolu as personel_resim, p.personel_resim_yolu
                    FROM nobetler n
                    LEFT JOIN personel p ON n.personel_id = p.id
                    WHERE n.firma_id = ?
                    AND n.silinme_tarihi IS NULL
                    AND (n.durum IS NULL OR n.durum NOT IN ('reddedildi', 'iptal'))
                    AND MONTH(n.nobet_tarihi) = ? AND YEAR(n.nobet_tarihi) = ?
                    ORDER BY n.nobet_tarihi ASC, n.baslangic_saati ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute([$firmaId, $ay, $yil]);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

            $daysMap = [];
            $daysInMonth = (int) date('t', strtotime("{$yil}-{$ay}-01"));

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $yil, $ay, $d);
                $gunNum = (int) date('N', strtotime($dateStr));
                $daysMap[$dateStr] = [
                    'date' => $dateStr,
                    'day' => $d,
                    'gun_adi' => getGunAdi($dateStr),
                    'is_weekend' => ($gunNum >= 6),
                    'duty_count' => 0,
                    'shifts' => []
                ];
            }

            foreach ($rows as $r) {
                $t = (string) $r->nobet_tarihi;
                if (!isset($daysMap[$t])) continue;

                $pName = (string) ($r->personel_adi ?: 'Bilinmeyen');
                $daysMap[$t]['duty_count']++;
                $daysMap[$t]['shifts'][] = [
                    'id' => Security::encrypt((string) $r->id),
                    'personel_name' => $pName,
                    'personel_initials' => getInitials($pName),
                    'personel_avatar' => getAvatarUrl((int) $r->personel_id, $r->personel_resim, $r->personel_resim_yolu),
                    'departman' => (string) ($r->departman ?: 'Genel'),
                    'baslangic_saati' => substr((string) $r->baslangic_saati, 0, 5),
                    'bitis_saati' => substr((string) $r->bitis_saati, 0, 5),
                    'nobet_tipi' => (string) ($r->nobet_tipi ?: 'hafta_ici'),
                    'yonetici_onayi' => !empty($r->yonetici_onayi),
                ];
            }

            nobetApiResponse([
                'success' => true,
                'ay' => $ay,
                'yil' => $yil,
                'days' => array_values($daysMap),
            ]);
        }

        case 'personnel_summary': {
            $search = trim((string) ($_GET['search'] ?? ''));
            $db = $nobetModel->getDb();

            $sql = "SELECT p.id, p.adi_soyadi, p.departman, p.resim_yolu, p.personel_resim_yolu,
                        COUNT(n.id) as nobet_sayisi,
                        SUM(CASE WHEN (n.yonetici_onayi = 0 OR n.yonetici_onayi IS NULL) AND n.id IS NOT NULL THEN 1 ELSE 0 END) as bekleyen_sayisi,
                        SUM(CASE WHEN n.yonetici_onayi = 1 THEN 1 ELSE 0 END) as onaylanan_sayisi
                    FROM personel p
                    LEFT JOIN nobetler n ON p.id = n.personel_id 
                        AND n.firma_id = ? 
                        AND n.silinme_tarihi IS NULL 
                        AND (n.durum IS NULL OR n.durum NOT IN ('reddedildi', 'iptal'))
                        AND MONTH(n.nobet_tarihi) = ? 
                        AND YEAR(n.nobet_tarihi) = ?
                    WHERE p.firma_id = ?
                    AND (p.aktif_mi = 1 OR p.aktif_mi IS NULL)
                    AND p.isten_cikis_tarihi IS NULL
                    AND p.silinme_tarihi IS NULL";

            $params = [$firmaId, $ay, $yil, $firmaId];
            if ($search !== '') {
                $sql .= " AND (p.adi_soyadi LIKE ? OR p.departman LIKE ?)";
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }

            $sql .= " GROUP BY p.id ORDER BY nobet_sayisi DESC, p.adi_soyadi ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

            $pool = [];
            $maxNobet = 0;
            foreach ($rows as $r) {
                $cnt = (int) $r->nobet_sayisi;
                if ($cnt > $maxNobet) $maxNobet = $cnt;

                $pName = (string) $r->adi_soyadi;
                $pool[] = [
                    'id' => Security::encrypt((string) $r->id),
                    'raw_id' => (int) $r->id,
                    'name' => $pName,
                    'initials' => getInitials($pName),
                    'avatar' => getAvatarUrl((int) $r->id, $r->resim_yolu, $r->personel_resim_yolu),
                    'department' => (string) ($r->departman ?: 'Genel'),
                    'nobet_sayisi' => $cnt,
                    'bekleyen_sayisi' => (int) $r->bekleyen_sayisi,
                    'onaylanan_sayisi' => (int) $r->onaylanan_sayisi,
                ];
            }

            nobetApiResponse([
                'success' => true,
                'ay' => $ay,
                'yil' => $yil,
                'max_nobet' => $maxNobet,
                'personnel' => $pool,
            ]);
        }

        case 'personnel_list': {
            $personeller = $personelModel->all(true);
            $result = [];
            foreach ($personeller as $p) {
                $pName = (string) $p->adi_soyadi;
                $result[] = [
                    'id' => Security::encrypt((string) $p->id),
                    'raw_id' => (int) $p->id,
                    'name' => $pName,
                    'initials' => getInitials($pName),
                    'avatar' => getAvatarUrl((int) $p->id, $p->resim_yolu ?? null, $p->personel_resim_yolu ?? null),
                    'department' => (string) ($p->departman ?: 'Genel'),
                ];
            }
            nobetApiResponse(['success' => true, 'personnel' => $result]);
        }

        default:
            nobetApiResponse(['success' => false, 'message' => 'Geçersiz GET aksiyonu.'], 400);
    }
} catch (Throwable $e) {
    error_log('Nobet API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    nobetApiResponse(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.'], 500);
}
