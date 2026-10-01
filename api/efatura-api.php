<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Service\EInvoiceService;
use App\Model\EInvoiceModel;
use App\Model\EInvoiceSettingsModel;
use App\Helper\Security;

header('Content-Type: application/json; charset=utf-8');

// Oturum ve Yetki Kontrolü
$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId <= 0 || $firmId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Oturum süreniz dolmuş veya yetkiniz bulunmamaktadır.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

$invoiceService = new EInvoiceService();
$invoiceModel = new EInvoiceModel();
$settingsModel = new EInvoiceSettingsModel();

try {
    switch ($action) {
        // 1. Mükellef Kontrolü (CheckUser)
        case 'check_taxpayer':
            $vkn = trim($_POST['vkn_tckn'] ?? '');
            if (empty($vkn)) {
                echo json_encode(['status' => 'error', 'message' => 'Lütfen geçerli bir VKN veya TCKN girin.']);
                exit;
            }
            $result = $invoiceService->checkTaxpayer($firmId, $vkn);
            echo json_encode(['status' => 'success', 'data' => $result]);
            break;

        // 2. Taslak Fatura Kaydet
        case 'save_draft':
            $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $header = $payload['header'] ?? [];
            $lines = $payload['lines'] ?? [];

            if (empty($header['alici_vkn_tckn']) || empty($header['alici_unvan'])) {
                echo json_encode(['status' => 'error', 'message' => 'Alıcı VKN/TCKN ve Unvan alanları zorunludur.']);
                exit;
            }

            if (empty($lines)) {
                echo json_encode(['status' => 'error', 'message' => 'Faturaya en az bir kalem eklenmelidir.']);
                exit;
            }

            $res = $invoiceService->createDraft($firmId, $header, $lines, $userId);
            if ($res['success']) {
                echo json_encode([
                    'status'       => 'success',
                    'message'      => $res['message'],
                    'invoice_id'   => $res['invoice_id'],
                    'encrypted_id' => Security::encrypt((string)$res['invoice_id'])
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => $res['message']]);
            }
            break;

        // 3. Faturayı EDM / GİB Sistemine Gönder
        case 'send_invoice':
            $encryptedId = $_POST['invoice_id'] ?? '';
            $invoiceId = is_numeric($encryptedId) ? (int)$encryptedId : (int)Security::decrypt($encryptedId);

            if (!$invoiceId) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz fatura kimliği.']);
                exit;
            }

            $sendRes = $invoiceService->sendInvoice($invoiceId, $firmId);
            if ($sendRes['success']) {
                echo json_encode(['status' => 'success', 'message' => $sendRes['message'], 'fatura_no' => $sendRes['fatura_no']]);
            } else {
                echo json_encode(['status' => 'error', 'message' => $sendRes['message']]);
            }
            break;

        // 4. GİB Durum Senkronizasyonu
        case 'sync_status':
            $encryptedId = $_POST['invoice_id'] ?? '';
            $invoiceId = is_numeric($encryptedId) ? (int)$encryptedId : (int)Security::decrypt($encryptedId);

            if (!$invoiceId) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz fatura kimliği.']);
                exit;
            }

            $syncRes = $invoiceService->syncStatus($invoiceId, $firmId);
            if ($syncRes['success']) {
                echo json_encode(['status' => 'success', 'data' => $syncRes]);
            } else {
                echo json_encode(['status' => 'error', 'message' => $syncRes['message']]);
            }
            break;

        // 5. HTML Önizleme Render
        case 'preview_html':
            $encryptedId = $_GET['invoice_id'] ?? ($_POST['invoice_id'] ?? '');
            $invoiceId = is_numeric($encryptedId) ? (int)$encryptedId : (int)Security::decrypt($encryptedId);

            if (!$invoiceId) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz fatura kimliği.']);
                exit;
            }

            $html = $invoiceService->renderHtmlPreview($invoiceId, $firmId);
            echo json_encode(['status' => 'success', 'html' => $html]);
            break;

        // 6. DataTables Sunucu Taraflı Giden Faturalar Listesi
        case 'list_giden':
            $params = $_GET;
            $list = $invoiceModel->ajaxList($params, $firmId, 'GIDEN');
            echo json_encode($list);
            break;

        // 7. Özet Kart Sayıları ve Tutarları
        case 'summary_stats':
            $stats = $invoiceModel->getSummaryStats($firmId, 'GIDEN');
            echo json_encode(['status' => 'success', 'data' => $stats]);
            break;

        // 8. Faturayı İptal Et
        case 'cancel_invoice':
            $encryptedId = $_POST['invoice_id'] ?? '';
            $invoiceId = is_numeric($encryptedId) ? (int)$encryptedId : (int)Security::decrypt($encryptedId);
            $reason = trim($_POST['reason'] ?? 'Kullanıcı talebi');

            if (!$invoiceId) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz fatura kimliği.']);
                exit;
            }

            $success = $invoiceModel->cancelInvoice($invoiceId, $firmId, $reason);
            if ($success) {
                echo json_encode(['status' => 'success', 'message' => 'Fatura başarıyla iptal edildi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Fatura iptal edilirken bir hata oluştu.']);
            }
            break;

        // 10. UBL-TR XML İndir
        case 'download_xml':
            $encryptedId = $_GET['invoice_id'] ?? '';
            $invoiceId = is_numeric($encryptedId) ? (int)$encryptedId : (int)Security::decrypt($encryptedId);
            if (!$invoiceId) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz fatura kimliği.']);
                exit;
            }
            $inv = $invoiceModel->getInvoiceById($invoiceId, $firmId);
            if (!$inv) {
                echo json_encode(['status' => 'error', 'message' => 'Fatura bulunamadı.']);
                exit;
            }
            $xmlContent = '';
            if (!empty($inv['ubl_xml_path']) && file_exists($inv['ubl_xml_path'])) {
                $xmlContent = file_get_contents($inv['ubl_xml_path']);
            } else {
                $settings = $settingsModel->getSettings($firmId);
                $supplier = [
                    'vkn_tckn'      => $settings['api_username'] ?? '',
                    'unvan'         => $_SESSION['firma_adi'] ?? 'ERSAN ELEKTRİK LTD. ŞTİ.',
                    'adres'         => 'Merkez Mah.',
                    'ilce'          => 'Merkez',
                    'il'            => 'Kayseri',
                    'vergi_dairesi' => 'Erciyes Vergi Dairesi'
                ];
                $ublService = new \App\Service\UblGeneratorService();
                $xmlContent = $ublService->generateInvoiceXml($inv, $supplier, $inv['satirlar'] ?? []);
            }
            header('Content-Type: application/xml; charset=utf-8');
            $fileName = ($inv['fatura_no'] ?: $inv['ettn']) . '.xml';
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            echo $xmlContent;
            exit;

        // 11. Excel'e Aktar
        case 'export_excel':
            $params = $_GET;
            $params['start'] = 0;
            $params['length'] = 10000;
            $list = $invoiceModel->ajaxList($params, $firmId, 'GIDEN');
            $fileName = 'giden_faturalar_' . date('Y-m-d_H-i') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($out, ['Fatura No', 'ETTN', 'Tarih', 'Alıcı Unvan', 'VKN/TCKN', 'Belge Türü', 'Senaryo', 'Ödenecek Tutar', 'Durum', 'GİB Durum Açıklaması'], ';');
            foreach ($list['data'] as $r) {
                fputcsv($out, [
                    strip_tags($r['fatura_no']),
                    $r['ettn'],
                    $r['fatura_tarihi'],
                    $r['alici_unvan'],
                    $r['alici_vkn_tckn'],
                    $r['belge_turu'],
                    $r['fatura_profili'],
                    $r['odenecek_tutar'],
                    $r['entegrator_durum_kodu'],
                    $r['gib_durum_aciklamasi']
                ], ';');
            }
            fclose($out);
            exit;

        // 12. Firma EDM Ayarlarını Kaydet
        case 'save_settings':
            $data = [
                'entegrator'                 => 'EDM',
                'api_username'               => trim($_POST['api_username'] ?? ''),
                'api_password'               => trim($_POST['api_password'] ?? ''),
                'environment'                => trim($_POST['environment'] ?? 'TEST'),
                'efatura_seri'               => trim($_POST['efatura_seri'] ?? 'ERS'),
                'earsiv_seri'                => trim($_POST['earsiv_seri'] ?? 'ERA'),
                'varsayilan_gonderici_alias' => trim($_POST['varsayilan_gonderici_alias'] ?? 'urn:mail:defaultgb'),
                'otomatik_gonder'            => !empty($_POST['otomatik_gonder']) ? 1 : 0
            ];

            $saved = $settingsModel->saveSettings($firmId, $data);
            if ($saved) {
                echo json_encode(['status' => 'success', 'message' => 'EDM E-Fatura ayarları başarıyla kaydedildi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Ayarlar kaydedilirken bir hata oluştu.']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Bilinmeyen veya desteklenmeyen işlem.']);
            break;
    }
} catch (Exception $e) {
    error_log("efatura-api.php Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Sunucu hatası: ' . $e->getMessage()]);
}
