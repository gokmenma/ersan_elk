<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\BordroPusulaModel;
use App\Service\BordroPusulaService;
use App\Service\Gate;

if (empty($_SESSION['user']->id) || empty($_SESSION['firma_id']) || !Gate::allows('bordro/raporlar')) {
    http_response_code(403);
    exit('Bu raporu görüntüleme yetkiniz bulunmamaktadır.');
}
header('Cache-Control: private, no-store, max-age=0');
try {
    $donemId = (int) Security::decrypt((string) ($_GET['donem'] ?? ''));
    $bordroId = isset($_GET['id']) ? (int) Security::decrypt((string) $_GET['id']) : null;
    if ($donemId <= 0 || ($bordroId !== null && $bordroId <= 0)) throw new DomainException('Geçersiz rapor bağlantısı. Raporlar sayfasından tekrar açın.');
    $rapor = (new BordroPusulaModel())->rapor((int) $_SESSION['firma_id'], $donemId, $bordroId);
    if (!$rapor['kayitlar']) throw new DomainException('Dönemde bordro kaydı bulunamadı.');
    foreach ($rapor['kayitlar'] as $item) {
        if ($item['hata']) throw new DomainException($item['kayit']->adi_soyadi . ': ' . $item['hata'] . ' Toplu çıktı için tüm kayıtlar hazır olmalıdır.');
    }
    $pdf = BordroPusulaService::pdf();
    foreach ($rapor['kayitlar'] as $i => $item) {
        if ($i > 0) $pdf->AddPage();
        $pdf->WriteHTML(BordroPusulaService::html($item['kayit'], $rapor['donem'], $rapor['firma'], $item['pusula']));
    }
    $file = 'ucret_hesap_pusulasi_' . date('Y_m', strtotime($rapor['donem']->baslangic_tarihi)) . '.pdf';
    while (ob_get_level() > 0) ob_end_clean();
    $pdf->Output($file, ($_GET['indir'] ?? '') === '1' ? 'D' : 'I');
} catch (DomainException $e) {
    http_response_code(422);
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
} catch (Throwable $e) {
    error_log('Bordro pusula PDF: ' . $e->getMessage());
    http_response_code(500);
    echo 'PDF oluşturulamadı. Lütfen tekrar deneyin.';
}
