<?php

declare(strict_types=1);

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\GelirGiderModel;
use App\Model\SystemLogModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function kasaApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function checkKasaAuth(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
        kasaApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
    }
    if (!Gate::allows('gelir_gider_takibi')) {
        kasaApiResponse(['success' => false, 'message' => 'Kasa ve gelir-gider modülünü görüntüleme yetkiniz bulunmuyor.'], 403);
    }
}

function verifyCsrfToken(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals((string) $sessionToken, (string) $token)) {
        kasaApiResponse(['success' => false, 'message' => 'Geçersiz güvenlik doğrulaması (CSRF). Lütfen sayfayı yenileyin.'], 419);
    }
}

checkKasaAuth();

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$currentUserId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));

$GelirGider = new GelirGiderModel();
$db = $GelirGider->getDb();

try {
    // 1. KASA İŞLEMLERİ LİSTESİ VE ÖZET
    if ($method === 'GET' && $action === 'list') {
        $yil = trim((string) ($_GET['yil'] ?? ''));
        $ay = trim((string) ($_GET['ay'] ?? ''));
        $tip = trim((string) ($_GET['tip'] ?? '')); // 1: Gelir, 2: Gider, boş: Tümü
        $search = trim((string) ($_GET['search'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int) ($_GET['limit'] ?? 30)));
        $offset = ($page - 1) * $limit;

        $where = "g.silinme_tarihi IS NULL";
        $bind = [];

        if ($yil !== '') {
            $where .= " AND YEAR(g.tarih) = :yil";
            $bind['yil'] = (int) $yil;
        }
        if ($ay !== '') {
            $where .= " AND MONTH(g.tarih) = :ay";
            $bind['ay'] = (int) $ay;
        }
        if ($tip === '1' || $tip === '2') {
            $where .= " AND g.type = :tip";
            $bind['tip'] = (int) $tip;
        }
        if ($search !== '') {
            $where .= " AND (g.hesap_adi LIKE :search OR g.kategori LIKE :search OR g.aciklama LIKE :search OR g.plaka LIKE :search OR g.banka_adi LIKE :search)";
            $bind['search'] = "%{$search}%";
        }

        // Toplam filtrelenen kayıt sayısı
        $countSql = "SELECT COUNT(*) FROM sql_gelir_gider g WHERE {$where}";
        $countStmt = $db->prepare($countSql);
        foreach ($bind as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $totalItems = (int) $countStmt->fetchColumn();

        // Sayfalanmış veri çekimi
        $sql = "SELECT g.* 
                FROM sql_gelir_gider g 
                WHERE {$where} 
                ORDER BY g.tarih DESC, g.id DESC 
                LIMIT :offset, :limit";
        $stmt = $db->prepare($sql);
        foreach ($bind as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $items = [];
        foreach ($rows as $r) {
            $typeVal = (int) ($r->type ?? 1);
            $tutar = (float) ($r->tutar ?? 0);
            $bakiye = (float) ($r->bakiye ?? 0);
            $rawId = (int) $r->id;

            $items[] = [
                'id' => Security::encrypt((string) $rawId),
                'raw_id' => $rawId,
                'type' => $typeVal, // 1: Gelir, 2: Gider
                'kategori' => (string) ($r->kategori ?? 'Kategorisiz'),
                'hesap_adi' => (string) ($r->hesap_adi ?? '-'),
                'tutar' => $tutar,
                'tutar_formatted' => Helper::formattedMoney($tutar),
                'tarih' => (string) $r->tarih,
                'tarih_formatted' => !empty($r->tarih) ? date('d.m.Y H:i', strtotime($r->tarih)) : '-',
                'tarih_short' => !empty($r->tarih) ? date('d.m.Y', strtotime($r->tarih)) : '-',
                'saat_short' => !empty($r->tarih) ? date('H:i', strtotime($r->tarih)) : '',
                'bakiye' => $bakiye,
                'bakiye_formatted' => Helper::formattedMoney(abs($bakiye)),
                'bakiye_yon' => $bakiye < 0 ? 'B' : ($bakiye > 0 ? 'A' : ''),
                'aciklama' => (string) ($r->aciklama ?? ''),
                'plaka' => (string) ($r->plaka ?? ''),
                'odeme_sekli' => (string) ($r->odeme_sekli ?? ''),
                'banka_adi' => (string) ($r->banka_adi ?? ''),
            ];
        }

        // Özet KPI bilgileri
        $summaryParams = [];
        if ($yil !== '') $summaryParams['yil'] = $yil;
        if ($ay !== '') $summaryParams['ay'] = $ay;
        if ($tip !== '') $summaryParams['tip'] = $tip;
        if ($search !== '') $summaryParams['search'] = $search;

        $summaryObj = $GelirGider->summary($summaryParams);

        $toplamGelir = (float) ($summaryObj->toplam_gelir ?? 0);
        $toplamGider = (float) ($summaryObj->toplam_gider ?? 0);
        $netBakiye = (float) ($summaryObj->bakiye ?? ($toplamGelir - $toplamGider));

        kasaApiResponse([
            'success' => true,
            'items' => $items,
            'summary' => [
                'toplam_gelir' => $toplamGelir,
                'toplam_gider' => $toplamGider,
                'bakiye' => $netBakiye,
                'gelir_adet' => (int) ($summaryObj->gelir_adet ?? 0),
                'gider_adet' => (int) ($summaryObj->gider_adet ?? 0),
                'toplam_islem' => (int) ($summaryObj->toplam_islem ?? 0),
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalItems,
                'has_more' => ($offset + count($items)) < $totalItems,
            ],
            'can_edit' => Gate::allowsAny(['gelir_gider_takibi', 'gelir-gider/duzenle', 'kasa/duzenle']),
            'can_delete' => Gate::allowsAny(['gelir_gider_takibi', 'gelir-gider/sil', 'kasa/sil']),
        ]);
    }

    // 2. TEKİL İŞLEM DETAYI
    if ($method === 'GET' && $action === 'detail') {
        $encId = (string) ($_GET['id'] ?? '');
        $id = (int) Security::decrypt($encId);
        if ($id <= 0) {
            kasaApiResponse(['success' => false, 'message' => 'Geçersiz işlem kaydı.'], 422);
        }

        $row = $GelirGider->find($id);
        if (!$row) {
            kasaApiResponse(['success' => false, 'message' => 'İşlem kaydı bulunamadı veya silinmiş.'], 404);
        }

        $typeVal = (int) ($row->type ?? 1);
        $tutar = (float) ($row->tutar ?? 0);

        kasaApiResponse([
            'success' => true,
            'item' => [
                'id' => $encId,
                'raw_id' => $id,
                'type' => $typeVal,
                'kategori' => (string) ($row->kategori ?? ''),
                'hesap_adi' => (string) ($row->hesap_adi ?? ''),
                'tutar' => $tutar,
                'tutar_formatted' => Helper::formattedMoney($tutar),
                'tarih' => (string) $row->tarih,
                'tarih_input' => !empty($row->tarih) ? date('Y-m-d\TH:i', strtotime($row->tarih)) : '',
                'tarih_formatted' => !empty($row->tarih) ? date('d.m.Y H:i', strtotime($row->tarih)) : '-',
                'aciklama' => (string) ($row->aciklama ?? ''),
                'plaka' => (string) ($row->plaka ?? ''),
                'odeme_sekli' => (string) ($row->odeme_sekli ?? ''),
                'banka_adi' => (string) ($row->banka_adi ?? ''),
            ],
        ]);
    }

    // 3. SEÇİM LİSTELERİ / METADATA (Kategoriler, Hesap Adları, Plakalar, Bankalar)
    if ($method === 'GET' && $action === 'meta') {
        $kategoriler = $GelirGider->getUniqueValues('kategori');
        $hesapAdlari = $GelirGider->getUniqueValues('hesap_adi');
        $plakalar = $GelirGider->getPlakalar();
        $bankalar = $GelirGider->getBankalar();

        kasaApiResponse([
            'success' => true,
            'kategoriler' => array_values(array_filter(array_unique(array_map('trim', (array) $kategoriler)))),
            'hesap_adlari' => array_values(array_filter(array_unique(array_map('trim', (array) $hesapAdlari)))),
            'plakalar' => array_values(array_filter(array_unique(array_map('trim', (array) $plakalar)))),
            'bankalar' => array_values(array_filter(array_unique(array_map('trim', (array) $bankalar)))),
        ]);
    }

    // 4. TÜM HAREKETLER (Geçmiş listesi ve modal)
    if ($method === 'GET' && $action === 'tum_hareketler') {
        $search = trim((string) ($_GET['search'] ?? ''));
        $type = trim((string) ($_GET['type'] ?? 'all')); // all | 1 | 2
        $baslangic = trim((string) ($_GET['baslangic'] ?? ''));
        $bitis = trim((string) ($_GET['bitis'] ?? ''));

        $where = "g.silinme_tarihi IS NULL";
        $bind = [];

        if ($search !== '') {
            $where .= " AND (g.hesap_adi LIKE :search OR g.kategori LIKE :search OR g.aciklama LIKE :search)";
            $bind['search'] = "%{$search}%";
        }
        if ($type === '1') {
            $where .= " AND g.type = 1";
        } elseif ($type === '2') {
            $where .= " AND g.type = 2";
        }
        if ($baslangic !== '') {
            $where .= " AND DATE(g.tarih) >= :baslangic";
            $bind['baslangic'] = $baslangic;
        }
        if ($bitis !== '') {
            $where .= " AND DATE(g.tarih) <= :bitis";
            $bind['bitis'] = $bitis;
        }

        $sql = "SELECT g.*, 
                (SELECT SUM(CASE WHEN g2.type = 1 THEN CAST(g2.tutar AS DECIMAL(15,2)) ELSE -CAST(g2.tutar AS DECIMAL(15,2)) END) 
                 FROM gelir_gider g2 
                 WHERE g2.silinme_tarihi IS NULL
                   AND (g2.tarih < g.tarih OR (g2.tarih = g.tarih AND g2.id <= g.id))) as global_yuruyen_bakiye
                FROM gelir_gider g
                WHERE {$where}
                ORDER BY g.tarih DESC, g.id DESC LIMIT 80";
        $stmt = $db->prepare($sql);
        foreach ($bind as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $formatted = [];
        foreach ($rows as $row) {
            $yuruyen = (float) ($row->global_yuruyen_bakiye ?? 0);
            $formatted[] = [
                'id' => Security::encrypt((string) $row->id),
                'kategori_adi' => (string) ($row->kategori ?? 'Kategorisiz'),
                'hesap_adi' => (string) ($row->hesap_adi ?? '-'),
                'aciklama' => (string) ($row->aciklama ?? ''),
                'tarih' => !empty($row->tarih) ? date('d.m.Y H:i', strtotime($row->tarih)) : '-',
                'date_short' => !empty($row->tarih) ? date('d.m.Y', strtotime($row->tarih)) : '-',
                'time_short' => !empty($row->tarih) ? date('H:i', strtotime($row->tarih)) : '',
                'tutar' => (float) ($row->tutar ?? 0),
                'tutar_formatted' => Helper::formattedMoney((float) ($row->tutar ?? 0)),
                'type' => (int) $row->type,
                'yuruyen' => $yuruyen,
                'yuruyen_formatted' => Helper::formattedMoney(abs($yuruyen)),
                'yuruyen_yon' => $yuruyen < 0 ? 'B' : ($yuruyen > 0 ? 'A' : ''),
            ];
        }

        kasaApiResponse([
            'success' => true,
            'items' => $formatted,
        ]);
    }

    // 5. GELİR / GİDER KAYDET (Yeni & Güncelleme)
    if ($method === 'POST' && $action === 'save') {
        verifyCsrfToken();

        $inputData = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($inputData)) {
            $inputData = $_POST;
        }

        $rawId = (string) ($inputData['id'] ?? $inputData['gelir_gider_id'] ?? '');
        $id = $rawId !== '' ? (int) Security::decrypt($rawId) : 0;

        $type = (int) ($inputData['type'] ?? 2);
        if ($type !== 1 && $type !== 2) {
            $type = 2; // Varsayılan Gider
        }

        $tutarRaw = str_replace([' ', '₺', 'TL'], '', (string) ($inputData['tutar'] ?? '0'));
        $tutar = Helper::formattedMoneyToNumber($tutarRaw);
        if ($tutar <= 0) {
            kasaApiResponse(['success' => false, 'message' => 'Lütfen geçerli bir işlem tutarı girin.'], 422);
        }

        $tarihRaw = trim((string) ($inputData['islem_tarihi'] ?? $inputData['tarih'] ?? date('Y-m-d H:i')));
        $tarih = date('Y-m-d H:i:s', strtotime($tarihRaw));

        $kategori = trim((string) ($inputData['kategori'] ?? $inputData['islem_turu'] ?? ''));
        $hesapAdi = trim((string) ($inputData['hesap_adi'] ?? ''));
        $aciklama = trim((string) ($inputData['aciklama'] ?? ''));
        $plaka = trim((string) ($inputData['plaka'] ?? ''));
        $odemeSekli = trim((string) ($inputData['odeme_sekli'] ?? ''));
        $bankaAdi = trim((string) ($inputData['banka_adi'] ?? ''));

        if ($kategori === '' && $hesapAdi === '') {
            kasaApiResponse(['success' => false, 'message' => 'Lütfen en az bir kategori veya hesap adı belirtin.'], 422);
        }

        $saveData = [
            'id' => $id > 0 ? $id : 0,
            'type' => $type,
            'tarih' => $tarih,
            'kategori' => $kategori,
            'hesap_adi' => $hesapAdi,
            'tutar' => $tutar,
            'aciklama' => $aciklama,
            'plaka' => $plaka ?: null,
            'odeme_sekli' => $odemeSekli ?: null,
            'banka_adi' => $bankaAdi ?: null,
        ];

        if ($id === 0) {
            $saveData['kayit_yapan'] = $currentUserId;
        } else {
            $saveData['duplicate_hash'] = null;
            $saveData['excel_import_id'] = null;
        }

        $lastInsertId = $GelirGider->saveWithAttr($saveData) ?? $id;
        $savedId = $id > 0 ? $id : (int) Security::decrypt($lastInsertId);

        // Log kaydı
        try {
            $logModel = new SystemLogModel();
            $logAction = $id > 0 ? 'Gelir/Gider Güncelleme' : 'Yeni Gelir/Gider Ekleme';
            $logDesc = sprintf(
                'Kasa %s: %s (Tutar: %s ₺, Kategori: %s, Hesap: %s)',
                $type === 1 ? 'Gelir' : 'Gider',
                $id > 0 ? "#{$id} güncellendi" : 'yeni kayıt eklendi',
                number_format($tutar, 2, ',', '.'),
                $kategori,
                $hesapAdi
            );
            $logModel->logAction($currentUserId, $logAction, $logDesc, SystemLogModel::LEVEL_INFO);
        } catch (Throwable $logEx) {
            error_log('Kasa Log Hatası: ' . $logEx->getMessage());
        }

        kasaApiResponse([
            'success' => true,
            'message' => $id > 0 ? 'İşlem başarıyla güncellendi.' : 'Yeni işlem başarıyla kaydedildi.',
            'id' => Security::encrypt((string) $savedId),
        ]);
    }

    // 6. GELİR / GİDER SİL
    if ($method === 'POST' && $action === 'delete') {
        verifyCsrfToken();

        $inputData = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($inputData)) {
            $inputData = $_POST;
        }

        $encId = (string) ($inputData['id'] ?? $inputData['gelir_gider_id'] ?? '');
        $id = (int) Security::decrypt($encId);
        if ($id <= 0) {
            kasaApiResponse(['success' => false, 'message' => 'Geçersiz işlem kaydı.'], 422);
        }

        $existing = $GelirGider->find($id);
        if (!$existing) {
            kasaApiResponse(['success' => false, 'message' => 'Silinecek işlem bulunamadı.'], 404);
        }

        $stmt = $db->prepare("UPDATE gelir_gider SET silinme_tarihi = NOW(), silen_kullanici = :uid, duplicate_hash = NULL WHERE id = :id");
        $stmt->execute(['uid' => $currentUserId, 'id' => $id]);

        // Log kaydı
        try {
            $logModel = new SystemLogModel();
            $logDesc = sprintf(
                'Kasa işlemi silindi (#%d, Tür: %s, Tutar: %s ₺, Kategori: %s)',
                $id,
                $existing->type == 1 ? 'Gelir' : 'Gider',
                number_format((float) $existing->tutar, 2, ',', '.'),
                $existing->kategori
            );
            $logModel->logAction($currentUserId, 'Gelir/Gider Silme', $logDesc, SystemLogModel::LEVEL_WARNING);
        } catch (Throwable $logEx) {
            error_log('Kasa Log Hatası: ' . $logEx->getMessage());
        }

        kasaApiResponse([
            'success' => true,
            'message' => 'İşlem kaydı başarıyla silindi.',
        ]);
    }

    kasaApiResponse(['success' => false, 'message' => 'Bilinmeyen API aksiyonu.'], 400);

} catch (Throwable $e) {
    error_log('Kasa API Hatası: ' . $e->getMessage());
    kasaApiResponse(['success' => false, 'message' => 'Sunucu hatası: ' . $e->getMessage()], 500);
}
