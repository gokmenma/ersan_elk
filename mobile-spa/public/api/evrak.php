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
use App\Service\MobilImzaPadesService;

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

    $canAccess = Gate::allowsAny(['evrak-takip/list', 'evrak-takip/gelen-evrak', 'evrak-takip/giden-evrak']);

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

$evrakSevkChainPayload = static fn(array $chain): array => array_map(static fn(object $r): array => [
    'sevk_eden' => (string) $r->sevk_eden,
    'alan' => (string) $r->alan,
    'not' => (string) ($r->sevk_notu ?? ''),
    'durum' => (string) $r->durum,
    'tarih' => $r->created_at ? date('d.m.Y H:i', strtotime((string) $r->created_at)) : '',
    'goruldu' => $r->goruldu_at ? date('d.m.Y H:i', strtotime((string) $r->goruldu_at)) : '',
], $chain);

$decryptId = static function ($value): int {
    if (empty($value)) return 0;
    if (is_numeric($value)) return (int) $value;
    $d = Security::decrypt((string) $value);
    return is_numeric($d) ? (int) $d : 0;
};

$EvrakModel = new EvrakTakipModel();
$db = $EvrakModel->getDb();
$kepMobilYetkisi = in_array($action, ['list', 'detail', 'meta'], true)
    && Gate::allows('evrak-takip/kep')
    && MobilImzaPadesService::isEnabled($firmaId);

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
            if (str_starts_with($cleanPath, 'uploads/evrak-takip/') && (new \App\Model\EvrakSevkModel())->canViewFilePath($cleanPath, $firmaId)) {
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

        if (empty($_SERVER['HTTP_RANGE'])) {
            (new \App\Model\EvrakSevkModel())->recordFileOpen(ltrim(str_replace('\\', '/', (string) $targetFile), '/'), $firmaId, $currentUserId, $originalFileName);
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
        if (!Gate::allows('evrak-takip/list')) {
            evrakApiResponse(['success' => false, 'message' => 'Evrak listesini görüntüleme yetkiniz bulunmuyor.'], 403);
        }
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
        $allBind = [];
        $allSql = "SELECT et.id, et.onay_durumu FROM evrak_takip et WHERE et.firma_id = :firma_id AND et.silinme_tarihi IS NULL AND " . (new \App\Model\EvrakSevkModel())->visibilitySql('et', $allBind);
        $allStmt = $db->prepare($allSql);
        $allStmt->execute(['firma_id' => $firmaId] + $allBind);
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
        $visibilityBindings = [];
        $where .= ' AND ' . (new \App\Model\EvrakSevkModel())->visibilitySql('et', $visibilityBindings);
        $bind += $visibilityBindings;

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
            ORDER BY et.tarih DESC, et.olusturma_tarihi DESC, et.id DESC
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

        $sevkModel = new \App\Model\EvrakSevkModel();
        $uetsReadIds = array_flip($sevkModel->readEvrakIds($firmaId, $currentUserId, array_map(static fn(object $r): int => (int) $r->id, $rows)));

        $stepsMap = $EvrakModel->getApprovalStepsMap(array_map(static fn(object $r): int => (int) $r->id, $rows));

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

            $listSteps = [];
            $currentFound = false;
            foreach ($stepsMap[$id] ?? [] as $rec) {
                $isCurrent = !$currentFound && $rec['durum'] === 'bekliyor' && $onayDurumu === 'onay_bekliyor';
                $currentFound = $currentFound || $isCurrent;
                $listSteps[] = [
                    'name' => (string) $rec['adi_soyadi'],
                    'sira' => (int) $rec['sira'],
                    'durum' => (string) $rec['durum'],
                    'onay_tarihi' => !empty($rec['onay_tarihi']) ? date('d.m.Y H:i', strtotime((string) $rec['onay_tarihi'])) : null,
                    'is_current' => $isCurrent,
                ];
            }

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

            $isKep = (($row->kaynak_tipi ?? '') === 'kep' || !empty($row->kep_mesaj_id) || ($row->gonderim_kanali ?? '') === 'kep');
            $isUets = (($row->kaynak_tipi ?? '') === 'uets' || !empty($row->uets_mesaj_id));

            $items[] = [
                'id' => $encId,
                'raw_id' => $id,
                'is_kep' => $isKep,
                'is_uets' => $isUets,
                'gonderim_kanali' => (string) ($row->gonderim_kanali ?? 'normal'),
                'kaynak_tipi' => (string) ($row->kaynak_tipi ?? 'manuel'),
                'kep_mesaj_id' => (string) ($row->kep_mesaj_id ?? ''),
                'alici_kep' => (string) ($row->alici_kep ?? ''),
                'uets_mesaj_id' => (string) ($row->uets_mesaj_id ?? ''),
                'uets_kisitli' => \App\Model\EvrakSevkModel::isRestricted($row),
                'uets_okunmamis' => \App\Model\EvrakSevkModel::isRestricted($row) && !isset($uetsReadIds[$id]),
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
                'approval_steps' => $listSteps,
                'kilitli' => $kilitli,
                'onaya_sunulabilir' => $onayaSunulabilir,
                'geri_alinabilir' => $geriAlinabilir,
                'kep_gonderime_hazir' => $kepMobilYetkisi && !$isGelen && ($row->gonderim_kanali ?? '') === 'kep'
                    && $onayDurumu === 'onaylandi' && empty($row->kep_mesaj_id),
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
        // Evrak atamalarında işten ayrılmış personel seçenek olarak sunulmaz.
        $personeller = $PersonelModel->all(true, 'evrak');
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

        $icraPersoneller = [];
        try {
            $icraUstYazi = new \App\Service\IcraUstYaziService();
            $icraMap = $icraUstYazi->icrasiOlanPersoneller();
            foreach ($icraMap as $pId => $label) {
                if (empty($pId)) continue;
                $icraPersoneller[] = [
                    'id' => Security::encrypt((int) $pId),
                    'raw_id' => (int) $pId,
                    'label' => (string) $label,
                ];
            }
        } catch (\Throwable $e) {
            error_log('Icra personel listesi alınamadı: ' . $e->getMessage());
        }

        evrakApiResponse([
            'success' => true,
            'meta' => [
                'personeller' => $personelOptions,
                'ofis_personeller' => $ofisPersonelOptions !== [] ? $ofisPersonelOptions : $personelOptions,
                'araclar' => $aracOptions,
                'signing_users' => $signersList,
                'gelen_evraklar' => $gelenList,
                'icra_personeller' => $icraPersoneller,
                'kep_mobil_imza' => $kepMobilYetkisi,
                'mobil_imza_aktif' => MobilImzaPadesService::isEnabled($firmaId),
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
        $isKep = (($evrak->kaynak_tipi ?? '') === 'kep' || !empty($evrak->kep_mesaj_id) || ($evrak->gonderim_kanali ?? '') === 'kep');
        $isUets = (($evrak->kaynak_tipi ?? '') === 'uets' || !empty($evrak->uets_mesaj_id));

        $data = [
            'id' => $encId,
            'raw_id' => $id,
            'is_kep' => $isKep,
            'is_uets' => $isUets,
            'gonderim_kanali' => (string) ($evrak->gonderim_kanali ?? 'normal'),
            'kaynak_tipi' => (string) ($evrak->kaynak_tipi ?? 'manuel'),
            'kep_mesaj_id' => (string) ($evrak->kep_mesaj_id ?? ''),
            'alici_kep' => (string) ($evrak->alici_kep ?? ''),
            'uets_mesaj_id' => (string) ($evrak->uets_mesaj_id ?? ''),
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
            'kep_gonderime_hazir' => $kepMobilYetkisi && !$isGelen && ($evrak->gonderim_kanali ?? '') === 'kep'
                && $onayDurumu === 'onaylandi' && empty($evrak->kep_mesaj_id),
            'approval_steps' => $approvalSteps,
            'iade_gerekcesi' => (string) ($evrak->e_imza_iade_gerekcesi ?: ''),
            'iade_eden' => $returnedBy,
            'iade_tarihi' => !empty($evrak->e_imza_iade_tarihi) ? date('d.m.Y H:i', strtotime((string) $evrak->e_imza_iade_tarihi)) : '',
            'yazi_tipi' => (string) ($evrak->yazi_tipi ?: 'times_new_roman'),
            'imza_kimin_adina' => json_decode((string) ($evrak->imza_kimin_adina_json ?? '[]'), true) ?: [],
            'imza_kullanici_ids' => array_map(fn($uid) => Security::encrypt((int) $uid), json_decode((string) ($evrak->imza_kullanici_ids ?? '[]'), true) ?: []),
            'attachments' => $attachmentList,
            'pdf_url' => ($evrak->evrak_tipi === 'giden') ? '/api/evrak.php?action=pdf&id=' . urlencode($encId) : null,
        ];

        $sevkModel = new \App\Model\EvrakSevkModel();
        if (\App\Model\EvrakSevkModel::isRestricted($evrak)) {
            $sevkModel->recordHistory($firmaId, $id, 'goruntuleme', $currentUserId, null, null);
            $sevkModel->markSeen($firmaId, $id, $currentUserId);
            $data['uets_kisitli'] = true;
            $data['uets_okunmamis'] = false;
            $data['sevk_gecmisi'] = $evrakSevkChainPayload($sevkModel->sevkChain($firmaId, $id));
            $data['tarihce'] = $sevkModel->hasFullAccess()
                ? array_map(static fn(object $r): array => [
                    'islem' => (string) $r->islem,
                    'islem_yapan' => (string) ($r->islem_yapan ?? 'Sistem'),
                    'hedef' => (string) ($r->hedef ?? ''),
                    'aciklama' => (string) ($r->aciklama ?? ''),
                    'tarih' => $r->created_at ? date('d.m.Y H:i', strtotime((string) $r->created_at)) : '',
                ], $sevkModel->auditTrail($firmaId, $id, 100))
                : null;
        }

        evrakApiResponse(['success' => true, 'data' => $data]);
    }

    if ($method === 'GET' && $action === 'uets-sevk-personel') {
        $evrak = $EvrakModel->getById($decryptId($_GET['id'] ?? ''));
        if (!$evrak || !\App\Model\EvrakSevkModel::isRestricted($evrak)) {
            evrakApiResponse(['success' => false, 'message' => 'Evrak bulunamadı.'], 404);
        }
        $users = (new \App\Model\EvrakSevkModel())->recipients($firmaId, (int) ($_SESSION['owner_id'] ?? 0), $currentUserId);
        evrakApiResponse(['success' => true, 'data' => array_map(static fn(object $u): array => [
            'id' => Security::encrypt((int) $u->id),
            'adi' => (string) $u->adi,
            'unvan' => (string) $u->unvan,
        ], $users)]);
    }

    if ($method === 'POST' && $action === 'uets-sevk-et') {
        verifyCsrfToken();
        $evrak = $EvrakModel->getById($decryptId($_POST['id'] ?? ''));
        if (!$evrak) {
            evrakApiResponse(['success' => false, 'message' => 'Evrak bulunamadı.'], 404);
        }
        try {
            (new \App\Model\EvrakSevkModel())->sevkEt(
                $evrak,
                $currentUserId,
                $decryptId($_POST['alan_user_id'] ?? ''),
                (string) ($_POST['sevk_notu'] ?? ''),
                (int) ($_SESSION['owner_id'] ?? 0)
            );
        } catch (\Throwable $e) {
            evrakApiResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
        evrakApiResponse(['success' => true, 'message' => 'Evrak seçilen personele sevk edildi.']);
    }

    // -------------------------------------------------------------
    // 5.1. KEP İLETİSİ VE DELİL DETAYI (GET/POST kep-detail)
    // -------------------------------------------------------------
    if ($action === 'kep-detail') {
        $evrakIdRaw = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
        $kepId = trim((string) ($_GET['kep_id'] ?? $_POST['kep_id'] ?? ''));

        $evrak = null;
        $evrakId = 0;
        if ($evrakIdRaw !== '') {
            $evrakId = $decryptId($evrakIdRaw);
            if ($evrakId <= 0 && is_numeric($evrakIdRaw)) {
                $evrakId = (int) $evrakIdRaw;
            }
            if ($evrakId > 0) {
                $evrak = $EvrakModel->getById($evrakId);
            }
        }

        $kepMesajModel = new \App\Model\KepMesajModel();
        $mesaj = null;
        if ($evrakId > 0) {
            $mesaj = $kepMesajModel->getByEvrakId($firmaId, $evrakId);
        }
        if (!$mesaj && $kepId !== '') {
            $mesaj = $kepMesajModel->getByKepId($firmaId, $kepId);
        }
        if (!$mesaj && $evrak && !empty($evrak->kep_mesaj_id)) {
            $mesaj = $kepMesajModel->getByKepId($firmaId, $evrak->kep_mesaj_id);
        }
        if ($mesaj && !$evrak && !empty($mesaj['evrak_id'])) {
            $evrak = $EvrakModel->getById((int) $mesaj['evrak_id']);
        }

        if (!$mesaj && !$evrak) {
            evrakApiResponse(['success' => false, 'message' => 'KEP iletisi veya evrak kaydı bulunamadı.'], 404);
        }

        // Live delil senkronizasyonu
        $targetKepId = $mesaj['kep_id'] ?? $evrak->kep_mesaj_id ?? '';
        if ($targetKepId !== '') {
            try {
                $kepService = new \App\Service\KepService();
                $syncRes = $kepService->syncEvidences($firmaId, $targetKepId);
                if (!empty($syncRes['mesaj'])) {
                    $mesaj = $syncRes['mesaj'];
                }
            } catch (\Throwable $e) {
                error_log('Mobile KEP live sync notice: ' . $e->getMessage());
            }
        }

        $delilModel = new \App\Model\KepDelilModel();
        $deliller = $mesaj ? $delilModel->getDelillerByMesajId((int) $mesaj['id']) : [];
        $formatliDeliller = [];
        foreach ($deliller as $delil) {
            $turId = (int) ($delil['delil_tur_id'] ?? 0);
            $turBilgisi = match ($turId) {
                10 => ['ad' => 'Kabul Delili (Submission Acceptance)', 'badge' => 'success', 'icon' => 'CheckCircle'],
                20 => ['ad' => 'KEPHS Aktarım Delili (Relay)', 'badge' => 'info', 'icon' => 'ArrowRightLeft'],
                30 => ['ad' => 'Alıcı Sunucuya İletim (Relay To Recipient)', 'badge' => 'primary', 'icon' => 'Send'],
                40 => ['ad' => 'Alıcı Posta Kutusuna Teslim Delili (Delivery To Recipient)', 'badge' => 'primary', 'icon' => 'MailCheck'],
                50 => ['ad' => 'Teslim Edilemedi Delili (Non-Delivery)', 'badge' => 'danger', 'icon' => 'AlertTriangle'],
                60 => ['ad' => 'Alıcı Tarafından Açıldı / Okundu Delili (Retrieval)', 'badge' => 'info', 'icon' => 'MailOpen'],
                70 => ['ad' => 'Alıcı Tarafından İndirildi (Download)', 'badge' => 'success', 'icon' => 'Download'],
                default => ['ad' => 'PTT Delili (' . $turId . ')', 'badge' => 'secondary', 'icon' => 'FileText'],
            };

            $hasXml = !empty($delil['delil_xml_yolu']) && file_exists($projectRoot . '/' . ltrim((string) $delil['delil_xml_yolu'], '/'));

            $formatliDeliller[] = [
                'id' => (int) $delil['id'],
                'delil_id' => (string) $delil['delil_id'],
                'delil_tur_id' => $turId,
                'tur_adi' => $turBilgisi['ad'],
                'badge' => $turBilgisi['badge'],
                'icon' => $turBilgisi['icon'],
                'aciklama' => (string) ($delil['delil_aciklama'] ?? ''),
                'olay_zamani' => !empty($delil['olay_zamani']) ? date('d.m.Y H:i:s', strtotime((string) $delil['olay_zamani'])) : '-',
                'kephs' => (string) ($delil['kephs'] ?: 'PTT KEPHS'),
                'has_xml' => $hasXml,
                'xml_url' => '/api/evrak.php?action=kep-delil-indir&delil_id=' . urlencode((string) $delil['delil_id']),
            ];
        }

        // Attachments
        $ekListesi = [];
        if ($evrak && !empty($evrak->id)) {
            $attachmentMap = $EvrakModel->getAttachmentMap([(int) $evrak->id]);
            $evrakFiles = $attachmentMap[(int) $evrak->id] ?? [];

            // Self-healing from encrypted package if needed
            if (empty($evrakFiles) && !empty($mesaj['ham_paket_yolu'])) {
                $encPath = $projectRoot . '/' . ltrim((string) $mesaj['ham_paket_yolu'], '/');
                if (is_file($encPath)) {
                    try {
                        $rawDec = Security::decryptFile(file_get_contents($encPath));
                        $pkg = json_decode((string) $rawDec, true);
                        if (!empty($pkg['files']) && is_array($pkg['files'])) {
                            $attDir = $projectRoot . '/uploads/evrak-takip/ekler/';
                            if (!is_dir($attDir)) {
                                @mkdir($attDir, 0777, true);
                            }
                            foreach ($pkg['files'] as $pIdx => $pf) {
                                $pName = (string) ($pf['name'] ?? 'belge.pdf');
                                $pExt = strtolower(pathinfo($pName, PATHINFO_EXTENSION));
                                $storedName = 'ek_' . $evrak->id . '_' . bin2hex(random_bytes(8)) . ($pExt !== '' ? '.' . $pExt : '');
                                $storedFullPath = $attDir . $storedName;
                                if (!empty($pf['content'])) {
                                    file_put_contents($storedFullPath, base64_decode($pf['content']));
                                    $EvrakModel->saveAttachment((int) $evrak->id, [
                                        'dosya_adi' => $pName,
                                        'dosya_yolu' => 'uploads/evrak-takip/ekler/' . $storedName,
                                        'mime_tipi' => (string) ($pf['mime'] ?? 'application/octet-stream'),
                                        'dosya_boyutu' => (int) ($pf['size'] ?? strlen(base64_decode($pf['content']))),
                                        'sira' => $pIdx + 1,
                                    ]);
                                }
                            }
                            $attachmentMap = $EvrakModel->getAttachmentMap([(int) $evrak->id]);
                            $evrakFiles = $attachmentMap[(int) $evrak->id] ?? [];
                        }
                    } catch (\Throwable $e) {}
                }
            }

            if (!empty($evrak->dosya_yolu) && !array_filter($evrakFiles, static fn(object $f): bool => ltrim((string) $f->dosya_yolu, '/') === ltrim((string) $evrak->dosya_yolu, '/'))) {
                array_unshift($evrakFiles, (object) [
                    'id' => 0,
                    'dosya_adi' => basename((string) $evrak->dosya_yolu),
                    'dosya_yolu' => $evrak->dosya_yolu,
                ]);
            }

            foreach ($evrakFiles as $ef) {
                $fullPath = $projectRoot . '/' . ltrim((string) $ef->dosya_yolu, '/');
                $attEncId = !empty($ef->id) ? Security::encrypt((int) $ef->id) : '';
                $streamUrl = !empty($attEncId)
                    ? '/api/evrak.php?action=stream-file&id=' . urlencode($attEncId)
                    : '/api/evrak.php?action=stream-file&path=' . urlencode((string) $ef->dosya_yolu);

                $ekListesi[] = [
                    'id' => $attEncId,
                    'name' => (string) $ef->dosya_adi,
                    'path' => (string) $ef->dosya_yolu,
                    'size' => is_file($fullPath) ? (int) filesize($fullPath) : (int) ($ef->dosya_boyutu ?? 0),
                    'url' => $streamUrl,
                ];
            }
        }

        if ($evrak && ($evrak->evrak_tipi ?? '') === 'giden' && empty($evrak->ust_yazi_gerekli_degil)) {
            array_unshift($ekListesi, [
                'id' => '',
                'name' => 'Üst Yazı.pdf',
                'path' => '',
                'size' => 0,
                'url' => '/api/evrak.php?action=pdf&id=' . urlencode(Security::encrypt((int) $evrak->id)),
            ]);
        }

        $icerik = '';
        if ($mesaj && !empty($mesaj['icerik'])) {
            $icerik = (string) $mesaj['icerik'];
        } elseif ($evrak && !empty($evrak->kep_mesaj_metni)) {
            $icerik = (string) $evrak->kep_mesaj_metni;
        } elseif ($evrak && !empty($evrak->aciklama)) {
            $icerik = (string) $evrak->aciklama;
        }

        $rawAlicilar = $mesaj['alici_kep'] ?? $evrak->kep_alicilar ?? $evrak->alici_kep ?? '';
        $aliciListesi = array_values(array_filter(array_map('trim', preg_split('/[;,\n\r]+/', (string) $rawAlicilar))));

        $durum = (string) ($mesaj['durum'] ?? $evrak->islem_durumu ?? 'gonderildi');
        $durumMetni = ($mesaj || !empty($evrak->kep_mesaj_id)) ? 'KEP İletisi Sevk Edildi' : 'Henüz KEP ile Gönderilmedi';
        $durumTheme = 'primary';
        if (!empty($formatliDeliller)) {
            $sonDelil = end($formatliDeliller);
            if ($sonDelil['delil_tur_id'] === 60) {
                $durumMetni = 'Alıcı Tarafından Açıldı / Okundu';
                $durumTheme = 'info';
            } elseif ($sonDelil['delil_tur_id'] === 40) {
                $durumMetni = 'Alıcı Posta Kutusuna Teslim Edildi';
                $durumTheme = 'success';
            }
        }

        $encId = $evrak ? Security::encrypt((int) $evrak->id) : '';

        evrakApiResponse([
            'success' => true,
            'data' => [
                'evrak_id' => $encId,
                'raw_id' => $evrak ? (int) $evrak->id : null,
                'evrak_no' => $evrak ? (string) ($evrak->evrak_no ?: '-') : '-',
                'kep_id' => (string) ($mesaj['kep_id'] ?? $evrak->kep_mesaj_id ?? '-'),
                'gonderen_kep' => (string) ($mesaj['gonderen_kep'] ?? '-'),
                'alici_kep' => (string) ($mesaj['alici_kep'] ?? $rawAlicilar),
                'alici_unvan' => (string) ($mesaj['alici_unvan'] ?? $evrak->kurum_adi ?? ''),
                'alici_listesi' => $aliciListesi,
                'konu' => (string) ($mesaj['konu'] ?? $evrak->konu ?? '-'),
                'icerik' => $icerik,
                'paket_turu' => (string) ($mesaj['paket_turu'] ?? $evrak->kep_paket_turu ?? 'Standart'),
                'ileti_tipi' => (string) ($evrak->kep_ileti_tipi ?? 'standart'),
                'durum' => $durum,
                'durum_metni' => $durumMetni,
                'durum_theme' => $durumTheme,
                'delil_sayisi' => count($formatliDeliller),
                'tarih' => !empty($mesaj['created_at']) ? date('d.m.Y H:i', strtotime((string) $mesaj['created_at'])) : (!empty($evrak->olusturma_tarihi) ? date('d.m.Y H:i', strtotime((string) $evrak->olusturma_tarihi)) : '-'),
                'ekler' => $ekListesi,
                'deliller' => $formatliDeliller,
                'pdf_url' => $encId ? '/api/evrak.php?action=pdf&id=' . urlencode($encId) : null,
            ],
        ]);
    }

    // -------------------------------------------------------------
    // 5.2. PTT'DEN DELİLLERİ SENKRONİZE ET (POST kep-sync-delil)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'kep-sync-delil') {
        verifyCsrfToken();
        $evrakIdRaw = trim((string) ($_POST['id'] ?? ''));
        $kepId = trim((string) ($_POST['kep_id'] ?? ''));
        $evrakId = $decryptId($evrakIdRaw);
        if ($evrakId <= 0 && is_numeric($evrakIdRaw)) {
            $evrakId = (int) $evrakIdRaw;
        }

        $targetKepId = $kepId;
        if ($targetKepId === '' && $evrakId > 0) {
            $ev = $EvrakModel->getById($evrakId);
            $targetKepId = (string) ($ev->kep_mesaj_id ?? '');
        }

        if ($targetKepId === '') {
            evrakApiResponse(['success' => false, 'message' => 'PTT KEP Id bulunamadı.'], 400);
        }

        $kepService = new \App\Service\KepService();
        $res = $kepService->syncEvidences($firmaId, $targetKepId);

        evrakApiResponse([
            'success' => true,
            'message' => 'PTT KEP yasal delilleri başarıyla güncellendi.',
            'synced_count' => (int) ($res['synced_count'] ?? 0),
        ]);
    }

    // -------------------------------------------------------------
    // 5.3. KEP DELİL XML İNDİR (GET kep-delil-indir)
    // -------------------------------------------------------------
    if ($action === 'kep-delil-indir') {
        $delilId = trim((string) ($_GET['delil_id'] ?? $_POST['delil_id'] ?? ''));
        if ($delilId === '') {
            evrakApiResponse(['success' => false, 'message' => 'Delil kimliği belirtilmedi.'], 400);
        }
        $kepService = new \App\Service\KepService();
        $res = $kepService->downloadEvidenceXml($firmaId, $delilId);

        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $res['filename'] . '"');
        header('Content-Length: ' . strlen((string) $res['xml']));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $res['xml'];
        exit;
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
            if (\App\Model\EvrakSevkModel::isRestricted($existing)) {
                evrakApiResponse(['success' => false, 'message' => 'UETS ile gelen tebligat evrakı mobilde değiştirilemez.'], 403);
            }
            if (($existing->onay_durumu ?? 'taslak') !== 'taslak') {
                evrakApiResponse(['success' => false, 'message' => 'Elektronik imza onay süreci başlamış evrak değiştirilemez.'], 400);
            }
        }

        $evrakTipi = (($_POST['evrak_tipi'] ?? 'gelen') === 'giden') ? 'giden' : 'gelen';
        if ($evrakTipi === 'giden' && !Gate::allows('evrak-takip/giden-evrak')) {
            evrakApiResponse(['success' => false, 'message' => 'Giden evrak oluşturma veya düzenleme yetkiniz bulunmuyor.'], 403);
        }
        if ($evrakTipi === 'gelen' && !Gate::allows('evrak-takip/gelen-evrak')) {
            evrakApiResponse(['success' => false, 'message' => 'Gelen evrak oluşturma veya düzenleme yetkiniz bulunmuyor.'], 403);
        }
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
        $yaziTipi = (($_POST['yazi_tipi'] ?? 'times_new_roman') === 'arial') ? 'arial' : 'times_new_roman';

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

        // Kimin adına imzalanacağı bilgisi
        $rawKiminAdina = $_POST['imza_kimin_adina'] ?? [];
        if (is_string($rawKiminAdina)) {
            $rawKiminAdina = json_decode($rawKiminAdina, true) ?: [];
        }
        $kiminAdinaList = [];
        if (is_array($rawKiminAdina)) {
            foreach ($rawKiminAdina as $ka) {
                $kiminAdinaList[] = mb_substr(trim((string) $ka), 0, 150, 'UTF-8');
            }
        }
        $imzaKiminAdinaJson = json_encode($kiminAdinaList, JSON_UNESCAPED_UNICODE);

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
            'yazi_tipi' => $yaziTipi,
            'ilgili_evrak_id' => $ilgiliEvrakId > 0 ? $ilgiliEvrakId : null,
            'ilgiler' => trim((string) ($_POST['ilgiler'] ?? '')) ?: null,
            'ekler' => trim((string) ($_POST['ekler'] ?? '')) ?: null,
            'plaka' => $plaka,
            'ceza_tutari' => $cezaTutari,
            'tutar' => $tutar,
            'imza_kullanici_ids' => $imzaKullaniciIdsJson,
            'imza_kimin_adina_json' => $imzaKiminAdinaJson,
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
        $filesObj = $_FILES['dosyalar'] ?? ($_FILES['dosya'] ?? ($_FILES['ek_dosyalari'] ?? null));

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
        if (\App\Model\EvrakSevkModel::isRestricted($document)) {
            evrakApiResponse(['success' => false, 'message' => 'UETS ile gelen tebligat evrakı silinemez.'], 403);
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
            SystemLogModel::LEVEL_IMPORTANT
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

        if (!MobilImzaPadesService::isEnabled($firmaId)) {
            evrakApiResponse(['success' => false, 'message' => 'Mobil imza hizmeti etkin değil. Evrak mobilden onaya sunulamaz.'], 422);
        }

        $submission = $EvrakModel->submitForApproval($id, $currentUserId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'mobil_imza');

        (new SystemLogModel())->logAction(
            $currentUserId,
            'Evrak Mobil İmza Onayına Sunma',
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
        evrakApiResponse(['success' => false, 'message' => 'Sanal onay kaldırıldı. Mobil imza ile imzalayınız.'], 410);
    }

    // -------------------------------------------------------------
    // 10.1. MOBİL İMZA (POST mobil-imza-baslat / mobil-imza-durum / mobil-imza-iptal)
    // -------------------------------------------------------------
    if ($method === 'POST' && in_array($action, ['mobil-imza-baslat', 'mobil-imza-durum', 'mobil-imza-iptal'], true)) {
        verifyCsrfToken();
        $mobilImza = new MobilImzaPadesService();
        $log = new SystemLogModel();
        $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        if ($action === 'mobil-imza-baslat') {
            $id = $decryptId($_POST['id'] ?? '');
            if ($id <= 0) {
                evrakApiResponse(['success' => false, 'message' => 'Geçersiz evrak ID.'], 400);
            }
            try {
                $started = $mobilImza->start($id, $currentUserId);
            } catch (\RuntimeException $e) {
                $log->logAction($currentUserId, 'Evrak Mobil İmza Hatası', "Evrak ID: {$id} mobil imza isteği başlatılamadı: " . $e->getMessage(), SystemLogModel::LEVEL_IMPORTANT);
                evrakApiResponse(['success' => false, 'message' => $e->getMessage()], 422);
            }
            $log->logAction($currentUserId, 'Evrak Mobil İmza İsteği', "Evrak ID: {$id} için {$started['msisdn']} hattına mobil imza isteği gönderildi.", SystemLogModel::LEVEL_IMPORTANT);
            evrakApiResponse(['success' => true, 'message' => 'İmza isteği telefonunuza gönderildi.', 'data' => $started]);
        }

        if ($action === 'mobil-imza-iptal') {
            $mobilImza->cancel((string) ($_POST['token'] ?? ''), $currentUserId);
            $log->logAction($currentUserId, 'Evrak Mobil İmza İptali', 'Bekleyen mobil imza isteği mobil uygulamadan iptal edildi.', SystemLogModel::LEVEL_INFO);
            evrakApiResponse(['success' => true, 'message' => 'Mobil imza isteği iptal edildi.']);
        }

        try {
            $result = $mobilImza->poll((string) ($_POST['token'] ?? ''), $currentUserId, $remoteAddr);
        } catch (\RuntimeException $e) {
            $log->logAction($currentUserId, 'Evrak Mobil İmza Hatası', 'Mobil imza tamamlanamadı: ' . $e->getMessage(), SystemLogModel::LEVEL_IMPORTANT);
            evrakApiResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
        if (in_array($result['state'], ['iptal', 'zaman_asimi', 'hata'], true)) {
            $log->logAction($currentUserId, 'Evrak Mobil İmza Sonucu', 'Mobil imza tamamlanmadı: ' . $result['message'], SystemLogModel::LEVEL_INFO);
        }
        if ($result['state'] !== 'imzalandi') {
            evrakApiResponse(['success' => true, 'data' => $result]);
        }

        $approval = $result['approval'];
        $id = (int) $result['evrak_id'];
        $log->logAction(
            $currentUserId,
            'Evrak Mobil İmza Onayı',
            "Evrak ID: {$id} Turkcell mobil imza ile doğrulanmış PAdES imzası olarak onaylandı ({$approval['approved']}/{$approval['total']} imza).",
            SystemLogModel::LEVEL_IMPORTANT
        );

        $onayBildirim = new EvrakOnayBildirimService();
        $doc = $EvrakModel->getById($id);
        $kepGonderilebilir = false;
        if ($approval['completed']) {
            $onayBildirim->onayTamamlandi($doc);
            $kepGonderilebilir = (($doc->gonderim_kanali ?? 'normal') === 'kep')
                && trim((string) ($doc->kep_alicilar ?: $doc->alici_kep)) !== ''
                && Gate::allows('evrak-takip/kep');
        } else {
            $siradaki = $EvrakModel->getNextPendingSigner($id);
            if ($siradaki) {
                $onayBildirim->imzaSirasiGeldi($doc, (int) $siradaki->kullanici_id, (int) $siradaki->sira, (int) $approval['total']);
            }
        }

        evrakApiResponse([
            'success' => true,
            'message' => $approval['completed']
                ? 'Tüm imzalar doğrulandı. Evrak elektronik imzalı olarak kilitlendi.'
                : 'Mobil imzanız doğrulandı. Evrak diğer imzacıların imzasını bekliyor.',
            'data' => [
                'state' => 'imzalandi',
                'completed' => (bool) $approval['completed'],
                'approved' => (int) $approval['approved'],
                'total' => (int) $approval['total'],
                'kep_gonderilebilir' => $kepGonderilebilir,
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'kep-adres-defteri') {
        if (!Gate::allows('evrak-takip/kep')) {
            evrakApiResponse(['success' => false, 'message' => 'KEP adres defterini görüntüleme yetkiniz bulunmuyor.'], 403);
        }
        $adresler = array_map(
            static fn(array $row): string => (string) $row['kep_adresi'],
            (new \App\Model\KepAdresDefteriModel())->listActive($firmaId)
        );
        evrakApiResponse(['success' => true, 'data' => array_values($adresler)]);
    }

    // -------------------------------------------------------------
    // 10.2. KEP MOBİL İMZA İLE GÖNDERİM (POST kep-hazirla / kep-mobil-imza-baslat / kep-mobil-imza-durum)
    // -------------------------------------------------------------
    if ($method === 'POST' && in_array($action, ['kep-hazirla', 'kep-mobil-imza-baslat', 'kep-mobil-imza-durum'], true)) {
        verifyCsrfToken();
        if (!Gate::allows('evrak-takip/kep')) {
            evrakApiResponse(['success' => false, 'message' => 'KEP gönderme yetkiniz bulunmuyor.'], 403);
        }
        $log = new SystemLogModel();
        $kepService = new \App\Service\KepService();

        if ($action === 'kep-hazirla') {
            if (!MobilImzaPadesService::isEnabled($firmaId)) {
                evrakApiResponse(['success' => false, 'message' => 'Mobil imza hizmeti etkin değil.'], 422);
            }
            $evrakId = $decryptId($_POST['id'] ?? '');
            if ($evrakId > 0) {
                $res = $kepService->prepareOutgoingKepMime($firmaId, $evrakId, [
                    'alici_kep' => (string) ($_POST['alicilar'] ?? ''),
                    'paket_turu' => 'Standart',
                    'kep_konu' => (string) ($_POST['konu'] ?? ''),
                    'kep_icerik' => (string) ($_POST['icerik'] ?? ''),
                    'kep_ileti_tipi' => 'standart',
                    'kep_ileti_detayi' => (string) ($_POST['detay'] ?? ''),
                    'kep_paket_turu' => 'Standart',
                ]);
            } else {
                $uploadedFiles = [];
                $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'zip', 'eyp'];
                if (!empty($_FILES['ekler']['name']) && is_array($_FILES['ekler']['name'])) {
                    if (count($_FILES['ekler']['name']) > 20) {
                        evrakApiResponse(['success' => false, 'message' => 'Bir KEP iletisine en fazla 20 dosya eklenebilir.'], 422);
                    }
                    foreach ($_FILES['ekler']['name'] as $index => $originalName) {
                        $error = (int) ($_FILES['ekler']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
                        if ($error === UPLOAD_ERR_NO_FILE) continue;
                        $tmpPath = (string) ($_FILES['ekler']['tmp_name'][$index] ?? '');
                        $size = (int) ($_FILES['ekler']['size'][$index] ?? 0);
                        $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
                        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmpPath) || $size <= 0 || !in_array($extension, $allowedExtensions, true)) {
                            evrakApiResponse(['success' => false, 'message' => 'Geçersiz KEP eki: ' . basename((string) $originalName)], 422);
                        }
                        $uploadedFiles[] = [
                            'path' => $tmpPath,
                            'name' => basename((string) $originalName),
                            'mime' => (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath) ?: 'application/octet-stream',
                            'size' => $size,
                        ];
                    }
                }
                $res = $kepService->prepareStandaloneKepMime($firmaId, [
                    'alici_kep' => trim((string) ($_POST['alicilar'] ?? '')),
                    'konu' => trim((string) ($_POST['konu'] ?? '')),
                    'icerik' => (string) ($_POST['icerik'] ?? ''),
                    'ileti_detayi' => mb_substr(trim((string) ($_POST['detay'] ?? '')), 0, 500),
                    'ileti_tipi' => in_array(($_POST['ileti_tipi'] ?? ''), ['resmi', 'standart'], true) ? $_POST['ileti_tipi'] : 'standart',
                    'paket_turu' => 'Standart',
                ], $uploadedFiles);
            }
            if (($res['status'] ?? '') !== 'success' || empty($res['temp_id'])) {
                evrakApiResponse(['success' => false, 'message' => (string) ($res['message'] ?? 'KEP paketi hazırlanamadı.')], 422);
            }
            evrakApiResponse(['success' => true, 'data' => ['temp_id' => (string) $res['temp_id']]]);
        }

        $mobilImza = new MobilImzaPadesService();

        if ($action === 'kep-mobil-imza-baslat') {
            try {
                $started = $mobilImza->startKep((string) ($_POST['temp_id'] ?? ''), $currentUserId);
            } catch (\RuntimeException $e) {
                $log->logAction($currentUserId, 'KEP Mobil İmza Hatası', 'KEP mobil imza isteği başlatılamadı: ' . $e->getMessage(), SystemLogModel::LEVEL_IMPORTANT);
                evrakApiResponse(['success' => false, 'message' => $e->getMessage()], 422);
            }
            $log->logAction($currentUserId, 'KEP Mobil İmza İsteği', "KEP iletisi için {$started['msisdn']} hattına mobil imza isteği gönderildi.", SystemLogModel::LEVEL_IMPORTANT);
            evrakApiResponse(['success' => true, 'message' => 'İmza isteği telefonunuza gönderildi.', 'data' => $started]);
        }

        try {
            $result = $mobilImza->poll((string) ($_POST['token'] ?? ''), $currentUserId, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        } catch (\RuntimeException $e) {
            $log->logAction($currentUserId, 'KEP Mobil İmza Hatası', 'KEP mobil imzası tamamlanamadı: ' . $e->getMessage(), SystemLogModel::LEVEL_IMPORTANT);
            evrakApiResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
        if ($result['state'] !== 'imzalandi') {
            evrakApiResponse(['success' => true, 'data' => ['state' => $result['state'], 'message' => $result['message'] ?? '']]);
        }
        if (empty($result['kep_signature']) || empty($result['kep_temp_id'])) {
            evrakApiResponse(['success' => false, 'message' => 'Bu mobil imza işlemi KEP iletisine ait değil.'], 422);
        }

        $tempId = (string) $result['kep_temp_id'];
        $draft = $_SESSION['kep_draft_' . $tempId] ?? null;
        if (!$draft) {
            $tmpDir = $projectRoot . '/uploads/evrak-takip/tmp/';
            foreach (['draft_', 'draft_evrak_'] as $prefix) {
                if (is_file($tmpDir . $prefix . $tempId . '.json')) {
                    $draft = json_decode((string) file_get_contents($tmpDir . $prefix . $tempId . '.json'), true);
                    break;
                }
            }
        }
        $log->logAction($currentUserId, 'KEP Mobil İmza Onayı', 'KEP iletisi Turkcell mobil imza ile imzalandı; PTT KEP sevki yapılıyor.', SystemLogModel::LEVEL_IMPORTANT);
        try {
            if (($draft['type'] ?? '') === 'evrak') {
                $sent = $kepService->completeOutgoingKepSend($firmaId, $tempId, (string) $result['kep_signature']);
            } else {
                $sent = $kepService->completeStandaloneKepSend($firmaId, $tempId, (string) $result['kep_signature']);
                if (($sent['status'] ?? '') === 'success' && !empty($draft['recipients'])) {
                    (new \App\Model\KepAdresDefteriModel())->markUsed($firmaId, $draft['recipients']);
                }
            }
        } catch (\RuntimeException $e) {
            evrakApiResponse(['success' => false, 'message' => $e->getMessage()], 422);
        }
        if (($sent['status'] ?? '') !== 'success') {
            evrakApiResponse(['success' => false, 'message' => (string) ($sent['message'] ?? 'PTT KEP sevki başarısız.')], 422);
        }
        evrakApiResponse([
            'success' => true,
            'message' => 'KEP iletisi mobil imzanızla imzalanıp PTT KEP ağına sevk edildi.',
            'data' => ['state' => 'gonderildi', 'kep_id' => (string) ($sent['kep_id'] ?? '')],
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

    // -------------------------------------------------------------
    // 13. İCRA DOSYALARI SORGULA (GET/POST icra-dosyalari)
    // -------------------------------------------------------------
    if ($action === 'icra-dosyalari') {
        $personelId = $decryptId($_GET['personel_id'] ?? $_POST['personel_id'] ?? '');
        if ($personelId <= 0) {
            evrakApiResponse(['success' => false, 'message' => 'Lütfen personel seçiniz.'], 400);
        }

        $icraService = new \App\Service\IcraUstYaziService();
        $dosyalar = $icraService->personelIcralari($personelId);

        evrakApiResponse([
            'success' => true,
            'dosyalar' => $dosyalar,
        ]);
    }

    // -------------------------------------------------------------
    // 14. İCRA ÜST YAZI TASLAĞI OLUŞTUR (GET/POST icra-taslak)
    // -------------------------------------------------------------
    if ($action === 'icra-taslak') {
        $icraId = $decryptId($_GET['icra_id'] ?? $_POST['icra_id'] ?? '');
        if ($icraId <= 0) {
            evrakApiResponse(['success' => false, 'message' => 'Lütfen icra dosyası seçiniz.'], 400);
        }

        $icraService = new \App\Service\IcraUstYaziService();
        $taslak = $icraService->build($icraId);
        $taslak['personel_id'] = !empty($taslak['personel_id']) ? Security::encrypt((int) $taslak['personel_id']) : '';

        evrakApiResponse([
            'success' => true,
            'taslak' => $taslak,
        ]);
    }

    // -------------------------------------------------------------
    // 15. YAPAY ZEKÂ İLE TASLAK OLUŞTUR (POST ai-taslak)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'ai-taslak') {
        verifyCsrfToken();

        $instruction = trim((string) ($_POST['talimat'] ?? ''));
        if ($instruction === '') {
            evrakApiResponse(['success' => false, 'message' => 'Lütfen AI için bir talimat veya istek yazınız.'], 422);
        }

        $file = $_FILES['dosya'] ?? [];
        $aiService = new \App\Service\EvrakAiTaslakService();
        $taslak = $aiService->create($file, $instruction);

        evrakApiResponse([
            'success' => true,
            'taslak' => $taslak,
        ]);
    }

    // -------------------------------------------------------------
    // 16. YAPAY ZEKÂ İLE METİN DÜZENLE (POST ai-duzenle)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'ai-duzenle') {
        verifyCsrfToken();

        $selectedText = trim((string) ($_POST['secili_metin'] ?? ''));
        $instruction = trim((string) ($_POST['talimat'] ?? ''));
        $context = (string) ($_POST['icerik'] ?? $_POST['baglam'] ?? '');

        if ($selectedText === '') {
            evrakApiResponse(['success' => false, 'message' => 'Lütfen düzenlenecek metni belirtiniz.'], 422);
        }
        if ($instruction === '') {
            evrakApiResponse(['success' => false, 'message' => 'Lütfen düzenleme talimatını belirtiniz.'], 422);
        }

        $aiService = new \App\Service\EvrakAiTaslakService();
        $editedHtml = $aiService->reviseSelection($selectedText, $instruction, $context);

        evrakApiResponse([
            'success' => true,
            'html' => $editedHtml,
        ]);
    }

    evrakApiResponse(['success' => false, 'message' => 'Bilinmeyen işlem.'], 404);

} catch (\Throwable $e) {
    error_log('Mobile Evrak API Hatası: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
    evrakApiResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
