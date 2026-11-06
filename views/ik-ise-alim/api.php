<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\IkBasvuruModel;
use App\Model\IkIlanModel;
use App\Model\PermissionPolicyModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Service\IkBasvuruAiService;
use App\Service\IkBasvuruDonusturService;

$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

$ikActions = [
    'dashboard-data' => 'ik_ise_alim_dashboard',
    'ilan-ajax-list' => 'ik_ilan_yonetimi',
    'ilan-getir' => 'ik_ilan_yonetimi',
    'ilan-kaydet' => 'ik_ilan_yonetimi',
    'ilan-durum' => 'ik_ilan_yonetimi',
    'ilan-sil' => 'ik_ilan_yonetimi',
    'ilan-link' => 'ik_ilan_yonetimi',
    'basvuru-ajax-list' => 'ik_basvuru_listesi',
    'basvuru-getir' => 'ik_basvuru_listesi',
    'basvuru-durum' => 'ik_basvuru_listesi',
    'basvuru-not' => 'ik_basvuru_listesi',
    'basvuru-ai-yeniden' => 'ik_basvuru_listesi',
    'basvuru-cv' => 'ik_basvuru_listesi',
    'basvuru-foto' => 'ik_basvuru_listesi',
    'basvuru-sil' => 'ik_basvuru_listesi',
    'personele-donustur' => 'ik_basvuru_personele_donustur',
];

$cevap = static function (array $veri, int $kod = 200): void {
    http_response_code($kod);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
};

if (!isset($ikActions[$action])) {
    $cevap(['status' => 'error', 'message' => 'Tanımsız işlem.'], 400);
}
if (!in_array($action, ['basvuru-cv', 'basvuru-foto'], true) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $cevap(['status' => 'error', 'message' => 'Geçersiz istek.'], 405);
}
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || empty($_SESSION['firma_id'])) {
    $cevap(['status' => 'error', 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
}

if ((new PermissionPolicyModel())->isReady()) {
    Gate::authorizeApiPolicy('ik-ise-alim/api', $action);
} elseif (!Gate::allows($ikActions[$action])) {
    $cevap(['status' => 'error', 'message' => 'Bu işlem için yetkiniz bulunmamaktadır.'], 403);
}

$firmaId = (int) $_SESSION['firma_id'];
$userId = (int) ($_SESSION['user_id'] ?? 0);

$Ilan = new IkIlanModel();
$Basvuru = new IkBasvuruModel();
$Log = new SystemLogModel();

$idCoz = static fn(string $alan): int => (int) Security::decrypt((string) ($_POST[$alan] ?? $_GET[$alan] ?? ''));
$metin = static fn(string $alan, int $limit): string => trim(mb_substr(str_replace("\r", '', (string) ($_POST[$alan] ?? '')), 0, $limit, 'UTF-8'));
$jsonAlan = static function ($deger): array {
    $dizi = json_decode((string) $deger, true);
    return is_array($dizi) ? $dizi : [];
};

try {
    switch ($action) {
        case 'dashboard-data':
            $cevap(['status' => 'success'] + $Basvuru->dashboardStats($firmaId, (int) ($_POST['gun'] ?? 30)));

        case 'ilan-ajax-list':
            $cevap($Ilan->ajaxList($_POST, $firmaId) + ['summary' => $Ilan->summary($firmaId)]);

        case 'ilan-getir':
            $ilan = $Ilan->bul($idCoz('id'), $firmaId);
            if (!$ilan) {
                $cevap(['status' => 'error', 'message' => 'İlan bulunamadı.'], 404);
            }
            $sorular = $jsonAlan($ilan->sorular);
            $cevap(['status' => 'success', 'data' => [
                'id' => Security::encrypt((int) $ilan->id),
                'baslik' => $ilan->baslik,
                'departman' => $ilan->departman,
                'gorev' => $ilan->gorev,
                'calisma_yeri' => $ilan->calisma_yeri,
                'aciklama' => $ilan->aciklama,
                'aranan_nitelikler' => $ilan->aranan_nitelikler,
                'ai_kriterleri' => $ilan->ai_kriterleri,
                'sorular' => implode("\n", $sorular),
                'durum' => $ilan->durum,
                'gecerlilik_tarihi' => $ilan->gecerlilik_tarihi ? substr((string) $ilan->gecerlilik_tarihi, 0, 10) : '',
            ]]);

        case 'ilan-kaydet':
            $id = ($_POST['id'] ?? '') !== '' ? $idCoz('id') : 0;
            if ($id > 0 && !$Ilan->bul($id, $firmaId)) {
                $cevap(['status' => 'error', 'message' => 'İlan bulunamadı.'], 404);
            }
            $baslik = $metin('baslik', 255);
            if ($baslik === '') {
                $cevap(['status' => 'error', 'message' => 'İlan başlığı zorunludur.']);
            }
            $durum = (string) ($_POST['durum'] ?? 'taslak');
            if (!isset(IkIlanModel::DURUMLAR[$durum])) {
                $durum = 'taslak';
            }
            $gecerlilik = $metin('gecerlilik_tarihi', 10);
            if ($gecerlilik !== '') {
                $d = DateTime::createFromFormat('Y-m-d', $gecerlilik);
                if (!$d || $d->format('Y-m-d') !== $gecerlilik) {
                    $cevap(['status' => 'error', 'message' => 'Geçerlilik tarihi hatalı.']);
                }
                $gecerlilik .= ' 23:59:59';
            }
            $sorular = [];
            foreach (explode("\n", $metin('sorular', 3000)) as $satir) {
                $satir = trim($satir);
                if ($satir !== '') {
                    $sorular[] = mb_substr($satir, 0, 300, 'UTF-8');
                }
                if (count($sorular) >= 8) {
                    break;
                }
            }
            $kayitId = $Ilan->kaydet([
                'id' => $id,
                'baslik' => $baslik,
                'departman' => $metin('departman', 150),
                'gorev' => $metin('gorev', 150),
                'calisma_yeri' => $metin('calisma_yeri', 150),
                'aciklama' => $metin('aciklama', 5000),
                'aranan_nitelikler' => $metin('aranan_nitelikler', 3000),
                'ai_kriterleri' => $metin('ai_kriterleri', 3000),
                'sorular' => $sorular ? json_encode($sorular, JSON_UNESCAPED_UNICODE) : null,
                'durum' => $durum,
                'gecerlilik_tarihi' => $gecerlilik,
            ], $firmaId, $userId);
            $Log->logAction($userId, 'İşe Alım İlanı', ($id > 0 ? 'İlan güncellendi: ' : 'İlan oluşturuldu: ') . $baslik . ' (İlan ID: ' . $kayitId . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'İlan kaydedildi.', 'id' => Security::encrypt($kayitId)]);

        case 'ilan-durum':
            $id = $idCoz('id');
            if (!$Ilan->durumGuncelle($id, $firmaId, (string) ($_POST['durum'] ?? ''))) {
                $cevap(['status' => 'error', 'message' => 'Durum güncellenemedi.']);
            }
            $Log->logAction($userId, 'İşe Alım İlanı', 'İlan durumu değişti (İlan ID: ' . $id . ') → ' . (string) $_POST['durum'], SystemLogModel::LEVEL_INFO);
            $cevap(['status' => 'success', 'message' => 'İlan durumu güncellendi.']);

        case 'ilan-sil':
            $id = $idCoz('id');
            if (!$Ilan->softDeleteForFirma($id, $firmaId)) {
                $cevap(['status' => 'error', 'message' => 'İlan silinemedi.']);
            }
            $Log->logAction($userId, 'İşe Alım İlanı', 'İlan silindi (İlan ID: ' . $id . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'İlan silindi.']);

        case 'ilan-link':
            $ilan = $Ilan->bul($idCoz('id'), $firmaId);
            if (!$ilan) {
                $cevap(['status' => 'error', 'message' => 'İlan bulunamadı.'], 404);
            }
            $link = IkIlanModel::linkOlustur($Ilan->tokenGetir($ilan));
            $firmaAdi = $Ilan->firmaAdi($firmaId);
            $mesaj = "Merhaba,\n\n" . ($firmaAdi !== '' ? $firmaAdi . ' olarak ' : '') . '"' . $ilan->baslik . "\" pozisyonu için başvuru alıyoruz.\n"
                . "Aşağıdaki bağlantıdan birkaç dakikada dijital başvuru yapabilirsiniz:\n\n" . $link;
            $Log->logAction($userId, 'İşe Alım İlanı', 'İlan başvuru bağlantısı alındı (İlan ID: ' . (int) $ilan->id . ')', SystemLogModel::LEVEL_INFO);
            $cevap(['status' => 'success', 'data' => [
                'link' => $link,
                'mesaj' => $mesaj,
                'yayinda' => $ilan->durum === 'yayinda',
                'qr' => \App\Helper\Helper::generateQrCode($link),
            ]]);

        case 'basvuru-ajax-list':
            $_POST['ilan_filtre'] = ($_POST['ilan_filtre'] ?? '') !== '' ? $idCoz('ilan_filtre') : 0;
            $cevap($Basvuru->ajaxList($_POST, $firmaId) + ['summary' => $Basvuru->summary($firmaId)]);

        case 'basvuru-getir':
            $b = $Basvuru->bul($idCoz('id'), $firmaId);
            if (!$b) {
                $cevap(['status' => 'error', 'message' => 'Başvuru bulunamadı.'], 404);
            }
            $olaylar = $Basvuru->olaylar((int) $b->id, $firmaId);
            $cevap(['status' => 'success', 'data' => [
                'id' => Security::encrypt((int) $b->id),
                'ad_soyad' => $b->ad_soyad,
                'telefon' => $b->telefon,
                'email' => $b->email,
                'dogum_tarihi' => $b->dogum_tarihi,
                'sehir' => $b->sehir,
                'egitim' => $b->egitim,
                'deneyim_yili' => $b->deneyim_yili,
                'deneyim_ozet' => $b->deneyim_ozet,
                'ehliyet' => $b->ehliyet,
                'maas_beklentisi' => $b->maas_beklentisi,
                'ise_baslama' => $b->ise_baslama,
                'cevaplar' => $jsonAlan($b->cevaplar),
                'cv_var' => !empty($b->cv_dosya),
                'foto_var' => !empty($b->foto_dosya),
                'cv_metin_var' => !empty($b->cv_metin),
                'ilan_baslik' => $b->ilan_baslik,
                'durum' => $b->durum,
                'ai_durum' => $b->ai_durum,
                'ai_puan' => $b->ai_puan,
                'ai_etiket' => $b->ai_etiket,
                'ai_ozet' => $b->ai_ozet,
                'ai_guclu' => $jsonAlan($b->ai_guclu),
                'ai_zayif' => $jsonAlan($b->ai_zayif),
                'ai_riskler' => $jsonAlan($b->ai_riskler),
                'ai_mulakat_sorulari' => $jsonAlan($b->ai_mulakat_sorulari),
                'ai_tarihi' => $b->ai_tarihi,
                'ik_notu' => $b->ik_notu,
                'personel_id' => $b->personel_id ? Security::encrypt((int) $b->personel_id) : null,
                'created_at' => $b->created_at,
                'olaylar' => $olaylar,
            ]]);

        case 'basvuru-durum':
            $id = $idCoz('id');
            $durum = (string) ($_POST['durum'] ?? '');
            if (!$Basvuru->durumGuncelle($id, $firmaId, $durum)) {
                $cevap(['status' => 'error', 'message' => 'Durum güncellenemedi.']);
            }
            $Basvuru->olayEkle($firmaId, $id, $userId, 'Durum değişti', IkBasvuruModel::DURUMLAR[$durum] ?? $durum);
            $Log->logAction($userId, 'İşe Alım Başvurusu', 'Başvuru durumu değişti (Başvuru ID: ' . $id . ') → ' . $durum, SystemLogModel::LEVEL_INFO);
            $cevap(['status' => 'success', 'message' => 'Durum güncellendi.']);

        case 'basvuru-not':
            $id = $idCoz('id');
            if (!$Basvuru->bul($id, $firmaId)) {
                $cevap(['status' => 'error', 'message' => 'Başvuru bulunamadı.'], 404);
            }
            $Basvuru->notKaydet($id, $firmaId, $metin('ik_notu', 3000));
            $Basvuru->olayEkle($firmaId, $id, $userId, 'İK notu güncellendi');
            $cevap(['status' => 'success', 'message' => 'Not kaydedildi.']);

        case 'basvuru-ai-yeniden':
            $id = $idCoz('id');
            $b = $Basvuru->bul($id, $firmaId);
            if (!$b) {
                $cevap(['status' => 'error', 'message' => 'Başvuru bulunamadı.'], 404);
            }
            $ilan = $Ilan->bul((int) $b->ilan_id, $firmaId);
            if (!$ilan) {
                $cevap(['status' => 'error', 'message' => 'İlan bulunamadı.'], 404);
            }
            set_time_limit(180);
            try {
                $sonuc = (new IkBasvuruAiService())->degerlendir($ilan, [
                    'sehir' => $b->sehir,
                    'egitim' => $b->egitim,
                    'deneyim_yili' => $b->deneyim_yili,
                    'deneyim_ozet' => $b->deneyim_ozet,
                    'ehliyet' => $b->ehliyet,
                    'maas_beklentisi' => $b->maas_beklentisi,
                    'ise_baslama' => $b->ise_baslama,
                    'cevaplar' => $jsonAlan($b->cevaplar),
                    'cv_metin' => (string) $b->cv_metin,
                ], $firmaId);
            } catch (Throwable $e) {
                error_log('İK AI yeniden değerlendirme hatası: ' . $e->getMessage());
                $Basvuru->aiHataKaydet($id);
                $cevap(['status' => 'error', 'message' => 'Yapay zekâ değerlendirmesi şu anda tamamlanamadı.']);
            }
            $Basvuru->aiSonucKaydet($id, $sonuc);
            $Basvuru->olayEkle($firmaId, $id, $userId, 'Yapay zekâ yeniden değerlendirdi', 'Puan: ' . $sonuc['puan']);
            $Log->logAction($userId, 'İşe Alım Başvurusu', 'Yapay zekâ değerlendirmesi yenilendi (Başvuru ID: ' . $id . ')', SystemLogModel::LEVEL_INFO);
            $cevap(['status' => 'success', 'message' => 'Değerlendirme yenilendi.']);

        case 'basvuru-cv':
            $b = $Basvuru->bul($idCoz('id'), $firmaId);
            if (!$b || empty($b->cv_dosya)) {
                http_response_code(404);
                exit;
            }
            $yol = dirname(__DIR__, 2) . '/uploads/ik_basvuru/' . $firmaId . '/' . basename((string) $b->cv_dosya);
            if (!is_file($yol)) {
                http_response_code(404);
                exit;
            }
            $icerik = Security::decryptFile((string) file_get_contents($yol));
            $Log->logAction($userId, 'İşe Alım Başvurusu', 'Aday CV dosyası görüntülendi (Başvuru ID: ' . (int) $b->id . ')', SystemLogModel::LEVEL_INFO);
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="cv.pdf"');
            header('Content-Length: ' . strlen($icerik));
            header('X-Content-Type-Options: nosniff');
            header("Content-Security-Policy: sandbox");
            header('Cache-Control: private, no-store');
            echo $icerik;
            exit;

        case 'basvuru-foto':
            $b = $Basvuru->bul($idCoz('id'), $firmaId);
            $yol = $b && !empty($b->foto_dosya) ? dirname(__DIR__, 2) . '/uploads/ik_basvuru/' . $firmaId . '/' . basename((string) $b->foto_dosya) : '';
            if ($yol === '' || !is_file($yol)) {
                http_response_code(404);
                exit;
            }
            $icerik = Security::decryptFile((string) file_get_contents($yol));
            header('Content-Type: image/jpeg');
            header('Content-Length: ' . strlen($icerik));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=300');
            echo $icerik;
            exit;

        case 'basvuru-sil':
            $id = $idCoz('id');
            if (!$Basvuru->softDeleteForFirma($id, $firmaId)) {
                $cevap(['status' => 'error', 'message' => 'Başvuru silinemedi.']);
            }
            $Log->logAction($userId, 'İşe Alım Başvurusu', 'Başvuru silindi (Başvuru ID: ' . $id . ')', SystemLogModel::LEVEL_IMPORTANT);
            $cevap(['status' => 'success', 'message' => 'Başvuru silindi.']);

        case 'personele-donustur':
            $b = $Basvuru->bul($idCoz('id'), $firmaId);
            if (!$b) {
                $cevap(['status' => 'error', 'message' => 'Başvuru bulunamadı.'], 404);
            }
            try {
                $sonuc = (new IkBasvuruDonusturService())->donustur(
                    $b,
                    preg_replace('/\D/', '', (string) ($_POST['tc_kimlik_no'] ?? '')),
                    $metin('ise_giris_tarihi', 10),
                    $firmaId,
                    $userId
                );
            } catch (DomainException $e) {
                $cevap(['status' => 'error', 'message' => $e->getMessage()]);
            }
            $cevap(['status' => 'success', 'message' => 'Aday personel olarak eklendi.', 'personel_id' => $sonuc['personel_enc_id']]);
    }
} catch (Throwable $e) {
    error_log('ik-ise-alim/api.php [' . $action . '] hatası: ' . $e->getMessage());
    $cevap(['status' => 'error', 'message' => 'İşlem sırasında bir hata oluştu.'], 500);
}
