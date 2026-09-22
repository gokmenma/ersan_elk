<?php
session_start();

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/Autoloader.php';

use App\Service\Gate;
use App\Model\GlobalSearchModel;

header('Content-Type: application/json; charset=utf-8');

// Oturum kontrolü
if (empty($_SESSION['user_id']) && empty($_SESSION['user']->id)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Yetkisiz erişim', 'results' => []]);
    exit;
}

$firmaId = (int)($_SESSION['firma_id'] ?? 0);
if ($firmaId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Firma seçilmedi', 'results' => []]);
    exit;
}

// Yetki kontrolü (Personel listesini görebilme yetkisi)
if (!Gate::allows('personel_listesi')) {
    echo json_encode(['status' => 'error', 'message' => 'Personel arama yetkiniz bulunmamaktadır', 'results' => []]);
    exit;
}

$query = trim($_GET['q'] ?? ($_POST['q'] ?? ''));

if (mb_strlen($query, 'UTF-8') < 2) {
    echo json_encode(['status' => 'success', 'total' => 0, 'data' => []]);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$searchModel = new GlobalSearchModel();

$searchResult = $searchModel->searchGlobal($query, $firmaId, $userId, ['personel'], 15);

echo json_encode([
    'status' => 'success',
    'query' => htmlspecialchars($query, ENT_QUOTES, 'UTF-8'),
    'total' => $searchResult['total'],
    'categories' => $searchResult['categories']
]);
