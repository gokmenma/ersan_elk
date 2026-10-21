<?php

declare(strict_types=1);

use App\Helper\Helper;
use App\Helper\MedyaServis;
use App\Helper\Security;
use App\Model\KacakKontrolModel;
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

function kacakApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function checkKacakAuth(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
        kacakApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
    }

    $canAccess = Gate::allows('kacak_islemleri') || Gate::isSuperAdmin();
    if (!$canAccess) {
        kacakApiResponse(['success' => false, 'message' => 'Kaçak kontrol modülüne erişim yetkiniz bulunmuyor.'], 403);
    }
}

function verifyCsrfToken(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals((string) $sessionToken, (string) $token)) {
        kacakApiResponse(['success' => false, 'message' => 'Güvenlik doğrulaması (CSRF) geçersiz. Lütfen sayfayı yenileyin.'], 419);
    }
}

checkKacakAuth();

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
if ($firmaId <= 0) {
    kacakApiResponse(['success' => false, 'message' => 'Geçerli bir firma seçilmemiş.'], 400);
}

$currentUserId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$Kacak = new KacakKontrolModel();
$SystemLog = new SystemLogModel();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');

// JSON POST gövdesi desteği
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    if ($rawInput !== false && $rawInput !== '') {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $_POST = array_merge($_POST, $json);
            if (empty($action) && isset($json['action'])) {
                $action = (string) $json['action'];
            }
        }
    }
}

try {
    // -------------------------------------------------------------
    // LİSTELEME
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'list') {
        $tarihDogrula = static function ($deger, string $varsayilan): string {
            $deger = (string) $deger;
            $tarih = DateTime::createFromFormat('Y-m-d', $deger);
            return $tarih && $tarih->format('Y-m-d') === $deger ? $deger : $varsayilan;
        };

        $bitisTarihi = $tarihDogrula($_GET['bitis'] ?? '', date('Y-m-d'));
        $baslangicTarihi = $tarihDogrula($_GET['baslangic'] ?? '', date('Y-m-d', strtotime('-1 month')));
        if ($baslangicTarihi > $bitisTarihi) {
            [$baslangicTarihi, $bitisTarihi] = [$bitisTarihi, $baslangicTarihi];
        }

        $kayitlar = $Kacak->getRecords([
            'tarih_baslangic' => $baslangicTarihi,
            'tarih_bitis' => $bitisTarihi,
        ]);

        $canApprove = Gate::allows('kacak_onay') || Gate::isSuperAdmin();
        $canCancel = Gate::allows('kacak_iptal_ekle') || Gate::isSuperAdmin();
        $canRevertCancel = Gate::allows('kacak_iptal') || Gate::isSuperAdmin();

        $counts = [
            'toplam' => count($kayitlar),
            'beklemede' => 0,
            'onaylandi' => 0,
            'reddedildi' => 0,
            'iptal' => 0,
        ];

        // Ekip istatistiği (Leaderboard)
        $personelOzet = [];
        $formattedRecords = [];

        foreach ($kayitlar as $kayit) {
            $durum = (string) ($kayit['durum'] ?? '');
            $onay = (string) ($kayit['onay_durumu'] ?? 'beklemede');

            if ($durum === 'iptal') {
                $counts['iptal']++;
            } elseif ($onay === 'onaylandi') {
                $counts['onaylandi']++;
            } elseif ($onay === 'reddedildi') {
                $counts['reddedildi']++;
            } else {
                $counts['beklemede']++;
            }

            // Ekip üyelerini derle
            $ekipUyeleri = array_values(array_filter(array_map(static function ($s) {
                return preg_replace('/\s+/', ' ', trim($s));
            }, preg_split('/[,&]/', (string) ($kayit['ekip_adi'] ?? '')))));
            if (!$ekipUyeleri && !empty($kayit['bildiren_adi'])) {
                $ekipUyeleri[] = preg_replace('/\s+/', ' ', trim((string) $kayit['bildiren_adi']));
            }
            if ($ekipUyeleri) {
                sort($ekipUyeleri, SORT_STRING | SORT_FLAG_CASE);
            }
            $ekipAdi = $ekipUyeleri ? implode(' & ', $ekipUyeleri) : 'Ekibi Belirtilmemiş';
            $personelOzet[$ekipAdi] = ($personelOzet[$ekipAdi] ?? 0) + max(1, (int) ($kayit['sayi'] ?? 1));

            $token = Security::encrypt((string) $kayit['id']);
            $formattedRecords[] = [
                'token' => $token,
                'raw_id' => (int) $kayit['id'],
                'tarih' => (string) ($kayit['tarih'] ?? ''),
                'tarih_formatted' => !empty($kayit['tarih']) ? date('d.m.Y', strtotime((string) $kayit['tarih'])) : '-',
                'ilce' => (string) ($kayit['ilce'] ?? '-'),
                'tur' => (string) ($kayit['tur'] ?? 'Kaçak'),
                'tutanak_no' => (string) ($kayit['tutanak_no'] ?? ''),
                'abone_adi' => (string) ($kayit['abone_adi'] ?? ''),
                'sayac_no' => (string) ($kayit['sayac_no'] ?? ''),
                'endeks' => (string) ($kayit['endeks'] ?? ''),
                'ekip_adi' => (string) ($kayit['ekip_adi'] ?? ''),
                'bildiren_adi' => (string) ($kayit['bildiren_adi'] ?? ''),
                'aciklama' => (string) ($kayit['aciklama'] ?? ''),
                'onay_durumu' => $onay,
                'durum' => $durum,
                'foto_sayisi' => (int) ($kayit['foto_sayisi'] ?? 0),
                'red_nedeni' => (string) ($kayit['red_nedeni'] ?? ''),
                'iptal_aciklama' => (string) ($kayit['iptal_aciklama'] ?? ''),
                'hakedisten_dus' => (int) ($kayit['hakedisten_dus'] ?? 0),
            ];
        }

        // Leaderboard sırala
        arsort($personelOzet);
        $topTeams = array_slice($personelOzet, 0, 10, true);
        $maxTeamCount = max([1, ...array_values($topTeams ?: [1])]);
        $leaderboard = [];
        $rank = 1;
        foreach ($topTeams as $teamName => $count) {
            $leaderboard[] = [
                'rank' => $rank++,
                'name' => $teamName,
                'count' => (int) $count,
                'percentage' => (int) round(($count / $maxTeamCount) * 100),
            ];
        }

        kacakApiResponse([
            'success' => true,
            'data' => [
                'records' => $formattedRecords,
                'counts' => $counts,
                'leaderboard' => $leaderboard,
                'permissions' => [
                    'can_approve' => $canApprove,
                    'can_cancel' => $canCancel,
                    'can_revert_cancel' => $canRevertCancel,
                ],
                'date_range' => [
                    'start' => $baslangicTarihi,
                    'end' => $bitisTarihi,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------
    // TEKİL DETAY & FOTOĞRAFLAR
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'detail') {
        $token = (string) ($_GET['token'] ?? '');
        $id = (int) Security::decrypt($token);
        if ($id <= 0) {
            kacakApiResponse(['success' => false, 'message' => 'Geçersiz kayıt belirteci.'], 400);
        }

        $kayit = $Kacak->getRecord($id);
        if (!$kayit) {
            kacakApiResponse(['success' => false, 'message' => 'Kayıt bulunamadı veya silinmiş.'], 404);
        }

        $fotolar = array_map(static function (array $f): array {
            $ext = strtolower(pathinfo((string) ($f['dosya_yolu'] ?? ''), PATHINFO_EXTENSION));
            $encToken = Security::encrypt((string) $f['id']);
            return [
                'token' => $encToken,
                'tur' => (string) ($f['tur'] ?? 'saha'),
                'ad' => (string) ($f['orijinal_ad'] ?? 'Fotoğraf'),
                'pdf' => $ext === 'pdf',
                'video' => in_array($ext, ['mp4', 'mov', 'webm', '3gp'], true),
                'thumbnail_url' => '/api/kacak.php?action=foto&token=' . urlencode($encToken) . '&boyut=kucuk',
                'full_url' => '/api/kacak.php?action=foto&token=' . urlencode($encToken),
            ];
        }, $Kacak->getPhotos($id));

        $canApprove = Gate::allows('kacak_onay') || Gate::isSuperAdmin();
        $canCancel = Gate::allows('kacak_iptal_ekle') || Gate::isSuperAdmin();
        $canRevertCancel = Gate::allows('kacak_iptal') || Gate::isSuperAdmin();

        kacakApiResponse([
            'success' => true,
            'data' => [
                'record' => [
                    'token' => $token,
                    'raw_id' => (int) $kayit['id'],
                    'tarih' => (string) ($kayit['tarih'] ?? ''),
                    'tarih_formatted' => !empty($kayit['tarih']) ? date('d.m.Y', strtotime((string) $kayit['tarih'])) : '-',
                    'ilce' => (string) ($kayit['ilce'] ?? '-'),
                    'tur' => (string) ($kayit['tur'] ?? 'Kaçak'),
                    'tutanak_no' => (string) ($kayit['tutanak_no'] ?? ''),
                    'abone_adi' => (string) ($kayit['abone_adi'] ?? ''),
                    'sayac_no' => (string) ($kayit['sayac_no'] ?? ''),
                    'endeks' => (string) ($kayit['endeks'] ?? ''),
                    'ekip_adi' => (string) ($kayit['ekip_adi'] ?? ''),
                    'bildiren_adi' => (string) ($kayit['bildiren_adi'] ?? ''),
                    'aciklama' => (string) ($kayit['aciklama'] ?? ''),
                    'onay_durumu' => (string) ($kayit['onay_durumu'] ?? 'beklemede'),
                    'durum' => (string) ($kayit['durum'] ?? ''),
                    'foto_sayisi' => (int) ($kayit['foto_sayisi'] ?? 0),
                    'red_nedeni' => (string) ($kayit['red_nedeni'] ?? ''),
                    'iptal_aciklama' => (string) ($kayit['iptal_aciklama'] ?? ''),
                    'hakedisten_dus' => (int) ($kayit['hakedisten_dus'] ?? 0),
                ],
                'photos' => $fotolar,
                'zip_url' => '/api/kacak.php?action=download_zip&token=' . urlencode($token),
                'permissions' => [
                    'can_approve' => $canApprove,
                    'can_cancel' => $canCancel,
                    'can_revert_cancel' => $canRevertCancel,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------
    // FOTOĞRAFLARI GETİR
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'photos') {
        $token = (string) ($_GET['token'] ?? '');
        $id = (int) Security::decrypt($token);
        if ($id <= 0) {
            kacakApiResponse(['success' => false, 'message' => 'Geçersiz kayıt belirteci.'], 400);
        }

        $fotolar = array_map(static function (array $f): array {
            $ext = strtolower(pathinfo((string) ($f['dosya_yolu'] ?? ''), PATHINFO_EXTENSION));
            $encToken = Security::encrypt((string) $f['id']);
            return [
                'token' => $encToken,
                'tur' => (string) ($f['tur'] ?? 'saha'),
                'ad' => (string) ($f['orijinal_ad'] ?? 'Fotoğraf'),
                'pdf' => $ext === 'pdf',
                'video' => in_array($ext, ['mp4', 'mov', 'webm', '3gp'], true),
                'thumbnail_url' => '/api/kacak.php?action=foto&token=' . urlencode($encToken) . '&boyut=kucuk',
                'full_url' => '/api/kacak.php?action=foto&token=' . urlencode($encToken),
            ];
        }, $Kacak->getPhotos($id));

        kacakApiResponse(['success' => true, 'data' => $fotolar]);
    }

    // -------------------------------------------------------------
    // FOTOĞRAF GÖRÜNTÜLE / İNDİR (STREAM)
    // -------------------------------------------------------------
    if ($method === 'GET' && $action === 'foto') {
        $fotoToken = (string) ($_GET['token'] ?? '');
        $fotoId = !empty($fotoToken) ? (int) Security::decrypt($fotoToken) : (int) ($_GET['id'] ?? 0);
        if ($fotoId <= 0) {
            http_response_code(400);
            exit('Geçersiz istek.');
        }

        $foto = $Kacak->getPhoto($fotoId);
        if (!$foto) {
            http_response_code(404);
            exit('Dosya bulunamadı.');
        }

        $kucukIstendi = ($_GET['boyut'] ?? '') === 'kucuk';
        $indirIstendi = isset($_GET['indir']) || isset($_GET['download']);
        $secilenYol = ($kucukIstendi && !empty($foto['kucuk_yol'])) ? $foto['kucuk_yol'] : $foto['dosya_yolu'];

        $dosya = KacakKontrolModel::rootPath() . '/' . ltrim((string) $secilenYol, '/');
        if (!is_file($dosya) && $secilenYol !== $foto['dosya_yolu']) {
            $dosya = KacakKontrolModel::rootPath() . '/' . ltrim((string) $foto['dosya_yolu'], '/');
        }

        if (!is_file($dosya)) {
            http_response_code(404);
            exit(!empty($foto['arsivlendi']) ? 'Bu dosya arşivlenmiş ve sunucudan silinmiştir.' : 'Dosya bulunamadı.');
        }

        $ext = strtolower(pathinfo($dosya, PATHINFO_EXTENSION));
        $isPdf = ($ext === 'pdf');
        $isVideo = (($foto['medya_tipi'] ?? '') === 'video' || in_array($ext, ['mp4', 'mov', 'webm', '3gp'], true));

        header_remove('Content-Type');

        if ($isPdf || $isVideo) {
            MedyaServis::gonder($dosya, 86400);
            exit;
        }

        $orijinalAd = $foto['orijinal_ad'] ?? basename($dosya);
        $orijinalBase = pathinfo((string) $orijinalAd, PATHINFO_FILENAME);
        $indirilecekAd = preg_replace('/[^\p{L}\p{N}_.-]+/u', '_', $orijinalBase) . '.jpeg';
        $dispositionType = $indirIstendi ? 'attachment' : 'inline';

        $jpegBinary = KacakKontrolModel::getAsJpegBinary($dosya);
        if ($jpegBinary !== null) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: image/jpeg');
            header('Content-Disposition: ' . $dispositionType . '; filename="' . $indirilecekAd . '"; filename*=UTF-8\'\'' . rawurlencode($indirilecekAd));
            header('Content-Length: ' . strlen($jpegBinary));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=86400');
            header('Accept-Ranges: bytes');
            echo $jpegBinary;
            exit;
        }

        MedyaServis::gonder($dosya, 86400);
        exit;
    }

    // -------------------------------------------------------------
    // ZIP İNDİR (DOWNLOAD ZIP)
    // -------------------------------------------------------------
    if ($method === 'GET' && ($action === 'download_zip' || $action === 'download-zip')) {
        $token = (string) ($_GET['token'] ?? '');
        $kacakId = !empty($token) ? (int) Security::decrypt($token) : (int) ($_GET['id'] ?? 0);
        if ($kacakId <= 0) {
            http_response_code(400);
            exit('Geçersiz kayıt belirteci.');
        }

        $kayit = $Kacak->getRecord($kacakId);
        if (!$kayit) {
            http_response_code(404);
            exit('Kayıt bulunamadı.');
        }

        $fotolar = $Kacak->getPhotos($kacakId);
        if (empty($fotolar)) {
            http_response_code(404);
            exit('Bu kayda ait fotoğraf bulunamadı.');
        }

        $root = KacakKontrolModel::rootPath();
        $tutanakNoClean = !empty($kayit['tutanak_no']) ? trim((string) $kayit['tutanak_no']) : '';
        $aboneAdiClean = !empty($kayit['abone_adi']) ? Helper::trUpper(trim((string) $kayit['abone_adi'])) : '';
        $turClean = !empty($kayit['tur']) ? Helper::trUpper(trim((string) $kayit['tur'])) : 'KACAK';

        $folderParts = [];
        if ($tutanakNoClean !== '') {
            $folderParts[] = $tutanakNoClean;
        }
        if ($aboneAdiClean !== '') {
            $folderParts[] = $aboneAdiClean;
        }

        $folderBase = !empty($folderParts) ? implode(' - ', $folderParts) : ('kayit_' . $kacakId);
        $rawFolderName = sprintf('%s (%s)', $folderBase, $turClean);
        $folderName = preg_replace('/[\/\\\\:\*\?"<>\|]/u', '_', trim($rawFolderName));
        $zipAdi = $folderName . '.zip';
        $zipYolu = sys_get_temp_dir() . '/' . uniqid('kacak_rec_zip_', true) . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipYolu, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            exit('Arşiv dosyası oluşturulamadı.');
        }

        $eklenenSayisi = 0;
        $tutanakSeq = 1;
        $sahaSeq = 1;
        $iptalSeq = 1;
        $videoSeq = 1;

        foreach ($fotolar as $foto) {
            $kaynak = $root . '/' . ltrim((string) $foto['dosya_yolu'], '/');
            if (!is_file($kaynak)) {
                continue;
            }

            $origExt = strtolower(pathinfo((string) $foto['dosya_yolu'], PATHINFO_EXTENSION));
            $fotoTur = strtolower((string) ($foto['tur'] ?? 'saha'));
            $medyaTipi = strtolower((string) ($foto['medya_tipi'] ?? 'foto'));

            if ($fotoTur === 'tutanak') {
                $prefix = 'tutanak';
                $seq = $tutanakSeq++;
            } elseif ($fotoTur === 'iptal') {
                $prefix = 'iptal';
                $seq = $iptalSeq++;
            } else {
                if ($medyaTipi === 'video') {
                    $prefix = 'video';
                    $seq = $videoSeq++;
                } else {
                    $prefix = 'saha';
                    $seq = $sahaSeq++;
                }
            }

            $isPdf = ($origExt === 'pdf');
            $isVideo = ($medyaTipi === 'video' || in_array($origExt, ['mp4', 'mov', 'webm', '3gp'], true));

            if ($isPdf) {
                $ext = 'pdf';
                $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNoClean ?: ('kayit_' . $kacakId), $seq, $ext);
                $zip->addFile($kaynak, $folderName . '/' . $dosyaAdi, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
            } elseif ($isVideo) {
                $ext = $origExt ?: 'mp4';
                $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNoClean ?: ('kayit_' . $kacakId), $seq, $ext);
                $zip->addFile($kaynak, $folderName . '/' . $dosyaAdi, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
            } else {
                $ext = 'jpeg';
                $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNoClean ?: ('kayit_' . $kacakId), $seq, $ext);
                $jpegData = KacakKontrolModel::getAsJpegBinary($kaynak);
                if ($jpegData !== null) {
                    $zip->addFromString($folderName . '/' . $dosyaAdi, $jpegData, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
                } else {
                    $zip->addFile($kaynak, $folderName . '/' . $dosyaAdi, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
                }
            }
            $eklenenSayisi++;
        }

        $zip->close();

        if ($eklenenSayisi === 0 || !is_file($zipYolu)) {
            @unlink($zipYolu);
            http_response_code(404);
            exit('İndirilebilir dosya bulunamadı.');
        }

        $SystemLog->logAction(
            $currentUserId,
            'Kaçak Kaydı Fotoğrafları İndirildi (ZIP - Mobile SPA)',
            "Kayıt ID: $kacakId, Tutanak No: " . ($kayit['tutanak_no'] ?? '-') . ", Eklenen Dosya: $eklenenSayisi",
            SystemLogModel::LEVEL_INFO
        );

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header_remove('Content-Type');
        $asciiZipAdi = Helper::toAscii($zipAdi);
        $encodedZipAdi = rawurlencode($zipAdi);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $asciiZipAdi . '"; filename*=UTF-8\'\'' . $encodedZipAdi);
        header('Content-Length: ' . (string) filesize($zipYolu));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($zipYolu);
        @unlink($zipYolu);
        exit;
    }

    // -------------------------------------------------------------
    // ONAYLA (APPROVE)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'approve') {
        verifyCsrfToken();
        if (!Gate::allows('kacak_onay') && !Gate::isSuperAdmin()) {
            kacakApiResponse(['success' => false, 'message' => 'Kaçak onaylama yetkiniz bulunmuyor.'], 403);
        }

        $token = (string) ($_POST['token'] ?? '');
        $id = (int) Security::decrypt($token);
        if ($id <= 0) {
            kacakApiResponse(['success' => false, 'message' => 'Geçersiz kayıt belirteci.'], 400);
        }

        $kayit = $Kacak->getRecord($id);
        if (!$kayit) {
            kacakApiResponse(['success' => false, 'message' => 'Kayıt bulunamadı.'], 404);
        }

        if (!$Kacak->approve($id, $currentUserId)) {
            kacakApiResponse(['success' => false, 'message' => 'Onaylama işlemi başarısız.'], 500);
        }

        // Bildirim gönder
        if (!empty($kayit['bildiren_personel_id'])) {
            try {
                $Push = new PushNotificationService();
                $Push->sendToPersonel((int) $kayit['bildiren_personel_id'], [
                    'title' => 'Kaçak Bildiriminiz Onaylandı',
                    'body' => date('d.m.Y', strtotime((string) $kayit['tarih'])) . ' tarihli ' . ($kayit['tur'] ?? 'Kaçak') . ' tutanağınız onaylandı.',
                    'url' => '?page=kacak',
                ]);
            } catch (\Throwable $e) {
                error_log('Kaçak bildirimi gönderilemedi: ' . $e->getMessage());
            }
        }

        $SystemLog->logAction(
            $currentUserId,
            'Kaçak Bildirimi Onaylandı (Mobile SPA)',
            "ID: $id, Tutanak: " . ($kayit['tutanak_no'] ?? '-'),
            SystemLogModel::LEVEL_IMPORTANT
        );

        kacakApiResponse(['success' => true, 'message' => 'Kaçak kontrol bildirimi onaylandı.']);
    }

    // -------------------------------------------------------------
    // REDDET (REJECT)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'reject') {
        verifyCsrfToken();
        if (!Gate::allows('kacak_onay') && !Gate::isSuperAdmin()) {
            kacakApiResponse(['success' => false, 'message' => 'Kaçak reddetme yetkiniz bulunmuyor.'], 403);
        }

        $token = (string) ($_POST['token'] ?? '');
        $id = (int) Security::decrypt($token);
        $neden = trim((string) ($_POST['red_nedeni'] ?? ''));

        if ($id <= 0) {
            kacakApiResponse(['success' => false, 'message' => 'Geçersiz kayıt belirteci.'], 400);
        }
        if ($neden === '') {
            kacakApiResponse(['success' => false, 'message' => 'Red nedeni yazılması zorunludur.'], 422);
        }

        $kayit = $Kacak->getRecord($id);
        if (!$kayit) {
            kacakApiResponse(['success' => false, 'message' => 'Kayıt bulunamadı.'], 404);
        }

        if (!$Kacak->reject($id, $currentUserId, $neden)) {
            kacakApiResponse(['success' => false, 'message' => 'Reddetme işlemi başarısız.'], 500);
        }

        // Bildirim gönder
        if (!empty($kayit['bildiren_personel_id'])) {
            try {
                $Push = new PushNotificationService();
                $Push->sendToPersonel((int) $kayit['bildiren_personel_id'], [
                    'title' => 'Kaçak Bildiriminiz Reddedildi',
                    'body' => date('d.m.Y', strtotime((string) $kayit['tarih'])) . ' tarihli ' . ($kayit['tur'] ?? 'Kaçak') . ' tutanağınız reddedildi. Neden: ' . $neden,
                    'url' => '?page=kacak',
                ]);
            } catch (\Throwable $e) {
                error_log('Kaçak bildirimi gönderilemedi: ' . $e->getMessage());
            }
        }

        $SystemLog->logAction(
            $currentUserId,
            'Kaçak Bildirimi Reddedildi (Mobile SPA)',
            "ID: $id, Neden: $neden",
            SystemLogModel::LEVEL_IMPORTANT
        );

        kacakApiResponse(['success' => true, 'message' => 'Bildirim reddedildi.']);
    }

    // -------------------------------------------------------------
    // İPTAL ET (CANCEL)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'cancel') {
        verifyCsrfToken();
        if (!Gate::allows('kacak_iptal_ekle') && !Gate::isSuperAdmin()) {
            kacakApiResponse(['success' => false, 'message' => 'Kaçak tutanağı iptal etme yetkiniz bulunmuyor.'], 403);
        }

        $token = (string) ($_POST['token'] ?? '');
        $id = (int) Security::decrypt($token);
        $aciklama = trim((string) ($_POST['iptal_aciklama'] ?? ''));
        $hakedistenDus = !empty($_POST['hakedisten_dus']);

        if ($id <= 0) {
            kacakApiResponse(['success' => false, 'message' => 'Geçersiz kayıt belirteci.'], 400);
        }
        if ($aciklama === '') {
            kacakApiResponse(['success' => false, 'message' => 'İptal açıklaması yazılması zorunludur.'], 422);
        }

        $kayit = $Kacak->getRecord($id);
        if (!$kayit) {
            kacakApiResponse(['success' => false, 'message' => 'Kayıt bulunamadı.'], 404);
        }
        if (($kayit['durum'] ?? '') === 'iptal') {
            kacakApiResponse(['success' => false, 'message' => 'Bu tutanak zaten iptal edilmiş.'], 409);
        }

        if (!$Kacak->cancel($id, $currentUserId, $aciklama, $hakedistenDus)) {
            kacakApiResponse(['success' => false, 'message' => 'İptal işlemi başarısız.'], 500);
        }

        $SystemLog->logAction(
            $currentUserId,
            'Kaçak Tutanağı İptal Edildi (Mobile SPA)',
            "ID: $id, Açıklama: $aciklama, Hakedişten Düş: " . ($hakedistenDus ? 'Evet' : 'Hayır'),
            SystemLogModel::LEVEL_IMPORTANT
        );

        kacakApiResponse(['success' => true, 'message' => 'Kaçak tutanağı iptal edildi.']);
    }

    // -------------------------------------------------------------
    // İPTALİ GERİ AL (REVERT-CANCEL)
    // -------------------------------------------------------------
    if ($method === 'POST' && $action === 'revert_cancel') {
        verifyCsrfToken();
        if (!Gate::allows('kacak_iptal') && !Gate::isSuperAdmin()) {
            kacakApiResponse(['success' => false, 'message' => 'İptali geri alma yetkiniz bulunmuyor.'], 403);
        }

        $token = (string) ($_POST['token'] ?? '');
        $id = (int) Security::decrypt($token);
        if ($id <= 0) {
            kacakApiResponse(['success' => false, 'message' => 'Geçersiz kayıt belirteci.'], 400);
        }

        $kayit = $Kacak->getRecord($id);
        if (!$kayit) {
            kacakApiResponse(['success' => false, 'message' => 'Kayıt bulunamadı.'], 404);
        }

        if (!$Kacak->revertCancel($id)) {
            kacakApiResponse(['success' => false, 'message' => 'İptal geri alınamadı.'], 500);
        }

        $SystemLog->logAction(
            $currentUserId,
            'Kaçak İptali Geri Alındı (Mobile SPA)',
            "ID: $id",
            SystemLogModel::LEVEL_IMPORTANT
        );

        kacakApiResponse(['success' => true, 'message' => 'İptal geri alındı, kayıt yeniden aktif duruma getirildi.']);
    }

    kacakApiResponse(['success' => false, 'message' => 'Geçersiz işlem.'], 400);

} catch (\Throwable $e) {
    error_log('Kaçak API Hatası: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    kacakApiResponse([
        'success' => false,
        'message' => 'Bir sunucu hatası meydana geldi.',
    ], 500);
}
