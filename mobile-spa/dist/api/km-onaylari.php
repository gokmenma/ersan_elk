<?php

declare(strict_types=1);

use App\Helper\Helper;
use App\Helper\MedyaServis;
use App\Helper\Security;
use App\Model\AracKmBildirimModel;
use App\Model\AracKmModel;
use App\Model\AracModel;
use App\Model\BildirimModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use App\Model\UserNotificationPreferenceModel;
use App\Service\Gate;
use App\Service\KmBildirimAiKontrolService;
use App\Service\PushNotificationService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function kmApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function checkKmAuth(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
        kmApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
    }

    $canAccess = Gate::allowsAny(['arac_takip_yonetim', 'onaylikm_duzenle', 'arac-takip/km-onaylari']) || Gate::isSuperAdmin();
    if (!$canAccess) {
        kmApiResponse(['success' => false, 'message' => 'KM onayları modülüne erişim yetkiniz bulunmuyor.'], 403);
    }
}

function verifyCsrfToken(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals((string) $sessionToken, (string) $token)) {
        kmApiResponse(['success' => false, 'message' => 'Güvenlik doğrulaması (CSRF) geçersiz. Lütfen sayfayı yenileyin.'], 419);
    }
}

function resolveKmBildirimId(string $tokenOrId): int
{
    $tokenOrId = trim($tokenOrId);
    if ($tokenOrId === '') {
        return 0;
    }
    if (ctype_digit($tokenOrId)) {
        return (int) $tokenOrId;
    }
    $decrypted = Security::decrypt($tokenOrId);
    return is_numeric($decrypted) ? (int) $decrypted : 0;
}

checkKmAuth();

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
if ($firmaId <= 0) {
    kmApiResponse(['success' => false, 'message' => 'Geçerli bir firma seçilmemiş.'], 400);
}

$currentUserId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$canEdit = Gate::allows('onaylikm_duzenle') || Gate::isSuperAdmin();

$KmBildirim = new AracKmBildirimModel();
$Km = new AracKmModel();
$Arac = new AracModel();
$SystemLog = new SystemLogModel();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');

try {
    if ($method === 'GET' && $action === 'list') {
        $status = (string) ($_GET['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'unreported', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($_GET['perPage'] ?? 15)));
        $search = trim((string) ($_GET['search'] ?? ''));
        $turFilter = trim((string) ($_GET['tur'] ?? '')); // 'sabah', 'aksam' veya boş

        $counts = $KmBildirim->getReportCounts();

        if ($status === 'unreported') {
            $today = date('Y-m-d');
            $allUnreported = $KmBildirim->getUnreported($today, 'sabah');

            // Akşam eksik sayılarını ekle
            $aksamEksikSayilari = $KmBildirim->getEveningMissingCounts(30);
            foreach ($allUnreported as $item) {
                $pid = (int) $item->personel_id;
                $item->son_30_gun_aksam_eksik = $aksamEksikSayilari[$pid] ?? 0;
                $item->hedef_tur = 'sabah';
                $item->personel_resim_url = $pid > 0
                    ? '/api/personnel-photo.php?id=' . urlencode(Security::encrypt((string) $pid))
                    : null;
            }

            if ($search !== '') {
                $searchLower = mb_strtolower($search, 'UTF-8');
                $allUnreported = array_values(array_filter($allUnreported, static function ($item) use ($searchLower) {
                    return mb_strpos(mb_strtolower((string) $item->personel_adi, 'UTF-8'), $searchLower) !== false
                        || mb_strpos(mb_strtolower((string) $item->plaka, 'UTF-8'), $searchLower) !== false;
                }));
            }

            $total = count($allUnreported);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
            $offset = ($page - 1) * $perPage;
            $items = array_slice($allUnreported, $offset, $perPage);

            kmApiResponse([
                'success' => true,
                'counts' => $counts,
                'can_edit' => $canEdit,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                ],
                'reports' => $items,
            ]);
        }

        // Standart bildirim listelemesi
        $dbStatus = match ($status) {
            'approved' => 'onaylandi',
            'rejected' => 'reddedildi',
            default => 'beklemede',
        };

        $whereSql = "WHERE akb.firma_id = :firma_id AND akb.durum = :status AND akb.silinme_tarihi IS NULL";
        $params = [
            'firma_id' => $firmaId,
            'status' => $dbStatus,
        ];

        if ($search !== '') {
            $whereSql .= " AND (
                p.adi_soyadi LIKE :search 
                OR a.plaka LIKE :search 
                OR a.marka LIKE :search 
                OR a.model LIKE :search
                OR akb.aciklama LIKE :search
            )";
            $params['search'] = '%' . $search . '%';
        }

        if ($turFilter === 'sabah' || $turFilter === 'aksam') {
            $whereSql .= " AND akb.tur = :tur";
            $params['tur'] = $turFilter;
        }

        // Toplam kayıt sayısı
        $countSql = "
            SELECT COUNT(*) 
            FROM arac_km_bildirimleri akb
            INNER JOIN araclar a ON akb.arac_id = a.id
            INNER JOIN personel p ON akb.personel_id = p.id
            {$whereSql}
        ";
        $countStmt = $KmBildirim->getDb()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $offset = ($page - 1) * $perPage;

        // Kayıtları getir
        $dataSql = "
            SELECT akb.id,
                   akb.firma_id,
                   akb.arac_id,
                   akb.personel_id,
                   akb.tarih,
                   akb.tur,
                   akb.bitis_km,
                   akb.onaylanan_km,
                   akb.resim_yolu,
                   akb.aciklama,
                   akb.durum,
                   akb.red_nedeni,
                   akb.ai_onay_mi,
                   akb.ai_onaylanmama_nedeni,
                   akb.onay_tarihi,
                   akb.olusturma_tarihi,
                   a.plaka,
                   a.marka,
                   a.model,
                   a.guncel_km as arac_guncel_km,
                   p.adi_soyadi as personel_adi,
                   p.resim_yolu as personel_resim,
                   u.adi_soyadi as onaylayan_adi,
                   (SELECT COALESCE(b2.onaylanan_km, b2.bitis_km) 
                    FROM arac_km_bildirimleri b2 
                    WHERE b2.arac_id = akb.arac_id 
                    AND b2.tarih = akb.tarih 
                    AND b2.tur = 'sabah' 
                    AND b2.durum != 'reddedildi' 
                    AND b2.silinme_tarihi IS NULL
                    LIMIT 1) as sabah_km
            FROM arac_km_bildirimleri akb
            INNER JOIN araclar a ON akb.arac_id = a.id
            INNER JOIN personel p ON akb.personel_id = p.id
            LEFT JOIN users u ON akb.onaylayan_id = u.id
            {$whereSql}
            ORDER BY akb.tarih DESC, akb.olusturma_tarihi DESC, akb.id DESC
            LIMIT {$offset}, {$perPage}
        ";

        $dataStmt = $KmBildirim->getDb()->prepare($dataSql);
        $dataStmt->execute($params);
        $records = $dataStmt->fetchAll(PDO::FETCH_OBJ);

        foreach ($records as $r) {
            $rId = (int) $r->id;
            $rToken = Security::encrypt((string) $rId);
            $r->token = $rToken;
            $r->resim_url = !empty($r->resim_yolu)
                ? '/api/km-onaylari.php?action=foto&token=' . $rToken
                : null;
            $pid = (int) ($r->personel_id ?? 0);
            $r->personel_resim_url = $pid > 0
                ? '/api/personnel-photo.php?id=' . Security::encrypt((string) $pid)
                : null;
        }

        kmApiResponse([
            'success' => true,
            'counts' => $counts,
            'can_edit' => $canEdit,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
            'reports' => $records,
        ]);
    }

    // -------------------------------------------------------------
    // FOTOĞRAF GÖRÜNTÜLEME (STREAM)
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'foto') {
        $projectRoot = realpath(dirname(__DIR__, 3));

        // 1. Doğrudan dosya yolu parametresi (fallback)
        if (!empty($_GET['file']) && $projectRoot) {
            $rawFile = trim((string) $_GET['file']);
            $temizFile = ltrim(str_replace('\\', '/', $rawFile), '/');
            $dosya = realpath($projectRoot . '/' . $temizFile);
            $uploadDir = realpath($projectRoot . '/uploads/km_bildirim');
            if (!$dosya || !is_file($dosya)) {
                $dosya = realpath($projectRoot . '/uploads/km_bildirim/' . basename($temizFile));
            }
            if ($dosya && $uploadDir && str_starts_with($dosya, $uploadDir . DIRECTORY_SEPARATOR) && is_file($dosya)) {
                header_remove('Content-Type');
                MedyaServis::gonder($dosya, 86400);
                exit;
            }
        }

        // 2. Token veya ID ile bildirimden bulma
        $tokenOrId = (string) ($_GET['token'] ?? $_GET['id'] ?? '');
        $id = resolveKmBildirimId($tokenOrId);
        if ($id <= 0) {
            http_response_code(400);
            exit('Geçersiz bildirim belirteci.');
        }

        $bildirim = $KmBildirim->find($id);
        $isSuperAdmin = Gate::isSuperAdmin();
        if (!$bildirim || (!$isSuperAdmin && (int) $bildirim->firma_id !== $firmaId)) {
            http_response_code(404);
            exit('Bildirim kaydı bulunamadı.');
        }

        $resimYolu = trim((string) ($bildirim->resim_yolu ?? ''));
        if ($resimYolu === '') {
            http_response_code(404);
            exit('Bu bildirime ait fotoğraf bulunmuyor.');
        }

        $temizYol = ltrim(str_replace('\\', '/', $resimYolu), '/');
        $dosya = $projectRoot ? realpath($projectRoot . '/' . $temizYol) : false;
        $uploadDir = $projectRoot ? realpath($projectRoot . '/uploads') : false;

        // Dosya yolu doğrudan eşleşmediyse dosya adıyla uploads/km_bildirim içinde ara
        if (!$dosya || !is_file($dosya)) {
            $sadeceDosyaAdi = basename($temizYol);
            $alternatif = $projectRoot ? realpath($projectRoot . '/uploads/km_bildirim/' . $sadeceDosyaAdi) : false;
            if ($alternatif && is_file($alternatif)) {
                $dosya = $alternatif;
            } else {
                // Uzantı farklılığı kontrolü (webp, jpg, jpeg, png)
                $baseWithoutExt = pathinfo($sadeceDosyaAdi, PATHINFO_FILENAME);
                foreach (['.webp', '.jpg', '.jpeg', '.png'] as $ext) {
                    $alternatifExt = $projectRoot ? realpath($projectRoot . '/uploads/km_bildirim/' . $baseWithoutExt . $ext) : false;
                    if ($alternatifExt && is_file($alternatifExt)) {
                        $dosya = $alternatifExt;
                        break;
                    }
                }
            }
        }

        if (!$dosya || !$uploadDir || !str_starts_with($dosya, $uploadDir . DIRECTORY_SEPARATOR) || !is_file($dosya)) {
            http_response_code(404);
            exit('Fotoğraf dosyası bulunamadı.');
        }

        header_remove('Content-Type');
        MedyaServis::gonder($dosya, 86400);
        exit;
    }

    // POST İşlemleri
    if ($method === 'POST') {
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $rawJson = file_get_contents('php://input');
            $jsonData = json_decode($rawJson, true);
            if (is_array($jsonData)) {
                $_POST = array_merge($_POST, $jsonData);
            }
        }

        verifyCsrfToken();

        // 1. TEKİL ONAYLA
        if ($action === 'approve') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                kmApiResponse(['success' => false, 'message' => 'Geçersiz bildirim ID.'], 400);
            }

            $bildirim = $KmBildirim->find($id);
            if (!$bildirim) {
                kmApiResponse(['success' => false, 'message' => 'Bildirim kaydı bulunamadı.'], 404);
            }

            if ($bildirim->durum !== 'beklemede') {
                $statusText = $bildirim->durum === 'onaylandi' ? 'daha önce onaylanmış' : 'daha önce reddedilmiş';
                kmApiResponse(['success' => false, 'message' => "Bu bildirim {$statusText}."], 400);
            }

            $tarih = $bildirim->tarih;
            $aracId = (int) $bildirim->arac_id;
            $degerKm = (int) $bildirim->bitis_km;
            $tur = (string) $bildirim->tur;

            $mevcutKmKaydi = $Km->kayitVarMi($aracId, $tarih, null, true);

            if ($tur === 'sabah') {
                $baslangicKm = $degerKm;
                $bitisKm = $mevcutKmKaydi ? (int) $mevcutKmKaydi->bitis_km : 0;
            } else {
                if ($mevcutKmKaydi) {
                    $baslangicKm = (int) $mevcutKmKaydi->baslangic_km;
                } else {
                    $sql = $Km->getDb()->prepare("SELECT bitis_km FROM arac_km_kayitlari WHERE arac_id = ? AND tarih <= ? AND silinme_tarihi IS NULL ORDER BY tarih DESC, id DESC LIMIT 1");
                    $sql->execute([$aracId, $tarih]);
                    $baslangicKm = (int) ($sql->fetchColumn() ?: 0);
                }
                $bitisKm = $degerKm;
            }

            $saveData = [
                'firma_id' => $bildirim->firma_id,
                'arac_id' => $aracId,
                'tarih' => $tarih,
                'baslangic_km' => $baslangicKm,
                'bitis_km' => $bitisKm,
                'aciklama' => $bildirim->aciklama,
                'olusturan_kullanici_id' => $currentUserId,
                'silinme_tarihi' => null,
            ];

            if ($mevcutKmKaydi) {
                $saveData['id'] = $mevcutKmKaydi->id;
            }

            $Km->saveWithAttr($saveData);
            $Arac->updateKm($aracId, $degerKm);

            $KmBildirim->saveWithAttr([
                'id' => $id,
                'durum' => 'onaylandi',
                'onaylanan_km' => $degerKm,
                'onaylayan_id' => $currentUserId,
                'ai_onay_mi' => 0,
                'onay_tarihi' => date('Y-m-d H:i:s'),
            ]);

            $SystemLog->logAction($currentUserId, 'KM Bildirim Onay', "Mobil SPA - KM Bildirim ID: {$id}, Araç ID: {$aracId}, KM: {$degerKm} onaylandı.", SystemLogModel::LEVEL_INFO);

            kmApiResponse(['success' => true, 'message' => 'KM bildirimi onaylandı ve sisteme işlendi.']);
        }

        // 2. DÜZELT VE ONAYLA
        if ($action === 'edit_approve') {
            if (!$canEdit) {
                kmApiResponse(['success' => false, 'message' => 'KM düzeltme ve onaylama yetkiniz bulunmuyor.'], 403);
            }

            $id = (int) ($_POST['id'] ?? 0);
            $newKm = (int) ($_POST['km'] ?? 0);
            $aciklama = trim((string) ($_POST['aciklama'] ?? ''));

            if ($id <= 0) {
                kmApiResponse(['success' => false, 'message' => 'Geçersiz bildirim ID.'], 400);
            }
            if ($newKm <= 0) {
                kmApiResponse(['success' => false, 'message' => 'KM değeri 0\'dan büyük bir sayı olmalıdır.'], 400);
            }

            $bildirim = $KmBildirim->find($id);
            if (!$bildirim) {
                kmApiResponse(['success' => false, 'message' => 'Bildirim bulunamadı.'], 404);
            }

            if ($bildirim->durum !== 'beklemede') {
                $statusText = $bildirim->durum === 'onaylandi' ? 'daha önce onaylanmış' : 'daha önce reddedilmiş';
                kmApiResponse(['success' => false, 'message' => "Bu bildirim {$statusText}."], 400);
            }

            $tarih = $bildirim->tarih;
            $aracId = (int) $bildirim->arac_id;
            $tur = (string) $bildirim->tur;

            $mevcutKmKaydi = $Km->kayitVarMi($aracId, $tarih, null, true);

            if ($tur === 'sabah') {
                $baslangicKm = $newKm;
                $bitisKm = $mevcutKmKaydi ? (int) $mevcutKmKaydi->bitis_km : 0;
            } else {
                if ($mevcutKmKaydi) {
                    $baslangicKm = (int) $mevcutKmKaydi->baslangic_km;
                } else {
                    $sql = $Km->getDb()->prepare("SELECT bitis_km FROM arac_km_kayitlari WHERE arac_id = ? AND tarih <= ? AND silinme_tarihi IS NULL ORDER BY tarih DESC, id DESC LIMIT 1");
                    $sql->execute([$aracId, $tarih]);
                    $baslangicKm = (int) ($sql->fetchColumn() ?: 0);
                }
                $bitisKm = $newKm;
            }

            $finalAciklama = $aciklama !== '' ? $aciklama : (string) $bildirim->aciklama;

            $saveData = [
                'firma_id' => $bildirim->firma_id,
                'arac_id' => $aracId,
                'tarih' => $tarih,
                'baslangic_km' => $baslangicKm,
                'bitis_km' => $bitisKm,
                'aciklama' => $finalAciklama,
                'olusturan_kullanici_id' => $currentUserId,
                'silinme_tarihi' => null,
            ];

            if ($mevcutKmKaydi) {
                $saveData['id'] = $mevcutKmKaydi->id;
            }

            $Km->saveWithAttr($saveData);
            $Arac->updateKm($aracId, $newKm);

            $KmBildirim->saveWithAttr([
                'id' => $id,
                'durum' => 'onaylandi',
                'onaylanan_km' => $newKm,
                'aciklama' => $finalAciklama,
                'onaylayan_id' => $currentUserId,
                'ai_onay_mi' => 0,
                'onay_tarihi' => date('Y-m-d H:i:s'),
            ]);

            $SystemLog->logAction($currentUserId, 'KM Bildirim Düzelt ve Onay', "Mobil SPA - Bildirim ID: {$id}, Bildirilen: {$bildirim->bitis_km}, Düzeltilen: {$newKm} olarak onaylandı.", SystemLogModel::LEVEL_INFO);

            kmApiResponse(['success' => true, 'message' => 'KM bildirimi düzeltilerek onaylandı.']);
        }

        // 3. REDDET
        if ($action === 'reject') {
            $id = (int) ($_POST['id'] ?? 0);
            $redNedeni = trim((string) ($_POST['red_nedeni'] ?? ''));

            if ($id <= 0) {
                kmApiResponse(['success' => false, 'message' => 'Geçersiz bildirim ID.'], 400);
            }

            $bildirim = $KmBildirim->find($id);
            if (!$bildirim) {
                kmApiResponse(['success' => false, 'message' => 'Bildirim bulunamadı.'], 404);
            }

            if ($bildirim->durum !== 'beklemede') {
                $statusText = $bildirim->durum === 'onaylandi' ? 'daha önce onaylanmış' : 'daha önce reddedilmiş';
                kmApiResponse(['success' => false, 'message' => "Bu bildirim {$statusText}."], 400);
            }

            $KmBildirim->saveWithAttr([
                'id' => $id,
                'durum' => 'reddedildi',
                'red_nedeni' => $redNedeni !== '' ? $redNedeni : 'Yönetici tarafından reddedildi.',
                'onaylayan_id' => $currentUserId,
                'onay_tarihi' => date('Y-m-d H:i:s'),
            ]);

            $SystemLog->logAction($currentUserId, 'KM Bildirim Red', "Mobil SPA - Bildirim ID: {$id}, Neden: {$redNedeni} reddedildi.", SystemLogModel::LEVEL_INFO);

            kmApiResponse(['success' => true, 'message' => 'KM bildirimi reddedildi.']);
        }

        // 4. TOPLU ONAYLA
        if ($action === 'bulk_approve') {
            $rawIds = $_POST['ids'] ?? [];
            if (is_string($rawIds)) {
                $rawIds = json_decode($rawIds, true) ?? [];
            }
            if (!is_array($rawIds) || empty($rawIds)) {
                kmApiResponse(['success' => false, 'message' => 'Seçili bildirim bulunamadı.'], 400);
            }

            $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn($v) => $v > 0)));
            if (empty($ids)) {
                kmApiResponse(['success' => false, 'message' => 'Geçerli bildirim seçilmedi.'], 400);
            }

            $approvedCount = 0;
            $now = date('Y-m-d H:i:s');

            foreach ($ids as $id) {
                try {
                    $bildirim = $KmBildirim->find($id);
                    if (!$bildirim || $bildirim->durum !== 'beklemede') {
                        continue;
                    }

                    $tarih = $bildirim->tarih;
                    $aracId = (int) $bildirim->arac_id;
                    $degerKm = (int) $bildirim->bitis_km;
                    $tur = (string) $bildirim->tur;

                    $mevcutKmKaydi = $Km->kayitVarMi($aracId, $tarih, null, true);

                    if ($tur === 'sabah') {
                        $baslangicKm = $degerKm;
                        $bitisKm = $mevcutKmKaydi ? (int) $mevcutKmKaydi->bitis_km : 0;
                    } else {
                        if ($mevcutKmKaydi) {
                            $baslangicKm = (int) $mevcutKmKaydi->baslangic_km;
                        } else {
                            $sql = $Km->getDb()->prepare("SELECT bitis_km FROM arac_km_kayitlari WHERE arac_id = ? AND tarih <= ? AND silinme_tarihi IS NULL ORDER BY tarih DESC, id DESC LIMIT 1");
                            $sql->execute([$aracId, $tarih]);
                            $baslangicKm = (int) ($sql->fetchColumn() ?: 0);
                        }
                        $bitisKm = $degerKm;
                    }

                    $saveData = [
                        'firma_id' => $bildirim->firma_id,
                        'arac_id' => $aracId,
                        'tarih' => $tarih,
                        'baslangic_km' => $baslangicKm,
                        'bitis_km' => $bitisKm,
                        'aciklama' => $bildirim->aciklama,
                        'olusturan_kullanici_id' => $currentUserId,
                        'silinme_tarihi' => null,
                    ];

                    if ($mevcutKmKaydi) {
                        $saveData['id'] = $mevcutKmKaydi->id;
                    }

                    $Km->saveWithAttr($saveData);
                    $Arac->updateKm($aracId, $degerKm);

                    $KmBildirim->saveWithAttr([
                        'id' => $id,
                        'durum' => 'onaylandi',
                        'onaylanan_km' => $degerKm,
                        'onaylayan_id' => $currentUserId,
                        'ai_onay_mi' => 0,
                        'onay_tarihi' => $now,
                    ]);

                    $approvedCount++;
                } catch (\Throwable $e) {
                    error_log("Toplu KM onaylama hatası (ID: {$id}): " . $e->getMessage());
                }
            }

            $SystemLog->logAction($currentUserId, 'Toplu KM Bildirim Onay', "Mobil SPA - {$approvedCount} adet bildirim toplu onaylandı.", SystemLogModel::LEVEL_INFO);

            kmApiResponse([
                'success' => true,
                'message' => "{$approvedCount} adet bildirim başarıyla onaylandı.",
                'approved_count' => $approvedCount,
            ]);
        }

        // 5. YAPAY ZEKA İLE KONTROL VE ONAY
        if ($action === 'ai_check') {
            $rawIds = $_POST['ids'] ?? [];
            if (is_string($rawIds)) {
                $rawIds = json_decode($rawIds, true) ?? [];
            }
            if (!is_array($rawIds) || empty($rawIds)) {
                kmApiResponse(['success' => false, 'message' => 'Kontrol edilecek bildirim seçilmedi.'], 400);
            }

            $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn($v) => $v > 0)));
            if (empty($ids)) {
                kmApiResponse(['success' => false, 'message' => 'Geçerli bildirim seçilmedi.'], 400);
            }

            try {
                $aiKontrol = new KmBildirimAiKontrolService();
                $sonuc = $aiKontrol->kontrolEtVeOnayla($ids, $currentUserId);

                $msg = "{$sonuc['onaylanan']} kayıt yapay zekâ tarafından doğrulanarak onaylandı.";
                if ($sonuc['manuel'] > 0) {
                    $msg .= " {$sonuc['manuel']} kayıt manuel kontrole bırakıldı.";
                }

                $SystemLog->logAction($currentUserId, 'KM AI Kontrol', "Mobil SPA - " . $msg, SystemLogModel::LEVEL_INFO);

                kmApiResponse([
                    'success' => true,
                    'message' => $msg,
                    'data' => $sonuc,
                ]);
            } catch (\Throwable $e) {
                kmApiResponse(['success' => false, 'message' => 'Yapay zekâ analizi sırasında hata: ' . $e->getMessage()], 500);
            }
        }

        // 6. TEKİL HATIRLATMA GÖNDER (BİLDİRİM YAPMAYANLARA)
        if ($action === 'send_reminder') {
            $personelId = (int) ($_POST['personel_id'] ?? 0);
            $tarih = (string) ($_POST['tarih'] ?? date('Y-m-d'));
            $tur = (string) ($_POST['tur'] ?? 'sabah');

            if ($personelId <= 0) {
                kmApiResponse(['success' => false, 'message' => 'Geçersiz personel ID.'], 400);
            }

            $turFmt = $tur === 'aksam' ? 'Akşam' : 'Sabah';
            $tarihFmt = date('d.m.Y', strtotime($tarih));
            $message = "{$tarihFmt} tarihli {$turFmt} kilometre bildirimini henüz yapmadınız. Lütfen en kısa sürede bildiriniz.";

            $Bildirim = new BildirimModel();
            $pdo = $Arac->getDb();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE personel_id = ? AND durum = 'Aktif' AND silinme_tarihi IS NULL");
            $stmt->execute([$personelId]);
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($userIds)) {
                kmApiResponse(['success' => false, 'message' => 'Personelin aktif sistem kullanıcısı bulunamadı.'], 404);
            }

            foreach ($userIds as $uid) {
                $Bildirim->createNotification(
                    (int) $uid,
                    'KM Bildirim Hatırlatması',
                    $message,
                    'views/personel-pwa/index.php?page=ana-sayfa',
                    'bell',
                    'warning',
                    UserNotificationPreferenceModel::TYPE_KM
                );

                try {
                    $push = new PushNotificationService();
                    $push->sendToUser((int) $uid, [
                        'title' => 'KM Bildirim Hatırlatması',
                        'body' => $message,
                        'url' => 'index_pwa.php',
                    ], true, UserNotificationPreferenceModel::TYPE_KM);
                } catch (\Throwable) {
                    // Push hatasını sessizce yut
                }
            }

            kmApiResponse(['success' => true, 'message' => 'Hatırlatma bildirimi personele başarıyla iletildi.']);
        }

        // 7. TOPLU HATIRLATMA GÖNDER
        if ($action === 'bulk_reminder') {
            $tarih = (string) ($_POST['tarih'] ?? date('Y-m-d'));
            $tur = (string) ($_POST['tur'] ?? 'sabah');

            $count = $KmBildirim->sendBatchKmHatirlatma($tarih, $tur);

            kmApiResponse([
                'success' => true,
                'message' => $count > 0 ? "Toplam {$count} personele hatırlatma bildirimi gönderildi." : "Bildirim gönderilecek uygun personel bulunamadı.",
                'count' => $count,
            ]);
        }

        kmApiResponse(['success' => false, 'message' => 'Geçersiz POST eylemi.'], 400);
    }

    kmApiResponse(['success' => false, 'message' => 'Desteklenmeyen istek yöntemi.'], 405);
} catch (\Throwable $e) {
    error_log('KM Onayları API Hatası: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    kmApiResponse(['success' => false, 'message' => 'Bir sunucu hatası oluştu: ' . $e->getMessage()], 500);
}
