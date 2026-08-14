<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Model\EkipGunlukDurumModel;
use App\Model\EkipMahalleAtamaModel;
use App\Model\KesmeAcmaRaporModel;
use App\Model\KesmeNobetModel;
use App\Model\MahalleModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Service\KesmeAcmaPlanService;

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId <= 0 || empty($_SESSION['firma_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum süresi doldu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function kaYanit(bool $ok, string $mesaj = '', array $ek = []): void
{
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $mesaj], $ek), JSON_UNESCAPED_UNICODE);
    exit;
}

function kaIzin(string $izin): bool
{
    static $onbellek = [];
    if (!array_key_exists($izin, $onbellek)) {
        $onbellek[$izin] = Gate::allows($izin) || Gate::isSuperAdmin();
    }
    return $onbellek[$izin];
}

function kaYetkiKontrol(string $izin): void
{
    if (!kaIzin($izin)) {
        kaYanit(false, 'Bu işlem için yetkiniz bulunmuyor.');
    }
}

function kaTarih($deger, ?string $varsayilan = null): string
{
    $deger = trim((string) $deger);
    if ($deger === '') {
        return $varsayilan ?: date('Y-m-d');
    }
    if (strpos($deger, '.') !== false) {
        $parca = explode('.', $deger);
        if (count($parca) === 3) {
            $deger = $parca[2] . '-' . $parca[1] . '-' . $parca[0];
        }
    }
    $ts = strtotime($deger);
    return $ts !== false ? date('Y-m-d', $ts) : ($varsayilan ?: date('Y-m-d'));
}

if (!kaIzin('kesme_acma')) {
    kaYanit(false, 'Bu işlem için yetkiniz yok.');
}

$Mahalle = new MahalleModel();
$Atama = new EkipMahalleAtamaModel();
$Durum = new EkipGunlukDurumModel();
$Rapor = new KesmeAcmaRaporModel();
$Nobet = new KesmeNobetModel();
$Plan = new KesmeAcmaPlanService($Mahalle, $Atama, $Durum, $Rapor, $Nobet);
$Log = new SystemLogModel();

$bugun = date('Y-m-d');

try {
    switch ($action) {

        case 'ozet': {
            $mahalleler = $Mahalle->listele();
            $ekipDurumlari = $Plan->ekipDurumlari($bugun);
            $oneriler = $Plan->oneriler($ekipDurumlari, $mahalleler, $bugun);
            $projeksiyon = $Plan->ayProjeksiyonu($bugun);

            $sayilar = ['atanabilir' => 0, 'bekliyor' => 0, 'mesajsiz' => 0, 'sahada' => 0, 'girilmiyor' => 0];
            foreach ($mahalleler as $mahalle) {
                $sayilar[$mahalle['durum']]++;
            }

            $kalanToplam = 0;
            foreach ($ekipDurumlari as $ekip) {
                $kalanToplam += (int) $ekip['kalan_is'];
            }

            $sahipsiz = $Rapor->sahipsizIsler($projeksiyon['ay_basi'], $bugun);
            $sahipsizToplam = 0;
            foreach ($sahipsiz as $satir) {
                $sahipsizToplam += (int) $satir['acik'];
            }

            $sahaBugun = $Nobet->sahaPlani($bugun, $bugun);
            $telefonBugun = $Nobet->telefonPlani($bugun, $bugun);
            $nobetciEkip = $sahaBugun[$bugun] ?? null;

            kaYanit(true, '', [
                'bugun' => $bugun,
                'nobetci' => $nobetciEkip ? [
                    'ekip_adi' => $nobetciEkip['ekip_adi'],
                    'personel' => $nobetciEkip['personel'],
                ] : null,
                'telefon' => $telefonBugun[$bugun]['adi_soyadi'] ?? null,
                'sayilar' => $sayilar,
                'kalan_is' => $kalanToplam,
                'sahipsiz' => $sahipsizToplam,
                'sahipsiz_liste' => $sahipsiz,
                'projeksiyon' => $projeksiyon,
                'oneriler' => $oneriler,
                'son_aktarim' => $Rapor->sonAktarim(),
            ]);
        }

        case 'mahalle-listesi': {
            $sonZiyaret = $Atama->sonZiyaretHaritasi();
            $mahalleler = $Mahalle->listele();
            foreach ($mahalleler as &$mahalle) {
                $mahalle['son_ziyaret'] = $sonZiyaret[$mahalle['id']] ?? null;
            }
            unset($mahalle);

            kaYanit(true, '', ['mahalleler' => $mahalleler, 'bugun' => $bugun]);
        }

        case 'ekip-listesi': {
            $mahalleler = $Mahalle->listele();
            $ekipDurumlari = $Plan->ekipDurumlari($bugun);
            $oneriler = $Plan->oneriler($ekipDurumlari, $mahalleler, $bugun);

            $oneriHarita = [];
            foreach ($oneriler as $oneri) {
                $oneriHarita[$oneri['ekip_id']] = $oneri;
            }
            foreach ($ekipDurumlari as &$ekip) {
                $ekip['oneri'] = $oneriHarita[$ekip['ekip_id']] ?? null;
            }
            unset($ekip);

            $atanabilir = array_values(array_filter($mahalleler, function ($mahalle) {
                return $mahalle['durum'] === 'atanabilir';
            }));

            kaYanit(true, '', ['ekipler' => $ekipDurumlari, 'atanabilir' => $atanabilir, 'bugun' => $bugun]);
        }

        case 'gecmis-listesi': {
            $ekipId = (int) ($_GET['ekip_id'] ?? 0);
            $ilce = trim((string) ($_GET['ilce'] ?? ''));
            if (!array_key_exists($ilce, MahalleModel::ILCELER)) {
                $ilce = '';
            }

            $kayitlar = $Atama->gecmis($ekipId ?: null, $ilce ?: null);
            foreach ($kayitlar as &$kayit) {
                $bitis = $kayit['bitis'] ?: $bugun;
                $kayit['is_gunu'] = KesmeAcmaPlanService::isGunSay($kayit['baslangic'], $bitis);
                $kayit['aktif'] = $kayit['durum'] === 'aktif';
            }
            unset($kayit);

            $tumu = $ekipId || $ilce ? $Atama->gecmis() : $kayitlar;
            $islemler = [];
            foreach ($tumu as $kayit) {
                $islemler[(int) $kayit['ekip_id']][] = $kayit;
            }

            $sonZiyaret = $Atama->sonZiyaretHaritasi();
            $mahalleler = $Mahalle->listele();
            $ziyaretSatirlari = [];
            $hicGidilmeyen = 0;
            foreach ($mahalleler as $mahalle) {
                if ($mahalle['durum'] === 'girilmiyor') {
                    continue;
                }
                if (isset($sonZiyaret[$mahalle['id']])) {
                    $kayit = $sonZiyaret[$mahalle['id']];
                    $ziyaretSatirlari[] = [
                        'mahalle_adi' => $mahalle['ad'],
                        'ilce' => $mahalle['ilce'],
                        'ekip_adi' => $kayit['ekip_adi'],
                        'tarih' => $kayit['ziyaret_tarihi'],
                        'aktif' => $kayit['durum'] === 'aktif',
                        'gun_once' => (int) floor((strtotime($bugun) - strtotime($kayit['ziyaret_tarihi'])) / 86400),
                    ];
                } else {
                    $hicGidilmeyen++;
                }
            }

            usort($ziyaretSatirlari, function ($a, $b) {
                return [$a['tarih'], $a['mahalle_adi']] <=> [$b['tarih'], $b['mahalle_adi']];
            });

            $tamamlanan = 0;
            $toplamGun = 0;
            foreach ($tumu as $kayit) {
                if ($kayit['durum'] === 'bitti') {
                    $tamamlanan++;
                    $toplamGun += KesmeAcmaPlanService::isGunSay($kayit['baslangic'], $kayit['bitis']);
                }
            }

            $uyeler = $Rapor->ekipUyeHaritasi();
            $gecmisEkipleri = [];
            foreach ($Rapor->ekipler() as $ekip) {
                if (!isset($islemler[(int) $ekip['id']])) {
                    continue;
                }
                $ekip['personel'] = $uyeler[(int) $ekip['id']] ?? '';
                $gecmisEkipleri[] = $ekip;
            }

            kaYanit(true, '', [
                'kayitlar' => $kayitlar,
                'zaman_cizelgesi' => $islemler,
                'ziyaretler' => $ziyaretSatirlari,
                'hic_gidilmeyen' => $hicGidilmeyen,
                'havuz_sayisi' => count($ziyaretSatirlari) + $hicGidilmeyen,
                'tamamlanan' => $tamamlanan,
                'ortalama_gun' => $tamamlanan > 0 ? (int) round($toplamGun / $tamamlanan) : 0,
                'ekipler' => $gecmisEkipleri,
                'bugun' => $bugun,
            ]);
        }

        case 'nobet-plani': {
            $ay = kaTarih($_GET['ay'] ?? '', $bugun);
            $ayBasi = date('Y-m-01', strtotime($ay));
            $aySonu = date('Y-m-t', strtotime($ay));

            $havuz = $Plan->nobetEkipleri();
            $uyeler = $Rapor->ekipUyeHaritasi();
            $ekipler = [];
            foreach ($Rapor->ekipler() as $ekip) {
                if (!in_array((int) $ekip['id'], $havuz, true)) {
                    continue;
                }
                $ekip['personel'] = $uyeler[(int) $ekip['id']] ?? '';
                $ekipler[] = $ekip;
            }

            $saha = $Nobet->sahaPlani($ayBasi, $aySonu);
            $sahaPersonelleri = $Nobet->sahaPersonelleri(max($ayBasi, $bugun), $havuz);

            kaYanit(true, '', [
                'ay_basi' => $ayBasi,
                'ay_sonu' => $aySonu,
                'saha' => $saha,
                'ilce' => $Nobet->ilcePlani(KesmeAcmaPlanService::haftaBasi($ayBasi), $aySonu),
                'telefon' => $Nobet->telefonPlani($ayBasi, $aySonu),
                'ekipler' => $ekipler,
                'saha_personelleri' => $sahaPersonelleri,
                'aracli_ekipler' => $Nobet->sirketAracliEkipler(),
                'personeller' => $Nobet->telefonHavuzu(),
                'bugun' => $bugun,
            ]);
        }

        case 'matris': {
            $baslangic = kaTarih($_GET['baslangic'] ?? '', date('Y-m-01'));
            $bitis = kaTarih($_GET['bitis'] ?? '', $bugun);
            if ($bitis < $baslangic) {
                [$baslangic, $bitis] = [$bitis, $baslangic];
            }

            $ekipler = $Rapor->ekipler();
            $uyeler = $Rapor->ekipUyeHaritasi();
            foreach ($ekipler as &$ekip) {
                $ekip['personel'] = $uyeler[(int) $ekip['id']] ?? '';
            }
            unset($ekip);

            kaYanit(true, '', [
                'baslangic' => $baslangic,
                'bitis' => $bitis,
                'ekipler' => $ekipler,
                'sonuclar' => $Rapor->sonuclar($baslangic, $bitis),
                'matris' => $Rapor->matris($baslangic, $bitis),
                'son_aktarim' => $Rapor->sonAktarim(),
            ]);
        }

        case 'mahalle-kaydet': {
            kaYetkiKontrol('kesme_mahalle_tanim');

            $id = (int) ($_POST['id'] ?? 0);
            $ad = trim((string) ($_POST['ad'] ?? ''));
            $ilce = (string) ($_POST['ilce'] ?? '');
            $kod = trim((string) ($_POST['kod_araligi'] ?? ''));
            $havuzda = (int) ($_POST['havuzda'] ?? 1) === 1 ? 1 : 0;

            if ($ad === '' || !array_key_exists($ilce, MahalleModel::ILCELER)) {
                kaYanit(false, 'Mahalle adı ve ilçe zorunludur.');
            }

            $veri = ['ad' => $ad, 'ilce' => $ilce, 'kod_araligi' => $kod, 'havuzda' => $havuzda];

            if ($id > 0) {
                if (!$Mahalle->bul($id)) {
                    kaYanit(false, 'Mahalle bulunamadı.');
                }
                $Mahalle->guncelle($id, $veri);
                $Log->logAction($userId, 'Mahalle Güncellendi', "#$id $ad", SystemLogModel::LEVEL_IMPORTANT);
                kaYanit(true, 'Mahalle güncellendi.');
            }

            $yeniId = $Mahalle->ekle($veri);
            $Log->logAction($userId, 'Mahalle Eklendi', "#$yeniId $ad", SystemLogModel::LEVEL_IMPORTANT);
            kaYanit(true, 'Mahalle eklendi.', ['id' => $yeniId]);
        }

        case 'mahalle-havuz': {
            kaYetkiKontrol('kesme_mahalle_tanim');

            $id = (int) ($_POST['id'] ?? 0);
            $havuzda = (int) ($_POST['havuzda'] ?? 1) === 1 ? 1 : 0;
            $mahalle = $Mahalle->bul($id);
            if (!$mahalle) {
                kaYanit(false, 'Mahalle bulunamadı.');
            }

            $Mahalle->havuzDurumu($id, $havuzda);
            $Log->logAction($userId, $havuzda ? 'Mahalle Havuza Alındı' : 'Mahalle Havuz Dışına Alındı',
                "#$id {$mahalle['ad']}", SystemLogModel::LEVEL_IMPORTANT);
            kaYanit(true, $havuzda ? 'Mahalle havuza alındı.' : 'Mahalle havuz dışına alındı.');
        }

        case 'mesaj-kaydet': {
            kaYetkiKontrol('kesme_mesaj');

            $id = (int) ($_POST['mahalle_id'] ?? 0);
            $tarih = kaTarih($_POST['mesaj_tarihi'] ?? '', $bugun);
            $mahalle = $Mahalle->bul($id);
            if (!$mahalle) {
                kaYanit(false, 'Mahalle bulunamadı.');
            }
            if ((int) $mahalle['havuzda'] === 0) {
                kaYanit(false, 'Girilmiyor işaretli mahalleye mesaj kaydı yapılamaz.');
            }
            if ($tarih > $bugun) {
                kaYanit(false, 'Mesaj tarihi ileri tarihli olamaz.');
            }

            $sonuc = $Mahalle->mesajKaydet($id, $tarih, $userId);
            $Log->logAction($userId, 'Mahalle Mesajı Kaydedildi',
                "#$id {$mahalle['ad']} · mesaj {$sonuc['mesaj_tarihi']} · hazır {$sonuc['hazir_tarihi']}",
                SystemLogModel::LEVEL_IMPORTANT);
            kaYanit(true, 'Mesaj kaydedildi. Mahalle ' . date('d.m.Y', strtotime($sonuc['hazir_tarihi'])) . ' tarihinde atanabilir olacak.', $sonuc);
        }

        case 'atama-yap': {
            kaYetkiKontrol('kesme_atama');

            $ekipId = (int) ($_POST['ekip_id'] ?? 0);
            $mahalleId = (int) ($_POST['mahalle_id'] ?? 0);
            $baslangic = kaTarih($_POST['baslangic'] ?? '', $bugun);

            $mahalle = $Mahalle->bul($mahalleId);
            if (!$mahalle || $ekipId <= 0) {
                kaYanit(false, 'Ekip ve mahalle seçilmelidir.');
            }
            if ((int) $mahalle['havuzda'] === 0) {
                kaYanit(false, 'Girilmiyor işaretli mahalle ekibe atanamaz.');
            }
            if ($Atama->mahalleAktifMi($mahalleId)) {
                kaYanit(false, 'Bu mahallede zaten bir ekip çalışıyor.');
            }

            $liste = $Mahalle->listele();
            $secilen = null;
            foreach ($liste as $satir) {
                if ($satir['id'] === $mahalleId) {
                    $secilen = $satir;
                    break;
                }
            }
            if ($secilen && $secilen['durum'] === 'bekliyor' && empty($_POST['zorla'])) {
                kaYanit(false, 'Mahalle henüz atanabilir değil; mesajdan 5 gün geçmesi gerekiyor (hazır: '
                    . date('d.m.Y', strtotime($secilen['hazir_tarihi'])) . ').');
            }
            if ($secilen && $secilen['durum'] === 'mesajsiz' && empty($_POST['zorla'])) {
                kaYanit(false, 'Mahalleye önce mesaj atılmalıdır.');
            }

            $atamaId = $Atama->ata($ekipId, $mahalleId, $baslangic, $userId);
            $Log->logAction($userId, 'Mahalle Ekibe Atandı',
                "#$atamaId ekip:$ekipId mahalle:{$mahalle['ad']} başlangıç:$baslangic",
                SystemLogModel::LEVEL_IMPORTANT);
            kaYanit(true, 'Mahalle ekibe atandı.', ['id' => $atamaId]);
        }

        case 'atama-kapat': {
            kaYetkiKontrol('kesme_atama');

            $atamaId = (int) ($_POST['id'] ?? 0);
            $bitis = kaTarih($_POST['bitis'] ?? '', $bugun);
            $atama = $Atama->bul($atamaId);
            if (!$atama) {
                kaYanit(false, 'Atama bulunamadı.');
            }
            if ($bitis < $atama['baslangic']) {
                kaYanit(false, 'Bitiş tarihi başlangıçtan önce olamaz.');
            }

            $Atama->kapat($atamaId, $bitis);
            $Log->logAction($userId, 'Mahalle Ataması Kapatıldı',
                "#$atamaId {$atama['mahalle_adi']} bitiş:$bitis", SystemLogModel::LEVEL_IMPORTANT);
            kaYanit(true, 'Atama kapatıldı.');
        }

        case 'atama-sil': {
            kaYetkiKontrol('kesme_atama');

            $atamaId = (int) ($_POST['id'] ?? 0);
            $atama = $Atama->bul($atamaId);
            if (!$atama) {
                kaYanit(false, 'Atama bulunamadı.');
            }

            $Atama->sil($atamaId);
            $Log->logAction($userId, 'Mahalle Ataması Silindi',
                "#$atamaId {$atama['mahalle_adi']}", SystemLogModel::LEVEL_CRITICAL);
            kaYanit(true, 'Atama kaydı silindi.');
        }

        case 'kalan-is-kaydet': {
            kaYetkiKontrol('kesme_kalan_is');

            $ekipId = (int) ($_POST['ekip_id'] ?? 0);
            $tarih = kaTarih($_POST['tarih'] ?? '', $bugun);
            $kalan = (int) ($_POST['kalan_is'] ?? -1);

            if ($ekipId <= 0 || $kalan < 0) {
                kaYanit(false, 'Ekip ve kalan iş sayısı geçerli olmalıdır.');
            }

            $Durum->kaydet($ekipId, $tarih, $kalan, $userId);
            $Log->logAction($userId, 'Kalan İş Girişi', "ekip:$ekipId tarih:$tarih kalan:$kalan");

            $ekipDurumlari = $Plan->ekipDurumlari($bugun);
            $guncel = null;
            foreach ($ekipDurumlari as $satir) {
                if ($satir['ekip_id'] === $ekipId) {
                    $guncel = $satir;
                    break;
                }
            }

            kaYanit(true, 'Kalan iş kaydedildi.', ['ekip' => $guncel]);
        }

        case 'nobet-saha-yaz': {
            kaYetkiKontrol('kesme_nobet');

            $tarih = kaTarih($_POST['tarih'] ?? '', $bugun);
            $personelId = (int) ($_POST['personel_id'] ?? 0);

            if ($tarih < $bugun) {
                kaYanit(false, 'Geçmiş tarihli nöbet kayıtları bu ekrandan değiştirilemez.');
            }
            if ($personelId > 0) {
                $izinliPersoneller = array_map('intval', array_column(
                    $Nobet->sahaPersonelleri($tarih, $Plan->nobetEkipleri()),
                    'id'
                ));
                if (!in_array($personelId, $izinliPersoneller, true)) {
                    kaYanit(false, 'Seçilen personel bu tarihte kesme/açma nöbet havuzunda değil.');
                }
            }

            $Nobet->sahaYaz($tarih, $personelId ?: null, true, $userId);
            $Log->logAction($userId, $personelId > 0 ? 'Saha Nöbeti Değiştirildi' : 'Saha Nöbeti Kaldırıldı', "tarih:$tarih personel:$personelId", SystemLogModel::LEVEL_IMPORTANT);
            kaYanit(true, $personelId > 0 ? 'Nöbet güncellendi.' : 'Nöbet kaldırıldı.');
        }

        case 'nobet-ilce-yaz': {
            kaYetkiKontrol('kesme_nobet');

            $hafta = kaTarih($_POST['hafta_basi'] ?? '', $bugun);
            $ilce = (string) ($_POST['ilce'] ?? '');
            $ekipId = (int) ($_POST['ekip_id'] ?? 0);

            if (!array_key_exists($ilce, KesmeNobetModel::ILCELER) || $ekipId <= 0) {
                kaYanit(false, 'İlçe ve ekip seçilmelidir.');
            }

            $Nobet->ilceYaz(KesmeAcmaPlanService::haftaBasi($hafta), $ilce, $ekipId, true, $userId);
            $Log->logAction($userId, 'İlçe Görevi Değiştirildi', "hafta:$hafta ilçe:$ilce ekip:$ekipId", SystemLogModel::LEVEL_IMPORTANT);
            kaYanit(true, 'İlçe görevi güncellendi.');
        }

        case 'nobet-telefon-yaz': {
            kaYetkiKontrol('kesme_nobet');

            $tarih = kaTarih($_POST['tarih'] ?? '', $bugun);
            $personelId = (int) ($_POST['personel_id'] ?? 0);

            if ($tarih < $bugun) {
                kaYanit(false, 'Geçmiş tarihli telefon nöbetleri değiştirilemez.');
            }

            $Nobet->telefonYaz($tarih, $personelId ?: null, true, $userId);
            $Log->logAction($userId, $personelId > 0 ? 'Telefon Nöbeti Değiştirildi' : 'Telefon Nöbeti Kaldırıldı', "tarih:$tarih personel:$personelId");
            kaYanit(true, $personelId > 0 ? 'Telefon nöbeti güncellendi.' : 'Telefon nöbeti kaldırıldı.');
        }

        case 'nobet-uret': {
            kaYetkiKontrol('kesme_nobet');

            $haftaBasi = KesmeAcmaPlanService::haftaBasi(kaTarih($_POST['hafta_basi'] ?? '', $bugun));
            $haftaSonu = date('Y-m-d', strtotime($haftaBasi . ' +6 day'));

            if ($haftaSonu < $bugun) {
                kaYanit(false, 'Geçmiş hafta için plan üretilmez; nöbetler yalnızca bugünden itibaren oluşturulur.');
            }

            $ilce = $Plan->ilcePlaniUret($haftaBasi, $haftaSonu, $userId);
            $saha = $Plan->sahaPlaniUret($haftaBasi, $haftaSonu, $userId);
            $telefon = $Plan->telefonPlaniUret($haftaBasi, $haftaSonu, $userId);

            $Log->logAction($userId, 'Nöbet Planı Üretildi',
                "hafta:$haftaBasi saha:$saha ilçe:$ilce telefon:$telefon", SystemLogModel::LEVEL_IMPORTANT);

            $etiket = date('d.m.Y', strtotime($haftaBasi)) . ' – ' . date('d.m.Y', strtotime($haftaSonu));
            $mesaj = "$etiket haftası: $saha gün saha nöbeti, $ilce ilçe görevi";
            $mesaj .= $telefon > 0
                ? ", $telefon gün telefon nöbeti."
                : '. Telefon nöbeti yazılmadı: personel kartlarında "Telefon Nöbeti Tutar" ayarı işaretli kimse yok.';

            if ($haftaBasi < $bugun) {
                $mesaj .= ' Haftanın geçmiş günlerine dokunulmadı.';
            }

            kaYanit(true, $mesaj, ['saha' => $saha, 'ilce' => $ilce, 'telefon' => $telefon, 'hafta_basi' => $haftaBasi]);
        }

        default:
            kaYanit(false, 'Geçersiz istek.');
    }
} catch (\Throwable $e) {
    error_log('kesme-acma api hatası (' . $action . '): ' . $e->getMessage());
    kaYanit(false, 'İşlem sırasında bir hata oluştu.');
}
