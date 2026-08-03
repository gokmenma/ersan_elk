<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Date;
use App\Model\KacakKontrolModel;
use App\Model\PersonelModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Service\KacakTutanakAnalizService;

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$userPersonelId = (int) ($_SESSION['personel_id'] ?? 0);

if ($userId <= 0 || empty($_SESSION['firma_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum süresi doldu.']);
    exit;
}

if (!Gate::allows('kacak_islemleri') && !Gate::isSuperAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
    exit;
}

$Kacak = new KacakKontrolModel();
$Log = new SystemLogModel();

function kacakYanit(bool $ok, string $mesaj = '', array $ek = []): void
{
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $mesaj], $ek), JSON_UNESCAPED_UNICODE);
    exit;
}

function kacakYetkiKontrol(string $izin): void
{
    if (!Gate::allows($izin) && !Gate::isSuperAdmin()) {
        kacakYanit(false, 'Bu işlem için yetkiniz bulunmuyor.');
    }
}

function kacakTarih($deger, string $varsayilan = ''): string
{
    $deger = trim((string) $deger);
    if ($deger === '') {
        return $varsayilan !== '' ? $varsayilan : date('Y-m-d');
    }
    $donusen = Date::convertExcelDate($deger, 'Y-m-d');
    if (!empty($donusen)) {
        return $donusen;
    }
    $ts = strtotime($deger);
    return $ts !== false ? date('Y-m-d', $ts) : ($varsayilan !== '' ? $varsayilan : date('Y-m-d'));
}

try {
    switch ($action) {

        // =====================================================
        // LİSTELEME
        // =====================================================
        case 'list':
            $filters = [
                'tarih_baslangic' => kacakTarih($_GET['start_date'] ?? '', date('Y-m-01')),
                'tarih_bitis' => kacakTarih($_GET['end_date'] ?? '', date('Y-m-d')),
                'ilce' => $_GET['ilce'] ?? '',
                'tur' => $_GET['tur'] ?? '',
                'durum' => $_GET['durum'] ?? '',
                'onay_durumu' => $_GET['onay_durumu'] ?? '',
                'kaynak' => $_GET['kaynak'] ?? '',
                'personel_id' => $_GET['personel_id'] ?? '',
                'arama' => $_GET['arama'] ?? '',
            ];

            // Personel tarafında (süper admin veya onay yetkilisi değilse) sadece kendi ekibinin bildirimlerini görebilir
            if ($userPersonelId > 0 && !Gate::isSuperAdmin() && !Gate::allows('kacak_onay')) {
                $filters['personel_id'] = $userPersonelId;
            }

            $kayitlar = $Kacak->getRecords($filters);
            foreach ($kayitlar as &$k) {
                $k['tarih_formatted'] = Date::dmY($k['tarih']);
                $k['foto_sayisi'] = (int) $k['foto_sayisi'];
            }
            unset($k);

            kacakYanit(true, '', [
                'data' => $kayitlar,
                'ozet' => $Kacak->getOzet($filters['tarih_baslangic'], $filters['tarih_bitis'], (int) ($filters['personel_id'] ?? 0)),
            ]);
            break;

        case 'get-record':
            $kayit = $Kacak->getRecord((int) ($_GET['id'] ?? 0));
            if (!$kayit) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if ($userPersonelId > 0 && !Gate::isSuperAdmin() && !Gate::allows('kacak_onay')) {
                $isEkip = ($kayit['bildiren_personel_id'] == $userPersonelId) || in_array($userPersonelId, $kayit['personel_ids_array'] ?? [], true);
                if (!$isEkip) {
                    kacakYanit(false, 'Bu kaydı görüntüleme yetkiniz bulunmuyor.');
                }
            }
            $kayit['tarih_formatted'] = Date::dmY($kayit['tarih']);
            kacakYanit(true, '', ['data' => $kayit]);
            break;

        case 'pending-count':
            kacakYanit(true, '', ['count' => $Kacak->getPendingCount()]);
            break;

        // =====================================================
        // KAYIT / GÜNCELLEME / SİLME
        // =====================================================
        case 'save':
            kacakYetkiKontrol('kacak_duzenle');

            $id = (int) ($_POST['id'] ?? 0);
            $tarih = kacakTarih($_POST['tarih'] ?? '');
            $personelIds = $_POST['kacak_personel_ids'] ?? [];

            if (empty($personelIds)) {
                kacakYanit(false, 'En az bir personel seçmelisiniz.');
            }

            $ilceArr = (array) ($_POST['ilce'] ?? []);
            $turArr = (array) ($_POST['tur'] ?? []);
            $tutanakNoArr = (array) ($_POST['tutanak_no'] ?? []);
            $aboneAdiArr = (array) ($_POST['abone_adi'] ?? []);
            $sayacNoArr = (array) ($_POST['sayac_no'] ?? []);
            $endeksArr = (array) ($_POST['endeks'] ?? []);
            $sayiArr = (array) ($_POST['sayi'] ?? []);
            $aciklamaArr = (array) ($_POST['aciklama'] ?? []);

            if ($id > 0) {
                $ok = $Kacak->updateRecord($id, [
                    'tarih' => $tarih,
                    'personel_ids' => $personelIds,
                    'ilce' => $ilceArr[0] ?? '',
                    'tur' => $turArr[0] ?? 'Kaçak',
                    'tutanak_no' => $tutanakNoArr[0] ?? null,
                    'abone_adi' => $aboneAdiArr[0] ?? null,
                    'sayac_no' => $sayacNoArr[0] ?? null,
                    'endeks' => $endeksArr[0] ?? null,
                    'sayi' => $sayiArr[0] ?? 1,
                    'aciklama' => $aciklamaArr[0] ?? null,
                ]);

                if (!$ok) {
                    kacakYanit(false, 'Kayıt güncellenemedi.');
                }

                kacakFotoYukle($Kacak, $id, $userId);

                $Log->logAction($userId, 'Kaçak Kaydı Güncellendi', "ID: $id, Tarih: $tarih", SystemLogModel::LEVEL_IMPORTANT);
                kacakYanit(true, 'Kayıt güncellendi.', ['id' => $id]);
            }

            if (empty($ilceArr)) {
                kacakYanit(false, 'En az bir giriş satırı eklemelisiniz.');
            }

            $eklenen = [];
            $db = $Kacak->getDb();
            $db->beginTransaction();
            try {
                foreach ($ilceArr as $i => $ilce) {
                    if (trim((string) $ilce) === '') {
                        continue;
                    }
                    $yeniId = $Kacak->createRecord([
                        'tarih' => $tarih,
                        'personel_ids' => $personelIds,
                        'ilce' => $ilce,
                        'tur' => $turArr[$i] ?? 'Kaçak',
                        'tutanak_no' => $tutanakNoArr[$i] ?? null,
                        'abone_adi' => $aboneAdiArr[$i] ?? null,
                        'sayac_no' => $sayacNoArr[$i] ?? null,
                        'endeks' => $endeksArr[$i] ?? null,
                        'sayi' => $sayiArr[$i] ?? 1,
                        'aciklama' => $aciklamaArr[$i] ?? null,
                        'kaynak' => 'masaustu',
                        'onay_durumu' => 'onaylandi',
                        'onaylayan_id' => $userId,
                    ]);
                    $eklenen[] = $yeniId;
                }
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            if (empty($eklenen)) {
                kacakYanit(false, 'Geçerli satır bulunamadı, kayıt eklenmedi.');
            }

            kacakFotoYukle($Kacak, $eklenen[0], $userId);

            $Log->logAction($userId, 'Kaçak Kaydı Eklendi', count($eklenen) . ' adet kaçak kaydı eklendi. Tarih: ' . $tarih, SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, count($eklenen) . ' kayıt eklendi.', ['ids' => $eklenen]);
            break;

        case 'delete':
            kacakYetkiKontrol('kacak_duzenle');
            $id = (int) ($_POST['id'] ?? 0);
            if (!$Kacak->getRecord($id)) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if (!$Kacak->softDeleteRecord($id)) {
                kacakYanit(false, 'Kayıt silinemedi.');
            }
            $Log->logAction($userId, 'Kaçak Kaydı Silindi', "ID: $id", SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, 'Kayıt silindi.');
            break;

        // =====================================================
        // ONAY / RED
        // =====================================================
        case 'approve':
            kacakYetkiKontrol('kacak_onay');
            $id = (int) ($_POST['id'] ?? 0);
            $kayit = $Kacak->getRecord($id);
            if (!$kayit) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if (!$Kacak->approve($id, $userId)) {
                kacakYanit(false, 'Onaylama işlemi başarısız.');
            }
            kacakBildirimGonder($kayit, 'onaylandi');
            $Log->logAction($userId, 'Kaçak Bildirimi Onaylandı', "ID: $id, Tutanak: " . ($kayit['tutanak_no'] ?? '-'), SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, 'Bildirim onaylandı.');
            break;

        case 'reject':
            kacakYetkiKontrol('kacak_onay');
            $id = (int) ($_POST['id'] ?? 0);
            $neden = trim((string) ($_POST['red_nedeni'] ?? ''));
            if ($neden === '') {
                kacakYanit(false, 'Red nedeni zorunludur.');
            }
            $kayit = $Kacak->getRecord($id);
            if (!$kayit) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if (!$Kacak->reject($id, $userId, $neden)) {
                kacakYanit(false, 'Reddetme işlemi başarısız.');
            }
            kacakBildirimGonder($kayit, 'reddedildi', $neden);
            $Log->logAction($userId, 'Kaçak Bildirimi Reddedildi', "ID: $id, Neden: $neden", SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, 'Bildirim reddedildi.');
            break;

        // =====================================================
        // İPTAL
        // =====================================================
        case 'cancel':
            kacakYetkiKontrol('kacak_iptal');
            $id = (int) ($_POST['id'] ?? 0);
            $aciklama = trim((string) ($_POST['iptal_aciklama'] ?? ''));
            $hakedistenDus = ($_POST['hakedisten_dus'] ?? '0') === '1';

            if ($aciklama === '') {
                kacakYanit(false, 'İptal açıklaması zorunludur.');
            }
            $kayit = $Kacak->getRecord($id);
            if (!$kayit) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if ($kayit['durum'] === 'iptal') {
                kacakYanit(false, 'Bu tutanak zaten iptal edilmiş.');
            }

            if (!$Kacak->cancel($id, $userId, $aciklama, $hakedistenDus)) {
                kacakYanit(false, 'İptal işlemi başarısız.');
            }

            if (!empty($_FILES['iptal_foto']['name'])) {
                try {
                    $yol = $Kacak->storeUploadedFile($_FILES['iptal_foto'], $id, 'iptal');
                    $Kacak->addPhoto($id, 'iptal', $yol, $_FILES['iptal_foto']['name'], null, $userId);
                } catch (\Throwable $e) {
                    error_log('Kaçak iptal fotoğrafı yüklenemedi: ' . $e->getMessage());
                }
            }

            $Log->logAction(
                $userId,
                'Kaçak Tutanağı İptal Edildi',
                "ID: $id, Tutanak: " . ($kayit['tutanak_no'] ?? '-') . ', Hakedişten düş: ' . ($hakedistenDus ? 'Evet' : 'Hayır'),
                SystemLogModel::LEVEL_IMPORTANT
            );
            kacakYanit(true, 'Tutanak iptal edildi.');
            break;

        case 'revert-cancel':
            kacakYetkiKontrol('kacak_iptal');
            $id = (int) ($_POST['id'] ?? 0);
            if (!$Kacak->getRecord($id)) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if (!$Kacak->revertCancel($id)) {
                kacakYanit(false, 'İptal geri alınamadı.');
            }
            $Log->logAction($userId, 'Kaçak İptali Geri Alındı', "ID: $id", SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, 'İptal geri alındı, kayıt yeniden aktif.');
            break;

        // =====================================================
        // FOTOĞRAF
        // =====================================================
        case 'get-photos':
            $id = (int) ($_GET['id'] ?? 0);
            if (!$Kacak->getRecord($id)) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            kacakYanit(true, '', ['data' => $Kacak->getPhotos($id)]);
            break;

        case 'upload-photo':
            kacakYetkiKontrol('kacak_duzenle');
            $id = (int) ($_POST['id'] ?? 0);
            if (!$Kacak->getRecord($id)) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            $eklenen = kacakFotoYukle($Kacak, $id, $userId);
            if ($eklenen === 0) {
                kacakYanit(false, 'Yüklenecek dosya bulunamadı veya limit doldu.');
            }
            kacakYanit(true, "$eklenen dosya yüklendi.");
            break;

        case 'delete-photo':
            kacakYetkiKontrol('kacak_arsiv');
            $fotoId = (int) ($_POST['foto_id'] ?? 0);
            if (!$Kacak->deletePhoto($fotoId)) {
                kacakYanit(false, 'Fotoğraf silinemedi.');
            }
            $Log->logAction($userId, 'Kaçak Fotoğrafı Silindi', "Foto ID: $fotoId", SystemLogModel::LEVEL_IMPORTANT);
            kacakYanit(true, 'Fotoğraf silindi.');
            break;

        case 'archive-preview':
            kacakYetkiKontrol('kacak_arsiv');
            $bas = kacakTarih($_GET['start_date'] ?? '', date('Y-m-01'));
            $bit = kacakTarih($_GET['end_date'] ?? '', date('Y-m-d'));
            $fotolar = $Kacak->getPhotosForArchive($bas, $bit);
            kacakYanit(true, '', ['count' => count($fotolar)]);
            break;

        case 'archive-download':
            kacakYetkiKontrol('kacak_arsiv');
            kacakArsivle($Kacak, $Log, $userId);
            break;

        // =====================================================
        // YAPAY ZEKA ANALİZİ
        // =====================================================
        case 'analyze':
            kacakYetkiKontrol('kacak_duzenle');

            if (empty($_FILES['tutanak_file']['name'])) {
                kacakYanit(false, 'Lütfen analiz edilecek tutanak dosyasını seçin.');
            }

            $varsayilanTarih = kacakTarih($_POST['tarih'] ?? '');

            $Personel = new PersonelModel();
            $dropdown = [];
            foreach ($Personel->all(false, 'puantaj', $varsayilanTarih) as $p) {
                $dropdown[] = ['id' => (int) $p->id, 'name' => $p->adi_soyadi];
            }

            $Analiz = new KacakTutanakAnalizService();
            $satirlar = $Analiz->analyze(
                $_FILES['tutanak_file'],
                $varsayilanTarih,
                $Analiz->getPersonelAdaylari($dropdown)
            );

            kacakYanit(true, 'Tutanak analiz edildi. Lütfen bilgileri kontrol edip kaydedin.', ['data' => $satirlar]);
            break;

        // =====================================================
        // RAPORLAR
        // =====================================================
        case 'gunluk-rapor':
            $tarih = kacakTarih($_GET['tarih'] ?? '');
            kacakYanit(true, '', [
                'tarih' => $tarih,
                'metin' => $Kacak->getGunlukRaporMetni($tarih),
                'veri' => $Kacak->getGunlukRapor($tarih),
            ]);
            break;

        case 'haftalik-rapor':
            $bas = kacakTarih($_GET['start_date'] ?? '', date('Y-m-d', strtotime('monday this week')));
            $bit = kacakTarih($_GET['end_date'] ?? '', date('Y-m-d'));
            kacakYanit(true, '', [
                'baslangic' => $bas,
                'bitis' => $bit,
                'data' => $Kacak->getBolgeBazliOzet($bas, $bit),
            ]);
            break;

        case 'teslim-alma-listesi':
            $bas = kacakTarih($_GET['start_date'] ?? '', date('Y-m-d', strtotime('monday this week')));
            $bit = kacakTarih($_GET['end_date'] ?? '', date('Y-m-d'));
            $liste = $Kacak->getTeslimAlmaListesi($bas, $bit);
            foreach ($liste as &$satir) {
                $satir['tarih_formatted'] = Date::dmY($satir['tarih']);
            }
            unset($satir);
            kacakYanit(true, '', ['baslangic' => $bas, 'bitis' => $bit, 'data' => $liste]);
            break;

        default:
            kacakYanit(false, 'Geçersiz istek.');
    }
} catch (\Throwable $e) {
    error_log('Kaçak API hatası (' . $action . '): ' . $e->getMessage());
    kacakYanit(false, $e->getMessage() !== '' ? $e->getMessage() : 'İşlem sırasında bir hata oluştu.');
}

/**
 * Modal üzerinden gelen tutanak ve saha fotoğraflarını kaydeder.
 */
function kacakFotoYukle(KacakKontrolModel $Kacak, int $kacakId, int $userId): int
{
    $eklenen = 0;

    if (!empty($_FILES['tutanak_foto']['name'])) {
        try {
            $yol = $Kacak->storeUploadedFile($_FILES['tutanak_foto'], $kacakId, 'tutanak');
            $Kacak->addPhoto($kacakId, 'tutanak', $yol, $_FILES['tutanak_foto']['name'], null, $userId);
            $eklenen++;
        } catch (\Throwable $e) {
            error_log('Kaçak tutanak fotoğrafı yüklenemedi: ' . $e->getMessage());
        }
    }

    if (!empty($_FILES['saha_fotolari']) && is_array($_FILES['saha_fotolari']['name'])) {
        $mevcut = $Kacak->countPhotos($kacakId, 'saha');
        foreach ($_FILES['saha_fotolari']['name'] as $i => $ad) {
            if ($mevcut >= KacakKontrolModel::MAX_SAHA_FOTO) {
                break;
            }
            if (empty($ad) || $_FILES['saha_fotolari']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            try {
                $dosya = [
                    'name' => $ad,
                    'tmp_name' => $_FILES['saha_fotolari']['tmp_name'][$i],
                    'error' => $_FILES['saha_fotolari']['error'][$i],
                    'size' => $_FILES['saha_fotolari']['size'][$i],
                ];
                $yol = $Kacak->storeUploadedFile($dosya, $kacakId, 'saha');
                $Kacak->addPhoto($kacakId, 'saha', $yol, $ad, null, $userId);
                $mevcut++;
                $eklenen++;
            } catch (\Throwable $e) {
                error_log('Kaçak saha fotoğrafı yüklenemedi: ' . $e->getMessage());
            }
        }
    }

    return $eklenen;
}

/**
 * Personel PWA bildirimi onaylandığında/reddedildiğinde bildiren personele haber verir.
 */
function kacakBildirimGonder(array $kayit, string $durum, string $neden = ''): void
{
    if (empty($kayit['bildiren_personel_id'])) {
        return;
    }

    try {
        $Push = new \App\Service\PushNotificationService();
        $baslik = $durum === 'onaylandi' ? 'Kaçak Bildiriminiz Onaylandı' : 'Kaçak Bildiriminiz Reddedildi';
        $mesaj = date('d.m.Y', strtotime($kayit['tarih'])) . ' tarihli ' . ($kayit['tur'] ?? 'Kaçak') . ' tutanağınız '
            . ($durum === 'onaylandi' ? 'onaylandı.' : 'reddedildi. Neden: ' . $neden);

        $Push->sendToPersonel((int) $kayit['bildiren_personel_id'], [
            'title' => $baslik,
            'body' => $mesaj,
            'url' => '?page=kacak',
        ]);
    } catch (\Throwable $e) {
        error_log('Kaçak bildirimi gönderilemedi: ' . $e->getMessage());
    }
}

/**
 * Tarih aralığındaki fotoğrafları ilçe bazlı klasörlenmiş ZIP olarak indirir ve sunucudan siler.
 */
function kacakArsivle(KacakKontrolModel $Kacak, SystemLogModel $Log, int $userId): void
{
    $bas = kacakTarih($_POST['start_date'] ?? $_GET['start_date'] ?? '', date('Y-m-01'));
    $bit = kacakTarih($_POST['end_date'] ?? $_GET['end_date'] ?? '', date('Y-m-d'));

    $fotolar = $Kacak->getPhotosForArchive($bas, $bit);
    if (empty($fotolar)) {
        kacakYanit(false, 'Seçilen tarih aralığında arşivlenecek fotoğraf bulunamadı.');
    }

    $root = KacakKontrolModel::rootPath();
    $zipAdi = 'kacak_fotograflari_' . $bas . '_' . $bit . '.zip';
    $zipYolu = sys_get_temp_dir() . '/' . uniqid('kacak_arsiv_', true) . '.zip';

    $zip = new \ZipArchive();
    if ($zip->open($zipYolu, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        kacakYanit(false, 'Arşiv dosyası oluşturulamadı.');
    }

    $eklenenIds = [];
    foreach ($fotolar as $foto) {
        $kaynak = $root . '/' . ltrim($foto['dosya_yolu'], '/');
        if (!is_file($kaynak)) {
            $eklenenIds[] = (int) $foto['id'];
            continue;
        }

        $ilce = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $foto['ilce'] ?: 'Belirtilmemis');
        $turKlasor = ucfirst($foto['tur']);
        $dosyaAdi = sprintf(
            '%s_%s_%s.%s',
            date('Y-m-d', strtotime($foto['tarih'])),
            preg_replace('/[^\p{L}\p{N}_-]+/u', '_', (string) ($foto['tutanak_no'] ?: 'tutanaksiz')),
            $foto['id'],
            pathinfo($foto['dosya_yolu'], PATHINFO_EXTENSION)
        );

        $zip->addFile($kaynak, $ilce . '/' . $turKlasor . '/' . $dosyaAdi);
        $eklenenIds[] = (int) $foto['id'];
    }

    $zip->close();

    if (empty($eklenenIds) || !is_file($zipYolu)) {
        @unlink($zipYolu);
        kacakYanit(false, 'Arşivlenecek dosya bulunamadı.');
    }

    $Kacak->markPhotosArchived($eklenenIds);

    foreach ($fotolar as $foto) {
        if (!in_array((int) $foto['id'], $eklenenIds, true)) {
            continue;
        }
        $kaynak = $root . '/' . ltrim($foto['dosya_yolu'], '/');
        if (is_file($kaynak)) {
            @unlink($kaynak);
        }
    }

    $Log->logAction(
        $userId,
        'Kaçak Fotoğrafları Arşivlendi',
        count($eklenenIds) . " adet fotoğraf arşivlenip sunucudan silindi. Aralık: $bas - $bit",
        SystemLogModel::LEVEL_CRITICAL
    );

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipAdi . '"');
    header('Content-Length: ' . filesize($zipYolu));
    readfile($zipYolu);
    @unlink($zipYolu);
    exit;
}
