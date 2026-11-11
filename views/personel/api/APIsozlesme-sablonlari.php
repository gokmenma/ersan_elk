<?php
session_start();
require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\SozlesmeSablonModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Service\SozlesmeSablonService;

$firma_id = (int) ($_SESSION['firma_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$action = (string) ($_REQUEST['action'] ?? '');
$indirme = $action === 'sablon-indir';

if (!$indirme) {
    header('Content-Type: application/json; charset=utf-8');
}

$cevap = static function (string $durum, string $mesaj, array $ek = []) {
    echo json_encode(['status' => $durum, 'message' => $mesaj] + $ek, JSON_UNESCAPED_UNICODE);
    exit;
};

if ($firma_id <= 0) {
    if ($indirme) {
        http_response_code(403);
        exit('Oturumunuz sona ermiş.');
    }
    $cevap('error', 'Oturumunuz sona ermiş. Lütfen sayfayı yenileyin.');
}

Gate::authorizeApiPolicy('personel/api/sozlesme-sablonlari', $action);

$Model = new SozlesmeSablonModel();
$SystemLog = new SystemLogModel();

if ($action === 'sablon-yer-tutucu-listesi') {
    $cevap('success', 'Tamam', ['data' => SozlesmeSablonService::alanTanimlari()]);
}

if ($action === 'sablon-indir') {
    $rawId = (string) ($_GET['id'] ?? '');
    if ($rawId === 'varsayilan') {
        $yol = SozlesmeSablonModel::varsayilanYol();
        $ad = 'Hizmet_Sozlesmesi_Sablon_Varsayilan.docx';
    } else {
        $id = (int) Security::decrypt($rawId);
        $kayit = $id > 0 ? $Model->bul($id, $firma_id) : null;
        if (!$kayit) {
            http_response_code(404);
            exit('Şablon bulunamadı.');
        }
        $yol = $Model->dosyaYolu($kayit);
        $ad = 'Hizmet_Sozlesmesi_Sablon_v' . (int) $kayit->versiyon . '.docx';
    }
    if (!is_file($yol)) {
        http_response_code(404);
        exit('Şablon dosyası bulunamadı.');
    }
    $SystemLog->logAction($userId, 'Sözleşme Şablonu İndirme', 'Hizmet sözleşmesi şablonu indirildi: ' . $ad, SystemLogModel::LEVEL_INFO);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $ad . '"');
    header('Content-Length: ' . filesize($yol));
    header('X-Content-Type-Options: nosniff');
    readfile($yol);
    exit;
}

if ($action === 'sablon-yukle') {
    try {
        $dosya = $_FILES['sablon_dosyasi'] ?? null;
        if (!$dosya || ($dosya['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($dosya['tmp_name'])) {
            $cevap('error', 'Lütfen bir .docx dosyası seçin.');
        }
        $orijinal = (string) $dosya['name'];
        if (strtolower(pathinfo($orijinal, PATHINFO_EXTENSION)) !== 'docx') {
            $cevap('error', 'Yalnızca .docx uzantılı Word dosyaları yüklenebilir.');
        }

        [$bulunan, $hatalar] = SozlesmeSablonService::dogrula($dosya['tmp_name']);
        if ($hatalar) {
            $cevap('error', implode(' ', $hatalar));
        }

        $klasor = SozlesmeSablonModel::klasor($firma_id);
        if (!is_dir($klasor) && !mkdir($klasor, 0770, true) && !is_dir($klasor)) {
            error_log('Sözleşme şablon klasörü oluşturulamadı: ' . $klasor);
            $cevap('error', 'Şablon klasörü oluşturulamadı.');
        }
        $saklananAd = bin2hex(random_bytes(16)) . '.docx';
        if (!move_uploaded_file($dosya['tmp_name'], $klasor . '/' . $saklananAd)) {
            error_log('Sözleşme şablonu taşınamadı: ' . $klasor);
            $cevap('error', 'Dosya kaydedilemedi.');
        }

        $aciklama = mb_substr(trim((string) ($_POST['aciklama'] ?? '')), 0, 255, 'UTF-8');
        $id = $Model->ekle(
            $firma_id,
            SozlesmeSablonModel::TUR_HIZMET,
            $saklananAd,
            mb_substr(basename($orijinal), 0, 255, 'UTF-8'),
            (int) filesize($klasor . '/' . $saklananAd),
            hash_file('sha256', $klasor . '/' . $saklananAd),
            $aciklama,
            $userId
        );
        $SystemLog->logAction($userId, 'Sözleşme Şablonu Yükleme', 'Hizmet sözleşmesi şablonunun yeni sürümü yüklendi ve kullanıma alındı (kayıt: ' . $id . ', yer tutucu: ' . count($bulunan) . ').', SystemLogModel::LEVEL_IMPORTANT);
        $uyari = $bulunan === [] ? ' Belgede hiç yer tutucu bulunamadı; personel bilgileri otomatik doldurulmayacak.' : '';
        $cevap('success', 'Yeni şablon sürümü yüklendi ve kullanıma alındı.' . $uyari, ['yer_tutucular' => $bulunan]);
    } catch (\Throwable $e) {
        error_log('sablon-yukle: ' . $e->getMessage());
        $cevap('error', 'Şablon yüklenirken bir hata oluştu.');
    }
}

if ($action === 'sablon-aktif-et') {
    try {
        $id = (int) Security::decrypt((string) ($_POST['id'] ?? ''));
        $kayit = $id > 0 ? $Model->aktifEt($id, $firma_id) : null;
        if (!$kayit) {
            $cevap('error', 'Şablon sürümü bulunamadı.');
        }
        $SystemLog->logAction($userId, 'Sözleşme Şablonu Sürüm Değişikliği', 'Hizmet sözleşmesi şablonu v' . (int) $kayit->versiyon . ' sürümüne alındı.', SystemLogModel::LEVEL_IMPORTANT);
        $cevap('success', 'Seçilen sürüm kullanıma alındı.');
    } catch (\Throwable $e) {
        error_log('sablon-aktif-et: ' . $e->getMessage());
        $cevap('error', 'İşlem sırasında bir hata oluştu.');
    }
}

if ($action === 'sablon-varsayilana-don') {
    try {
        $Model->varsayilanaDon($firma_id);
        $SystemLog->logAction($userId, 'Sözleşme Şablonu Sürüm Değişikliği', 'Hizmet sözleşmesi şablonu sistem varsayılanına döndürüldü.', SystemLogModel::LEVEL_IMPORTANT);
        $cevap('success', 'Sistem varsayılan şablonu kullanıma alındı.');
    } catch (\Throwable $e) {
        error_log('sablon-varsayilana-don: ' . $e->getMessage());
        $cevap('error', 'İşlem sırasında bir hata oluştu.');
    }
}

$cevap('error', 'Geçersiz işlem.');
