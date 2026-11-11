<?php
require_once dirname(__DIR__, 2) . '/layouts/session.php';
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\PersonelModel;
use App\Model\SozlesmeSablonModel;
use App\Model\SystemLogModel;
use App\Service\SozlesmeSablonService;
use App\Service\Gate;

if (!Gate::allows('personel_duzenle') && !Gate::allows('personel_listesi') && !Gate::allows('personel_detay') && !Gate::isSuperAdmin()) {
    http_response_code(403);
    exit('Bu işlem için yetkiniz yok.');
}

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
$rawId = (string) ($_GET['id'] ?? '');
if ($firmaId <= 0 || $rawId === '') {
    http_response_code(400);
    exit('Geçersiz istek.');
}

$personelId = is_numeric($rawId) ? 0 : (int) Security::decrypt($rawId);
if ($personelId <= 0) {
    http_response_code(400);
    exit('Geçersiz personel kaydı.');
}

$Personel = new PersonelModel();
$p = $Personel->find($personelId);
if (!$p || (int) $p->firma_id !== $firmaId || !empty($p->silinme_tarihi)) {
    http_response_code(404);
    exit('Personel bulunamadı.');
}

$sablon = (new SozlesmeSablonModel())->aktifDosyaYolu($firmaId);
if (!is_file($sablon)) {
    error_log('Hizmet sözleşmesi şablonu bulunamadı.');
    http_response_code(500);
    exit('Sözleşme şablonu bulunamadı.');
}

$gecici = tempnam(sys_get_temp_dir(), 'hs_');
if ($gecici === false || !SozlesmeSablonService::doldur($sablon, SozlesmeSablonService::degerler($p), $gecici)) {
    if ($gecici !== false) {
        @unlink($gecici);
    }
    error_log('Hizmet sözleşmesi oluşturulamadı.');
    http_response_code(500);
    exit('Sözleşme oluşturulamadı.');
}

try {
    (new SystemLogModel())->logAction(
        (int) ($_SESSION['user_id'] ?? 0),
        'Hizmet Sözleşmesi İndirme',
        ($p->adi_soyadi ?? '') . ' (ID: ' . $personelId . ') için hizmet sözleşmesi indirildi.',
        SystemLogModel::LEVEL_INFO
    );
} catch (Throwable $e) {
    error_log('Hizmet sözleşmesi log hatası: ' . $e->getMessage());
}

$dosyaAdi = 'Hizmet_Sozlesmesi_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', strtr(mb_strtoupper(strtr(trim((string) ($p->adi_soyadi ?? 'Personel')), ['i' => 'İ', 'ı' => 'I']), 'UTF-8'), ['Ç' => 'C', 'Ğ' => 'G', 'İ' => 'I', 'Ö' => 'O', 'Ş' => 'S', 'Ü' => 'U'])) . '.docx';

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');
header('Content-Length: ' . filesize($gecici));
header('X-Content-Type-Options: nosniff');
readfile($gecici);
@unlink($gecici);
exit;
