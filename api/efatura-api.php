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

            if (isset($header['notlar'])) {
                $rawNotes = html_entity_decode((string)$header['notlar'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $stripped = trim(strip_tags(str_replace(['&nbsp;', '<br>', '<br/>', '<br />'], ' ', $rawNotes)));
                if ($stripped === '') {
                    $header['notlar'] = null;
                }
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
            // Eğer istek get-unique-values parametresi içeriyorsa benzersiz değerleri dön
            if (isset($_REQUEST['action_type']) && in_array($_REQUEST['action_type'], ['get-unique-values', 'get_unique_values'], true)) {
                $col = (string)($_REQUEST['column'] ?? $_REQUEST['col'] ?? '');
                $listType = (string)($_REQUEST['list_type'] ?? 'giden');
                $yon = ($listType === 'gelen') ? 'GELEN' : 'GIDEN';
                $uniqueValues = $invoiceModel->getUniqueValues($col, $firmId, $yon, $listType);
                echo json_encode(['status' => 'success', 'data' => $uniqueValues]);
                break;
            }

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

        case 'get-unique-values':
        case 'get_unique_values':
            $col = (string)($_REQUEST['column'] ?? $_REQUEST['col'] ?? '');
            $listType = (string)($_REQUEST['list_type'] ?? 'giden');
            $yon = ($listType === 'gelen') ? 'GELEN' : 'GIDEN';
            $uniqueValues = $invoiceModel->getUniqueValues($col, $firmId, $yon, $listType);
            echo json_encode(['status' => 'success', 'data' => $uniqueValues]);
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

        case 'sync_job_start':
        case 'sync_job_status':
        case 'sync_job_resume':
        case 'sync_job_cancel':
            session_write_close();
            $jobs = new \App\Service\EInvoiceSyncJobService();
            $token = is_string($_POST['job_token'] ?? null) ? $_POST['job_token'] : '';
            $data = match ($action) {
                'sync_job_start' => $jobs->start($firmId, $userId, trim((string)($_POST['start_date'] ?? '')), trim((string)($_POST['end_date'] ?? '')), (string)($_POST['list_type'] ?? 'taslak')),
                'sync_job_resume' => $jobs->resume($firmId, $userId, $token),
                'sync_job_cancel' => $jobs->cancel($firmId, $userId, $token),
                default => $jobs->status($firmId, $userId, $token ?: null, isset($_POST['list_type']) ? (string)$_POST['list_type'] : null),
            };
            echo json_encode(['status' => 'success', 'data' => $data]);
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
            $listType = is_string($params['list_type'] ?? null) ? $params['list_type'] : 'giden';
            if (!in_array($listType, ['taslak', 'giden', 'gelen'], true)) throw new \InvalidArgumentException('Geçersiz liste türü.');
            $list = $invoiceModel->ajaxList($params, $firmId, $listType === 'gelen' ? 'GELEN' : 'GIDEN', $listType);
            $fileName = $listType . '_faturalar_' . date('Y-m-d_H-i') . '.csv';
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

        // 16. Fatura Tahsilat / Ödeme Bilgisi Getir
        case 'get_invoice_payment_info':
            $invoiceId = EInvoiceSecurity::invoiceId($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? '');
            $tahsilatModel = new \App\Model\FaturaTahsilatModel();
            $paymentInfo = $tahsilatModel->getInvoicePaymentInfo($invoiceId, $firmId);
            if (!$paymentInfo) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Fatura veya tahsilat bilgisi bulunamadı.']);
                exit;
            }
            echo json_encode(['status' => 'success', 'data' => $paymentInfo]);
            break;

        // 17. Faturaya Tahsilat Ekle
        case 'save_invoice_payment':
            $invoiceId = EInvoiceSecurity::invoiceId($_POST['invoice_id'] ?? '');
            $tutarRaw = $_POST['tutar'] ?? '0';
            $tutar = is_numeric($tutarRaw) ? (float)$tutarRaw : \App\Helper\Helper::formattedMoneyToNumber($tutarRaw);
            $kasaId = (int)($_POST['kasa_id'] ?? 0);
            $tahsilatTipi = trim($_POST['tahsilat_tipi'] ?? 'nakit');
            $islemTarihi = trim($_POST['islem_tarihi'] ?? date('Y-m-d'));
            $aciklama = trim($_POST['aciklama'] ?? '');
            $paraBirimi = trim($_POST['para_birimi'] ?? 'TRY');

            if ($kasaId <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Lütfen geçerli bir Kasa/Banka hesabı seçin.']);
                exit;
            }
            if ($tutar <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Tahsilat tutarı 0\'dan büyük olmalıdır.']);
                exit;
            }

            $tahsilatModel = new \App\Model\FaturaTahsilatModel();
            $res = $tahsilatModel->addPayment($firmId, $invoiceId, [
                'kasa_id'       => $kasaId,
                'tutar'         => $tutar,
                'islem_tarihi'  => $islemTarihi,
                'tahsilat_tipi' => $tahsilatTipi,
                'aciklama'      => $aciklama,
                'para_birimi'   => $paraBirimi
            ], $userId);

            if ($res['status'] === 'success') {
                $res['data'] = $tahsilatModel->getInvoicePaymentInfo($invoiceId, $firmId);
            }
            echo json_encode($res);
            break;

        // 18. Fatura Tahsilatını Sil
        case 'delete_invoice_payment':
            $rawPaymentId = $_POST['payment_id'] ?? '';
            $paymentId = (int)Security::decrypt($rawPaymentId);
            if ($paymentId <= 0) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz tahsilat ID.']);
                exit;
            }
            $rawInvoiceId = $_POST['invoice_id'] ?? '';
            $invoiceId = !empty($rawInvoiceId) ? EInvoiceSecurity::invoiceId($rawInvoiceId) : 0;

            $tahsilatModel = new \App\Model\FaturaTahsilatModel();
            $ok = $tahsilatModel->deletePayment($paymentId, $firmId, $userId);
            if ($ok) {
                $updatedInfo = ($invoiceId > 0) ? $tahsilatModel->getInvoicePaymentInfo($invoiceId, $firmId) : null;
                echo json_encode(['status' => 'success', 'message' => 'Tahsilat kaydı başarıyla silindi.', 'data' => $updatedInfo]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Tahsilat silinemedi.']);
            }
            break;

        // 19. Alt Bilgi / Not Şablonlarını Listele
        case 'list_note_templates':
            $sablonModel = new \App\Model\EFaturaNotSablonModel();
            $list = $sablonModel->getAll($firmId);
            echo json_encode(['status' => 'success', 'data' => $list]);
            break;

        // 20. Tekil Şablon Getir
        case 'get_note_template':
            $templateId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            $sablonModel = new \App\Model\EFaturaNotSablonModel();
            $template = $sablonModel->getById($templateId, $firmId);
            if (!$template) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Şablon bulunamadı.']);
                exit;
            }
            echo json_encode(['status' => 'success', 'data' => $template]);
            break;

        // 21. Şablon Kaydet / Güncelle
        case 'save_note_template':
            $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $templateId = !empty($payload['id']) ? (int)$payload['id'] : null;
            $baslik = trim((string)($payload['baslik'] ?? ''));
            $icerik = (string)($payload['icerik'] ?? '');
            $varsayilanMi = !empty($payload['varsayilan_mi']) ? 1 : 0;

            if ($baslik === '') {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Lütfen şablon için bir başlık girin.']);
                exit;
            }

            $sablonModel = new \App\Model\EFaturaNotSablonModel();
            $res = $sablonModel->saveTemplate($firmId, $templateId, $baslik, $icerik, $varsayilanMi, $userId);
            $res['status'] = $res['success'] ? 'success' : 'error';
            if (!$res['success']) {
                http_response_code(422);
            }
            echo json_encode($res);
            break;

        // 22. Şablon Sil
        case 'delete_note_template':
            $rawInput = file_get_contents('php://input');
            $jsonInput = json_decode($rawInput, true) ?? [];
            $templateId = (int)($_POST['id'] ?? $jsonInput['id'] ?? 0);
            if ($templateId <= 0) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Geçersiz şablon ID.']);
                exit;
            }
            $sablonModel = new \App\Model\EFaturaNotSablonModel();
            $ok = $sablonModel->deleteTemplate($templateId, $firmId, $userId);
            if ($ok) {
                echo json_encode(['status' => 'success', 'success' => true, 'message' => 'Şablon başarıyla silindi.']);
            } else {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Şablon silinemedi.']);
            }
            break;

        // 23. Varsayılan Şablon Olarak Belirle
        case 'set_default_note_template':
            $templateId = (int)($_POST['id'] ?? 0);
            if ($templateId <= 0) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz şablon ID.']);
                exit;
            }
            $sablonModel = new \App\Model\EFaturaNotSablonModel();
            $ok = $sablonModel->setDefault($templateId, $firmId);
            if ($ok) {
                echo json_encode(['status' => 'success', 'message' => 'Varsayılan şablon güncellendi.']);
            } else {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Güncelleme başarısız.']);
            }
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
