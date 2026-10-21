<?php

declare(strict_types=1);

use App\Helper\Helper;
use App\Helper\Security;
use App\Helper\EInvoiceSecurity;
use App\Model\EInvoiceModel;
use App\Model\EFaturaCariModel;
use App\Model\EFaturaMalHizmetModel;
use App\Service\EInvoiceService;
use App\Service\InvoiceCalculationService;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function faturaApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function checkFaturaAuth(): void
{
    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? ($_SESSION['user']->id ?? 0));
    $firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);

    if ($userId <= 0 || empty($_SESSION['user'])) {
        faturaApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
    }
    if ($firmId <= 0) {
        faturaApiResponse(['success' => false, 'message' => 'Lütfen önce aktif firma seçimi yapınız.'], 400);
    }

    $canAccess = Gate::allows('efatura/dashboard') 
        || Gate::allows('efatura/giden-list') 
        || Gate::allows('efatura/gelen-list') 
        || Gate::allows('efatura/taslak-list') 
        || Gate::allows('efatura/olustur');

    if (!$canAccess) {
        faturaApiResponse(['success' => false, 'message' => 'E-Fatura modülünü görüntüleme yetkiniz bulunmuyor.'], 403);
    }
}

function verifyCsrfToken(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals((string) $sessionToken, (string) $token)) {
        faturaApiResponse(['success' => false, 'message' => 'Geçersiz güvenlik doğrulaması (CSRF). Lütfen sayfayı yenileyin.'], 419);
    }
}

function requireInvoiceViewAccess(array $invoice): void
{
    $direction = strtoupper((string) ($invoice['yon'] ?? ''));
    $status = strtoupper((string) ($invoice['entegrator_durum_kodu'] ?? ''));
    $allowed = $direction === 'GELEN'
        ? Gate::allows('efatura/gelen-list')
        : ($status === 'TASLAK'
            ? Gate::allowsAny(['efatura/taslak-list', 'efatura/olustur'])
            : Gate::allows('efatura/giden-list'));
    if (!$allowed) {
        faturaApiResponse(['success' => false, 'message' => 'Bu faturayı görüntüleme yetkiniz bulunmuyor.'], 403);
    }
}

checkFaturaAuth();

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'dashboard');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$currentUserId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$firmId = (int) ($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);

$invoiceModel = new EInvoiceModel();
$invoiceService = new EInvoiceService();
$malHizmetModel = new EFaturaMalHizmetModel();

try {
    // 1. DASHBOARD & FINANS OZETI
    if ($method === 'GET' && $action === 'dashboard') {
        if (!Gate::allows('efatura/dashboard')) {
            faturaApiResponse(['success' => false, 'message' => 'Dashboard görüntüleme yetkiniz bulunmuyor.'], 403);
        }

        $startDate = !empty($_GET['baslangic_tarihi']) ? trim((string)$_GET['baslangic_tarihi']) : date('Y-m-01');
        $endDate = !empty($_GET['bitis_tarihi']) ? trim((string)$_GET['bitis_tarihi']) : date('Y-m-t');

        $dashData = $invoiceModel->getDashboardData($firmId, $startDate, $endDate);

        // Şifrelenmiş ID ekle
        if (!empty($dashData['recent_gelen'])) {
            foreach ($dashData['recent_gelen'] as &$rg) {
                if (empty($rg['encrypted_id']) && !empty($rg['id'])) {
                    $rg['encrypted_id'] = Security::encrypt((string)$rg['id']);
                }
            }
            unset($rg);
        }
        if (!empty($dashData['recent_giden'])) {
            foreach ($dashData['recent_giden'] as &$rg) {
                if (empty($rg['encrypted_id']) && !empty($rg['id'])) {
                    $rg['encrypted_id'] = Security::encrypt((string)$rg['id']);
                }
            }
            unset($rg);
        }

        faturaApiResponse([
            'success' => true,
            'data' => $dashData,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    // 2. FATURA LISTESI (Giden, Gelen, Taslak)
    if ($method === 'GET' && $action === 'list') {
        $listType = (string)($_GET['list_type'] ?? 'giden'); // giden, gelen, taslak
        if (!in_array($listType, ['giden', 'gelen', 'taslak'], true)) {
            $listType = 'giden';
        }

        // Yetki kontrolü
        $gateKey = match ($listType) {
            'gelen'  => 'efatura/gelen-list',
            'taslak' => 'efatura/taslak-list',
            default  => 'efatura/giden-list',
        };
        if (!Gate::allows($gateKey)) {
            faturaApiResponse(['success' => false, 'message' => 'Bu fatura listesini görüntüleme yetkiniz bulunmuyor.'], 403);
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 25)));
        $start = ($page - 1) * $limit;

        $params = [
            'start' => $start,
            'length' => $limit,
            'draw' => (int)($_GET['draw'] ?? 1),
            'list_type' => $listType,
        ];

        if (!empty($_GET['search'])) {
            $params['search'] = ['value' => trim((string)$_GET['search'])];
        }
        if (!empty($_GET['urun_ara'])) {
            $params['urun_ara'] = trim((string)$_GET['urun_ara']);
        }
        if (!empty($_GET['durum_filtre'])) {
            $params['durum_filtre'] = trim((string)$_GET['durum_filtre']);
        }
        if (!empty($_GET['ticari_yanit'])) {
            $params['ticari_yanit'] = trim((string)$_GET['ticari_yanit']);
        }
        if (!empty($_GET['baslangic_tarihi'])) {
            $params['baslangic_tarihi'] = trim((string)$_GET['baslangic_tarihi']);
        }
        if (!empty($_GET['bitis_tarihi'])) {
            $params['bitis_tarihi'] = trim((string)$_GET['bitis_tarihi']);
        }

        $yon = ($listType === 'gelen') ? 'GELEN' : 'GIDEN';
        $result = $invoiceModel->ajaxList($params, $firmId, $yon, $listType);

        $items = $result['data'] ?? [];
        foreach ($items as &$item) {
            if (empty($item['encrypted_id']) && !empty($item['id'])) {
                $item['encrypted_id'] = Security::encrypt((string)$item['id']);
            }
        }
        unset($item);

        $totalFiltered = (int)($result['recordsFiltered'] ?? count($items));
        $totalPages = $limit > 0 ? (int)ceil($totalFiltered / $limit) : 1;

        faturaApiResponse([
            'success' => true,
            'data' => $items,
            'summary' => $result['summary'] ?? [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalFiltered,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ]);
    }

    // 3. FATURA DETAYI
    if ($method === 'GET' && $action === 'detail') {
        $encId = (string)($_GET['id'] ?? $_GET['invoice_id'] ?? '');
        $rawId = Security::decrypt($encId);
        $invoiceId = ($rawId && is_numeric($rawId)) ? (int)$rawId : 0;

        if ($invoiceId <= 0) {
            faturaApiResponse(['success' => false, 'message' => 'Geçersiz fatura kimliği.'], 400);
        }

        $invoice = $invoiceModel->getInvoiceById($invoiceId, $firmId);
        if (!$invoice) {
            faturaApiResponse(['success' => false, 'message' => 'Fatura kaydı bulunamadı.'], 404);
        }
        requireInvoiceViewAccess($invoice);

        $invoice['encrypted_id'] = $encId;

        faturaApiResponse([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    // 4. FATURA HTML ÖNİZLEME
    if ($method === 'GET' && $action === 'preview_html') {
        $encId = (string)($_GET['id'] ?? $_GET['invoice_id'] ?? '');
        $rawId = Security::decrypt($encId);
        $invoiceId = ($rawId && is_numeric($rawId)) ? (int)$rawId : 0;

        if ($invoiceId <= 0) {
            faturaApiResponse(['success' => false, 'message' => 'Geçersiz fatura kimliği.'], 400);
        }

        $invoice = $invoiceModel->getInvoiceById($invoiceId, $firmId);
        if (!$invoice) {
            faturaApiResponse(['success' => false, 'message' => 'Fatura kaydı bulunamadı.'], 404);
        }
        requireInvoiceViewAccess($invoice);
        $html = $invoiceService->renderHtmlPreview($invoiceId, $firmId);
        faturaApiResponse([
            'success' => true,
            'html' => $html,
        ]);
    }

    // 5. DOĞRUDAN HTML / PDF İNDİRME VEYA GÖSTERME
    if ($method === 'GET' && in_array($action, ['show_html', 'download_pdf'], true)) {
        $encId = (string)($_GET['id'] ?? $_GET['invoice_id'] ?? '');
        $rawId = Security::decrypt($encId);
        $invoiceId = ($rawId && is_numeric($rawId)) ? (int)$rawId : 0;

        if ($invoiceId <= 0) {
            http_response_code(400);
            exit('Geçersiz fatura kimliği.');
        }

        $invoice = $invoiceModel->getInvoiceById($invoiceId, $firmId);
        if (!$invoice) {
            http_response_code(404);
            exit('Fatura kaydı bulunamadı.');
        }
        requireInvoiceViewAccess($invoice);

        if ($action === 'show_html') {
            $html = $invoiceService->renderHtmlPreview($invoiceId, $firmId);
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
        }

        if ($action === 'download_pdf') {
            try {
                $pdf = $invoiceService->downloadPdf($invoiceId, $firmId);
                $filename = preg_replace('/[^A-Za-z0-9_-]/', '', ($invoice['fatura_no'] ?? '') ?: ($invoice['ettn'] ?? 'fatura')) . '.pdf';
                header('Content-Type: application/pdf');
                header('X-Content-Type-Options: nosniff');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($pdf));
                echo $pdf;
                exit;
            } catch (\Throwable $e) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        }
    }

    // 6. MÜKELLEF KONTROLÜ (VKN/TCKN -> E-Fatura veya E-Arşiv)
    if ($method === 'POST' && $action === 'check_taxpayer') {
        verifyCsrfToken();
        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $vkn = trim((string)($payload['vkn_tckn'] ?? ''));

        if (empty($vkn)) {
            faturaApiResponse(['success' => false, 'message' => 'Lütfen geçerli bir VKN veya TCKN girin.'], 400);
        }

        $result = $invoiceService->checkTaxpayer($firmId, $vkn);
        faturaApiResponse([
            'success' => true,
            'data' => $result,
        ]);
    }

    // 7. CARİ LİSTESİ (Fatura Keserken Müşteri Seçimi İçin)
    if ($method === 'GET' && $action === 'customers') {
        $search = trim((string)($_GET['search'] ?? ''));
        $customers = $invoiceModel->invoiceCustomers($firmId);

        if ($search !== '') {
            $searchLower = mb_strtolower($search, 'UTF-8');
            $customers = array_values(array_filter($customers, static function ($c) use ($searchLower) {
                return (str_contains(mb_strtolower((string)($c['CariAdi'] ?? ''), 'UTF-8'), $searchLower))
                    || (str_contains(mb_strtolower((string)($c['vkn_tckn'] ?? ''), 'UTF-8'), $searchLower))
                    || (str_contains(mb_strtolower((string)($c['Telefon'] ?? ''), 'UTF-8'), $searchLower));
            }));
        }

        foreach ($customers as &$c) {
            $c['encrypted_id'] = Security::encrypt((string)$c['id']);
        }
        unset($c);

        faturaApiResponse([
            'success' => true,
            'data' => array_slice($customers, 0, 50),
        ]);
    }

    // 8. MAL / HİZMET LİSTESİ (Fatura Kalemi Seçimi İçin)
    if ($method === 'GET' && $action === 'products') {
        $search = trim((string)($_GET['search'] ?? ''));
        if ($search !== '') {
            $items = $malHizmetModel->search($search, $firmId, 30);
        } else {
            $items = $malHizmetModel->getAllActive($firmId);
        }

        faturaApiResponse([
            'success' => true,
            'data' => array_slice($items, 0, 50),
        ]);
    }

    // 9. SERİLER LİSTESİ
    if ($method === 'GET' && $action === 'serials') {
        $serials = $invoiceService->getSerialsFast($firmId, false);
        faturaApiResponse([
            'success' => true,
            'data' => $serials,
        ]);
    }

    // 10. KALEM TUTAR VE VERGİ HESAPLAMA
    if ($method === 'POST' && $action === 'calculate') {
        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];

        $calc = (new InvoiceCalculationService())->calculate($lines);
        faturaApiResponse([
            'success' => true,
            'data' => $calc,
        ]);
    }

    // 11. TASLAK FATURA KAYDET / GÜNCELLE
    if ($method === 'POST' && $action === 'save_draft') {
        verifyCsrfToken();
        if (!Gate::allows('efatura/olustur') && !Gate::allows('efatura/taslak-list')) {
            faturaApiResponse(['success' => false, 'message' => 'Fatura düzenleme yetkiniz bulunmuyor.'], 403);
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $header = $payload['header'] ?? [];
        $lines = $payload['lines'] ?? [];

        if (!is_array($header) || !is_array($lines) || empty($lines)) {
            faturaApiResponse(['success' => false, 'message' => 'Faturaya en az bir kalem eklenmelidir.'], 400);
        }

        if (empty($header['alici_vkn_tckn']) || empty($header['alici_unvan'])) {
            faturaApiResponse(['success' => false, 'message' => 'Alıcı VKN/TCKN ve Unvan alanları zorunludur.'], 400);
        }

        $rawInvoiceId = null;
        if (!empty($payload['invoice_id'])) {
            $dec = Security::decrypt((string)$payload['invoice_id']);
            if ($dec && is_numeric($dec)) {
                $rawInvoiceId = (int)$dec;
            }
        }

        if (!empty($header['cari_id'])) {
            $decCari = Security::decrypt((string)$header['cari_id']);
            if ($decCari && is_numeric($decCari)) {
                $header['cari_id'] = (int)$decCari;
            }
        }

        if ($rawInvoiceId && $rawInvoiceId > 0) {
            $res = $invoiceService->updateDraft($rawInvoiceId, $firmId, $header, $lines, $currentUserId);
        } else {
            $res = $invoiceService->createDraft($firmId, $header, $lines, $currentUserId);
        }

        if ($res['success']) {
            $finalId = $res['invoice_id'] ?? $rawInvoiceId;
            faturaApiResponse([
                'success' => true,
                'message' => $res['message'] ?? 'Taslak fatura başarıyla kaydedildi.',
                'invoice_id' => Security::encrypt((string)$finalId),
            ]);
        } else {
            faturaApiResponse(['success' => false, 'message' => $res['message'] ?? 'Taslak kaydedilemedi.'], 400);
        }
    }

    // 12. TASLAK FATURAYI SİL
    if ($method === 'POST' && $action === 'delete_draft') {
        verifyCsrfToken();
        if (!Gate::allows('efatura/taslak-list') && !Gate::allows('efatura/olustur')) {
            faturaApiResponse(['success' => false, 'message' => 'Taslak silme yetkiniz bulunmuyor.'], 403);
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $encId = (string)($payload['invoice_id'] ?? '');
        $rawId = Security::decrypt($encId);
        $invoiceId = ($rawId && is_numeric($rawId)) ? (int)$rawId : 0;

        if ($invoiceId <= 0) {
            faturaApiResponse(['success' => false, 'message' => 'Geçersiz fatura kimliği.'], 400);
        }

        $ok = $invoiceModel->deleteDraftInvoice($invoiceId, $firmId);
        if ($ok) {
            faturaApiResponse(['success' => true, 'message' => 'Taslak fatura başarıyla silindi.']);
        } else {
            faturaApiResponse(['success' => false, 'message' => 'Taslak fatura silinemedi.'], 400);
        }
    }

    // 13. FATURAYI EDM / GİB'E GÖNDER
    if ($method === 'POST' && $action === 'send_invoice') {
        verifyCsrfToken();
        if (!Gate::allows('efatura/gonder') && !Gate::allows('efatura/olustur')) {
            faturaApiResponse(['success' => false, 'message' => 'Fatura gönderme yetkiniz bulunmuyor.'], 403);
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $encId = (string)($payload['invoice_id'] ?? '');
        $rawId = Security::decrypt($encId);
        $invoiceId = ($rawId && is_numeric($rawId)) ? (int)$rawId : 0;

        if ($invoiceId <= 0) {
            faturaApiResponse(['success' => false, 'message' => 'Geçersiz fatura kimliği.'], 400);
        }

        $sendRes = $invoiceService->sendInvoice($invoiceId, $firmId);
        if ($sendRes['success']) {
            faturaApiResponse([
                'success' => true,
                'message' => $sendRes['message'] ?? 'Fatura başarıyla GİB / EDM sistemine iletildi.',
                'fatura_no' => $sendRes['fatura_no'] ?? '',
            ]);
        } else {
            faturaApiResponse([
                'success' => false,
                'message' => $sendRes['message'] ?? 'Fatura gönderilirken hata oluştu.',
            ], 400);
        }
    }

    // 14. TİCARİ FATURA YANITI VER (KABUL / RED)
    if ($method === 'POST' && $action === 'respond_commercial') {
        verifyCsrfToken();
        if (!Gate::allows('efatura/yanit') && !Gate::allows('efatura/gelen-list')) {
            faturaApiResponse(['success' => false, 'message' => 'Ticari yanıt verme yetkiniz bulunmuyor.'], 403);
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $encId = (string)($payload['invoice_id'] ?? '');
        $rawId = Security::decrypt($encId);
        $invoiceId = ($rawId && is_numeric($rawId)) ? (int)$rawId : 0;
        $responseType = strtoupper(trim((string)($payload['response_type'] ?? '')));
        $reason = trim((string)($payload['reason'] ?? ''));

        if ($invoiceId <= 0 || !in_array($responseType, ['KABUL', 'RED'], true)) {
            faturaApiResponse(['success' => false, 'message' => 'Geçersiz fatura veya yanıt türü.'], 400);
        }

        $res = $invoiceService->respondToIncomingInvoice($invoiceId, $firmId, $responseType, $reason);
        if ($res['success']) {
            faturaApiResponse([
                'success' => true,
                'message' => $res['message'] ?? 'Ticari fatura yanıtı iletildi.',
            ]);
        } else {
            faturaApiResponse([
                'success' => false,
                'message' => $res['message'] ?? 'Yanıt iletilemedi.',
            ], 400);
        }
    }

    // 15. FATURA CARİ BİLGİSİ SORGULA (Cariye İşle Öncesi Eşleşme Kontrolü)
    if ($method === 'GET' && $action === 'check_invoice_cari') {
        if (!Gate::allows('efatura/giden-list') && !Gate::allows('efatura/gelen-list') && !Gate::allows('cari_hesap_hareketleri') && !Gate::allows('cari_takibi')) {
            faturaApiResponse(['success' => false, 'message' => 'Cari kontrol yetkiniz bulunmuyor.'], 403);
        }

        $encId = (string)($_GET['invoice_id'] ?? $_GET['id'] ?? '');
        $rawId = Security::decrypt($encId);
        $invoiceId = ($rawId && is_numeric($rawId)) ? (int)$rawId : 0;

        if ($invoiceId <= 0) {
            faturaApiResponse(['success' => false, 'message' => 'Geçersiz fatura kimliği.'], 400);
        }

        $cariHareketModel = new \App\Model\CariHareketleriModel();
        $stmt = $cariHareketModel->db->prepare("
            SELECT f.*, c.CariAdi AS cari_adi, c.firma AS cari_firma
            FROM faturalar f
            LEFT JOIN cari c ON c.id = f.cari_id AND c.silinme_tarihi IS NULL
            WHERE f.id = :id AND f.firm_id = :firm_id
            LIMIT 1
        ");
        $stmt->execute(['id' => $invoiceId, 'firm_id' => $firmId]);
        $fatura = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$fatura) {
            faturaApiResponse(['success' => false, 'message' => 'Fatura kaydı bulunamadı.'], 404);
        }

        $cariModel = new \App\Model\CariModel();
        $cariName = null;
        $cariExists = false;

        if (!empty($fatura->cari_id) && (!empty($fatura->cari_adi) || !empty($fatura->cari_firma))) {
            $cariName = !empty($fatura->cari_adi) ? $fatura->cari_adi : $fatura->cari_firma;
            $cariExists = true;
        } else {
            $matchedCari = $cariModel->findMatchingCari($fatura->alici_vkn_tckn, $fatura->alici_unvan);
            if ($matchedCari) {
                $cariName = !empty($matchedCari->CariAdi) ? $matchedCari->CariAdi : $matchedCari->firma;
                $cariExists = true;
            }
        }

        $unvan = trim((string)($fatura->alici_unvan ?: ($fatura->alici_vkn_tckn ?: 'İsimsiz Müşteri/Tedarikçi')));
        $yon = strtoupper(trim((string)($fatura->yon ?? 'GIDEN')));
        $directionText = ($yon === 'GELEN') ? 'Alacak' : 'Borç';
        $odenecekTutar = (float)($fatura->odenecek_tutar ?? 0);

        faturaApiResponse([
            'success'           => true,
            'status'            => 'success',
            'cari_exists'       => $cariExists,
            'cari_name'         => $cariName ?: $unvan,
            'counterparty_name' => $unvan,
            'invoice_no'        => trim((string)($fatura->fatura_no ?: ($fatura->ettn ?: 'Fatura'))),
            'fatura_tarihi'     => date('d.m.Y', strtotime($fatura->fatura_tarihi ?: date('Y-m-d'))),
            'direction'         => $yon,
            'direction_text'    => $directionText,
            'odenecek_tutar'    => Helper::formattedMoney($odenecekTutar) . ' ₺',
            'odenecek_tutar_raw'=> $odenecekTutar,
        ]);
    }

    // 16. FATURAYI CARİYE İŞLE (Sync to Cari Movements)
    if ($method === 'POST' && $action === 'sync_to_cari') {
        verifyCsrfToken();
        if (!Gate::allows('efatura/giden-list') && !Gate::allows('efatura/gelen-list') && !Gate::allows('cari_hesap_hareketleri') && !Gate::allows('cari_takibi')) {
            faturaApiResponse(['success' => false, 'message' => 'Cariye işleme yetkiniz bulunmuyor.'], 403);
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $encId = (string)($payload['invoice_id'] ?? $payload['id'] ?? '');
        $rawId = Security::decrypt($encId);
        $invoiceId = ($rawId && is_numeric($rawId)) ? (int)$rawId : 0;
        $force = !empty($payload['force']) && ($payload['force'] == 1 || $payload['force'] === 'true' || $payload['force'] === true || $payload['force'] === '1');

        if ($invoiceId <= 0) {
            faturaApiResponse(['success' => false, 'message' => 'Geçersiz fatura kimliği.'], 400);
        }

        $cariHareketModel = new \App\Model\CariHareketleriModel();

        // Eğer zorlama (force) onayı gelmediyse aynı tarihte aynı tutarda mevcut hareket var mı kontrol et
        if (!$force) {
            $duplicateCheck = $cariHareketModel->checkSameDateAmount($invoiceId);
            if ($duplicateCheck) {
                faturaApiResponse([
                    'success' => true,
                    'status'  => 'warning_duplicate',
                    'message' => "Bu cariye ait {$duplicateCheck['fatura_tarihi']} tarihinde {$duplicateCheck['tutar']} tutarında {$duplicateCheck['count']} adet mevcut hareket kaydı bulunmaktadır. Yine de bu faturayı cariye işlemek istiyor musunuz?",
                    'data'    => $duplicateCheck
                ]);
            }
        }

        $movementId = $cariHareketModel->syncFaturaHareketi($invoiceId, $currentUserId, true);

        if ($movementId) {
            faturaApiResponse([
                'success' => true,
                'status'  => 'success',
                'message' => 'Fatura cari hareketlerine başarıyla işlendi.',
                'data'    => [
                    'movement_id' => $movementId,
                    'invoice_id'  => $invoiceId,
                ]
            ]);
        } else {
            $stmt = $cariHareketModel->db->prepare("SELECT cari_id, alici_unvan, alici_vkn_tckn, yon FROM faturalar WHERE id = :id AND firm_id = :firm_id");
            $stmt->execute(['id' => $invoiceId, 'firm_id' => $firmId]);
            $fat = $stmt->fetch(PDO::FETCH_OBJ);

            $msg = 'Fatura cariye işlenemedi.';
            if ($fat) {
                $unvanVkn = trim(($fat->alici_unvan ?? '') . ' ' . ($fat->alici_vkn_tckn ?? ''));
                $msg = "Fatura ile eşleşen bir cari kart bulunamadı ({$unvanVkn}). Lütfen önce cari kartı oluşturun veya VKN/TCKN bilgisini kontrol edin.";
            }
            faturaApiResponse([
                'success' => false,
                'status'  => 'error',
                'message' => $msg
            ], 422);
        }
    }

    faturaApiResponse(['success' => false, 'message' => 'Desteklenmeyen işlem veya HTTP yöntemi.'], 400);

} catch (\Throwable $e) {
    error_log('Mobile SPA Fatura API Hatası: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    faturaApiResponse([
        'success' => false,
        'message' => 'İşlem sırasında sunucu hatası oluştu. Lütfen tekrar deneyiniz.',
    ], 500);
}
