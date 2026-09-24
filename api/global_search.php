<?php
ob_start();
session_start();

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/Autoloader.php';

use App\Service\Gate;
use App\Model\GlobalSearchModel;

// Oturum kontrolü
if (empty($_SESSION['user_id']) && empty($_SESSION['user']->id)) {
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Yetkisiz erişim veya oturum süresi dolmuş.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$firmaId = (int)($_SESSION['firma_id'] ?? 0);
if ($firmaId <= 0) {
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error', 
        'message' => 'Lütfen bir firma seçiniz.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$query    = isset($_GET['q']) ? trim((string)$_GET['q']) : (isset($_POST['q']) ? trim((string)$_POST['q']) : '');
$category = isset($_GET['category']) ? trim((string)$_GET['category']) : 'all';
$limit    = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 1), 20) : 8;

$allowedCategories = ['all', 'personel', 'araclar', 'demirbaslar', 'cariler', 'evraklar', 'gorevler', 'kacak', 'aparatlar'];
if (!in_array($category, $allowedCategories, true)) {
    $category = 'all';
}

// Modül izinleri kontrolü
$allowedModules = [];
$isSuperAdmin = Gate::isSuperAdmin();

if ($isSuperAdmin || Gate::allows('Personel Listesi') || Gate::allows('Personeller') || Gate::allows('personel_listesi')) {
    $allowedModules[] = 'personel';
}
if ($isSuperAdmin || Gate::allows('Araç Takip') || Gate::allows('Araç Takip/Yönetim')) {
    $allowedModules[] = 'araclar';
}
if ($isSuperAdmin || Gate::allows('Demirbaş Yönetimi') || Gate::allows('Demirbaş/Zimmet İşlemleri Sayfası')) {
    $allowedModules[] = 'demirbaslar';
}
if ($isSuperAdmin || Gate::allows('Cari Takibi') || Gate::allows('Cari Hesap Hareketleri')) {
    $allowedModules[] = 'cariler';
}
if ($isSuperAdmin || Gate::allows('Evrak Takip') || Gate::allows('Evrak Bilgileri Sekmesi')) {
    $allowedModules[] = 'evraklar';
}
if ($isSuperAdmin || Gate::allows('Görevler') || Gate::allows('Görev ve Bildirimler')) {
    $allowedModules[] = 'gorevler';
}
if ($isSuperAdmin || Gate::allows('Kaçak İşlemleri') || Gate::allows('Kaçak Bildirim Onayı')) {
    $allowedModules[] = 'kacak';
}
if ($isSuperAdmin || Gate::allows('Aparat Takip') || Gate::allows('Aparat Deposu') || Gate::allows('Aparat Tanımları')) {
    $allowedModules[] = 'aparatlar';
}

if (empty($allowedModules)) {
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'Arama yapabileceğiniz modül yetkisi bulunmamaktadır.',
        'counts' => ['all' => 0],
        'results' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $userId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
    $searchModel = new GlobalSearchModel();
    $data = $searchModel->search($query, $firmaId, $userId, $category, $limit, $allowedModules);

    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'          => 'success',
        'query'           => $query,
        'counts'          => $data['counts'],
        'results'         => $data['results'],
        'allowed_modules' => $allowedModules
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (\Exception $e) {
    error_log('Global Search API Error: ' . $e->getMessage());

    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Arama işlemi sırasında bir sunucu hatası oluştu.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
