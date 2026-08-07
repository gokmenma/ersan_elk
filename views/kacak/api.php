<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Date;
use App\Helper\Security;
use App\Model\KacakKontrolModel;
use App\Model\KacakSicilEksikModel;
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

if (!empty($_POST['mobile_token'])) {
    $csrf = (string) ($_POST['_mobile_csrf'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyin.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_POST['id'] = (int) Security::decrypt((string) $_POST['mobile_token']);
}

if (!kacakIzin('kacak_islemleri') && !kacakIzin('kacak/list') && !kacakSuperAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
    exit;
}

// Salt okunur isteklerde oturum kilidini bırak; aksi halde aynı sekmeden gelen
// paralel AJAX çağrıları PHP session dosya kilidi yüzünden sıraya girer.
// Gate, AuthController::user() üzerinden session_start() çağırdığı için tüm
// yetki sonuçları kilit bırakılmadan önce önbelleğe alınır.
$saltOkunurActionlar = [
    'list',
    'get-record',
    'pending-count',
    'get-photos',
    'archive-preview',
    'gunluk-rapor',
    'haftalik-rapor',
    'teslim-alma-listesi',
    'sicil-list',
    'sicil-counts',
    'sicil-detay',
    'sicil-tutanak-ara',
];

if (in_array($action, $saltOkunurActionlar, true)) {
    kacakSuperAdmin();
    foreach (['kacak_onay', 'kacak_duzenle', 'kacak_iptal', 'kacak_arsiv', 'kacak_sicil_bildir', 'kacak_sicil_yanitla'] as $izin) {
        kacakIzin($izin);
    }
    session_write_close();
}

$Kacak = new KacakKontrolModel();
$Sicil = new KacakSicilEksikModel();
$Log = new SystemLogModel();

function kacakYanit(bool $ok, string $mesaj = '', array $ek = []): void
{
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $mesaj], $ek), JSON_UNESCAPED_UNICODE);
    exit;
}

function kacakSuperAdmin(): bool
{
    static $deger = null;
    if ($deger === null) {
        $deger = Gate::isSuperAdmin();
    }
    return $deger;
}

function kacakIzin(string $izin): bool
{
    static $onbellek = [];
    if (!array_key_exists($izin, $onbellek)) {
        $onbellek[$izin] = Gate::allows($izin);
    }
    return $onbellek[$izin];
}

function kacakYetkiKontrol(string $izin): void
{
    if (!kacakIzin($izin) && !kacakSuperAdmin()) {
        kacakYanit(false, 'Bu işlem için yetkiniz bulunmuyor.');
    }
}

/**
 * Sicil oluşmayanlar sekmesini görüntüleme yetkisi: bildirme veya yanıtlama.
 */
function sicilGorusYetkiKontrol(): void
{
    if (!kacakIzin('kacak_sicil_bildir') && !kacakIzin('kacak_sicil_yanitla') && !kacakSuperAdmin()) {
        kacakYanit(false, 'Bu işlem için yetkiniz bulunmuyor.');
    }
}

/**
 * Alt sekme adını geçerli durum listesine çevirir.
 */
function sicilDurumFiltresi(string $sekme): array
{
    switch ($sekme) {
        case 'beklemede':
            return ['beklemede'];
        case 'yanitlandi':
            return ['yanitlandi'];
        case 'arsiv':
            return ['cozuldu', 'iptal'];
        default:
            return [];
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
            $dataTableRequest = isset($_GET['draw']);
            $columnNames = ['tarih', 'tutanak_no', 'abone_adi', 'ilce', 'tur', 'sayac_no', 'sayi', 'ekip_adi', 'kaynak', '', 'durum', ''];
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

            if ($dataTableRequest) {
                $globalSearch = trim((string) ($_GET['search']['value'] ?? ''));
                if ($globalSearch !== '') {
                    $filters['arama'] = trim($filters['arama'] . ' ' . $globalSearch);
                }
                foreach (($_GET['columns'] ?? []) as $index => $column) {
                    $value = trim((string) ($column['search']['value'] ?? ''));
                    if ($value !== '' && !empty($columnNames[$index])) {
                        $filters['kolon_aramalari'][$columnNames[$index]] = $value;
                    }
                }
            }

            // Personel tarafında (süper admin veya onay yetkilisi değilse) sadece kendi ekibinin bildirimlerini görebilir
            if ($userPersonelId > 0 && !kacakSuperAdmin() && !kacakIzin('kacak_onay')) {
                $filters['personel_id'] = $userPersonelId;
            }

            $limit = $dataTableRequest ? max(1, min(100, (int) ($_GET['length'] ?? 25))) : 0;
            $offset = $dataTableRequest ? max(0, (int) ($_GET['start'] ?? 0)) : 0;
            $orderIndex = (int) ($_GET['order'][0]['column'] ?? 0);
            $orderColumn = $columnNames[$orderIndex] ?? 'tarih';
            $orderDirection = (string) ($_GET['order'][0]['dir'] ?? 'desc');
            $kayitlar = $Kacak->getRecords($filters, $limit, $offset, $orderColumn, $orderDirection);
            foreach ($kayitlar as &$k) {
                $k['tarih_formatted'] = Date::dmY($k['tarih']);
                $k['foto_sayisi'] = (int) $k['foto_sayisi'];
                $k['beklenen_foto_sayisi'] = (int) ($k['beklenen_foto_sayisi'] ?? 0);
            }
            unset($k);

            if ($dataTableRequest) {
                $filteredCount = $Kacak->countRecords($filters);
                $totalFilters = $filters;
                unset($totalFilters['arama'], $totalFilters['kolon_aramalari']);
                kacakYanit(true, '', [
                    'draw' => (int) $_GET['draw'],
                    'recordsTotal' => $Kacak->countRecords($totalFilters),
                    'recordsFiltered' => $filteredCount,
                    'data' => $kayitlar,
                    'ozet' => $Kacak->getOzet($filters['tarih_baslangic'], $filters['tarih_bitis'], (int) ($filters['personel_id'] ?? 0)),
                ]);
            }

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
            // Sicil bildirimi açacak kurum kullanıcısının tutanak detayını görmesi gerekir.
            if ($userPersonelId > 0 && !kacakSuperAdmin() && !kacakIzin('kacak_onay') && !kacakIzin('kacak_sicil_bildir')) {
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
                $duplicate = $Kacak->findDuplicateRecord([
                    'tutanak_no' => $tutanakNoArr[0] ?? null,
                    'sayac_no' => $sayacNoArr[0] ?? null,
                    'tarih' => $tarih,
                ], $id);
                if ($duplicate) {
                    $rec = $duplicate['record'];
                    $tarihFmt = !empty($rec['tarih']) ? date('d.m.Y', strtotime($rec['tarih'])) : '';
                    if ($duplicate['type'] === 'tutanak_no') {
                        $msg = "Mükerrer Kayıt: '" . htmlspecialchars($rec['tutanak_no'], ENT_QUOTES, 'UTF-8') . "' numaralı tutanak daha önce sisteme girilmiş. ({$tarihFmt})";
                    } else {
                        $msg = "Mükerrer Kayıt: '" . htmlspecialchars($rec['sayac_no'], ENT_QUOTES, 'UTF-8') . "' numaralı sayaç için {$tarihFmt} tarihinde zaten kayıt mevcuttur.";
                    }
                    kacakYanit(false, $msg);
                }

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
                    $tNo = $tutanakNoArr[$i] ?? null;
                    $sNo = $sayacNoArr[$i] ?? null;
                    $duplicate = $Kacak->findDuplicateRecord([
                        'tutanak_no' => $tNo,
                        'sayac_no' => $sNo,
                        'tarih' => $tarih,
                    ]);
                    if ($duplicate) {
                        $rec = $duplicate['record'];
                        $tarihFmt = !empty($rec['tarih']) ? date('d.m.Y', strtotime($rec['tarih'])) : '';
                        if ($duplicate['type'] === 'tutanak_no') {
                            $msg = "Mükerrer Kayıt: '" . htmlspecialchars($rec['tutanak_no'], ENT_QUOTES, 'UTF-8') . "' numaralı tutanak daha önce sisteme girilmiş. ({$tarihFmt})";
                        } else {
                            $msg = "Mükerrer Kayıt: '" . htmlspecialchars($rec['sayac_no'], ENT_QUOTES, 'UTF-8') . "' numaralı sayaç için {$tarihFmt} tarihinde zaten kayıt mevcuttur.";
                        }
                        $db->rollBack();
                        kacakYanit(false, $msg);
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

        case 'download-zip':
            $id = !empty($_GET['token'])
                ? (int) Security::decrypt((string) $_GET['token'])
                : (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) {
                kacakYanit(false, 'Geçersiz kayıt ID.');
            }
            kacakRecordZipIndir($Kacak, $Log, $userId, $userPersonelId, $id);
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

        // =====================================================
        // SİCİL OLUŞMAYANLAR
        // =====================================================
        case 'sicil-list':
            sicilGorusYetkiKontrol();
            $columnNames = ['tutanak_no', 'tutanak_tarihi', 'abone_adi', 'ekip_adi', 'neden', '', '', '', 'tur_sira', 'durum', ''];
            $filters = [
                'durum' => sicilDurumFiltresi($_GET['durum'] ?? ''),
                'neden' => $_GET['neden'] ?? '',
                'arama' => $_GET['arama'] ?? '',
            ];

            if (!empty($_GET['start_date'])) {
                $filters['tarih_baslangic'] = kacakTarih($_GET['start_date']);
            }
            if (!empty($_GET['end_date'])) {
                $filters['tarih_bitis'] = kacakTarih($_GET['end_date']);
            }

            $dataTableRequest = isset($_GET['draw']);
            if ($dataTableRequest) {
                $globalSearch = trim((string) ($_GET['search']['value'] ?? ''));
                if ($globalSearch !== '') {
                    $filters['arama'] = trim($filters['arama'] . ' ' . $globalSearch);
                }
                foreach (($_GET['columns'] ?? []) as $index => $column) {
                    $value = trim((string) ($column['search']['value'] ?? ''));
                    if ($value !== '' && !empty($columnNames[$index])) {
                        $filters['kolon_aramalari'][$columnNames[$index]] = $value;
                    }
                }
            }

            // Yalnızca yanıtlama yetkisi olan personel kendi ekibinin taleplerini görür.
            if ($userPersonelId > 0 && !kacakSuperAdmin() && !kacakIzin('kacak_sicil_bildir') && !kacakIzin('kacak_onay')) {
                $filters['personel_id'] = $userPersonelId;
            }

            $limit = $dataTableRequest ? max(1, min(100, (int) ($_GET['length'] ?? 25))) : 0;
            $offset = $dataTableRequest ? max(0, (int) ($_GET['start'] ?? 0)) : 0;
            $orderIndex = (int) ($_GET['order'][0]['column'] ?? 0);
            $orderColumn = $columnNames[$orderIndex] ?? 'bildirim_tarihi';
            $orderDirection = (string) ($_GET['order'][0]['dir'] ?? 'desc');

            $kayitlar = $Sicil->getRecords($filters, $limit, $offset, $orderColumn ?: 'bildirim_tarihi', $orderDirection);
            foreach ($kayitlar as &$s) {
                $s['bildirim_tarihi_formatted'] = Date::dmYHis($s['bildirim_tarihi'], 'd.m.Y H:i');
                $s['tutanak_tarihi_formatted'] = !empty($s['tutanak_tarihi']) ? Date::dmY($s['tutanak_tarihi']) : '';
                $s['yanit_tarihi_formatted'] = !empty($s['yanit_tarihi']) ? Date::dmYHis($s['yanit_tarihi'], 'd.m.Y H:i') : '';
                $s['kapatma_tarihi_formatted'] = !empty($s['kapatma_tarihi']) ? Date::dmYHis($s['kapatma_tarihi'], 'd.m.Y H:i') : '';
            }
            unset($s);

            if ($dataTableRequest) {
                $filteredCount = $Sicil->countRecords($filters);
                kacakYanit(true, '', [
                    'draw' => (int) $_GET['draw'],
                    'recordsTotal' => $filteredCount,
                    'recordsFiltered' => $filteredCount,
                    'data' => $kayitlar,
                ]);
            }

            kacakYanit(true, '', ['data' => $kayitlar]);
            break;

        case 'sicil-counts':
            sicilGorusYetkiKontrol();
            $sadeceEkip = $userPersonelId > 0 && !kacakSuperAdmin()
                && !kacakIzin('kacak_sicil_bildir') && !kacakIzin('kacak_onay');
            kacakYanit(true, '', ['counts' => $Sicil->getCounts($sadeceEkip ? $userPersonelId : 0)]);
            break;

        case 'sicil-detay':
            sicilGorusYetkiKontrol();
            $kayit = $Sicil->getRecord((int) ($_GET['id'] ?? 0));
            if (!$kayit) {
                kacakYanit(false, 'Kayıt bulunamadı.');
            }
            if ($userPersonelId > 0 && !kacakSuperAdmin() && !kacakIzin('kacak_sicil_bildir') && !kacakIzin('kacak_onay')) {
                if (!$Sicil->personelYetkiliMi((int) $kayit['id'], $userPersonelId)) {
                    kacakYanit(false, 'Bu kaydı görüntüleme yetkiniz bulunmuyor.');
                }
            }
            kacakYanit(true, '', ['data' => $kayit]);
            break;

        case 'sicil-tutanak-ara':
            kacakYetkiKontrol('kacak_sicil_bildir');
            $sonuclar = $Sicil->tutanakAra((string) ($_GET['q'] ?? ''));
            foreach ($sonuclar as &$t) {
                $t['tarih_formatted'] = Date::dmY($t['tarih']);
            }
            unset($t);
            kacakYanit(true, '', ['data' => $sonuclar]);
            break;

        case 'sicil-create':
            kacakYetkiKontrol('kacak_sicil_bildir');
            try {
                $sicilId = $Sicil->create([
                    'tutanak_no' => $_POST['tutanak_no'] ?? '',
                    'kacak_id' => $_POST['kacak_id'] ?? null,
                    'neden' => $_POST['neden'] ?? '',
                    'aciklama' => $_POST['aciklama'] ?? '',
                ], $userId);
            } catch (\Exception $e) {
                kacakYanit(false, $e->getMessage());
            }

            $yeniKayit = $Sicil->getRecord($sicilId);
            $Log->logAction(
                $userId,
                'Sicil Eksik Bildirimi Açıldı',
                'Tutanak: ' . ($yeniKayit['tutanak_no'] ?? '-') . ', Neden: ' . ($yeniKayit['neden_metin'] ?? '-'),
                SystemLogModel::LEVEL_IMPORTANT
            );

            sicilEkibeBildir($yeniKayit ?? []);

            kacakYanit(true, 'Sicil eksik bildirimi oluşturuldu, ekibe iletildi.', ['id' => $sicilId]);
            break;

        case 'sicil-yanitla':
            kacakYetkiKontrol('kacak_sicil_yanitla');
            $sicilId = (int) ($_POST['id'] ?? 0);
            try {
                $guncel = $Sicil->yanitla($sicilId, [
                    'abone_adi' => $_POST['abone_adi'] ?? '',
                    'abone_tc' => $_POST['abone_tc'] ?? '',
                    'abone_dogum_tarihi' => $_POST['abone_dogum_tarihi'] ?? '',
                    'abone_adres' => $_POST['abone_adres'] ?? '',
                    'sayac_no' => $_POST['sayac_no'] ?? '',
                    'yanit_aciklama' => $_POST['yanit_aciklama'] ?? '',
                ], $userPersonelId, $userId);
            } catch (\Exception $e) {
                kacakYanit(false, $e->getMessage());
            }

            $Log->logAction(
                $userId,
                'Sicil Eksik Bildirimi Yanıtlandı',
                'ID: ' . $sicilId . ', Tutanak: ' . ($guncel['tutanak_no'] ?? '-'),
                SystemLogModel::LEVEL_IMPORTANT
            );

            sicilKurumaBildir($guncel ?? []);

            kacakYanit(true, 'Düzeltilmiş bilgi kaydedildi ve kuruma iletildi.');
            break;

        case 'sicil-kapat':
            kacakYetkiKontrol('kacak_sicil_bildir');
            $sicilId = (int) ($_POST['id'] ?? 0);
            $sonuc = (string) ($_POST['sonuc'] ?? '');
            try {
                $guncel = $Sicil->kapat($sicilId, $sonuc, (string) ($_POST['aciklama'] ?? ''), $userId);
            } catch (\Exception $e) {
                kacakYanit(false, $e->getMessage());
            }

            $Log->logAction(
                $userId,
                $sonuc === 'cozuldu' ? 'Sicil Eksik Bildirimi Çözüldü' : 'Sicil Eksik Bildirimi İptal Edildi',
                'ID: ' . $sicilId . ', Tutanak: ' . ($guncel['tutanak_no'] ?? '-'),
                SystemLogModel::LEVEL_IMPORTANT
            );

            if ($sonuc === 'cozuldu') {
                sicilEkibeBildir($guncel ?? [], 'cozuldu');
            }

            kacakYanit(true, $sonuc === 'cozuldu' ? 'Kayıt çözüldü olarak kapatıldı.' : 'Kayıt iptal edildi.');
            break;

        case 'sicil-eslestir':
            kacakYetkiKontrol('kacak_sicil_yanitla');
            $sicilId = (int) ($_POST['id'] ?? 0);
            try {
                $guncel = $Sicil->eslestir($sicilId, (int) ($_POST['kacak_id'] ?? 0));
            } catch (\Exception $e) {
                kacakYanit(false, $e->getMessage());
            }

            $Log->logAction(
                $userId,
                'Sicil Eksik Bildirimi Eşleştirildi',
                'ID: ' . $sicilId . ', Tutanak: ' . ($guncel['tutanak_no'] ?? '-'),
                SystemLogModel::LEVEL_IMPORTANT
            );

            sicilEkibeBildir($guncel ?? []);

            kacakYanit(true, 'Kayıt tutanakla eşleştirildi, ekibe bildirim gönderildi.');
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

    if (!empty($_FILES['videolar']) && is_array($_FILES['videolar']['name'])) {
        $sureler = $_POST['video_sureleri'] ?? [];
        $kapaklar = $_POST['video_kapaklari'] ?? [];
        foreach ($_FILES['videolar']['name'] as $i => $ad) {
            if (empty($ad) || ($_FILES['videolar']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            try {
                $sonuc = $Kacak->storeUploadedVideo(
                    [
                        'name' => $ad,
                        'tmp_name' => $_FILES['videolar']['tmp_name'][$i],
                        'error' => $_FILES['videolar']['error'][$i],
                        'size' => $_FILES['videolar']['size'][$i],
                    ],
                    $kacakId,
                    isset($sureler[$i]) && is_numeric($sureler[$i]) ? (int) ceil((float) $sureler[$i]) : null,
                    isset($kapaklar[$i]) ? (string) $kapaklar[$i] : null
                );
                $Kacak->addVideo($kacakId, $sonuc['yol'], $sonuc['kapak'], $sonuc['sure_saniye'], $ad, null, $userId);
                $eklenen++;
            } catch (\Throwable $e) {
                error_log('Kaçak videosu yüklenemedi: ' . $e->getMessage());
            }
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
 * Sicil eksik bildirimini tutanağı tutan ekibe iletir.
 * $tip: 'acildi' -> düzeltme isteniyor, 'cozuldu' -> bilgilendirme
 */
function sicilEkibeBildir(array $kayit, string $tip = 'acildi'): void
{
    if (empty($kayit['atanan_personel_ids'])) {
        return;
    }

    $personelIds = array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string) $kayit['atanan_personel_ids']))
    )));

    if (!$personelIds) {
        return;
    }

    $tutanakNo = $kayit['tutanak_no'] ?? '-';

    if ($tip === 'cozuldu') {
        $baslik = 'Tutanak Sicili Oluşturuldu';
        $mesaj = $tutanakNo . ' nolu tutanak için sicil oluşturuldu, düzeltme talebi kapandı.';
    } else {
        $baslik = 'Tutanak Bilgi Düzeltmesi Gerekiyor';
        $mesaj = $tutanakNo . ' nolu tutanağın ' . mb_strtolower($kayit['neden_metin'] ?? 'bilgisi hatalı', 'UTF-8')
            . ' olduğu bildirildi. Aboneye ulaşıp doğru bilgiyi uygulamadan giriniz.';
        if (!empty($kayit['aciklama'])) {
            $mesaj .= ' Not: ' . $kayit['aciklama'];
        }
    }

    try {
        $Push = new \App\Service\PushNotificationService();
        foreach ($personelIds as $personelId) {
            $Push->sendToPersonel($personelId, [
                'title' => $baslik,
                'body' => $mesaj,
                'url' => '?page=kacak',
            ]);
        }
    } catch (\Throwable $e) {
        error_log('Sicil eksik bildirimi ekibe gönderilemedi: ' . $e->getMessage());
    }
}

/**
 * Ekip düzeltmeyi girdiğinde bildirimi açan kurum kullanıcısına haber verir.
 */
function sicilKurumaBildir(array $kayit): void
{
    if (empty($kayit['bildiren_user_id'])) {
        return;
    }

    $tutanakNo = $kayit['tutanak_no'] ?? '-';
    $mesaj = $tutanakNo . ' nolu tutanak için ekip düzeltilmiş bilgiyi girdi. Kontrol edip sicil oluşturabilirsiniz.';
    $link = 'index.php?p=kacak/list&tab=sicil&sicil_id=' . (int) ($kayit['id'] ?? 0);

    try {
        $Bildirim = new \App\Model\BildirimModel();
        $Bildirim->createNotification(
            (int) $kayit['bildiren_user_id'],
            'Sicil Düzeltmesi Yanıtlandı',
            $mesaj,
            $link,
            'user-check',
            'success'
        );
    } catch (\Throwable $e) {
        error_log('Sicil düzeltme bildirimi kaydedilemedi: ' . $e->getMessage());
    }

    try {
        $Push = new \App\Service\PushNotificationService();
        $Push->sendToUser((int) $kayit['bildiren_user_id'], [
            'title' => 'Sicil Düzeltmesi Yanıtlandı',
            'body' => $mesaj,
            'url' => $link,
        ], true);
    } catch (\Throwable $e) {
        error_log('Sicil düzeltme push bildirimi gönderilemedi: ' . $e->getMessage());
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
        $turKlasor = ($foto['medya_tipi'] ?? 'foto') === 'video' ? 'Video' : ucfirst($foto['tur']);
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
        foreach ([$foto['dosya_yolu'], $foto['kucuk_yol'] ?? null] as $yol) {
            if (empty($yol)) {
                continue;
            }
            $kaynak = $root . '/' . ltrim($yol, '/');
            if (is_file($kaynak)) {
                @unlink($kaynak);
            }
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

/**
 * Belirli bir kaçak kaydının fotoğraflarını ZIP olarak indirir.
 */
function kacakRecordZipIndir(KacakKontrolModel $Kacak, SystemLogModel $Log, int $userId, int $userPersonelId, int $kacakId): void
{
    $kayit = $Kacak->getRecord($kacakId);
    if (!$kayit) {
        http_response_code(404);
        exit('Kayıt bulunamadı.');
    }

    if ($userPersonelId > 0 && !kacakSuperAdmin() && !kacakIzin('kacak_onay')) {
        $isEkip = ($kayit['bildiren_personel_id'] == $userPersonelId) || in_array($userPersonelId, $kayit['personel_ids_array'] ?? [], true);
        if (!$isEkip) {
            http_response_code(403);
            exit('Bu kaydın fotoğraflarını indirme yetkiniz bulunmuyor.');
        }
    }

    $fotolar = $Kacak->getPhotos($kacakId);
    if (empty($fotolar)) {
        http_response_code(404);
        exit('Bu kayda ait indirilebilir foto/belge bulunamadı.');
    }

    $root = KacakKontrolModel::rootPath();

    $tutanakNoClean = !empty($kayit['tutanak_no']) ? trim((string) $kayit['tutanak_no']) : '';
    $aboneAdiClean = !empty($kayit['abone_adi']) ? mb_strtoupper(trim((string) $kayit['abone_adi']), 'UTF-8') : '';
    $turClean = !empty($kayit['tur']) ? mb_strtoupper(trim((string) $kayit['tur']), 'UTF-8') : 'KAÇAK';

    $folderParts = [];
    if ($tutanakNoClean !== '') {
        $folderParts[] = $tutanakNoClean;
    }
    if ($aboneAdiClean !== '') {
        $folderParts[] = $aboneAdiClean;
    }

    if (!empty($folderParts)) {
        $folderBase = implode(' - ', $folderParts);
        $rawFolderName = sprintf('%s (%s)', $folderBase, $turClean);
    } else {
        $rawFolderName = sprintf('kayit_%d (%s)', $kacakId, $turClean);
    }

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
        $kaynak = $root . '/' . ltrim($foto['dosya_yolu'], '/');
        if (!is_file($kaynak)) {
            continue;
        }

        $ext = pathinfo($foto['dosya_yolu'], PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = 'jpg';
        }

        $fotoTur = strtolower($foto['tur'] ?? 'saha');
        $medyaTipi = strtolower($foto['medya_tipi'] ?? 'foto');

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

        $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNoClean ?: ('kayit_' . $kacakId), $seq, $ext);

        $zip->addFile($kaynak, $folderName . '/' . $dosyaAdi);
        $eklenenSayisi++;
    }

    $zip->close();

    if ($eklenenSayisi === 0 || !is_file($zipYolu)) {
        @unlink($zipYolu);
        http_response_code(404);
        exit('İndirilebilir dosya bulunamadı.');
    }

    $Log->logAction(
        $userId,
        'Kaçak Kaydı Fotoğrafları İndirildi (ZIP)',
        "Kayıt ID: $kacakId, Tutanak No: " . ($kayit['tutanak_no'] ?? '-') . ", Eklenen Dosya: $eklenenSayisi",
        SystemLogModel::LEVEL_INFO
    );

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $encodedZipAdi = rawurlencode($zipAdi);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $zipAdi) . '"; filename*=UTF-8\'\'' . $encodedZipAdi);
    header('Content-Length: ' . filesize($zipYolu));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($zipYolu);
    @unlink($zipYolu);
    exit;
}
