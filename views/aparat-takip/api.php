<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Date;
use App\Helper\Security;
use App\Model\AparatDashboardModel;
use App\Model\AparatHareketModel;
use App\Model\AparatSayimModel;
use App\Model\AparatStokModel;
use App\Model\AparatTipiModel;
use App\Model\AparatTransferModel;
use App\Model\KesmeAcmaIslemModel;
use App\Model\SystemLogModel;
use App\Service\AparatStokService;
use App\Service\Gate;
use App\Model\PermissionPolicyModel;

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId <= 0 || empty($_SESSION['firma_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum süresi doldu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function aparatSuperAdmin(): bool
{
    static $deger = null;
    if ($deger === null) {
        $deger = Gate::isSuperAdmin();
    }
    return $deger;
}

function aparatIzin(string $izin): bool
{
    static $onbellek = [];
    if (!array_key_exists($izin, $onbellek)) {
        $onbellek[$izin] = Gate::allows($izin);
    }
    return $onbellek[$izin];
}

function aparatYanit(bool $ok, string $mesaj = '', array $ek = []): void
{
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $mesaj], $ek), JSON_UNESCAPED_UNICODE);
    exit;
}

function aparatYetkiKontrol(string $izin): void
{
    if (!aparatIzin($izin) && !aparatSuperAdmin()) {
        aparatYanit(false, 'Bu işlem için yetkiniz bulunmuyor.');
    }
}

function aparatAnahtar($metin): string
{
    $metin = strtr((string) $metin, [
        'İ' => 'I', 'ı' => 'I', 'i' => 'I', 'Ş' => 'S', 'ş' => 'S', 'Ç' => 'C', 'ç' => 'C',
        'Ğ' => 'G', 'ğ' => 'G', 'Ü' => 'U', 'ü' => 'U', 'Ö' => 'O', 'ö' => 'O',
    ]);
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($metin));
}

function aparatTarih($deger, string $varsayilan): string
{
    $deger = trim((string) $deger);
    if ($deger === '') {
        return $varsayilan;
    }
    $donusen = Date::convertExcelDate($deger, 'Y-m-d');
    if (!empty($donusen)) {
        return $donusen;
    }
    $ts = strtotime($deger);
    return $ts !== false ? date('Y-m-d', $ts) : $varsayilan;
}

$temelErisimVar = aparatIzin('aparat_takip')
    || aparatIzin('aparat-takip/list')
    || ($action === 'dashboard-data' && aparatIzin('aparat-takip/dashboard'))
    || aparatSuperAdmin();

if (!$temelErisimVar) {
    aparatYanit(false, 'Bu işlem için yetkiniz yok.');
}

$permissionPolicy = new PermissionPolicyModel();
if ($permissionPolicy->isReady()) {
    Gate::authorizeApiPolicy('aparat-takip/api', (string) $action);
}

$gelismisActionlar = ['islem-listesi', 'islem-detay', 'islem-kaydet', 'islem-iptal', 'islem-sil',
    'hareket-listesi', 'hareket-iptal', 'transfer-listesi', 'transfer-iptal',
    'sayim-listesi', 'sayim-detay', 'sayim-baslat', 'sayim-gir', 'sayim-excel-yukle', 'sayim-ekip-ekle', 'sayim-ekip-cikar', 'sayim-farklari-isle', 'sayim-kapat', 'sayim-iptal',
    'sahada-takili', 'donemsel-ozet', 'api-karsilastirma', 'tutarlilik', 'bakiye-yeniden-kur',
    'tip-kaydet', 'tip-sil'];

if (in_array($action, $gelismisActionlar, true)) {
    aparatYetkiKontrol('aparat_gelismis');
}

$saltOkunurActionlar = ['dashboard-data', 'stok-matris', 'son-depo-hareketleri', 'islem-listesi', 'hareket-listesi', 'transfer-listesi',
    'sayim-listesi', 'sayim-detay', 'tip-listesi', 'islem-detay', 'sahada-takili',
    'donemsel-ozet', 'api-karsilastirma', 'tutarlilik'];

if (in_array($action, $saltOkunurActionlar, true)) {
    aparatSuperAdmin();
    foreach (['aparat_depo', 'aparat_iptal', 'aparat_sayim', 'aparat_tanim', 'aparat_transfer_yonet', 'aparat_gelismis', 'aparat-takip/dashboard'] as $izin) {
        aparatIzin($izin);
    }
    session_write_close();
}

$Tip = new AparatTipiModel();
$Stok = new AparatStokModel();
$Hareket = new AparatHareketModel();
$Islem = new KesmeAcmaIslemModel();
$Transfer = new AparatTransferModel();
$Sayim = new AparatSayimModel();
$Log = new SystemLogModel();

$bugun = date('Y-m-d');
$ayBasi = date('Y-m-01');

try {
    switch ($action) {

        // =====================================================
        // DASHBOARD VERİLERİ
        // =====================================================
        case 'dashboard-data':
            aparatYetkiKontrol('aparat-takip/dashboard');
            $Dashboard = new AparatDashboardModel();
            $gelismis = aparatIzin('aparat_gelismis') || aparatSuperAdmin();
            $filtre = [
                'period' => (string) ($_POST['period'] ?? $_GET['period'] ?? 'this_month'),
                'start_date' => !empty($_POST['start_date'] ?? $_GET['start_date'] ?? '')
                    ? aparatTarih($_POST['start_date'] ?? $_GET['start_date'] ?? '', $ayBasi)
                    : null,
                'end_date' => !empty($_POST['end_date'] ?? $_GET['end_date'] ?? '')
                    ? aparatTarih($_POST['end_date'] ?? $_GET['end_date'] ?? '', $bugun)
                    : null,
                'aparat_tip_id' => (int) ($_POST['aparat_tip_id'] ?? $_GET['aparat_tip_id'] ?? 0),
            ];
            aparatYanit(true, '', $Dashboard->getDashboardData($filtre, $gelismis));
            break;

        // =====================================================
        // STOK MATRİSİ
        // =====================================================
        case 'stok-matris':
            $kirmaSonuc = ['islenen' => 0, 'bekleyen' => 0];
            try {
                $kirmaSonuc = (new AparatStokService())->otomatikKirmaSenkronize((int) $_SESSION['firma_id']);
            } catch (Throwable $e) {
                error_log('Aparat otomatik kırma senkronu: ' . $e->getMessage());
            }
            $matris = $Stok->matris();
            $gelismis = aparatIzin('aparat_gelismis') || aparatSuperAdmin();

            aparatYanit(true, '', [
                'data' => $matris,
                'kritik' => $Stok->kritikStoklar(),
                'tutarsiz_satir' => $gelismis ? count($Hareket->tutarlilikKontrolu()) : 0,
                'kirma_bekleyen' => $gelismis ? (int) $kirmaSonuc['bekleyen'] : 0,
            ]);
            break;

        case 'son-depo-hareketleri':
            $uyeHaritasi = $Stok->ekipUyeHaritasi();
            $kayitlar = [];
            foreach ($Hareket->sonDepoHareketleri(10) as $h) {
                $ekipId = (int) ($h['ekip_id'] ?? 0);
                $kayitlar[] = [
                    'tarih' => $h['tarih'],
                    'hareket_tipi' => $h['gorunen_tip'],
                    'hareket_adi' => ['depo_giris' => 'Depoya Giriş', 'depo_cikis' => 'Ekibe Verildi', 'depo_iade' => 'İade Alındı', 'depo_iade_hurda' => 'Bozuk İade (Hurda)'][$h['gorunen_tip']],
                    'aparat_adi' => $h['aparat_adi'],
                    'adet' => abs((int) $h['adet']),
                    'ekip' => $ekipId > 0 ? ($uyeHaritasi[$ekipId] ?? $h['ekip_adi']) : '',
                    'kullanici' => $h['kullanici_adi'] ?? '',
                    'aciklama' => $h['aciklama'] ?? '',
                ];
            }
            aparatYanit(true, '', ['data' => $kayitlar]);
            break;

        case 'tutarlilik':
            aparatYanit(true, '', ['data' => $Hareket->tutarlilikKontrolu()]);
            break;

        case 'bakiye-yeniden-kur':
            aparatYetkiKontrol('aparat_depo');
            $sayi = $Hareket->bakiyeleriYenidenKur();
            $Log->logAction($userId, 'Aparat Bakiye Onarımı',
                "Bakiye tablosu ana defterden yeniden kuruldu ($sayi satır).", SystemLogModel::LEVEL_CRITICAL);
            aparatYanit(true, 'Bakiyeler ana defterden yeniden hesaplandı.');
            break;

        // =====================================================
        // APARAT TİPLERİ
        // =====================================================
        case 'tip-listesi':
            aparatYanit(true, '', [
                'data' => $Tip->listele(isset($_GET['sadece_aktif']) ? (bool) $_GET['sadece_aktif'] : false),
            ]);
            break;

        case 'tip-kaydet':
            aparatYetkiKontrol('aparat_tanim');

            $tipId = (int) ($_POST['id'] ?? 0);
            $ad = trim((string) ($_POST['ad'] ?? ''));
            $kod = strtoupper(trim((string) ($_POST['kod'] ?? '')));

            if ($ad === '' || $kod === '') {
                aparatYanit(false, 'Aparat adı ve kodu zorunludur.');
            }
            if ($Tip->kodVarMi($kod, $tipId)) {
                aparatYanit(false, 'Bu kod ile tanımlı başka bir aparat tipi var.');
            }

            $mevcutTip = $tipId > 0 ? $Tip->getir($tipId) : null;

            $veri = [
                'ad' => $ad,
                'kod' => $kod,
                'renk' => in_array($_POST['renk'] ?? '', AparatTipiModel::RENKLER, true) ? $_POST['renk'] : 'primary',
                'sira' => (int) ($_POST['sira'] ?? 1),
                'aciklama' => trim((string) ($_POST['aciklama'] ?? '')) ?: null,
                'depo_kritik_stok' => max(0, (int) ($_POST['depo_kritik_stok'] ?? 0)),
                'ekip_kritik_stok' => max(0, (int) ($_POST['ekip_kritik_stok'] ?? 0)),
                'varsayilan_kirma' => !empty($_POST['varsayilan_kirma']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1,
            ];

            // Resim yükleme veya silme işlemleri
            $resimSil = !empty($_POST['resim_sil']);
            $yeniResimYuklendi = isset($_FILES['resim']) && $_FILES['resim']['error'] === UPLOAD_ERR_OK;

            if ($yeniResimYuklendi) {
                $file = $_FILES['resim'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                if ($file['size'] > $maxSize) {
                    aparatYanit(false, 'Yüklenen resim boyutu 5MB\'dan büyük olamaz.');
                }

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
                if (!in_array($mimeType, $allowedMimes, true)) {
                    aparatYanit(false, 'Geçersiz resim formatı. Sadece JPG, PNG, WEBP, GIF veya SVG yükleyebilirsiniz.');
                }

                $targetDir = dirname(__DIR__, 2) . '/files/aparat_tipleri/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                $binaryData = file_get_contents($file['tmp_name']);
                if ($binaryData === false) {
                    aparatYanit(false, 'Dosya okunamadı.');
                }

                $yeniDosyaAdi = bin2hex(random_bytes(16)) . '.enc';
                $sifreliVeri = Security::encryptFile($binaryData);

                if (file_put_contents($targetDir . $yeniDosyaAdi, $sifreliVeri) === false) {
                    aparatYanit(false, 'Resim dosyası kaydedilemedi.');
                }

                // Eski resmi sil
                if ($mevcutTip && !empty($mevcutTip['resim'])) {
                    $eskiYol = $targetDir . $mevcutTip['resim'];
                    if (is_file($eskiYol)) {
                        @unlink($eskiYol);
                    }
                }

                $veri['resim'] = $yeniDosyaAdi;
            } elseif ($resimSil) {
                if ($mevcutTip && !empty($mevcutTip['resim'])) {
                    $targetDir = dirname(__DIR__, 2) . '/files/aparat_tipleri/';
                    $eskiYol = $targetDir . $mevcutTip['resim'];
                    if (is_file($eskiYol)) {
                        @unlink($eskiYol);
                    }
                }
                $veri['resim'] = null;
            }

            if ($tipId > 0) {
                $Tip->guncelle($tipId, $veri);
                if ($veri['varsayilan_kirma'] === 1) {
                    $Tip->varsayilanKirmaTek($tipId);
                }
                $Log->logAction($userId, 'Aparat Tipi Güncellendi', "#$tipId $ad ($kod)", SystemLogModel::LEVEL_IMPORTANT);
                aparatYanit(true, 'Aparat tipi güncellendi.');
            }

            $yeniId = $Tip->ekle($veri, $userId);
            if ($veri['varsayilan_kirma'] === 1) {
                $Tip->varsayilanKirmaTek($yeniId);
            }
            $Log->logAction($userId, 'Aparat Tipi Eklendi', "#$yeniId $ad ($kod)", SystemLogModel::LEVEL_IMPORTANT);
            aparatYanit(true, 'Aparat tipi eklendi.', ['id' => $yeniId]);
            break;

        case 'tip-sil':
            aparatYetkiKontrol('aparat_tanim');

            $tipId = (int) ($_POST['id'] ?? 0);
            if ($tipId <= 0 || !$Tip->getir($tipId)) {
                aparatYanit(false, 'Aparat tipi bulunamadı.');
            }

            if ($Tip->kullanimdaMi($tipId)) {
                $Tip->durumDegistir($tipId, 0);
                $Log->logAction($userId, 'Aparat Tipi Pasife Alındı', "#$tipId", SystemLogModel::LEVEL_IMPORTANT);
                aparatYanit(true, 'Bu tipin stok hareketi olduğu için silinmedi, pasife alındı.');
            }

            $Tip->sil($tipId);
            $Log->logAction($userId, 'Aparat Tipi Silindi', "#$tipId", SystemLogModel::LEVEL_IMPORTANT);
            aparatYanit(true, 'Aparat tipi silindi.');
            break;

        // =====================================================
        // SAHA İŞLEMLERİ
        // =====================================================
        case 'islem-listesi':
            $filtre = [
                'baslangic' => aparatTarih($_GET['start_date'] ?? '', $ayBasi),
                'bitis' => aparatTarih($_GET['end_date'] ?? '', $bugun),
                'ekip_id' => (int) ($_GET['ekip_id'] ?? 0),
                'islem_tipi' => $_GET['islem_tipi'] ?? '',
                'aparat_tip_id' => (int) ($_GET['aparat_tip_id'] ?? 0),
                'durum' => $_GET['durum'] ?? '',
                'abone_no' => trim((string) ($_GET['abone_no'] ?? '')),
                'sadece_negatif' => !empty($_GET['sadece_negatif']) ? 1 : 0,
            ];

            aparatYanit(true, '', [
                'data' => $Islem->listele($filtre, 1000),
                'toplam' => $Islem->sayisi($filtre),
            ]);
            break;

        case 'islem-detay':
            $islemId = (int) ($_GET['id'] ?? 0);
            $kayit = $Islem->getir($islemId);
            if (!$kayit) {
                aparatYanit(false, 'Kayıt bulunamadı.');
            }

            aparatYanit(true, '', [
                'data' => $kayit,
                'fotograflar' => $Islem->fotograflar($islemId),
                'hareketler' => $Hareket->listele([
                    'referans_tipi' => 'kesme_acma_islem',
                    'referans_id' => $islemId,
                ], 50),
            ]);
            break;

        case 'islem-kaydet':
            aparatYetkiKontrol('aparat_depo');

            $Servis = new AparatStokService();
            $sonuc = $Servis->islemKaydet([
                'islem_tipi' => $_POST['islem_tipi'] ?? '',
                'ekip_id' => (int) ($_POST['ekip_id'] ?? 0),
                'aparat_tip_id' => (int) ($_POST['aparat_tip_id'] ?? 0),
                'adet' => (int) ($_POST['adet'] ?? 1),
                'aparatsiz' => (int) ($_POST['aparatsiz'] ?? 0),
                'aparat_durumu' => $_POST['aparat_durumu'] ?? null,
                'abone_no' => trim((string) ($_POST['abone_no'] ?? '')),
                'sayac_no' => trim((string) ($_POST['sayac_no'] ?? '')),
                'abone_adi' => trim((string) ($_POST['abone_adi'] ?? '')),
                'ilce' => trim((string) ($_POST['ilce'] ?? '')),
                'mahalle' => trim((string) ($_POST['mahalle'] ?? '')),
                'aciklama' => trim((string) ($_POST['aciklama'] ?? '')),
                'tarih' => aparatTarih($_POST['tarih'] ?? '', $bugun),
                'kaynak' => 'masaustu',
                'client_uuid' => null,
                'kaydeden_id' => $userId,
            ]);

            aparatYanit(true, $sonuc['negatif']
                ? 'Kayıt eklendi. Dikkat: ekip stoğu eksiye düştü.'
                : 'Kayıt eklendi.', ['data' => $sonuc]);
            break;

        case 'islem-iptal':
            aparatYetkiKontrol('aparat_iptal');

            $islemId = (int) ($_POST['id'] ?? 0);
            $aciklama = trim((string) ($_POST['aciklama'] ?? ''));

            if ($aciklama === '') {
                aparatYanit(false, 'İptal gerekçesi zorunludur.');
            }

            $Servis = new AparatStokService();
            $Servis->islemIptal($islemId, $aciklama, $userId);
            aparatYanit(true, 'Kayıt iptal edildi, stok hareketleri geri alındı.');
            break;

        case 'islem-sil':
            aparatYetkiKontrol('aparat_sil');

            $islemId = (int) ($_POST['id'] ?? 0);
            $aciklama = trim((string) ($_POST['aciklama'] ?? ''));

            if ($aciklama === '') {
                aparatYanit(false, 'Silme gerekçesi zorunludur.');
            }

            $Servis = new AparatStokService();
            $Servis->islemSil($islemId, $aciklama, $userId);
            aparatYanit(true, 'Kayıt silindi, stok hareketleri geri alındı.');
            break;

        // =====================================================
        // HAREKET DÖKÜMÜ
        // =====================================================
        case 'hareket-listesi':
            $tumTarih = !empty($_GET['tum_tarih']);
            $filtre = [
                'baslangic' => $tumTarih ? '' : aparatTarih($_GET['start_date'] ?? '', $ayBasi),
                'bitis' => $tumTarih ? '' : aparatTarih($_GET['end_date'] ?? '', $bugun),
                'ekip_id' => (int) ($_GET['ekip_id'] ?? 0),
                'aparat_tip_id' => (int) ($_GET['aparat_tip_id'] ?? 0),
                'hareket_tipi' => $_GET['hareket_tipi'] ?? '',
                'sahip_tipi' => $_GET['sahip_tipi'] ?? '',
                'sahip_id' => (int) ($_GET['sahip_id'] ?? 0),
            ];

            $uyeHaritasi = $Stok->ekipUyeHaritasi();
            $personelAdlari = [];
            foreach ($Stok->kacakPersoneller() as $kp) {
                $personelAdlari[(int) $kp['id']] = $kp['adi_soyadi'];
            }
            $ekipKodlari = [];
            foreach ($Stok->ekipler(false) as $e) {
                $ekipKodlari[(int) $e['id']] = $e['tur_adi'];
            }
            $ekipEtiket = function (int $id) use ($uyeHaritasi, $ekipKodlari): string {
                $kod = $ekipKodlari[$id] ?? ('Ekip #' . $id);
                return ($uyeHaritasi[$id] ?? '') !== '' ? $uyeHaritasi[$id] . ' (' . $kod . ')' : $kod;
            };
            $tarafEtiket = function (?array $taraf, string $hareketTipi) use ($ekipEtiket, $personelAdlari): string {
                if ($taraf === null) {
                    return match ($hareketTipi) {
                        'depo_giris' => 'Satın alma',
                        'acilis' => 'Açılış stoğu',
                        default => '—',
                    };
                }
                return match ($taraf['tip']) {
                    'ekip' => $ekipEtiket((int) $taraf['id']),
                    'personel' => ($personelAdlari[(int) $taraf['id']] ?? ('Personel #' . (int) $taraf['id'])) . ' (Kaçak Kontrol)',
                    'depo' => 'Depo',
                    'saha' => 'Abonede takılı',
                    'hurda' => 'Hurda',
                    'kayip' => 'Kayıp',
                    default => (string) $taraf['tip'],
                };
            };
            $hareketAdi = [
                'kesme' => 'Kesme', 'acma' => 'Açma', 'transfer' => 'Ekipler Arası Transfer',
                'depo_giris' => 'Depoya Giriş', 'depo_cikis' => 'Ekibe Verildi', 'depo_iade' => 'İade Alındı',
                'hurda' => 'Hurdaya Ayrıldı', 'kayip' => 'Kayıp', 'sayim_duzeltme' => 'Sayım Düzeltmesi', 'acilis' => 'Açılış Stoğu',
            ];

            $olaylar = [];
            foreach ($Hareket->olaylar($filtre, 2000) as $o) {
                $ozel = match ($o['referans_tipi']) {
                    'otomatik_kirma' => 'Aparat Kırma (otomatik)',
                    'online_kesme' => 'Kesme (online)',
                    'online_acma' => 'Açma (online)',
                    'bozuk_iade' => 'Bozuk İade → Hurda',
                    default => null,
                };
                $ekipId = (int) ($o['ekip_id'] ?? 0);
                $yapan = (string) ($o['personel_adi'] ?? '');
                if ($yapan === '' && $ekipId > 0 && in_array($o['hareket_tipi'], ['kesme', 'acma', 'transfer'], true)) {
                    $yapan = $ekipEtiket($ekipId);
                } elseif ($yapan !== '' && $ekipId > 0) {
                    $yapan .= ' (' . ($ekipKodlari[$ekipId] ?? '') . ')';
                }
                $kaydeden = (string) ($o['kullanici_adi'] ?? '');
                if ($kaydeden === '') {
                    $kaydeden = match ($o['referans_tipi']) {
                        'otomatik_kirma', 'online_kesme', 'online_acma' => 'Sistem (puantaj)',
                        'kesme_acma_islem' => 'Personel (telefon)',
                        'aparat_transfer' => 'Personel (telefon)',
                        default => '',
                    };
                }

                $olaylar[] = [
                    'id' => (int) $o['id'],
                    'tarih' => $o['tarih'],
                    'hareket_tipi' => $o['hareket_tipi'],
                    'hareket_adi' => $ozel ?? ($hareketAdi[$o['hareket_tipi']] ?? $o['hareket_tipi']),
                    'nereden' => $tarafEtiket($o['kaynak'], $o['hareket_tipi']),
                    'nereye' => $tarafEtiket($o['hedef'], $o['hareket_tipi']),
                    'kaynak' => $o['kaynak'],
                    'hedef' => $o['hedef'],
                    'aparat_adi' => $o['aparat_adi'],
                    'adet' => (int) $o['adet'],
                    'yapan' => $yapan,
                    'kaydeden' => $kaydeden,
                    'aciklama' => (string) ($o['aciklama'] ?? ''),
                    'iptal_mi' => (int) $o['iptal_mi'],
                    'referans_tipi' => (string) ($o['referans_tipi'] ?? ''),
                ];
            }

            aparatYanit(true, '', ['data' => $olaylar, 'toplam' => count($olaylar)]);
            break;

        case 'hareket-iptal':
            aparatYetkiKontrol('aparat_iptal');

            $hareketId = (int) ($_POST['id'] ?? 0);
            $aciklama = trim((string) ($_POST['aciklama'] ?? ''));
            if ($aciklama === '') {
                $aciklama = 'Geri alındı';
            }

            $Servis = new AparatStokService();
            $sonuc = $Servis->havuzHareketiIptal($hareketId, $aciklama, $userId);

            aparatYanit(true, $sonuc['negatif']
                ? 'Geri alındı. Dikkat: ilgili stok eksiye düştü.'
                : 'Geri alındı. Stok eski haline döndü.');
            break;

        case 'havuz-geri-al':
            aparatYetkiKontrol('aparat_depo');

            $idler = json_decode((string) ($_POST['grup_idleri'] ?? '[]'), true);
            if (!is_array($idler) || empty($idler) || count($idler) > 1000) {
                aparatYanit(false, 'Geri alınacak kayıt bulunamadı.');
            }

            $sonuc = (new AparatStokService())->havuzHareketleriGeriAl($idler, $userId);
            aparatYanit(true, $sonuc['negatif']
                ? 'Geri alındı. Dikkat: ilgili stok eksiye düştü.'
                : 'Geri alındı. Stok eski haline döndü.');
            break;

        case 'sayim-satir-geri-al':
            aparatYetkiKontrol('aparat_sayim');

            $sonuc = (new AparatStokService())->sayimSatiriGeriAl((int) ($_POST['detay_id'] ?? 0), $userId);
            aparatYanit(true, 'Satır geri alındı. Stok eski haline döndü, sayımı yeniden düzenleyebilirsiniz.');
            break;

        // =====================================================
        // HAVUZ HAREKETLERİ (depo / hurda / kayıp / açılış)
        // =====================================================
        case 'havuz-hareketi':
            aparatYetkiKontrol('aparat_depo');

            $tur = $_POST['tur'] ?? '';
            if (!in_array($tur, ['depo_giris', 'depo_cikis', 'depo_iade', 'depo_iade_hurda'], true)) {
                aparatYetkiKontrol('aparat_gelismis');
            }
            $Servis = new AparatStokService();
            $sonuc = $Servis->havuzHareketi($tur, [
                'aparat_tip_id' => (int) ($_POST['aparat_tip_id'] ?? 0),
                'adet' => (int) ($_POST['adet'] ?? 0),
                'ekip_id' => (int) ($_POST['ekip_id'] ?? 0),
                'aciklama' => trim((string) ($_POST['aciklama'] ?? '')),
            ], $userId);

            aparatYanit(true, $sonuc['negatif']
                ? 'Kaydedildi. Dikkat: ilgili stok eksiye düştü.'
                : 'Kaydedildi.', ['grup_idleri' => !empty($sonuc['grup_id']) ? [(int) $sonuc['grup_id']] : []]);
            break;

        case 'havuz-toplu':
            aparatYetkiKontrol('aparat_depo');

            $tur = (string) ($_POST['tur'] ?? '');
            if (!in_array($tur, ['depo_giris', 'depo_cikis', 'depo_iade', 'depo_iade_hurda', 'kayip', 'acilis'], true)) {
                aparatYanit(false, 'Geçersiz hareket türü.');
            }
            if ($tur === 'acilis') {
                aparatYetkiKontrol('aparat_gelismis');
            }

            $ham = json_decode((string) ($_POST['satirlar'] ?? '[]'), true);
            if (!is_array($ham) || empty($ham) || count($ham) > 1000) {
                aparatYanit(false, 'Girilmiş adet bulunamadı.');
            }

            $birlesik = [];
            foreach ($ham as $satir) {
                $ekipId = (int) ($satir['ekip_id'] ?? 0);
                $personelId = (int) ($satir['personel_id'] ?? 0);
                $tipId = (int) ($satir['aparat_tip_id'] ?? 0);
                $adet = (int) ($satir['adet'] ?? 0);
                if (($tur !== 'depo_giris' && $ekipId <= 0 && $personelId <= 0) || $tipId <= 0 || $adet <= 0) {
                    continue;
                }
                $anahtar = ($tur === 'depo_giris' ? 'd' : ($personelId > 0 ? 'p' . $personelId : 'e' . $ekipId)) . '_' . $tipId;
                $birlesik[$anahtar] = [
                    'ekip_id' => $tur === 'depo_giris' || $personelId > 0 ? 0 : $ekipId,
                    'personel_id' => $tur === 'depo_giris' ? 0 : $personelId,
                    'aparat_tip_id' => $tipId,
                    'adet' => ($birlesik[$anahtar]['adet'] ?? 0) + $adet,
                ];
            }

            $birimCeza = $tur === 'kayip' ? max(0.0, (float) str_replace(',', '.', (string) ($_POST['ceza_tutari'] ?? '0'))) : 0.0;
            $Servis = new AparatStokService();
            $sonuc = $Servis->havuzHareketiToplu($tur, array_values($birlesik), trim((string) ($_POST['aciklama'] ?? '')), $userId, $birimCeza);

            aparatYanit(true, $sonuc['islenen'] . ' kayıt eklendi (toplam ' . $sonuc['adet'] . ' adet).'
                . ($sonuc['negatif'] ? ' Dikkat: ilgili stok eksiye düştü.' : ''), ['grup_idleri' => $sonuc['grup_idleri']]);
            break;

        // =====================================================
        // TRANSFERLER
        // =====================================================
        case 'transfer-listesi':
            aparatYanit(true, '', [
                'data' => $Transfer->listele([
                    'durum' => $_GET['durum'] ?? '',
                    'ekip_id' => (int) ($_GET['ekip_id'] ?? 0),
                    'baslangic' => !empty($_GET['start_date']) ? aparatTarih($_GET['start_date'], $ayBasi) : '',
                    'bitis' => !empty($_GET['end_date']) ? aparatTarih($_GET['end_date'], $bugun) : '',
                ], 500),
            ]);
            break;

        case 'transfer-iptal':
            aparatYetkiKontrol('aparat_transfer_yonet');

            $Servis = new AparatStokService();
            $Servis->transferIptal((int) ($_POST['id'] ?? 0), $userId);
            aparatYanit(true, 'Transfer iptal edildi.');
            break;

        // =====================================================
        // SAYIM
        // =====================================================
        case 'sayim-listesi':
            aparatYanit(true, '', [
                'data' => $Sayim->listele(),
                'acik' => $Sayim->acikSayim(),
            ]);
            break;

        case 'sayim-detay':
            $sayimId = (int) ($_GET['id'] ?? 0);
            $sayim = $Sayim->getir($sayimId);
            if (!$sayim) {
                aparatYanit(false, 'Sayım bulunamadı.');
            }

            $uyeHaritasi = $Stok->ekipUyeHaritasi();
            $detaylar = $Sayim->detaylar($sayimId, (int) ($_GET['ekip_id'] ?? 0) ?: null);
            foreach ($detaylar as &$detay) {
                $detay['ekip_uyeleri'] = (int) $detay['ekip_id'] < 0
                    ? 'Ekipsiz Kaçak Kontrol personeli'
                    : ($uyeHaritasi[(int) $detay['ekip_id']] ?? '');
            }
            unset($detay);

            aparatYanit(true, '', [
                'data' => $sayim,
                'detaylar' => $detaylar,
            ]);
            break;

        case 'sayim-baslat':
            aparatYetkiKontrol('aparat_sayim');

            if ($Sayim->acikSayim()) {
                aparatYanit(false, 'Zaten açık bir sayım var. Önce onu tamamlayın.');
            }

            $tipler = array_map(fn($t) => (int) $t['id'], $Tip->listele(true));
            if (empty($tipler)) {
                aparatYanit(false, 'Önce aparat tipi tanımlamalısınız.');
            }

            $ekipler = array_filter(array_map('intval', (array) ($_POST['ekipler'] ?? [])));
            if (empty($ekipler)) {
                $ekipler = array_map(fn($e) => (int) $e['id'], $Stok->ekipler());
                foreach ($Stok->kacakPersoneller() as $kp) {
                    $ekipler[] = -((int) $kp['id']);
                }
            }
            if (empty($ekipler)) {
                aparatYanit(false, 'Sayıma dahil edilecek ekip bulunamadı.');
            }

            $baslik = trim((string) ($_POST['baslik'] ?? '')) ?: ('Aparat Sayımı ' . date('d.m.Y'));
            $sayimId = $Sayim->baslat($baslik, $ekipler, $tipler, $userId, trim((string) ($_POST['aciklama'] ?? '')));

            $Log->logAction($userId, 'Aparat Sayımı Başlatıldı',
                "#$sayimId $baslik (" . count($ekipler) . ' ekip)', SystemLogModel::LEVEL_IMPORTANT);
            aparatYanit(true, 'Sayım başlatıldı.', ['id' => $sayimId]);
            break;

        case 'sayim-gir':
            aparatYetkiKontrol('aparat_sayim');

            $sayimId = (int) ($_POST['sayim_id'] ?? 0);
            $ekipId = (int) ($_POST['ekip_id'] ?? 0);
            $tipId = (int) ($_POST['aparat_tip_id'] ?? 0);
            $sayilan = (int) ($_POST['sayilan_adet'] ?? -1);
            $aciklama = trim((string) ($_POST['aciklama'] ?? ''));

            if ($sayilan < 0) {
                aparatYanit(false, 'Sayılan adet geçersiz.');
            }

            $sayim = $Sayim->getir($sayimId);
            if (!$sayim || $sayim['durum'] !== 'acik') {
                aparatYanit(false, 'Sayım açık değil.');
            }

            if (!$Sayim->sayimGir($sayimId, $ekipId, $tipId, $sayilan, $aciklama, null)) {
                aparatYanit(false, 'Sayım satırı güncellenemedi (işlenmiş olabilir).');
            }

            $otomatikAciklama = $Sayim->otomatikAciklamaUygula($sayimId, $ekipId, $tipId);
            $guncel = $Sayim->satirGetir($sayimId, $ekipId, $tipId);
            aparatYanit(true, 'Sayım adedi kaydedildi.', [
                'sistem_adet' => (int) ($guncel['sistem_adet'] ?? 0),
                'fark' => (int) ($guncel['fark'] ?? 0),
                'aciklama' => $otomatikAciklama,
            ]);
            break;

        case 'sayim-ekip-ekle':
            aparatYetkiKontrol('aparat_sayim');

            $sayimId = (int) ($_POST['sayim_id'] ?? 0);
            $sayim = $Sayim->getir($sayimId);
            if (!$sayim || $sayim['durum'] !== 'acik') {
                aparatYanit(false, 'Sayım açık değil.');
            }

            $sahipler = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ekipler'] ?? [])))));
            if (empty($sahipler)) {
                aparatYanit(false, 'Eklenecek ekip veya personel seçilmedi.');
            }
            foreach ($sahipler as $sahip) {
                $gecerli = $sahip > 0 ? $Stok->ekipGecerliMi($sahip) : $Stok->personelGecerliMi(-$sahip);
                if (!$gecerli) {
                    aparatYanit(false, 'Geçersiz ekip veya personel seçimi.');
                }
            }

            $tipIdleri = array_map(fn($t) => (int) $t['id'], $Tip->listele(true));
            $eklenenSatir = $Sayim->satirlariEkle($sayimId, $sahipler, $tipIdleri);
            $Log->logAction($userId, 'Aparat Sayımına Ekleme', "#$sayimId sayıma " . count($sahipler) . ' ekip/personel eklendi', SystemLogModel::LEVEL_IMPORTANT);

            aparatYanit(true, $eklenenSatir > 0 ? 'Sayıma eklendi.' : 'Seçilenler zaten sayımda.');
            break;

        case 'sayim-ekip-cikar':
            aparatYetkiKontrol('aparat_sayim');

            $sayimId = (int) ($_POST['sayim_id'] ?? 0);
            $sahipId = (int) ($_POST['ekip_id'] ?? 0);
            $sayim = $Sayim->getir($sayimId);
            if (!$sayim || $sayim['durum'] !== 'acik') {
                aparatYanit(false, 'Sayım açık değil.');
            }
            if ($sahipId === 0) {
                aparatYanit(false, 'Geçersiz ekip veya personel.');
            }
            if ($Sayim->sahipIslenmisSatirVarMi($sayimId, $sahipId)) {
                aparatYanit(false, 'Farkları stoğa işlenmiş satırı olan ekip sayımdan çıkarılamaz. Önce işlenen satırları geri alın.');
            }

            $silinen = $Sayim->sahibiCikar($sayimId, $sahipId);
            $Log->logAction($userId, 'Aparat Sayımından Çıkarma', "#$sayimId sayımdan " . ($sahipId < 0 ? 'personel #' . (-$sahipId) : 'ekip #' . $sahipId) . " çıkarıldı ($silinen satır)", SystemLogModel::LEVEL_IMPORTANT);

            aparatYanit(true, 'Sayımdan çıkarıldı.');
            break;

        case 'sayim-excel-yukle':
            aparatYetkiKontrol('aparat_sayim');

            $sayimId = (int) ($_POST['sayim_id'] ?? 0);
            $sayim = $Sayim->getir($sayimId);
            if (!$sayim || $sayim['durum'] !== 'acik') {
                aparatYanit(false, 'Sayım açık değil.');
            }

            $dosya = $_FILES['dosya'] ?? null;
            if (!$dosya || ($dosya['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($dosya['tmp_name'])) {
                aparatYanit(false, 'Excel dosyası yüklenemedi.');
            }
            if (strtolower(pathinfo((string) $dosya['name'], PATHINFO_EXTENSION)) !== 'xlsx' || (int) $dosya['size'] > 5 * 1024 * 1024) {
                aparatYanit(false, 'Yalnızca 5 MB altındaki .xlsx dosyaları kabul edilir.');
            }

            try {
                $okuyucu = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                $okuyucu->setReadDataOnly(true);
                $satirlar = $okuyucu->load($dosya['tmp_name'])->getActiveSheet()->toArray(null, true, false, false);
            } catch (\Throwable $e) {
                error_log('Aparat sayım excel okuma hatası: ' . $e->getMessage());
                aparatYanit(false, 'Excel dosyası okunamadı. Sayım formunu indirip aynı biçimde kullanın.');
            }

            $detaylar = $Sayim->detaylar($sayimId);
            $uyeHaritasi = $Stok->ekipUyeHaritasi();
            $tipAdlari = [];
            $ekipAdlari = [];
            $detayHaritasi = [];
            foreach ($detaylar as $d) {
                $ekipId = (int) $d['ekip_id'];
                $tipId = (int) $d['aparat_tip_id'];
                $tipAdlari[$tipId] = $d;
                $ekipAdlari[$ekipId] = $d;
                $detayHaritasi[$ekipId][$tipId] = $d;
            }

            $ekipEtiketleri = [];
            $uyeSayaci = [];
            foreach ($ekipAdlari as $ekipId => $d) {
                $ekipEtiketleri[aparatAnahtar($d['ekip_adi'])][$ekipId] = true;
                $uyeler = (string) ($uyeHaritasi[$ekipId] ?? '');
                if ($uyeler !== '') {
                    $ekipEtiketleri[aparatAnahtar($uyeler)][$ekipId] = true;
                    foreach (explode(',', $uyeler) as $uye) {
                        $uyeSayaci[aparatAnahtar($uye)][$ekipId] = true;
                    }
                }
            }
            foreach ($uyeSayaci as $anahtar => $ekipKumesi) {
                if (!isset($ekipEtiketleri[$anahtar])) {
                    $ekipEtiketleri[$anahtar] = $ekipKumesi;
                }
            }

            $baslik = $satirlar[0] ?? [];
            $tipSutunlari = [];
            $ekipNoSutunu = null;
            $etiketSutunlari = [];
            foreach ($baslik as $kolon => $metin) {
                if (trim((string) $metin) === '') {
                    continue;
                }
                $anahtar = aparatAnahtar($metin);
                if ($anahtar === 'EKIPNO') {
                    $ekipNoSutunu = $kolon;
                    continue;
                }
                if ($anahtar === 'EKIP' || $anahtar === 'PERSONEL') {
                    $etiketSutunlari[] = $kolon;
                    continue;
                }
                if ($kolon === 0) {
                    $etiketSutunlari[] = $kolon;
                    continue;
                }

                $eslesen = [];
                foreach ($tipAdlari as $tipId => $d) {
                    $adAnahtar = aparatAnahtar($d['aparat_adi']);
                    if ($adAnahtar === $anahtar || aparatAnahtar($d['aparat_kod'] ?? '') === $anahtar) {
                        $eslesen = [$tipId];
                        break;
                    }
                    if ($anahtar !== '' && strpos($adAnahtar, $anahtar) !== false) {
                        $eslesen[] = $tipId;
                    }
                }
                if (count($eslesen) === 1) {
                    $tipSutunlari[$kolon] = $eslesen[0];
                } else {
                    $hatalar[] = "'" . trim((string) $metin) . "' sütunu bir aparat tipiyle eşleşmedi, atlandı.";
                }
            }

            if (empty($tipSutunlari)) {
                aparatYanit(false, 'Başlık satırında tanınan aparat sütunu bulunamadı. Sayım formunu indirip aynı biçimde kullanın.');
            }

            $guncellenen = 0;
            $atlanan = 0;
            $sifirlanan = 0;
            $hatalar = $hatalar ?? [];

            foreach ($satirlar as $no => $satir) {
                if ($no === 0) {
                    continue;
                }
                $etiketler = [];
                foreach (array_unique($etiketSutunlari ?: [0]) as $kolon) {
                    $deger = trim((string) ($satir[$kolon] ?? ''));
                    if ($deger !== '') {
                        $etiketler[] = $deger;
                    }
                }
                $etiket = implode(' / ', $etiketler);
                $toplamSatiri = false;
                foreach ($etiketler as $deger) {
                    $toplamSatiri = $toplamSatiri || strpos(aparatAnahtar($deger), 'TOPLAM') === 0;
                }
                if ($etiket === '' || $toplamSatiri) {
                    continue;
                }

                $ekipId = 0;
                if ($ekipNoSutunu !== null && isset($satir[$ekipNoSutunu]) && is_numeric($satir[$ekipNoSutunu])
                    && isset($ekipAdlari[(int) $satir[$ekipNoSutunu]])) {
                    $ekipId = (int) $satir[$ekipNoSutunu];
                } else {
                    foreach ($etiketler as $deger) {
                        $anahtar = aparatAnahtar($deger);
                        if (isset($ekipEtiketleri[$anahtar]) && count($ekipEtiketleri[$anahtar]) === 1) {
                            $ekipId = (int) array_key_first($ekipEtiketleri[$anahtar]);
                            break;
                        }
                    }
                }

                if ($ekipId === 0) {
                    $hatalar[] = 'Satır ' . ($no + 1) . ": '" . $etiket . "' sayımdaki bir ekiple eşleşmedi, atlandı.";
                    continue;
                }

                $doluVar = false;
                foreach (array_keys($tipSutunlari) as $kolon) {
                    if (isset($satir[$kolon]) && trim((string) $satir[$kolon]) !== '') {
                        $doluVar = true;
                        break;
                    }
                }
                if (!$doluVar) {
                    $atlanan++;
                    continue;
                }

                foreach ($tipSutunlari as $kolon => $tipId) {
                    $ham = $satir[$kolon] ?? null;
                    $bos = $ham === null || trim((string) $ham) === '';
                    if (!$bos && (!is_numeric($ham) || (float) $ham < 0 || (float) $ham != (int) $ham)) {
                        $hatalar[] = 'Satır ' . ($no + 1) . ' (' . $etiket . "): '" . $ham . "' geçerli bir adet değil.";
                        continue;
                    }

                    $detay = $detayHaritasi[$ekipId][$tipId] ?? null;
                    if (!$detay || (int) $detay['islendi'] === 1) {
                        $hatalar[] = 'Satır ' . ($no + 1) . ' (' . $etiket . '): ' . ($tipAdlari[$tipId]['aparat_adi'] ?? '') . ' satırı işlenmiş veya sayımda yok.';
                        continue;
                    }

                    if ($Sayim->sayimGir($sayimId, $ekipId, $tipId, $bos ? 0 : (int) $ham, (string) ($detay['aciklama'] ?? ''), null)) {
                        $Sayim->otomatikAciklamaUygula($sayimId, $ekipId, $tipId);
                        $guncellenen++;
                        $sifirlanan += $bos ? 1 : 0;
                    }
                }
            }

            $Log->logAction($userId, 'Aparat Sayımı Excel Yükleme',
                "#$sayimId $guncellenen satır güncellendi, " . count($hatalar) . ' hata', SystemLogModel::LEVEL_IMPORTANT);

            aparatYanit(true, "$guncellenen hücre güncellendi" . ($sifirlanan ? " ($sifirlanan boş hücre 0 kabul edildi)" : '') . ($atlanan ? ", $atlanan boş ekip satırı atlandı" : '')
                . (count($hatalar) ? ', ' . count($hatalar) . ' satırda hata var' : '') . '.',
                ['guncellenen' => $guncellenen, 'atlanan' => $atlanan, 'hatalar' => array_slice($hatalar, 0, 20)]);
            break;

        case 'sayim-farklari-isle':
            aparatYetkiKontrol('aparat_sayim');

            $sayimId = (int) ($_POST['sayim_id'] ?? 0);
            $sayim = $Sayim->getir($sayimId);
            if (!$sayim || $sayim['durum'] !== 'acik') {
                aparatYanit(false, 'Sayım açık değil.');
            }

            $farklar = $Sayim->islenmemisFarklar($sayimId);
            foreach ($farklar as $detay) {
                if (trim((string) $detay['aciklama']) === '') {
                    $Sayim->otomatikAciklamaUygula($sayimId, (int) $detay['ekip_id'], (int) $detay['aparat_tip_id']);
                }
            }
            $farklar = $Sayim->islenmemisFarklar($sayimId);

            $sifirlanan = 0;
            if (!empty($_POST['bos_sifir'])) {
                $tumDetaylar = $Sayim->detaylar($sayimId);
                $sayilanEkipler = [];
                foreach ($tumDetaylar as $detay) {
                    if ($detay['sayilan_adet'] !== null) {
                        $sayilanEkipler[(int) $detay['ekip_id']] = true;
                    }
                }
                foreach ($tumDetaylar as $detay) {
                    if ($detay['sayilan_adet'] === null && (int) $detay['islendi'] === 0
                        && isset($sayilanEkipler[(int) $detay['ekip_id']])
                        && $Sayim->sayimGir($sayimId, (int) $detay['ekip_id'], (int) $detay['aparat_tip_id'], 0, 'Kullanıldı (sayımda girilmedi, 0 kabul edildi)', null)) {
                        $sifirlanan++;
                    }
                }
                $farklar = $Sayim->islenmemisFarklar($sayimId);
            }

            $Servis = new AparatStokService();
            $islenen = 0;
            foreach ($farklar as $detay) {
                $Servis->sayimFarkiIsle((int) $detay['id'], $userId);
                $islenen++;
            }

            if ($sifirlanan > 0) {
                $Log->logAction($userId, 'Aparat Sayımı Boş Satırlar Sıfırlandı',
                    "#$sayimId $sifirlanan girilmemiş satır sıfır kabul edildi", SystemLogModel::LEVEL_IMPORTANT);
            }

            aparatYanit(true, "$islenen satırdaki fark stoğa işlendi" . ($sifirlanan ? " ($sifirlanan girilmemiş satır 0 sayıldı)" : '') . '.');
            break;

        case 'sayim-kapat':
            aparatYetkiKontrol('aparat_sayim');

            $sayimId = (int) ($_POST['sayim_id'] ?? 0);
            if (!empty($Sayim->islenmemisFarklar($sayimId))) {
                aparatYanit(false, 'İşlenmemiş fark satırları var. Önce farkları işleyin.');
            }
            if (!$Sayim->kapat($sayimId)) {
                aparatYanit(false, 'Sayım kapatılamadı.');
            }

            $Log->logAction($userId, 'Aparat Sayımı Tamamlandı', "#$sayimId", SystemLogModel::LEVEL_IMPORTANT);
            aparatYanit(true, 'Sayım tamamlandı.');
            break;

        case 'sayim-iptal':
            aparatYetkiKontrol('aparat_sayim');

            $sayimId = (int) ($_POST['sayim_id'] ?? 0);
            foreach ($Sayim->detaylar($sayimId) as $detay) {
                if ((int) $detay['islendi'] === 1) {
                    aparatYanit(false, 'Farkları stoğa işlenmiş sayım iptal edilemez. Sayımı "Tamamla" ile kapatın.');
                }
            }

            if (!$Sayim->iptalEt($sayimId)) {
                aparatYanit(false, 'Sayım iptal edilemedi.');
            }
            aparatYanit(true, 'Sayım iptal edildi.');
            break;

        // =====================================================
        // RAPORLAR
        // =====================================================
        case 'sahada-takili':
            aparatYanit(true, '', [
                'data' => $Stok->sahadaTakililar(
                    (int) ($_GET['aparat_tip_id'] ?? 0) ?: null,
                    (int) ($_GET['min_gun'] ?? 0)
                ),
            ]);
            break;

        case 'donemsel-ozet':
            aparatYanit(true, '', [
                'data' => $Islem->donemselOzet(
                    aparatTarih($_GET['start_date'] ?? '', $ayBasi),
                    aparatTarih($_GET['end_date'] ?? '', $bugun),
                    (int) ($_GET['ekip_id'] ?? 0) ?: null
                ),
            ]);
            break;

        case 'api-karsilastirma':
            aparatYanit(true, '', [
                'data' => $Islem->apiKarsilastirma(
                    aparatTarih($_GET['start_date'] ?? '', $ayBasi),
                    aparatTarih($_GET['end_date'] ?? '', $bugun)
                ),
            ]);
            break;

        case 'ekip-listesi':
            aparatYanit(true, '', ['data' => $Stok->ekipler()]);
            break;

        default:
            aparatYanit(false, 'Geçersiz istek.');
    }
} catch (Throwable $e) {
    error_log('Aparat takip API hatası [' . $action . ']: ' . $e->getMessage());

    // Yalnızca iş kuralı doğrulamaları (düz Exception) kullanıcıya gösterilir;
    // PDO ve diğer sistem hataları dışarı sızmaz.
    $kullaniciMesaji = get_class($e) === Exception::class && $e->getMessage() !== ''
        ? $e->getMessage()
        : 'İşlem sırasında bir hata oluştu.';

    aparatYanit(false, $kullaniciMesaji);
}
