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

if ($action === 'sablon-kaydet') {
    try {
        if (!$Model->htmlHazirMi()) {
            $cevap('error', 'Şablon yönetimi için veritabanı betiği (sql/2026_11_21_sozlesme_sablon_web_editor.sql) çalıştırılmalıdır.');
        }
        $ham = (string) ($_POST['icerik'] ?? '');
        if (strlen($ham) > SozlesmeSablonService::HTML_MAKS_BOYUT * 2) {
            $cevap('error', 'Şablon içeriği çok büyük (en fazla 1 MB).');
        }
        $html = SozlesmeSablonService::htmlTemizle($ham);
        [$bulunan, $hatalar] = SozlesmeSablonService::htmlDogrula($html);
        if ($hatalar) {
            $cevap('error', implode(' ', $hatalar));
        }
        $cerceve = ($_POST['sayfa_cercevesi'] ?? '0') === '1';
        $id = $Model->htmlKaydet($firma_id, $html, $cerceve, $userId);
        $SystemLog->logAction($userId, 'Sözleşme Şablonu Kaydetme', 'Hizmet sözleşmesi şablonu web editöründe kaydedildi (kayıt: ' . $id . ', yer tutucu: ' . count($bulunan) . ').', SystemLogModel::LEVEL_IMPORTANT);
        $cevap('success', 'Şablon kaydedildi ve kullanıma alındı.', ['yer_tutucular' => $bulunan]);
    } catch (\Throwable $e) {
        error_log('sablon-kaydet: ' . $e->getMessage());
        $cevap('error', 'Şablon kaydedilirken bir hata oluştu.');
    }
}

$cevap('error', 'Geçersiz işlem.');
