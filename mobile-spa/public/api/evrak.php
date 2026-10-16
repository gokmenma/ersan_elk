<?php

declare(strict_types=1);

use App\Helper\Date;
use App\Helper\RichTextSanitizer;
use App\Helper\Security;
use App\Model\AracModel;
use App\Model\EvrakTakipModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use App\Model\UserModel;
use App\Service\EvrakOnayBildirimService;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$projectRoot = dirname(__DIR__, 3);
require_once $projectRoot . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function evrakApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function checkEvrakAuth(): void
{
    $userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? ($_SESSION['user']->id ?? 0));
    $firmId = (int) ($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);

    if ($userId <= 0 || empty($_SESSION['user'])) {
        evrakApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
    }
    if ($firmId <= 0) {
        evrakApiResponse(['success' => false, 'message' => 'Lütfen önce aktif firma seçimi yapınız.'], 400);
    }

    $canAccess = Gate::allowsAny(['evrak-takip/list', 'documents']);
    if (!$canAccess) {
        $menuModel = new \App\Model\MenuModel();
        $canAccess = $menuModel->userCanAccessMenuLink($userId, 'evrak-takip/list');
    }

    if (!$canAccess) {
        evrakApiResponse(['success' => false, 'message' => 'Evrak takip modülünü görüntüleme yetkiniz bulunmuyor.'], 403);
    }
}

function verifyCsrfToken(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals((string) $sessionToken, (string) $token)) {
        evrakApiResponse(['success' => false, 'message' => 'Geçersiz güvenlik doğrulaması (CSRF). Lütfen sayfayı yenileyin.'], 419);
    }
}

checkEvrakAuth();

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$currentUserId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);

$decryptId = static function ($value): int {
    if (empty($value)) return 0;
    if (is_numeric($value)) return (int) $value;
    $d = Security::decrypt((string) $value);
    return is_numeric($d) ? (int) $d : 0;
};

$EvrakModel = new EvrakTakipModel();
$db = $EvrakModel->getDb();

try {
    // -------------------------------------------------------------
    // 1. DOSYA STREAM / ÖNİZLEME (GET stream-file)
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'stream-file') {
        $attachmentEncId = trim((string) ($_GET['id'] ?? ''));
        $filePathRaw = trim((string) ($_GET['path'] ?? ''));
        $targetFile = null;
        $originalFileName = 'belge';
        $mimeType = 'application/octet-stream';

        if ($attachmentEncId !== '') {
            $attachmentId = $decryptId($attachmentEncId);
            if ($attachmentId > 0) {
                $attachment = $EvrakModel->getAttachmentById($attachmentId);
                if ($attachment && !empty($attachment->dosya_yolu)) {
                    $targetFile = $attachment->dosya_yolu;
                    $originalFileName = (string) ($attachment->dosya_adi ?: basename($attachment->dosya_yolu));
                    $mimeType = (string) ($attachment->mime_tipi ?: '');
                }
            }
        } elseif ($filePathRaw !== '') {
            // Direct path requested (e.g. legacy dosya_yolu from evrak_takip)
            $cleanPath = ltrim(str_replace('\\', '/', $filePathRaw), '/');
            // Security check: must start with uploads/evrak-takip/
            if (str_starts_with($cleanPath, 'uploads/evrak-takip/')) {
                $targetFile = $cleanPath;
                $originalFileName = basename($cleanPath);
            }
        }

        if (!$targetFile) {
            http_response_code(404);
            exit('Dosya kaydı bulunamadı.');
        }

        $absolutePath = $projectRoot . '/' . ltrim($targetFile, '/');
        $realPath = realpath($absolutePath);

        if (!$realPath || !str_starts_with($realPath, realpath($projectRoot . '/uploads') ?: $projectRoot)) {
            http_response_code(403);
            exit('Erişim engellendi.');
        }

        if (!file_exists($realPath) || !is_readable($realPath)) {
            http_response_code(404);
            exit('Fiziksel dosya sunucuda bulunamadı.');
        }

        $rawContent = file_get_contents($realPath);
        if ($rawContent === false) {
            http_response_code(500);
            exit('Dosya okunamadı.');
        }

        // ISO 27001 deşifre denemesi (şifrelenmişse aç, değilse ham bırak)
        try {
            $fileData = Security::decryptFile($rawContent);
        } catch (\Throwable $e) {
            $fileData = $rawContent;
        }

        if (empty($mimeType) || $mimeType === 'application/octet-stream') {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($fileData);
            if ($detected) {
                $mimeType = $detected;
            }
        }

        $isDownload = !empty($_GET['download']);
        $disposition = $isDownload ? 'attachment' : 'inline';

        header_remove('Content-Type');
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . strlen($fileData));
        header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($originalFileName) . '"');
        header('Cache-Control: private, max-age=3600');
        echo $fileData;
        exit;
    }

    // -------------------------------------------------------------
    // 2. GİDEN EVRAK RESMÎ YAZI PDF ÇIKTISI (GET pdf)
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'pdf') {
        $id = $decryptId($_GET['id'] ?? '');
        if ($id <= 0) {
            http_response_code(400);
            exit('Geçersiz evrak ID.');
        }

        // Delegate to existing tested pdf.php renderer
        $_GET['id'] = Security::encrypt($id);
        require $projectRoot . '/views/evrak-takip/pdf.php';
        exit;
    }

    // -------------------------------------------------------------
    // 3. EVRAK LİSTESİ VE ÖZET İSTATİSTİKLER (GET list)
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'list') {
        $filter = trim((string) ($_GET['filter'] ?? 'all'));
        $search = trim((string) ($_GET['search'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(5, (int) ($_GET['limit'] ?? 30)));
        $offset = ($page - 1) * $limit;

        // Ensure approval rows for draft giden evraks
        $EvrakModel->ensureApprovalRowsForDrafts();
        $onayMap = $EvrakModel->getApprovalSummaryMap($currentUserId);
        $eklerMap = $EvrakModel->getAttachmentMap();

        // Stats calculation
        $statsRaw = $EvrakModel->getStats();
        $toplamEvrak = (int) ($statsRaw->toplam_evrak ?? 0);
        $gelenEvrak = (int) ($statsRaw->gelen_evrak ?? 0);
        $gidenEvrak = (int) ($statsRaw->giden_evrak ?? 0);
        $cevapBekleyen = (int) ($statsRaw->cevap_bekleyen ?? 0);

        // Fetch all active IDs to count 'imzam'
        $allSql = "SELECT et.id, et.onay_durumu FROM evrak_takip et WHERE et.firma_id = :firma_id AND et.silinme_tarihi IS NULL";
        $allStmt = $db->prepare($allSql);
        $allStmt->execute(['firma_id' => $firmaId]);
        $imzamdaBekleyen = 0;
        foreach ($allStmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $eid = (int) $row->id;
            $oDurum = (string) ($row->onay_durumu ?? 'taslak');
            if ($oDurum === 'onay_bekliyor' && !empty($onayMap[$eid]['sira_bende'])) {
                $imzamdaBekleyen++;
            }
        }

        // Base where
        $where = "et.firma_id = :firma_id AND et.silinme_tarihi IS NULL";
        $bind = ['firma_id' => $firmaId];

        if ($filter === 'gelen') {
            $where .= " AND et.evrak_tipi = 'gelen'";
        } elseif ($filter === 'giden') {
            $where .= " AND et.evrak_tipi = 'giden'";
        } elseif ($filter === 'bekleyen') {
            $where .= " AND et.evrak_tipi = 'gelen' AND (et.cevap_verildi_mi = 0 OR et.cevap_verildi_mi IS NULL)";
        }

        if ($search !== '') {
            $where .= " AND (
                et.evrak_no LIKE :s
                OR et.konu LIKE :s
                OR et.kurum_adi LIKE :s
                OR et.aciklama LIKE :s
                OR et.plaka LIKE :s
                OR p1.adi_soyadi LIKE :s
                OR p2.adi_soyadi LIKE :s
            )";
            $bind['s'] = "%{$search}%";
        }

        // If filter is 'imzam', we filter in SQL by subquery matching current user's turn
        if ($filter === 'imzam') {
            $where .= " AND et.evrak_tipi = 'giden' AND et.onay_durumu = 'onay_bekliyor' AND EXISTS (
                SELECT 1 FROM evrak_takip_onaylari o 
                WHERE o.evrak_id = et.id 
                  AND o.firma_id = et.firma_id 
                  AND o.kullanici_id = :current_user_id 
                  AND o.durum = 'bekliyor' 
                  AND o.sira = (
                      SELECT MIN(o2.sira) FROM evrak_takip_onaylari o2 
                      WHERE o2.evrak_id = et.id AND o2.firma_id = et.firma_id AND o2.durum = 'bekliyor'
                  )
            )";
            $bind['current_user_id'] = $currentUserId;
        }

        // Count total
        $countSql = "
            SELECT COUNT(*) 
            FROM evrak_takip et
            LEFT JOIN personel p1 ON et.personel_id = p1.id
            LEFT JOIN personel p2 ON et.ilgili_personel_id = p2.id
            WHERE {$where}
        ";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($bind);
        $totalItems = (int) $countStmt->fetchColumn();

        // Data query
        $dataSql = "
            SELECT 
                et.*, 
                p1.adi_soyadi as personel_adi,
                p2.adi_soyadi as ilgili_personel_adi,
                u.adi_soyadi as olusturan_adi
            FROM evrak_takip et
            LEFT JOIN personel p1 ON et.personel_id = p1.id
            LEFT JOIN personel p2 ON et.ilgili_personel_id = p2.id
            LEFT JOIN users u ON et.olusturan_kullanici_id = u.id
            WHERE {$where}
            ORDER BY et.tarih DESC, et.id DESC
            LIMIT :offset, :limit
        ";
        $stmt = $db->prepare($dataSql);
        foreach ($bind as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $encId = Security::encrypt($id);
            $isGelen = ($row->evrak_tipi === 'gelen');
            $isBekleyen = $isGelen && (!$row->cevap_verildi_mi);
            $onayDurumu = (string) ($row->onay_durumu ?? 'taslak');
            $onayInfo = $onayMap[$id] ?? [
                'toplam' => 0,
                'onaylanan' => 0,
                'sira_bende' => false,
                'bekleyen_imzam' => false,
                'siradaki_kisi' => '',
            ];

            $siraBende = ($onayDurumu === 'onay_bekliyor' && !empty($onayInfo['sira_bende']));
            $kilitli = (!$isGelen && $onayDurumu !== 'taslak');
            $onayaSunulabilir = (!$isGelen && $onayDurumu === 'taslak' && ($onayInfo['toplam'] ?? 0) > 0);
            $geriAlinabilir = ($kilitli && $EvrakModel->canRevokeApproval($row, $currentUserId));

            // Attachments
            $ekler = $eklerMap[$id] ?? [];
            if ($ekler === [] && !empty($row->dosya_yolu)) {
                $ekler[] = (object) [
                    'id' => 0,
                    'dosya_adi' => basename((string) $row->dosya_yolu),
                    'dosya_yolu' => $row->dosya_yolu,
                    'mime_tipi' => '',
                    'dosya_boyutu' => 0,
                ];
            }

            $attachmentsList = [];
            foreach ($ekler as $ek) {
                $attEncId = !empty($ek->id) ? Security::encrypt((int) $ek->id) : '';
                $streamUrl = !empty($attEncId)
                    ? '/api/evrak.php?action=stream-file&id=' . urlencode($attEncId)
                    : '/api/evrak.php?action=stream-file&path=' . urlencode((string) $ek->dosya_yolu);

                $attachmentsList[] = [
                    'id' => $attEncId,
                    'name' => (string) ($ek->dosya_adi ?: basename((string) $ek->dosya_yolu)),
                    'path' => (string) $ek->dosya_yolu,
                    'size' => (int) ($ek->dosya_boyutu ?? 0),
                    'mime' => (string) ($ek->mime_tipi ?? ''),
                    'url' => $streamUrl,
                ];
            }

            $items[] = [
                'id' => $encId,
                'raw_id' => $id,
                'evrak_tipi' => (string) $row->evrak_tipi,
                'evrak_no' => (string) ($row->evrak_no ?: '-'),
                'tarih' => (string) $row->tarih,
                'tarih_formatted' => !empty($row->tarih) ? date('d.m.Y', strtotime((string) $row->tarih)) : '-',
                'konu' => (string) ($row->konu ?: 'Belirtilmedi'),
                'kurum_adi' => (string) ($row->kurum_adi ?: '-'),
                'muhatap_alt_birim' => (string) ($row->muhatap_alt_birim ?: ''),
                'muhatap_adres' => (string) ($row->muhatap_adres ?: ''),
                'personel_adi' => (string) ($row->personel_adi ?: ''),
                'ilgili_personel_adi' => (string) ($row->ilgili_personel_adi ?: ''),
                'olusturan_adi' => (string) ($row->olusturan_adi ?: ''),
                'aciklama' => (string) ($row->aciklama ?: ''),
                'cevap_verildi_mi' => !empty($row->cevap_verildi_mi),
                'cevap_tarihi' => (string) ($row->cevap_tarihi ?: ''),
                'cevap_tarihi_formatted' => !empty($row->cevap_tarihi) ? date('d.m.Y', strtotime((string) $row->cevap_tarihi)) : '',
                'is_bekleyen' => $isBekleyen,
                'onay_durumu' => $onayDurumu,
                'sira_bende' => $siraBende,
                'onay_toplam' => (int) ($onayInfo['toplam'] ?? 0),
                'onaylanan' => (int) ($onayInfo['onaylanan'] ?? 0),
                'siradaki_kisi' => (string) ($onayInfo['siradaki_kisi'] ?? ''),
                'kilitli' => $kilitli,
                'onaya_sunulabilir' => $onayaSunulabilir,
                'geri_alinabilir' => $geriAlinabilir,
                'ust_yazi_gerekli_degil' => !empty($row->ust_yazi_gerekli_degil),
                'plaka' => (string) ($row->plaka ?: ''),
                'ceza_tutari' => !empty($row->ceza_tutari) ? (float) $row->ceza_tutari : null,
                'tutar' => !empty($row->tutar) ? (float) $row->tutar : null,
                'attachments_count' => count($attachmentsList),
                'attachments' => $attachmentsList,
                'pdf_url' => ($row->evrak_tipi === 'giden') ? '/api/evrak.php?action=pdf&id=' . urlencode($encId) : null,
            ];
        }

        evrakApiResponse([
            'success' => true,
            'items' => $items,
            'stats' => [
                'toplam_evrak' => $toplamEvrak,
                'gelen_evrak' => $gelenEvrak,
                'giden_evrak' => $gidenEvrak,
                'cevap_bekleyen' => $cevapBekleyen,
                'imzamda_bekleyen' => $imzamdaBekleyen,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalItems,
                'has_more' => ($offset + count($rows)) < $totalItems,
            ],
        ]);
    }

    // -------------------------------------------------------------
    // 4. META SEÇENEKLERİ (GET meta)
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'meta') {
        $PersonelModel = new PersonelModel();
        $personeller = $PersonelModel->all(false, 'evrak');
        $activeVehicles = $EvrakModel->getActiveVehicles();
        $signingUsers = $EvrakModel->getSigningUsers();
        $gelenEvraklar = $EvrakModel->getGelenEvraklar();

        $personelOptions = [];
        $ofisPersonelOptions = [];
        foreach ($personeller as $p) {
            $opt = [
                'id' => Security::encrypt((int) $p->id),
                'raw_id' => (int) $p->id,
                'name' => (string) $p->adi_soyadi,
                'department' => (string) ($p->departman ?? ''),
            ];
            $personelOptions[] = $opt;
            if (mb_strtoupper((string) ($p->departman ?? ''), 'UTF-8') === 'BÜRO' || mb_strtoupper((string) ($p->departman ?? ''), 'UTF-8') === 'OFİS') {
                $ofisPersonelOptions[] = $opt;
            }
        }

        $aracOptions = [];
        foreach ($activeVehicles as $ar) {
            $aracOptions[] = [
                'id' => Security::encrypt((int) $ar->id),
                'raw_id' => (int) $ar->id,
                'plaka' => (string) $ar->plaka,
                'marka_model' => trim((string) (($ar->marka ?? '') . ' ' . ($ar->model ?? ''))),
            ];
        }

        $signersList = [];
        foreach ($signingUsers as $u) {
            $signersList[] = [
                'id' => Security::encrypt((int) $u->id),
                'raw_id' => (int) $u->id,
                'name' => (string) $u->adi_soyadi,
                'title' => (string) ($u->imza_unvani ?: 'Yetkili'),
            ];
        }

        $gelenList = [];
        foreach ($gelenEvraklar as $ge) {
            $gelenList[] = [
                'id' => Security::encrypt((int) $ge->id),
                'raw_id' => (int) $ge->id,
                'evrak_no' => (string) ($ge->evrak_no ?: '-'),
                'konu' => (string) $ge->konu,
                'tarih' => !empty($ge->tarih) ? date('d.m.Y', strtotime((string) $ge->tarih)) : '',
            ];
        }

        evrakApiResponse([
            'success' => true,
            'meta' => [
                'personeller' => $personelOptions,
                'ofis_personeller' => $ofisPersonelOptions !== [] ? $ofisPersonelOptions : $personelOptions,
                'araclar' => $aracOptions,
                'signing_users' => $signersList,
                'gelen_evraklar' => $gelenList,
                'next_evrak_no' => [
                    'gelen' => $EvrakModel->getMaxEvrakNo('gelen'),
                    'giden' => $EvrakModel->getMaxEvrakNo('giden'),
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------
    // 5. TEK EVRAK DETAYI (GET detail)
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'detail') {
        $id = $decryptId($_GET['id'] ?? '');
        if ($id <= 0) {
            evrakApiResponse(['success' => false, 'message' => 'Geçersiz evrak ID.'], 400);
        }

        $evrak = $EvrakModel->getById($id);
        if (!$evrak) {
            evrakApiResponse(['success' => false, 'message' => 'Evrak bulunamadı.'], 404);
        }

        $isGelen = ($evrak->evrak_tipi === 'gelen');
        $onayDurumu = (string) ($evrak->onay_durumu ?? 'taslak');
        $onayRecords = $EvrakModel->getApprovalState($id);
        $nextSigner = $EvrakModel->getNextPendingSigner($id);
        $siraBende = ($nextSigner && (int) $nextSigner->kullanici_id === $currentUserId);
        $canRevoke = $EvrakModel->canRevokeApproval($evrak, $currentUserId);

        // Signer users list with their approval state
        $approvalSteps = [];
        foreach ($onayRecords as $rec) {
            $approvalSteps[] = [
                'user_id' => Security::encrypt((int) $rec['kullanici_id']),
                'name' => (string) $rec['adi_soyadi'],
                'sira' => (int) $rec['sira'],
                'durum' => (string) $rec['durum'],
                'onay_tarihi' => !empty($rec['onay_tarihi']) ? date('d.m.Y H:i', strtotime((string) $rec['onay_tarihi'])) : null,
                'is_current' => ($nextSigner && (int) $nextSigner->kullanici_id === (int) $rec['kullanici_id']),
            ];
        }

        // Attachments
        $attachments = $EvrakModel->getAttachments($id);
        if ($attachments === [] && !empty($evrak->dosya_yolu)) {
            $attachments[] = (object) [
                'id' => 0,
                'dosya_adi' => basename((string) $evrak->dosya_yolu),
                'dosya_yolu' => $evrak->dosya_yolu,
                'mime_tipi' => '',
                'dosya_boyutu' => 0,
            ];
        }

        $attachmentList = [];
        foreach ($attachments as $att) {
            $attEncId = !empty($att->id) ? Security::encrypt((int) $att->id) : '';
            $attachmentList[] = [
                'id' => $attEncId,
                'name' => (string) ($att->dosya_adi ?: basename((string) $att->dosya_yolu)),
                'path' => (string) $att->dosya_yolu,
                'size' => (int) ($att->dosya_boyutu ?? 0),
                'mime' => (string) ($att->mime_tipi ?? ''),
                'url' => !empty($attEncId)
                    ? '/api/evrak.php?action=stream-file&id=' . urlencode($attEncId)
                    : '/api/evrak.php?action=stream-file&path=' . urlencode((string) $att->dosya_yolu),
            ];
        }

        $returnedBy = '';
        if (!empty($evrak->e_imza_iade_kullanici_id)) {
            $userRow = (new UserModel())->find((int) $evrak->e_imza_iade_kullanici_id);
            $returnedBy = (string) ($userRow->adi_soyadi ?? '');
        }

        $encId = Security::encrypt($id);
        $data = [
            'id' => $encId,
            'raw_id' => $id,
            'evrak_tipi' => (string) $evrak->evrak_tipi,
            'evrak_no' => (string) ($evrak->evrak_no ?: ''),
            'tarih' => (string) $evrak->tarih,
            'tarih_formatted' => !empty($evrak->tarih) ? date('d.m.Y', strtotime((string) $evrak->tarih)) : '-',
            'konu' => (string) $evrak->konu,
            'kurum_adi' => (string) $evrak->kurum_adi,
            'muhatap_alt_birim' => (string) ($evrak->muhatap_alt_birim ?: ''),
            'muhatap_adres' => (string) ($evrak->muhatap_adres ?: ''),
            'personel_id' => !empty($evrak->personel_id) ? Security::encrypt((int) $evrak->personel_id) : '',
            'personel_adi' => (string) ($evrak->personel_adi ?: ''),
            'ilgili_personel_id' => !empty($evrak->ilgili_personel_id) ? Security::encrypt((int) $evrak->ilgili_personel_id) : '',
            'ilgili_personel_adi' => (string) ($evrak->ilgili_personel_adi ?: ''),
            'cevap_verildi_mi' => !empty($evrak->cevap_verildi_mi),
            'cevap_tarihi' => (string) ($evrak->cevap_tarihi ?: ''),
            'cevap_tarihi_formatted' => !empty($evrak->cevap_tarihi) ? date('d.m.Y', strtotime((string) $evrak->cevap_tarihi)) : '',
            'aciklama' => (string) ($evrak->aciklama ?: ''),
            'ust_yazi_gerekli_degil' => !empty($evrak->ust_yazi_gerekli_degil),
            'ilgiler' => (string) ($evrak->ilgiler ?: ''),
            'ekler_metin' => (string) ($evrak->ekler ?: ''),
            'ilgili_evrak_id' => !empty($evrak->ilgili_evrak_id) ? Security::encrypt((int) $evrak->ilgili_evrak_id) : '',
            'plaka' => (string) ($evrak->plaka ?: ''),
            'ceza_tutari' => !empty($evrak->ceza_tutari) ? (float) $evrak->ceza_tutari : null,
            'tutar' => !empty($evrak->tutar) ? (float) $evrak->tutar : null,
            'onay_durumu' => $onayDurumu,
            'sira_bende' => $siraBende,
            'can_revoke' => $canRevoke,
            'approval_steps' => $approvalSteps,
            'iade_gerekcesi' => (string) ($evrak->e_imza_iade_gerekcesi ?: ''),
            'iade_eden' => $returnedBy,
            'iade_tarihi' => !empty($evrak->e_imza_iade_tarihi) ? date('d.m.Y H:i', strtotime((string) $evrak->e_imza_iade_tarihi)) : '',
            'attachments' => $attachmentList,
            'pdf_url' => ($evrak->evrak_tipi === 'giden') ? '/api/evrak.php?action=pdf&id=' . urlencode($encId) : null,
        ];

        evrakApiResponse(['success' => true, 'data' => $data]);
    }

    // -------------------------------------------------------------
    // 6. ARAÇ ZİMMET SORGULA (GET arac-zimmet)
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'arac-zimmet') {
        $plaka = trim((string) ($_GET['plaka'] ?? ''));
        $tarih = trim((string) ($_GET['tarih'] ?? date('Y-m-d')));

        if ($plaka === '') {
            evrakApiResponse(['success' => true, 'assignment' => null]);
        }

        $assignment = $EvrakModel->findVehicleAssignment($plaka, $tarih);
        if ($assignment) {
            evrakApiResponse([
                'success' => true,
                'assignment' => [
                    'personel_id' => Security::encrypt((int) $assignment->personel_id),
                    'raw_personel_id' => (int) $assignment->personel_id,
                    'adi_soyadi' => (string) $assignment->adi_soyadi,
                ],
            ]);
        }

        evrakApiResponse(['success' => true, 'assignment' => null]);
    }

    // -------------------------------------------------------------
    // 7. EVRAK KAYDET / GÜNCELLE (POST save)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'save') {
        verifyCsrfToken();

        $rawId = $decryptId($_POST['id'] ?? '');
        $isEdit = ($rawId > 0);

        if ($isEdit) {
            $existing = $EvrakModel->getById($rawId);
            if (!$existing) {
                evrakApiResponse(['success' => false, 'message' => 'Güncellenecek evrak bulunamadı.'], 404);
            }
            if (($existing->onay_durumu ?? 'taslak') !== 'taslak') {
                evrakApiResponse(['success' => false, 'message' => 'Elektronik imza onay süreci başlamış evrak değiştirilemez.'], 400);
            }
        }

        $evrakTipi = (($_POST['evrak_tipi'] ?? 'gelen') === 'giden') ? 'giden' : 'gelen';
        $konu = trim((string) ($_POST['konu'] ?? ''));
        $kurumAdi = trim((string) ($_POST['kurum_adi'] ?? ''));

        if ($konu === '') {
            evrakApiResponse(['success' => false, 'message' => 'Lütfen evrak konusunu belirtiniz.'], 422);
        }
        if ($kurumAdi === '') {
            evrakApiResponse(['success' => false, 'message' => 'Lütfen muhatap kurum/firma adını belirtiniz.'], 422);
        }

        $tarih = !empty($_POST['tarih']) ? Date::Ymd((string) $_POST['tarih']) : date('Y-m-d');
        $evrakNo = trim((string) ($_POST['evrak_no'] ?? ''));
        if ($evrakNo === '') {
            $evrakNo = (string) $EvrakModel->getMaxEvrakNo($evrakTipi);
        }

        $personelId = $decryptId($_POST['personel_id'] ?? '');
        $ilgiliPersonelId = $decryptId($_POST['ilgili_personel_id'] ?? '');
        $ilgiliEvrakId = $decryptId($_POST['ilgili_evrak_id'] ?? '');

        $aciklama = RichTextSanitizer::sanitize((string) ($_POST['aciklama'] ?? ''));
        $ustYaziGerekliDegil = !empty($_POST['ust_yazi_gerekli_degil']) ? 1 : 0;
        $cevapVerildiMi = !empty($_POST['cevap_verildi_mi']) ? 1 : 0;
        $cevapTarihi = ($cevapVerildiMi && !empty($_POST['cevap_tarihi'])) ? Date::Ymd((string) $_POST['cevap_tarihi']) : null;
        $personelBildirimDurumu = !empty($_POST['personel_bildirim_durumu']) ? 1 : 0;

        $plaka = trim((string) ($_POST['plaka'] ?? '')) ?: null;
        $cezaTutari = !empty($_POST['ceza_tutari']) ? (float) str_replace(['.', ','], ['', '.'], (string) $_POST['ceza_tutari']) : null;
        $tutar = !empty($_POST['tutar']) ? (float) str_replace(['.', ','], ['', '.'], (string) $_POST['tutar']) : ($cezaTutari ?: null);

        // Giden evrak için imzacılar
        $signerIds = [];
        $rawSigners = $_POST['imza_kullanici_ids'] ?? [];
        if (is_string($rawSigners)) {
            $rawSigners = json_decode($rawSigners, true) ?: [];
        }
        if (is_array($rawSigners)) {
            foreach (array_slice($rawSigners, 0, 3) as $encSId) {
                $sId = $decryptId($encSId);
                if ($sId > 0) {
                    $signerIds[] = $sId;
                }
            }
        }
        $validSigners = $EvrakModel->getSigningUsersByIds($signerIds);
        $imzaKullaniciIdsJson = json_encode(array_map(fn($u) => (int) $u->id, $validSigners), JSON_UNESCAPED_UNICODE);

        $payload = [
            'id' => $rawId > 0 ? $rawId : null,
            'firma_id' => $firmaId,
            'evrak_tipi' => $evrakTipi,
            'tarih' => $tarih,
            'evrak_no' => $evrakNo,
            'konu' => mb_substr($konu, 0, 500, 'UTF-8'),
            'kurum_adi' => mb_substr($kurumAdi, 0, 500, 'UTF-8'),
            'muhatap_alt_birim' => trim((string) ($_POST['muhatap_alt_birim'] ?? '')) ?: null,
            'muhatap_adres' => trim((string) ($_POST['muhatap_adres'] ?? '')) ?: null,
            'personel_id' => $personelId > 0 ? $personelId : null,
            'ilgili_personel_id' => $ilgiliPersonelId > 0 ? $ilgiliPersonelId : null,
            'cevap_verildi_mi' => $cevapVerildiMi,
            'cevap_tarihi' => $cevapTarihi,
            'personel_bildirim_durumu' => $personelBildirimDurumu,
            'aciklama' => $aciklama,
            'ust_yazi_gerekli_degil' => $ustYaziGerekliDegil,
            'ilgili_evrak_id' => $ilgiliEvrakId > 0 ? $ilgiliEvrakId : null,
            'ilgiler' => trim((string) ($_POST['ilgiler'] ?? '')) ?: null,
            'ekler' => trim((string) ($_POST['ekler'] ?? '')) ?: null,
            'plaka' => $plaka,
            'ceza_tutari' => $cezaTutari,
            'tutar' => $tutar,
            'imza_kullanici_ids' => $imzaKullaniciIdsJson,
        ];

        if (!$isEdit) {
            $payload['olusturan_kullanici_id'] = $currentUserId;
            $payload['onay_durumu'] = 'taslak';
            $savedId = (int) $EvrakModel->saveWithAttr($payload);
        } else {
            $EvrakModel->saveWithAttr($payload);
            $savedId = $rawId;
        }

        // Removed attachments if any
        $removedIdsRaw = $_POST['silinen_ek_ids'] ?? [];
        if (is_string($removedIdsRaw)) {
            $removedIdsRaw = json_decode($removedIdsRaw, true) ?: [];
        }
        $removedIds = [];
        if (is_array($removedIdsRaw)) {
            foreach ($removedIdsRaw as $rEnc) {
                $rId = $decryptId($rEnc);
                if ($rId > 0) $removedIds[] = $rId;
            }
        }
        if ($removedIds !== []) {
            $EvrakModel->syncAttachmentState($savedId, [], $removedIds);
        }

        // Multiple file uploads (ISO 27001 encrypted storage)
        $uploadDir = $projectRoot . '/uploads/evrak-takip/ekler/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $uploadedFilesList = [];
        $filesObj = $_FILES['dosyalar'] ?? ($_FILES['dosya'] ?? null);

        if ($filesObj && !empty($filesObj['name'])) {
            $names = is_array($filesObj['name']) ? $filesObj['name'] : [$filesObj['name']];
            $tmpNames = is_array($filesObj['tmp_name']) ? $filesObj['tmp_name'] : [$filesObj['tmp_name']];
            $sizes = is_array($filesObj['size']) ? $filesObj['size'] : [$filesObj['size']];
            $errors = is_array($filesObj['error']) ? $filesObj['error'] : [$filesObj['error']];

            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

            foreach ($names as $idx => $origName) {
                if (($errors[$idx] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }
                $tmp = $tmpNames[$idx] ?? '';
                $size = (int) ($sizes[$idx] ?? 0);
                if (!is_uploaded_file($tmp) || $size <= 0 || $size > 15 * 1024 * 1024) {
                    continue;
                }

                $ext = strtolower(pathinfo((string) $origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions, true)) {
                    continue;
                }

                $rawBinary = file_get_contents($tmp);
                if ($rawBinary === false) {
                    continue;
                }

                // ISO 27001 Physical Encryption
                $encryptedBinary = Security::encryptFile($rawBinary);

                $safeStoredName = 'ek_' . $savedId . '_' . bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
                $diskTarget = $uploadDir . $safeStoredName;

                if (file_put_contents($diskTarget, $encryptedBinary) !== false) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->buffer($rawBinary) ?: 'application/octet-stream';
                    $safeOriginalName = mb_substr(basename((string) $origName), 0, 255, 'UTF-8');

                    $EvrakModel->saveAttachment($savedId, [
                        'dosya_adi' => $safeOriginalName,
                        'dosya_yolu' => 'uploads/evrak-takip/ekler/' . $safeStoredName,
                        'mime_tipi' => $mime,
                        'dosya_boyutu' => $size,
                        'sira' => 100 + $idx,
                    ]);
                }
            }
        }

        // Reset approval workflow if signers changed in draft
        if ($evrakTipi === 'giden' && !empty($validSigners)) {
            $EvrakModel->resetApprovalWorkflow($savedId, array_map(fn($u) => (int) $u->id, $validSigners));
        }

        // System Log
        (new SystemLogModel())->logAction(
            $currentUserId,
            $isEdit ? 'Evrak Güncellendi' : 'Yeni Evrak Oluşturuldu',
            "Evrak No: {$evrakNo}, Konu: {$konu}, Kurum: {$kurumAdi} (ID: {$savedId})",
            SystemLogModel::LEVEL_INFO
        );

        evrakApiResponse([
            'success' => true,
            'message' => $isEdit ? 'Evrak başarıyla güncellendi.' : 'Yeni evrak kaydı başarıyla oluşturuldu.',
            'id' => Security::encrypt($savedId),
        ]);
    }

    // -------------------------------------------------------------
    // 8. EVRAK SİL (POST delete)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'delete') {
        verifyCsrfToken();

        $id = $decryptId($_POST['id'] ?? '');
        if ($id <= 0) {
            evrakApiResponse(['success' => false, 'message' => 'Geçersiz evrak ID.'], 400);
        }

        $document = $EvrakModel->getById($id);
        if (!$document) {
            evrakApiResponse(['success' => false, 'message' => 'Evrak bulunamadı.'], 404);
        }
        if (($document->onay_durumu ?? 'taslak') !== 'taslak') {
            evrakApiResponse(['success' => false, 'message' => 'Elektronik imza onay süreci başlamış veya tamamlanmış evrak silinemez.'], 400);
        }

        $EvrakModel->softDelete($id);

        // Delete associated deduction if exists
        $delKesinti = $db->prepare("UPDATE personel_kesintileri SET silinme_tarihi = NOW() WHERE aciklama LIKE :desc AND silinme_tarihi IS NULL");
        $delKesinti->execute(['desc' => "%Evrak ID: {$id}%"]);

        (new SystemLogModel())->logAction(
            $currentUserId,
            'Evrak Silindi',
            "Evrak ID: {$id}, Konu: " . ($document->konu ?? '-'),
            SystemLogModel::LEVEL_WARNING
        );

        evrakApiResponse(['success' => true, 'message' => 'Evrak kaydı başarıyla silindi.']);
    }

    // -------------------------------------------------------------
    // 9. E-İMZA ONAYA SUN (POST e-imza-onaya-sun)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'e-imza-onaya-sun') {
        verifyCsrfToken();

        $id = $decryptId($_POST['id'] ?? '');
        if ($id <= 0) {
            evrakApiResponse(['success' => false, 'message' => 'Geçersiz evrak ID.'], 400);
        }

        $submission = $EvrakModel->submitForApproval($id, $currentUserId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        (new SystemLogModel())->logAction(
            $currentUserId,
            'Evrak E-İmza Onayına Sunma',
            "Evrak ID: {$id} onaya sunuldu. İmzacılar: " . implode(', ', $submission['signers']),
            SystemLogModel::LEVEL_IMPORTANT
        );

        $siradaki = $EvrakModel->getNextPendingSigner($id);
        if ($siradaki) {
            (new EvrakOnayBildirimService())->imzaSirasiGeldi(
                $EvrakModel->getById($id),
                (int) $siradaki->kullanici_id,
                (int) $siradaki->sira,
                (int) $submission['total']
            );
        }

        $msg = 'Evrak elektronik imza onayına sunuldu.';
        if ($submission['completed']) {
            $msg = 'Tek imzacı siz olduğunuz için imzanız otomatik atıldı ve evrak elektronik imzalı hâle geldi.';
        } elseif ($submission['otomatik_imza']) {
            $msg = 'Evrak onaya sunuldu ve ilk imzacı olduğunuz için imzanız otomatik atıldı. Sıradaki imzacı bilgilendirildi.';
        }

        evrakApiResponse([
            'success' => true,
            'message' => $msg,
            'data' => $submission,
        ]);
    }

    // -------------------------------------------------------------
    // 10. E-İMZA ONAYLA (POST e-imza-onayla)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'e-imza-onayla') {
        verifyCsrfToken();

        $id = $decryptId($_POST['id'] ?? '');
        if ($id <= 0) {
            evrakApiResponse(['success' => false, 'message' => 'Geçersiz evrak ID.'], 400);
        }

        $approval = $EvrakModel->approveWithWorkflow($id, $currentUserId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        (new SystemLogModel())->logAction(
            $currentUserId,
            'Evrak E-İmza Onayı',
            "Evrak ID: {$id} onaylandı ({$approval['approved']}/{$approval['total']} imza).",
            SystemLogModel::LEVEL_IMPORTANT
        );

        $onayBildirim = new EvrakOnayBildirimService();
        $doc = $EvrakModel->getById($id);
        if ($approval['completed']) {
            $onayBildirim->onayTamamlandi($doc);
        } else {
            $siradaki = $EvrakModel->getNextPendingSigner($id);
            if ($siradaki) {
                $onayBildirim->imzaSirasiGeldi($doc, (int) $siradaki->kullanici_id, (int) $siradaki->sira, (int) $approval['total']);
            }
        }

        evrakApiResponse([
            'success' => true,
            'message' => $approval['completed']
                ? 'Tüm imzalar tamamlandı. Evrak elektronik imzalı olarak kilitlendi.'
                : 'İmzanız kaydedildi. Evrak sonraki imzacının onayını bekliyor.',
            'data' => $approval,
        ]);
    }

    // -------------------------------------------------------------
    // 11. E-İMZA İADE ET (POST e-imza-iade)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'e-imza-iade') {
        verifyCsrfToken();

        $id = $decryptId($_POST['id'] ?? '');
        $gerekce = trim((string) ($_POST['gerekce'] ?? ''));

        if ($id <= 0) {
            evrakApiResponse(['success' => false, 'message' => 'Geçersiz evrak ID.'], 400);
        }
        if (mb_strlen($gerekce, 'UTF-8') < 5) {
            evrakApiResponse(['success' => false, 'message' => 'Lütfen en az 5 karakterlik bir iade gerekçesi yazınız.'], 422);
        }

        $doc = $EvrakModel->getById($id);
        if (!$doc) {
            evrakApiResponse(['success' => false, 'message' => 'Evrak bulunamadı.'], 404);
        }

        $iade = $EvrakModel->rejectApproval($id, $currentUserId, $gerekce);

        (new SystemLogModel())->logAction(
            $currentUserId,
            'Evrak E-İmza İadesi',
            "Evrak ID: {$id} iade edildi. Gerekçe: {$gerekce}",
            SystemLogModel::LEVEL_IMPORTANT
        );

        (new EvrakOnayBildirimService())->evrakIadeEdildi(
            $doc,
            (string) ($_SESSION['user_full_name'] ?? 'İmzacı'),
            $gerekce
        );

        evrakApiResponse([
            'success' => true,
            'message' => 'Evrak düzeltilmek üzere iade edildi. Hazırlayan kullanıcı bilgilendirildi.',
        ]);
    }

    // -------------------------------------------------------------
    // 12. E-İMZA GERİ AL (POST e-imza-geri-al)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'e-imza-geri-al') {
        verifyCsrfToken();

        $id = $decryptId($_POST['id'] ?? '');
        if ($id <= 0) {
            evrakApiResponse(['success' => false, 'message' => 'Geçersiz evrak ID.'], 400);
        }

        $EvrakModel->revokeApproval($id, $currentUserId);

        (new SystemLogModel())->logAction(
            $currentUserId,
            'Evrak E-İmza Onayı Geri Alma',
            "Evrak ID: {$id} için imza süreci iptal edilerek taslağa alındı.",
            SystemLogModel::LEVEL_CRITICAL
        );

        evrakApiResponse([
            'success' => true,
            'message' => 'Evrak üzerinize geri alındı ve taslak durumuna döndürüldü.',
        ]);
    }

    evrakApiResponse(['success' => false, 'message' => 'Bilinmeyen işlem.'], 404);

} catch (\Throwable $e) {
    error_log('Mobile Evrak API Hatası: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
    evrakApiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
