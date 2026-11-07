<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\IkIlanModel;
use App\Model\PermissionPolicyModel;
use App\Model\SiteAyarModel;
use App\Model\SiteHizmetModel;
use App\Model\SiteReferansModel;
use App\Model\SiteSssModel;
use App\Model\SiteIstatistikModel;
use App\Model\SiteMesajModel;
use App\Model\SiteProjeModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Service\SiteGorselService;

$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

$siteActions = [
    'dashboard-data' => 'site_dashboard',
    'hizmet-ajax-list' => 'site_hizmet_yonetimi',
    'hizmet-getir' => 'site_hizmet_yonetimi',
    'hizmet-kaydet' => 'site_hizmet_yonetimi',
    'hizmet-durum' => 'site_hizmet_yonetimi',
    'hizmet-sil' => 'site_hizmet_yonetimi',
    'referans-ajax-list' => 'site_referans_yonetimi',
    'referans-getir' => 'site_referans_yonetimi',
    'referans-kaydet' => 'site_referans_yonetimi',
    'referans-durum' => 'site_referans_yonetimi',
    'referans-sil' => 'site_referans_yonetimi',
    'sss-ajax-list' => 'site_sss_yonetimi',
    'sss-getir' => 'site_sss_yonetimi',
    'sss-kaydet' => 'site_sss_yonetimi',
    'sss-durum' => 'site_sss_yonetimi',
    'sss-sil' => 'site_sss_yonetimi',
    'istatistik-ajax-list' => 'site_istatistik_yonetimi',
    'istatistik-getir' => 'site_istatistik_yonetimi',
    'istatistik-kaydet' => 'site_istatistik_yonetimi',
    'istatistik-durum' => 'site_istatistik_yonetimi',
    'istatistik-sil' => 'site_istatistik_yonetimi',
    'ayar-getir' => 'site_ayar_yonetimi',
    'ayar-kaydet' => 'site_ayar_yonetimi',
    'proje-ajax-list' => 'site_proje_yonetimi',
    'proje-getir' => 'site_proje_yonetimi',
    'proje-kaydet' => 'site_proje_yonetimi',
    'proje-durum' => 'site_proje_yonetimi',
    'proje-sil' => 'site_proje_yonetimi',
    'mesaj-ajax-list' => 'site_mesaj_yonetimi',
    'mesaj-getir' => 'site_mesaj_yonetimi',
    'mesaj-durum' => 'site_mesaj_yonetimi',
    'mesaj-sil' => 'site_mesaj_yonetimi',
];

$cevap = static function (array $veri, int $kod = 200): void {
    http_response_code($kod);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
};

if (!isset($siteActions[$action])) {
    $cevap(['status' => 'error', 'message' => 'Tanımsız işlem.'], 400);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $cevap(['status' => 'error', 'message' => 'Geçersiz istek.'], 405);
}
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || empty($_SESSION['firma_id'])) {
    $cevap(['status' => 'error', 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
}

if ((new PermissionPolicyModel())->isReady()) {
    Gate::authorizeApiPolicy('site-yonetimi/api', $action);
} elseif (!Gate::allows($siteActions[$action])) {
    $cevap(['status' => 'error', 'message' => 'Bu işlem için yetkiniz bulunmamaktadır.'], 403);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

$idCoz = static fn(string $alan): int => (int) Security::decrypt((string) ($_POST[$alan] ?? ''));
$metin = static fn(string $alan, int $limit): string => trim(mb_substr(str_replace("\r", '', (string) ($_POST[$alan] ?? '')), 0, $limit, 'UTF-8'));
$satirlar = static function (string $alan, int $adet, int $uzunluk) use ($metin): string {
    $sonuc = [];
    foreach (explode("\n", $metin($alan, 4000)) as $satir) {
        $satir = trim($satir);
        if ($satir !== '') {
            $sonuc[] = mb_substr($satir, 0, $uzunluk, 'UTF-8');
        }
        if (count($sonuc) >= $adet) {
            break;
        }
    }
    return implode("\n", $sonuc);
};

try {
    $Hizmet = new SiteHizmetModel();
    $Log = new SystemLogModel();

    switch ($action) {
        case 'dashboard-data':
            $hizmetIzin = Gate::allows('site_hizmet_yonetimi');
            $ilanIzin = Gate::allows('ik_ilan_yonetimi');
            $mesajIzin = Gate::allows('site_mesaj_yonetimi');
            $projeIzin = Gate::allows('site_proje_yonetimi');
            $ayarIzin = Gate::allows('site_ayar_yonetimi');
            $icerikIzin = [];
            $icerikOzet = [];
            $icerikModulleri = [
                'referans' => ['site_referans_yonetimi', SiteReferansModel::class],
                'sss' => ['site_sss_yonetimi', SiteSssModel::class],
                'istatistik' => ['site_istatistik_yonetimi', SiteIstatistikModel::class],
            ];
            foreach ($icerikModulleri as $anahtar => [$yetki, $sinif]) {
                $icerikIzin[$anahtar] = Gate::allows($yetki);
                $icerikOzet[$anahtar] = null;
                if ($icerikIzin[$anahtar]) {
                    $ozetSatiri = (new $sinif())->summary();
                    $icerikOzet[$anahtar] = ['toplam' => $ozetSatiri->toplam, 'yayinda' => $ozetSatiri->yayinda, 'taslak' => $ozetSatiri->taslak];
                }
            }
            $projeOzet = null;
            if ($projeIzin) {
                $pz = (new SiteProjeModel())->summary();
                $projeOzet = ['toplam' => $pz->toplam, 'yayinda' => $pz->yayinda, 'taslak' => $pz->taslak];
            }
            $mesajOzet = null;
            if ($mesajIzin) {
                $m = (new SiteMesajModel())->summary();
                $mesajOzet = ['toplam' => $m->toplam, 'yeni' => $m->yeni, 'okundu' => $m->okundu, 'cevaplandi' => $m->cevaplandi];
            }
            $kariyer = null;
            if ($ilanIzin) {
                $ilanOzet = (new IkIlanModel())->summary((int) $_SESSION['firma_id']);
                $kariyer = ['toplam' => (int) $ilanOzet->toplam, 'yayinda' => (int) $ilanOzet->yayinda, 'taslak' => (int) $ilanOzet->taslak];
            }
            $hizmet = null;
            $sonHizmetler = [];
            $islemler = [];
            if ($hizmetIzin) {
                $ozet = $Hizmet->summary();
                $hizmet = [
                    'toplam' => (int) $ozet->toplam,
                    'yayinda' => (int) $ozet->yayinda,
                    'taslak' => (int) $ozet->taslak,
                    'gorselsiz' => (int) $ozet->gorselsiz,
                ];
                $sonHizmetler = $Hizmet->sonGuncellenenler(5);
                foreach ($Log->getRecentByActionType('Site Hizmeti', (int) $_SESSION['firma_id'], 8) as $kayit) {
                    $islemler[] = [
                        'aciklama' => (string) $kayit->description,
                        'kullanici' => $kayit->adi_soyadi,
                        'tarih' => $kayit->created_at,
                    ];
                }
            }
            $cevap([
                'status' => 'success',
                'izin' => ['hizmet' => $hizmetIzin, 'ilan' => $ilanIzin, 'mesaj' => $mesajIzin, 'proje' => $projeIzin, 'ayar' => $ayarIzin] + $icerikIzin,
                'icerik' => $icerikOzet,
                'proje' => $projeOzet,
                'mesaj' => $mesajOzet,
                'hizmet' => $hizmet,
                'son_hizmetler' => $sonHizmetler,
                'son_islemler' => $islemler,
                'kariyer' => $kariyer,
            ]);

        case 'ayar-getir':
            $cevap(['status' => 'success', 'data' => (new SiteAyarModel())->tumu()]);

        case 'ayar-kaydet':
            $temiz = [];
            $hatalar = [];
            foreach (SiteAyarModel::SEMA as $anahtar => $kural) {
                if (!array_key_exists($anahtar, $_POST)) {
                    continue;
                }
                [$gecerli, $deger, $hata] = SiteAyarModel::temizle($anahtar, (string) $_POST[$anahtar]);
                if (!$gecerli) {
                    $hatalar[$anahtar] = $hata;
                    continue;
                }
                $temiz[$anahtar] = $deger;
            }
            if ($hatalar) {
                $cevap(['status' => 'error', 'message' => 'Lütfen işaretli alanları kontrol edin.', 'hatalar' => $hatalar], 422);
            }
            (new SiteAyarModel())->kaydet($temiz, $userId);
            $Log->logAction($userId, 'Site Ayarları', 'Site ayarları güncellendi (' . count($temiz) . ' alan)', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Site ayarları kaydedildi.']);

        case 'proje-ajax-list':
            $Proje = new SiteProjeModel();
            $cevap($Proje->ajaxList($_POST) + ['summary' => $Proje->summary()]);

        case 'proje-getir':
            $pr = (new SiteProjeModel())->bul($idCoz('id'));
            if (!$pr) {
                $cevap(['status' => 'error', 'message' => 'Proje bulunamadı.'], 404);
            }
            $cevap(['status' => 'success', 'data' => [
                'id' => Security::encrypt((int) $pr->id),
                'baslik' => $pr->baslik,
                'slug' => $pr->slug,
                'kategori' => $pr->kategori,
                'musteri' => $pr->musteri,
                'konum' => $pr->konum,
                'proje_yili' => $pr->proje_yili,
                'kisa_aciklama' => $pr->kisa_aciklama,
                'aciklama' => $pr->aciklama,
                'gorsel' => $pr->gorsel,
                'sira' => (int) $pr->sira,
                'durum' => $pr->durum,
            ]]);

        case 'proje-kaydet':
            $Proje = new SiteProjeModel();
            $id = ($_POST['id'] ?? '') !== '' ? $idCoz('id') : 0;
            $mevcut = $id > 0 ? $Proje->bul($id) : null;
            if ($id > 0 && !$mevcut) {
                $cevap(['status' => 'error', 'message' => 'Proje bulunamadı.'], 404);
            }
            $baslik = $metin('baslik', 200);
            if ($baslik === '') {
                $cevap(['status' => 'error', 'message' => 'Proje başlığı zorunludur.']);
            }
            $durum = (string) ($_POST['durum'] ?? 'taslak');
            if (!isset(SiteProjeModel::DURUMLAR[$durum])) {
                $durum = 'taslak';
            }
            $slug = SiteHizmetModel::slugOlustur($metin('slug', 150) !== '' ? $metin('slug', 150) : $baslik);
            if ($slug === '') {
                $cevap(['status' => 'error', 'message' => 'Sayfa adresi oluşturulamadı; lütfen geçerli bir başlık veya adres girin.']);
            }
            if ($Proje->slugKullanimda($slug, $id)) {
                $cevap(['status' => 'error', 'message' => 'Bu sayfa adresi başka bir projede kullanılıyor.']);
            }
            $yil = $metin('proje_yili', 4);
            if ($yil !== '' && (!ctype_digit($yil) || (int) $yil < 1990 || (int) $yil > 2100)) {
                $cevap(['status' => 'error', 'message' => 'Proje yılı 1990 ile 2100 arasında olmalıdır.']);
            }

            $veri = [
                'id' => $id,
                'slug' => $slug,
                'baslik' => $baslik,
                'kategori' => $metin('kategori', 100),
                'musteri' => $metin('musteri', 150),
                'konum' => $metin('konum', 150),
                'proje_yili' => $yil,
                'kisa_aciklama' => $metin('kisa_aciklama', 300),
                'aciklama' => $metin('aciklama', 8000),
                'sira' => max(0, min(9999, (int) ($_POST['sira'] ?? 0))),
                'durum' => $durum,
            ];

            $eskiGorsel = $mevcut->gorsel ?? null;
            if (isset($_FILES['gorsel']) && (int) ($_FILES['gorsel']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $yukleme = SiteGorselService::kaydet($_FILES['gorsel'], 'projeler');
                if (!$yukleme['ok']) {
                    $cevap(['status' => 'error', 'message' => $yukleme['mesaj']]);
                }
                $veri['gorsel'] = $yukleme['yol'];
            } elseif ($id > 0 && ($_POST['gorsel_kaldir'] ?? '0') === '1') {
                $veri['gorsel'] = null;
            }

            $kayitId = $Proje->kaydet($veri, $userId);
            if (array_key_exists('gorsel', $veri) && $eskiGorsel && $eskiGorsel !== $veri['gorsel']) {
                SiteGorselService::sil($eskiGorsel);
            }
            $Log->logAction($userId, 'Site Projesi', ($id > 0 ? 'Proje güncellendi: ' : 'Proje oluşturuldu: ') . $baslik . ' (Proje ID: ' . $kayitId . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Proje kaydedildi.', 'id' => Security::encrypt($kayitId)]);

        case 'proje-durum':
            $id = $idCoz('id');
            $durum = (string) ($_POST['durum'] ?? '');
            if (!(new SiteProjeModel())->durumGuncelle($id, $durum)) {
                $cevap(['status' => 'error', 'message' => 'Durum güncellenemedi.']);
            }
            $Log->logAction($userId, 'Site Projesi', 'Proje durumu değişti (Proje ID: ' . $id . ') → ' . $durum, SystemLogModel::LEVEL_INFO);
            $cevap(['status' => 'success', 'message' => 'Proje durumu güncellendi.']);

        case 'proje-sil':
            $id = $idCoz('id');
            if (!(new SiteProjeModel())->softDelete($id)) {
                $cevap(['status' => 'error', 'message' => 'Proje silinemedi.']);
            }
            $Log->logAction($userId, 'Site Projesi', 'Proje silindi (Proje ID: ' . $id . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Proje silindi.']);

        case 'referans-ajax-list':
            $Referans = new SiteReferansModel();
            $cevap($Referans->ajaxList($_POST) + ['summary' => $Referans->summary()]);

        case 'referans-getir':
            $kayit = (new SiteReferansModel())->bul($idCoz('id'));
            if (!$kayit) {
                $cevap(['status' => 'error', 'message' => 'Kayıt bulunamadı.'], 404);
            }
            $cevap(['status' => 'success', 'data' => [
                'id' => Security::encrypt((int) $kayit->id),
                'kurum' => $kayit->kurum,
                'kisi' => $kayit->kisi,
                'unvan' => $kayit->unvan,
                'yorum' => $kayit->yorum,
                'logo' => $kayit->logo,
                'sira' => (int) $kayit->sira,
                'durum' => $kayit->durum,
            ]]);

        case 'referans-kaydet':
            $Referans = new SiteReferansModel();
            $id = ($_POST['id'] ?? '') !== '' ? $idCoz('id') : 0;
            $mevcut = $id > 0 ? $Referans->bul($id) : null;
            if ($id > 0 && !$mevcut) {
                $cevap(['status' => 'error', 'message' => 'Kayıt bulunamadı.'], 404);
            }
            if ($metin('kurum', 150) === '') {
                $cevap(['status' => 'error', 'message' => 'Kurum / Müşteri zorunludur.']);
            }
            if ($metin('yorum', 1500) === '') {
                $cevap(['status' => 'error', 'message' => 'Yorum zorunludur.']);
            }
            $durum = (string) ($_POST['durum'] ?? 'taslak');
            if (!isset(SiteReferansModel::DURUMLAR[$durum])) {
                $durum = 'taslak';
            }
            $veri = [
                'id' => $id,
                'kurum' => $metin('kurum', 150),
                'kisi' => $metin('kisi', 150),
                'unvan' => $metin('unvan', 150),
                'yorum' => $metin('yorum', 1500),
                'sira' => max(0, min(9999, (int) ($_POST['sira'] ?? 0))),
                'durum' => $durum,
            ];

            $eskiGorsel = $mevcut->logo ?? null;
            if (isset($_FILES['gorsel']) && (int) ($_FILES['gorsel']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $yukleme = SiteGorselService::kaydet($_FILES['gorsel'], 'referanslar');
                if (!$yukleme['ok']) {
                    $cevap(['status' => 'error', 'message' => $yukleme['mesaj']]);
                }
                $veri['logo'] = $yukleme['yol'];
            } elseif ($id > 0 && ($_POST['gorsel_kaldir'] ?? '0') === '1') {
                $veri['logo'] = null;
            }

            $kayitId = $Referans->kaydet($veri, $userId);
            if (array_key_exists('logo', $veri) && $eskiGorsel && $eskiGorsel !== $veri['logo']) {
                SiteGorselService::sil($eskiGorsel);
            }
            $Log->logAction($userId, 'Site Referans', ($id > 0 ? 'Referans güncellendi: ' : 'Referans oluşturuldu: ') . $veri['kurum'] . ' (Kayıt ID: ' . $kayitId . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Kayıt kaydedildi.', 'id' => Security::encrypt($kayitId)]);

        case 'referans-durum':
            $id = $idCoz('id');
            $durum = (string) ($_POST['durum'] ?? '');
            if (!(new SiteReferansModel())->durumGuncelle($id, $durum)) {
                $cevap(['status' => 'error', 'message' => 'Durum güncellenemedi.']);
            }
            $Log->logAction($userId, 'Site Referans', 'Referans durumu değişti (Kayıt ID: ' . $id . ') → ' . $durum, SystemLogModel::LEVEL_INFO);
            $cevap(['status' => 'success', 'message' => 'Durum güncellendi.']);

        case 'referans-sil':
            $id = $idCoz('id');
            if (!(new SiteReferansModel())->softDelete($id)) {
                $cevap(['status' => 'error', 'message' => 'Kayıt silinemedi.']);
            }
            $Log->logAction($userId, 'Site Referans', 'Referans silindi (Kayıt ID: ' . $id . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Kayıt silindi.']);

        case 'sss-ajax-list':
            $Sss = new SiteSssModel();
            $cevap($Sss->ajaxList($_POST) + ['summary' => $Sss->summary()]);

        case 'sss-getir':
            $kayit = (new SiteSssModel())->bul($idCoz('id'));
            if (!$kayit) {
                $cevap(['status' => 'error', 'message' => 'Kayıt bulunamadı.'], 404);
            }
            $cevap(['status' => 'success', 'data' => [
                'id' => Security::encrypt((int) $kayit->id),
                'soru' => $kayit->soru,
                'cevap' => $kayit->cevap,
                'sira' => (int) $kayit->sira,
                'durum' => $kayit->durum,
            ]]);

        case 'sss-kaydet':
            $Sss = new SiteSssModel();
            $id = ($_POST['id'] ?? '') !== '' ? $idCoz('id') : 0;
            $mevcut = $id > 0 ? $Sss->bul($id) : null;
            if ($id > 0 && !$mevcut) {
                $cevap(['status' => 'error', 'message' => 'Kayıt bulunamadı.'], 404);
            }
            if ($metin('soru', 300) === '') {
                $cevap(['status' => 'error', 'message' => 'Soru zorunludur.']);
            }
            if ($metin('cevap', 3000) === '') {
                $cevap(['status' => 'error', 'message' => 'Cevap zorunludur.']);
            }
            $durum = (string) ($_POST['durum'] ?? 'taslak');
            if (!isset(SiteSssModel::DURUMLAR[$durum])) {
                $durum = 'taslak';
            }
            $veri = [
                'id' => $id,
                'soru' => $metin('soru', 300),
                'cevap' => $metin('cevap', 3000),
                'sira' => max(0, min(9999, (int) ($_POST['sira'] ?? 0))),
                'durum' => $durum,
            ];

            $kayitId = $Sss->kaydet($veri, $userId);
            $Log->logAction($userId, 'Site Soru', ($id > 0 ? 'Soru güncellendi: ' : 'Soru oluşturuldu: ') . $veri['soru'] . ' (Kayıt ID: ' . $kayitId . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Kayıt kaydedildi.', 'id' => Security::encrypt($kayitId)]);

        case 'sss-durum':
            $id = $idCoz('id');
            $durum = (string) ($_POST['durum'] ?? '');
            if (!(new SiteSssModel())->durumGuncelle($id, $durum)) {
                $cevap(['status' => 'error', 'message' => 'Durum güncellenemedi.']);
            }
            $Log->logAction($userId, 'Site Soru', 'Soru durumu değişti (Kayıt ID: ' . $id . ') → ' . $durum, SystemLogModel::LEVEL_INFO);
            $cevap(['status' => 'success', 'message' => 'Durum güncellendi.']);

        case 'sss-sil':
            $id = $idCoz('id');
            if (!(new SiteSssModel())->softDelete($id)) {
                $cevap(['status' => 'error', 'message' => 'Kayıt silinemedi.']);
            }
            $Log->logAction($userId, 'Site Soru', 'Soru silindi (Kayıt ID: ' . $id . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Kayıt silindi.']);

        case 'istatistik-ajax-list':
            $Istatistik = new SiteIstatistikModel();
            $cevap($Istatistik->ajaxList($_POST) + ['summary' => $Istatistik->summary()]);

        case 'istatistik-getir':
            $kayit = (new SiteIstatistikModel())->bul($idCoz('id'));
            if (!$kayit) {
                $cevap(['status' => 'error', 'message' => 'Kayıt bulunamadı.'], 404);
            }
            $cevap(['status' => 'success', 'data' => [
                'id' => Security::encrypt((int) $kayit->id),
                'etiket' => $kayit->etiket,
                'deger' => $kayit->deger,
                'sonek' => $kayit->sonek,
                'sira' => (int) $kayit->sira,
                'durum' => $kayit->durum,
            ]]);

        case 'istatistik-kaydet':
            $Istatistik = new SiteIstatistikModel();
            $id = ($_POST['id'] ?? '') !== '' ? $idCoz('id') : 0;
            $mevcut = $id > 0 ? $Istatistik->bul($id) : null;
            if ($id > 0 && !$mevcut) {
                $cevap(['status' => 'error', 'message' => 'Kayıt bulunamadı.'], 404);
            }
            if ($metin('etiket', 100) === '') {
                $cevap(['status' => 'error', 'message' => 'Etiket zorunludur.']);
            }
            $durum = (string) ($_POST['durum'] ?? 'taslak');
            if (!isset(SiteIstatistikModel::DURUMLAR[$durum])) {
                $durum = 'taslak';
            }
            $veri = [
                'id' => $id,
                'etiket' => $metin('etiket', 100),
                'deger' => max(0, min(99999999, (int) ($_POST['deger'] ?? 0))),
                'sonek' => $metin('sonek', 10),
                'sira' => max(0, min(9999, (int) ($_POST['sira'] ?? 0))),
                'durum' => $durum,
            ];

            $kayitId = $Istatistik->kaydet($veri, $userId);
            $Log->logAction($userId, 'Site İstatistik', ($id > 0 ? 'İstatistik güncellendi: ' : 'İstatistik oluşturuldu: ') . $veri['etiket'] . ' (Kayıt ID: ' . $kayitId . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Kayıt kaydedildi.', 'id' => Security::encrypt($kayitId)]);

        case 'istatistik-durum':
            $id = $idCoz('id');
            $durum = (string) ($_POST['durum'] ?? '');
            if (!(new SiteIstatistikModel())->durumGuncelle($id, $durum)) {
                $cevap(['status' => 'error', 'message' => 'Durum güncellenemedi.']);
            }
            $Log->logAction($userId, 'Site İstatistik', 'İstatistik durumu değişti (Kayıt ID: ' . $id . ') → ' . $durum, SystemLogModel::LEVEL_INFO);
            $cevap(['status' => 'success', 'message' => 'Durum güncellendi.']);

        case 'istatistik-sil':
            $id = $idCoz('id');
            if (!(new SiteIstatistikModel())->softDelete($id)) {
                $cevap(['status' => 'error', 'message' => 'Kayıt silinemedi.']);
            }
            $Log->logAction($userId, 'Site İstatistik', 'İstatistik silindi (Kayıt ID: ' . $id . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Kayıt silindi.']);

        case 'mesaj-ajax-list':
            $Mesaj = new SiteMesajModel();
            $cevap($Mesaj->ajaxList($_POST) + ['summary' => $Mesaj->summary()]);

        case 'mesaj-getir':
            $m = (new SiteMesajModel())->bul($idCoz('id'));
            if (!$m) {
                $cevap(['status' => 'error', 'message' => 'Mesaj bulunamadı.'], 404);
            }
            $cevap(['status' => 'success', 'data' => [
                'id' => Security::encrypt((int) $m->id),
                'ad_soyad' => $m->ad_soyad,
                'telefon' => $m->telefon,
                'email' => $m->email,
                'konu' => $m->konu,
                'mesaj' => $m->mesaj,
                'durum' => $m->durum,
                'yonetici_notu' => $m->yonetici_notu,
                'created_at' => $m->created_at,
            ]]);

        case 'mesaj-durum':
            $id = $idCoz('id');
            $durum = (string) ($_POST['durum'] ?? '');
            $not = $metin('yonetici_notu', 2000);
            if (!(new SiteMesajModel())->durumGuncelle($id, $durum, $userId, $not)) {
                $cevap(['status' => 'error', 'message' => 'Mesaj güncellenemedi.']);
            }
            $Log->logAction($userId, 'Site Mesajı', 'Mesaj durumu değişti (Mesaj ID: ' . $id . ') → ' . $durum, SystemLogModel::LEVEL_INFO);
            $cevap(['status' => 'success', 'message' => 'Mesaj güncellendi.']);

        case 'mesaj-sil':
            $id = $idCoz('id');
            if (!(new SiteMesajModel())->softDelete($id)) {
                $cevap(['status' => 'error', 'message' => 'Mesaj silinemedi.']);
            }
            $Log->logAction($userId, 'Site Mesajı', 'Mesaj silindi (Mesaj ID: ' . $id . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Mesaj silindi.']);

        case 'hizmet-ajax-list':
            $cevap($Hizmet->ajaxList($_POST) + ['summary' => $Hizmet->summary()]);

        case 'hizmet-getir':
            $h = $Hizmet->bul($idCoz('id'));
            if (!$h) {
                $cevap(['status' => 'error', 'message' => 'Hizmet bulunamadı.'], 404);
            }
            $cevap(['status' => 'success', 'data' => [
                'id' => Security::encrypt((int) $h->id),
                'baslik' => $h->baslik,
                'slug' => $h->slug,
                'kisa_aciklama' => $h->kisa_aciklama,
                'aciklama' => $h->aciklama,
                'ozellikler' => $h->ozellikler,
                'adimlar' => $h->adimlar,
                'gorsel' => $h->gorsel,
                'sira' => (int) $h->sira,
                'durum' => $h->durum,
            ]]);

        case 'hizmet-kaydet':
            $id = ($_POST['id'] ?? '') !== '' ? $idCoz('id') : 0;
            $mevcut = $id > 0 ? $Hizmet->bul($id) : null;
            if ($id > 0 && !$mevcut) {
                $cevap(['status' => 'error', 'message' => 'Hizmet bulunamadı.'], 404);
            }
            $baslik = $metin('baslik', 200);
            if ($baslik === '') {
                $cevap(['status' => 'error', 'message' => 'Hizmet başlığı zorunludur.']);
            }
            $durum = (string) ($_POST['durum'] ?? 'taslak');
            if (!isset(SiteHizmetModel::DURUMLAR[$durum])) {
                $durum = 'taslak';
            }
            $slug = SiteHizmetModel::slugOlustur($metin('slug', 150) !== '' ? $metin('slug', 150) : $baslik);
            if ($slug === '') {
                $cevap(['status' => 'error', 'message' => 'Sayfa adresi oluşturulamadı; lütfen geçerli bir başlık veya adres girin.']);
            }
            if ($Hizmet->slugKullanimda($slug, $id)) {
                $cevap(['status' => 'error', 'message' => 'Bu sayfa adresi başka bir hizmette kullanılıyor.']);
            }

            $veri = [
                'id' => $id,
                'slug' => $slug,
                'baslik' => $baslik,
                'kisa_aciklama' => $metin('kisa_aciklama', 300),
                'aciklama' => $metin('aciklama', 6000),
                'ozellikler' => $satirlar('ozellikler', 12, 200),
                'adimlar' => $satirlar('adimlar', 8, 200),
                'sira' => max(0, min(9999, (int) ($_POST['sira'] ?? 0))),
                'durum' => $durum,
            ];

            $eskiGorsel = $mevcut->gorsel ?? null;
            $yeniGorsel = null;
            if (isset($_FILES['gorsel']) && (int) ($_FILES['gorsel']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $yukleme = SiteGorselService::kaydet($_FILES['gorsel']);
                if (!$yukleme['ok']) {
                    $cevap(['status' => 'error', 'message' => $yukleme['mesaj']]);
                }
                $yeniGorsel = $yukleme['yol'];
                $veri['gorsel'] = $yeniGorsel;
            } elseif ($id > 0 && ($_POST['gorsel_kaldir'] ?? '0') === '1') {
                $veri['gorsel'] = null;
            }

            $kayitId = $Hizmet->kaydet($veri, $userId);
            if (array_key_exists('gorsel', $veri) && $eskiGorsel && $eskiGorsel !== $veri['gorsel']) {
                SiteGorselService::sil($eskiGorsel);
            }
            $Log->logAction($userId, 'Site Hizmeti', ($id > 0 ? 'Hizmet güncellendi: ' : 'Hizmet oluşturuldu: ') . $baslik . ' (Hizmet ID: ' . $kayitId . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Hizmet kaydedildi.', 'id' => Security::encrypt($kayitId)]);

        case 'hizmet-durum':
            $id = $idCoz('id');
            $durum = (string) ($_POST['durum'] ?? '');
            if (!$Hizmet->durumGuncelle($id, $durum)) {
                $cevap(['status' => 'error', 'message' => 'Durum güncellenemedi.']);
            }
            $Log->logAction($userId, 'Site Hizmeti', 'Hizmet durumu değişti (Hizmet ID: ' . $id . ') → ' . $durum, SystemLogModel::LEVEL_INFO);
            $cevap(['status' => 'success', 'message' => 'Hizmet durumu güncellendi.']);

        case 'hizmet-sil':
            $id = $idCoz('id');
            if (!$Hizmet->softDelete($id)) {
                $cevap(['status' => 'error', 'message' => 'Hizmet silinemedi.']);
            }
            $Log->logAction($userId, 'Site Hizmeti', 'Hizmet silindi (Hizmet ID: ' . $id . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Hizmet silindi.']);
    }
} catch (Throwable $e) {
    error_log('site-yonetimi/api hatası (' . $action . '): ' . $e->getMessage());
    $cevap(['status' => 'error', 'message' => 'İşlem sırasında bir hata oluştu.'], 500);
}
