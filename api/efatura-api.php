<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Service\EInvoiceService;
use App\Model\EInvoiceModel;
use App\Model\EInvoiceSettingsModel;
use App\Helper\Security;
use App\Helper\EInvoiceSecurity;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

// Oturum ve Yetki Kontrolü
$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId <= 0 || $firmId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Oturum süreniz dolmuş veya yetkiniz bulunmamaktadır.']);
    exit;
}

$action = is_string($_REQUEST['action'] ?? null) ? $_REQUEST['action'] : '';
if (!EInvoiceSecurity::checkPermission($action)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz bulunmuyor.']);
    exit;
}
if (!EInvoiceSecurity::readOnly($action)) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405); header('Allow: POST');
        echo json_encode(['status' => 'error', 'message' => 'Bu işlem POST isteği gerektirir.']); exit;
    }
    if (!EInvoiceSecurity::validCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyin.']); exit;
    }
}

$invoiceService = new EInvoiceService();
$invoiceModel = new EInvoiceModel();
$settingsModel = new EInvoiceSettingsModel();

try {
    switch ($action) {
        case 'calculate_invoice':
            $payload = json_decode(file_get_contents('php://input'), true);
            $lines = is_array($payload) && is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
            echo json_encode(['status' => 'success', 'data' => (new \App\Service\InvoiceCalculationService())->calculate($lines)]);
            break;
        case 'connection_info':
            echo json_encode(['status' => 'success', 'data' => $invoiceService->connectionInfo($firmId)]);
            break;
        case 'counter_info':
            echo json_encode(['status' => 'success', 'data' => $invoiceService->counterInfo($firmId)]);
            break;
        case 'invoice_history':
        case 'refresh_history':
            $invoiceId = EInvoiceSecurity::invoiceId($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? '');
            echo json_encode(['status' => 'success', 'data' => $invoiceService->history($invoiceId, $firmId, $action === 'refresh_history')]);
            break;
        case 'download_pdf':
            $invoiceId = EInvoiceSecurity::invoiceId($_GET['invoice_id'] ?? '');
            $pdf = $invoiceService->downloadPdf($invoiceId, $firmId);
            $invoice = $invoiceModel->getInvoiceById($invoiceId, $firmId);
            $filename = preg_replace('/[^A-Za-z0-9_-]/', '', $invoice['fatura_no'] ?: $invoice['ettn']) . '.pdf';
            header('Content-Type: application/pdf');
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: private, no-store');
            echo $pdf;
            exit;
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

        // 2. Taslak Fatura Kaydet / Güncelle
        case 'save_draft':
            $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $header = $payload['header'] ?? [];
            if (!is_array($header) || !is_array($payload['lines'] ?? null)) throw new \InvalidArgumentException('Geçersiz fatura verisi.');
            if (!empty($header['cari_id'])) $header['cari_id'] = EInvoiceSecurity::invoiceId($header['cari_id']);
            $lines = $payload['lines'] ?? [];
            $rawId = $payload['invoice_id'] ?? $header['invoice_id'] ?? null;
            $invoiceId = null;
            if (!empty($rawId)) {
                $invoiceId = EInvoiceSecurity::invoiceId($rawId);
            }

            if (empty($header['alici_vkn_tckn']) || empty($header['alici_unvan'])) {
                echo json_encode(['status' => 'error', 'message' => 'Alıcı VKN/TCKN ve Unvan alanları zorunludur.']);
                exit;
            }

            if (empty($lines)) {
                echo json_encode(['status' => 'error', 'message' => 'Faturaya en az bir kalem eklenmelidir.']);
                exit;
            }

            if ($invoiceId && $invoiceId > 0) {
                $res = $invoiceService->updateDraft($invoiceId, $firmId, $header, $lines, $userId);
            } else {
                $res = $invoiceService->createDraft($firmId, $header, $lines, $userId);
            }

            if ($res['success']) {
                $finalId = $res['invoice_id'] ?? $invoiceId;
                echo json_encode([
                    'status'       => 'success',
                    'message'      => $res['message'],
                    'invoice_id'   => Security::encrypt((string)$finalId),
                    'encrypted_id' => Security::encrypt((string)$finalId)
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => $res['message']]);
            }
            break;

        // 3. Faturayı EDM / GİB Sistemine Gönder
        case 'send_invoice':
            $encryptedId = $_POST['invoice_id'] ?? '';
            $invoiceId = EInvoiceSecurity::invoiceId($encryptedId);

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
            $invoiceId = EInvoiceSecurity::invoiceId($encryptedId);

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
            $invoiceId = EInvoiceSecurity::invoiceId($encryptedId);

            if (!$invoiceId) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz fatura kimliği.']);
                exit;
            }

            $html = $invoiceService->renderHtmlPreview($invoiceId, $firmId);
            echo json_encode(['status' => 'success', 'html' => $html]);
            break;

        // 6. DataTables Sunucu Taraflı Faturalar Listesi (Taslak, Giden, Gelen)
        case 'list_giden':
        case 'list_invoices':
            $params = $_GET;
            $listType = $params['list_type'] ?? ($action === 'list_giden' ? 'giden' : 'giden');
            $yon = ($listType === 'gelen') ? 'GELEN' : 'GIDEN';
            try {
                $list = $invoiceModel->ajaxList($params, $firmId, $yon, $listType);
                echo json_encode($list);
            } catch (\Throwable $e) {
                error_log("EInvoiceModel::ajaxList Error: " . $e->getMessage());
                echo json_encode([
                    'draw'            => (int)($params['draw'] ?? 1),
                    'recordsTotal'    => 0,
                    'recordsFiltered' => 0,
                    'data'            => []
                ]);
            }
            break;

        // 7. Özet Kart Sayıları ve Tutarları
        case 'summary_stats':
            $listType = $_GET['list_type'] ?? 'giden';
            $yon = ($listType === 'gelen') ? 'GELEN' : 'GIDEN';
            $startDate = !empty($_GET['baslangic_tarihi']) ? trim($_GET['baslangic_tarihi']) : null;
            $endDate = !empty($_GET['bitis_tarihi']) ? trim($_GET['bitis_tarihi']) : null;
            $stats = $invoiceModel->getSummaryStats($firmId, $yon, $listType, $startDate, $endDate);
            echo json_encode(['status' => 'success', 'data' => $stats]);
            break;

        // 8. Toplu Taslak Fatura Gönderimi
        case 'bulk_send_invoices':
            $ids = $_POST['invoice_ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['status' => 'error', 'message' => 'Lütfen gönderilecek en az bir fatura seçin.']);
                exit;
            }

            $successCount = 0;
            $failCount = 0;
            $errors = [];

            foreach ($ids as $rawId) {
                $invoiceId = EInvoiceSecurity::invoiceId($rawId);
                if ($invoiceId > 0) {
                    $sendRes = $invoiceService->sendInvoice($invoiceId, $firmId);
                    if ($sendRes['success']) {
                        $successCount++;
                    } else {
                        $failCount++;
                        $errors[] = 'Fatura gönderilemedi: ' . $sendRes['message'];
                    }
                }
            }

            echo json_encode([
                'status'        => $successCount > 0 ? 'success' : 'error',
                'message'       => "Toplam {$successCount} fatura başarıyla EDM sistemine iletildi. " . ($failCount > 0 ? "({$failCount} adet başarısız)" : ""),
                'success_count' => $successCount,
                'fail_count'    => $failCount,
                'errors'        => $errors
            ]);
            break;

        // 9. Faturayı İptal Et
        case 'cancel_invoice':
            $encryptedId = $_POST['invoice_id'] ?? '';
            $invoiceId = EInvoiceSecurity::invoiceId($encryptedId);
            $reason = trim($_POST['reason'] ?? 'Kullanıcı talebi');

            if (!$invoiceId) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz fatura kimliği.']);
                exit;
            }

            $cancelResult = $invoiceService->cancelInvoice($invoiceId, $firmId, $reason);
            $success = $cancelResult['success'];
            if ($success) {
                echo json_encode(['status' => 'success', 'message' => 'Fatura başarıyla iptal edildi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => $cancelResult['message']]);
            }
            break;

        // 9. Taslak Faturayı Sil (Soft Delete)
        case 'delete_draft':
            $encryptedId = $_POST['invoice_id'] ?? '';
            $invoiceId = EInvoiceSecurity::invoiceId($encryptedId);

            if (!$invoiceId) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz fatura kimliği.']);
                exit;
            }

            $success = $invoiceModel->deleteDraftInvoice($invoiceId, $firmId);
            if ($success) {
                echo json_encode(['status' => 'success', 'message' => 'Taslak fatura başarıyla silindi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Taslak fatura silinemedi. Sadece taslak durumundaki faturalar silinebilir.']);
            }
            break;

        // 10. EDM Gelen Faturaları Senkronize Et
        case 'sync_incoming_invoices':
            $start = !empty($_POST['start_date']) ? trim($_POST['start_date']) : (!empty($_GET['start_date']) ? trim($_GET['start_date']) : null);
            $end = !empty($_POST['end_date']) ? trim($_POST['end_date']) : (!empty($_GET['end_date']) ? trim($_GET['end_date']) : null);
            $res = $invoiceService->syncIncomingInvoices($firmId, $start, $end);
            $res['status'] = (!empty($res['success'])) ? 'success' : 'error';
            echo json_encode($res + ['status' => $res['success'] ? 'success' : 'error']);
            break;

        // 10.1. EDM Giden ve Taslak Faturaları Senkronize Et
        case 'sync_outgoing_invoices':
            $start = !empty($_POST['start_date']) ? trim($_POST['start_date']) : (!empty($_GET['start_date']) ? trim($_GET['start_date']) : null);
            $end = !empty($_POST['end_date']) ? trim($_POST['end_date']) : (!empty($_GET['end_date']) ? trim($_GET['end_date']) : null);
            $res = $invoiceService->syncOutgoingInvoices($firmId, $start, $end);
            $res['status'] = (!empty($res['success'])) ? 'success' : 'error';
            echo json_encode($res + ['status' => $res['success'] ? 'success' : 'error']);
            break;

        // 11. Ticari Faturaya Kabul / Red Yanıtı
        case 'respond_commercial':
            $encryptedId = $_POST['invoice_id'] ?? '';
            $invoiceId = EInvoiceSecurity::invoiceId($encryptedId);
            $responseType = strtoupper(trim($_POST['response_type'] ?? ''));
            $reason = trim($_POST['reason'] ?? '');

            if (!$invoiceId || !in_array($responseType, ['KABUL', 'RED'])) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz fatura veya yanıt türü.']);
                exit;
            }

            $res = $invoiceService->respondToIncomingInvoice($invoiceId, $firmId, $responseType, $reason);
            echo json_encode($res + ['status' => $res['success'] ? 'success' : 'error']);
            break;

        // 12. UBL-TR XML İndir
        case 'download_xml':
            $encryptedId = $_GET['invoice_id'] ?? '';
            $invoiceId = EInvoiceSecurity::invoiceId($encryptedId);
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
                $supplier = $invoiceService->supplier($firmId);
                $ublService = new \App\Service\UblGeneratorService();
                $xmlContent = $ublService->generateInvoiceXml($inv, $supplier, $inv['satirlar'] ?? []);
            }
            header('Content-Type: application/xml; charset=utf-8');
            $fileName = preg_replace('/[^A-Za-z0-9_-]/', '', $inv['fatura_no'] ?: $inv['ettn']) . '.xml';
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
                'varsayilan_gonderici_alias' => trim($_POST['varsayilan_gonderici_alias'] ?? ''),
                'otomatik_gonder'            => !empty($_POST['otomatik_gonder']) ? 1 : 0,
                'kontor_esik' => max(0, (int)($_POST['kontor_esik'] ?? 100))
            ];
            if (!empty($_POST['efatura_seri'])) $data['efatura_seri'] = trim($_POST['efatura_seri']);
            if (!empty($_POST['earsiv_seri'])) $data['earsiv_seri'] = trim($_POST['earsiv_seri']);

            $saved = $settingsModel->saveSettings($firmId, $data);
            if ($saved) {
                echo json_encode(['status' => 'success', 'message' => 'EDM E-Fatura ayarları başarıyla kaydedildi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Ayarlar kaydedilirken bir hata oluştu.']);
            }
            break;

        // 13. Kayıtlı Numaratör Sayaçlarını Getir
        case 'list_numarators':
            $list = $settingsModel->getNumarators($firmId);
            echo json_encode(['status' => 'success', 'data' => $list]);
            break;

        // 14. Numaratör Sayacı Ekle / Güncelle
        case 'save_numarator':
            $type = trim($_POST['belge_turu'] ?? 'EFATURA');
            $series = trim($_POST['seri'] ?? '');
            $year = (int)($_POST['yil'] ?? date('Y'));
            $lastNo = (int)($_POST['son_numara'] ?? 0);

            $ok = $settingsModel->saveNumarator($firmId, $type, $series, $year, $lastNo);
            if ($ok) {
                echo json_encode(['status' => 'success', 'message' => 'Seri sayacı başarıyla kaydedildi.', 'data' => $settingsModel->getNumarators($firmId)]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Sayaç kaydedilemedi.']);
            }
            break;

        // 15. EDM Serilerini Numaratöre Senkronize Et
        case 'sync_serials':
            $info = $invoiceService->connectionInfo($firmId);
            $syncedCount = 0;
            if (!empty($info['SERIALS']) && is_array($info['SERIALS'])) {
                foreach ($info['SERIALS'] as $s) {
                    $sYear = (int)($s['year'] ?? date('Y'));
                    $sType = ((int)($s['earchive'] ?? 0) === 1) ? 'EARSIV' : 'EFATURA';
                    $sSeries = strtoupper(trim($s['series'] ?? ''));
                    $sLast = (int)($s['last'] ?? 0);
                    if (!empty($sSeries)) {
                        $settingsModel->reconcileSerial($firmId, $sType, $sSeries, $sYear, $sLast);
                        $syncedCount++;
                    }
                }
            }
            echo json_encode([
                'status' => 'success',
                'message' => "EDM üzerinden {$syncedCount} adet seri bilgisi ve sayacı senkronize edildi.",
                'data' => [
                    'edm_serials' => $info['SERIALS'] ?? [],
                    'numarators' => $settingsModel->getNumarators($firmId)
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Bilinmeyen veya desteklenmeyen işlem.']);
            break;
    }
} catch (\Throwable $e) {
    error_log("efatura-api.php Exception: " . $e->getMessage());
    http_response_code($e instanceof \InvalidArgumentException ? 422 : 500);
    echo json_encode(['status' => 'error', 'message' => ($e instanceof \InvalidArgumentException || $e instanceof \App\Service\EdmOperationException) ? $e->getMessage() : 'İşlem tamamlanamadı. Sistem kayıtlarını kontrol edin.']);
}
