<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Model\EFaturaCariModel;
use App\Service\EInvoiceService;
use App\Helper\Security;
use App\Helper\EInvoiceSecurity;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId <= 0 || $firmId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Oturum süreniz dolmuş veya yetkiniz bulunmamaktadır.']);
    exit;
}

$action = is_string($_REQUEST['action'] ?? null) ? trim($_REQUEST['action']) : '';

// Yetki kontrolü
if (!Gate::allows('efatura/cari-list') && !Gate::allows('efatura/olustur') && !Gate::allows('efatura/giden-list')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu modüle erişim yetkiniz bulunmuyor.']);
    exit;
}

// CSRF kontrolü (POST isteklerinde)
if (in_array($action, ['save', 'delete', 'save_cari', 'delete_cari'], true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['status' => 'error', 'message' => 'Bu işlem POST isteği gerektirir.']);
        exit;
    }
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
    if (!EInvoiceSecurity::validCsrf($csrfToken)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Güvenlik doğrulaması (CSRF) başarısız. Lütfen sayfayı yenileyin.']);
        exit;
    }
}

$cariModel = new EFaturaCariModel();

try {
    switch ($action) {
        case 'list':
        case 'list_cariler':
            $result = $cariModel->ajaxList($_REQUEST, $firmId);
            echo json_encode($result);
            break;

        case 'summary':
        case 'summary_cariler':
            $summary = $cariModel->summary($firmId);
            echo json_encode(['status' => 'success', 'data' => $summary]);
            break;

        case 'get':
        case 'get_cari':
            $encId = $_GET['id'] ?? '';
            $id = 0;
            if (is_numeric($encId)) {
                $id = (int)$encId;
            } else {
                $dec = Security::decrypt((string)$encId);
                $id = (int)$dec;
            }
            if ($id <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz kayıt kimliği.']);
                exit;
            }
            $cari = $cariModel->getById($id, $firmId);
            if (!$cari) {
                echo json_encode(['status' => 'error', 'message' => 'Cari kaydı bulunamadı.']);
                exit;
            }
            $cari['enc_id'] = Security::encrypt((string)$cari['id']);
            echo json_encode(['status' => 'success', 'data' => $cari]);
            break;

        case 'save':
        case 'save_cari':
            $data = $_POST;
            if (!empty($data['enc_id'])) {
                $dec = Security::decrypt((string)$data['enc_id']);
                $data['id'] = (int)$dec;
            } elseif (!empty($data['id']) && !is_numeric($data['id'])) {
                $dec = Security::decrypt((string)$data['id']);
                $data['id'] = (int)$dec;
            }

            $res = $cariModel->saveCari($data, $firmId, $userId);
            if ($res['success']) {
                echo json_encode(['status' => 'success', 'message' => $res['message'], 'id' => $res['id'] ?? null]);
            } else {
                echo json_encode(['status' => 'error', 'message' => $res['message']]);
            }
            break;

        case 'delete':
        case 'delete_cari':
            $encId = $_POST['id'] ?? $_POST['enc_id'] ?? '';
            $id = 0;
            if (is_numeric($encId)) {
                $id = (int)$encId;
            } else {
                $dec = Security::decrypt((string)$encId);
                $id = (int)$dec;
            }
            if ($id <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Geçersiz kayıt kimliği.']);
                exit;
            }
            $res = $cariModel->deleteCari($id, $firmId, $userId);
            if ($res) {
                echo json_encode(['status' => 'success', 'message' => 'Cari kaydı başarıyla silindi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Cari silinirken hata oluştu.']);
            }
            break;

        case 'search':
        case 'search_cariler':
            $term = $_GET['q'] ?? $_GET['term'] ?? '';
            $list = $cariModel->search($term, $firmId);
            foreach ($list as &$item) {
                $item['enc_id'] = Security::encrypt((string)$item['id']);
            }
            echo json_encode(['status' => 'success', 'results' => $list]);
            break;

        case 'check_taxpayer':
            $vkn = preg_replace('/\D/', '', $_REQUEST['vkn'] ?? '');
            if (strlen($vkn) !== 10 && strlen($vkn) !== 11) {
                echo json_encode(['status' => 'error', 'message' => 'Geçerli bir 10 haneli VKN veya 11 haneli TCKN giriniz.']);
                exit;
            }
            $svc = new EInvoiceService();
            $info = $svc->checkTaxpayer($firmId, $vkn);
            echo json_encode(['status' => 'success', 'data' => $info]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem talebi.']);
            break;
    }
} catch (\Throwable $e) {
    error_log('EFatura Cari API Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'İşlem sırasında sunucu hatası oluştu. Lütfen tekrar deneyin.']);
}
