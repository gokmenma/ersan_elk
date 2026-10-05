<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Model\EFaturaMalHizmetModel;
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
if (!Gate::allows('efatura/mal-hizmet-list') && !Gate::allows('efatura/olustur') && !Gate::allows('efatura/giden-list')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu modüle erişim yetkiniz bulunmuyor.']);
    exit;
}

// CSRF kontrolü (POST isteklerinde)
if (in_array($action, ['save', 'delete', 'save_mal_hizmet', 'delete_mal_hizmet'], true)) {
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

$model = new EFaturaMalHizmetModel();

try {
    switch ($action) {
        case 'list':
        case 'list_mal_hizmet':
            $result = $model->ajaxList($_REQUEST, $firmId);
            echo json_encode($result);
            break;

        case 'summary':
        case 'summary_mal_hizmet':
            $summary = $model->summary($firmId);
            echo json_encode(['status' => 'success', 'data' => $summary]);
            break;

        case 'get':
        case 'get_mal_hizmet':
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
            $item = $model->getById($id, $firmId);
            if (!$item) {
                echo json_encode(['status' => 'error', 'message' => 'Mal/Hizmet kaydı bulunamadı.']);
                exit;
            }
            $item['enc_id'] = Security::encrypt((string)$item['id']);
            echo json_encode(['status' => 'success', 'data' => $item]);
            break;

        case 'save':
        case 'save_mal_hizmet':
            $data = $_POST;
            if (!empty($data['enc_id'])) {
                $dec = Security::decrypt((string)$data['enc_id']);
                $data['id'] = (int)$dec;
            } elseif (!empty($data['id']) && !is_numeric($data['id'])) {
                $dec = Security::decrypt((string)$data['id']);
                $data['id'] = (int)$dec;
            }

            $res = $model->saveItem($data, $firmId, $userId);
            if ($res['success']) {
                echo json_encode(['status' => 'success', 'message' => $res['message'], 'id' => $res['id'] ?? null]);
            } else {
                echo json_encode(['status' => 'error', 'message' => $res['message']]);
            }
            break;

        case 'delete':
        case 'delete_mal_hizmet':
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
            $res = $model->deleteItem($id, $firmId, $userId);
            if ($res) {
                echo json_encode(['status' => 'success', 'message' => 'Mal / Hizmet kaydı başarıyla silindi.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Kayıt silinirken hata oluştu.']);
            }
            break;

        case 'search':
        case 'search_mal_hizmet':
            $term = $_GET['q'] ?? $_GET['term'] ?? '';
            $list = $model->search($term, $firmId);
            foreach ($list as &$item) {
                $item['enc_id'] = Security::encrypt((string)$item['id']);
            }
            echo json_encode(['status' => 'success', 'results' => $list]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem talebi.']);
            break;
    }
} catch (\Throwable $e) {
    error_log('EFatura Mal/Hizmet API Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'İşlem sırasında sunucu hatası oluştu. Lütfen tekrar deneyin.']);
}
