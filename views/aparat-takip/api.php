<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Date;
use App\Helper\Security;
use App\Model\AparatHareketModel;
use App\Model\AparatSayimModel;
use App\Model\AparatStokModel;
use App\Model\AparatTipiModel;
use App\Model\AparatTransferModel;
use App\Model\KesmeAcmaIslemModel;
use App\Model\SystemLogModel;
use App\Service\AparatStokService;
use App\Service\Gate;

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

if (!aparatIzin('aparat_takip') && !aparatIzin('aparat-takip/list') && !aparatSuperAdmin()) {
    aparatYanit(false, 'Bu işlem için yetkiniz yok.');
}

$saltOkunurActionlar = ['stok-matris', 'islem-listesi', 'hareket-listesi', 'transfer-listesi',
    'sayim-listesi', 'sayim-detay', 'tip-listesi', 'islem-detay', 'sahada-takili',
    'donemsel-ozet', 'api-karsilastirma', 'tutarlilik'];

if (in_array($action, $saltOkunurActionlar, true)) {
    aparatSuperAdmin();
    foreach (['aparat_depo', 'aparat_iptal', 'aparat_sayim', 'aparat_tanim', 'aparat_transfer_yonet'] as $izin) {
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
        // STOK MATRİSİ
        // =====================================================
        case 'stok-matris':
            $matris = $Stok->matris();
            $tutarsizlik = $Hareket->tutarlilikKontrolu();

            aparatYanit(true, '', [
                'data' => $matris,
                'tutarsiz_satir' => count($tutarsizlik),
                'negatif_islem' => $Islem->sayisi(['sadece_negatif' => 1, 'durum' => 'aktif']),
                'bekleyen_transfer' => count($Transfer->listele(['durum' => 'beklemede'], 500)),
            ]);
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
                $Log->logAction($userId, 'Aparat Tipi Güncellendi', "#$tipId $ad ($kod)", SystemLogModel::LEVEL_IMPORTANT);
                aparatYanit(true, 'Aparat tipi güncellendi.');
            }

            $yeniId = $Tip->ekle($veri, $userId);
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

        // =====================================================
        // HAREKET DÖKÜMÜ
        // =====================================================
        case 'hareket-listesi':
            $filtre = [
                'baslangic' => aparatTarih($_GET['start_date'] ?? '', $ayBasi),
                'bitis' => aparatTarih($_GET['end_date'] ?? '', $bugun),
                'ekip_id' => (int) ($_GET['ekip_id'] ?? 0),
                'aparat_tip_id' => (int) ($_GET['aparat_tip_id'] ?? 0),
                'hareket_tipi' => $_GET['hareket_tipi'] ?? '',
                'sahip_tipi' => $_GET['sahip_tipi'] ?? '',
            ];

            aparatYanit(true, '', [
                'data' => $Hareket->listele($filtre, 2000),
                'toplam' => $Hareket->sayisi($filtre),
            ]);
            break;

        // =====================================================
        // HAVUZ HAREKETLERİ (depo / hurda / kayıp / açılış)
        // =====================================================
        case 'havuz-hareketi':
            aparatYetkiKontrol('aparat_depo');

            $tur = $_POST['tur'] ?? '';
            $Servis = new AparatStokService();
            $sonuc = $Servis->havuzHareketi($tur, [
                'aparat_tip_id' => (int) ($_POST['aparat_tip_id'] ?? 0),
                'adet' => (int) ($_POST['adet'] ?? 0),
                'ekip_id' => (int) ($_POST['ekip_id'] ?? 0),
                'aciklama' => trim((string) ($_POST['aciklama'] ?? '')),
            ], $userId);

            aparatYanit(true, $sonuc['negatif']
                ? 'Hareket işlendi. Dikkat: ilgili havuz eksiye düştü.'
                : 'Hareket işlendi.');
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

            aparatYanit(true, '', [
                'data' => $sayim,
                'detaylar' => $Sayim->detaylar($sayimId, (int) ($_GET['ekip_id'] ?? 0) ?: null),
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

            aparatYanit(true, 'Sayım adedi kaydedildi.');
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
                    aparatYanit(false, 'Farklı çıkan her satır için açıklama girilmelidir.');
                }
            }

            $Servis = new AparatStokService();
            $islenen = 0;
            foreach ($farklar as $detay) {
                $Servis->sayimFarkiIsle((int) $detay['id'], $userId);
                $islenen++;
            }

            aparatYanit(true, "$islenen satırdaki fark stoğa işlendi.");
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

            if (!$Sayim->iptalEt((int) ($_POST['sayim_id'] ?? 0))) {
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
