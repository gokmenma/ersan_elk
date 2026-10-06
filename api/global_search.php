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

$allowedCategories = ['all', 'personel', 'araclar', 'demirbaslar', 'cariler', 'evraklar', 'gorevler', 'kacak', 'aparatlar', 'faturalar'];
if (!in_array($category, $allowedCategories, true)) {
    $category = 'all';
}

// Modül izinleri kontrolü
$allowedModules = [];
$isSuperAdmin = Gate::isSuperAdmin();

if ($isSuperAdmin 
    || Gate::allows('Personel Listesi') 
    || Gate::allows('Personeller') 
    || Gate::allows('personel_listesi') 
    || Gate::allows('personel/list') 
    || Gate::allows('personel')) {
    $allowedModules[] = 'personel';
}

if ($isSuperAdmin 
    || Gate::allows('Araç Takip') 
    || Gate::allows('Araç Takip/Yönetim') 
    || Gate::allows('arac_takip') 
    || Gate::allows('arac_takip_yonetim') 
    || Gate::allows('arac-takip/list') 
    || Gate::allows('arac_takip_puantaj')) {
    $allowedModules[] = 'araclar';
}

if ($isSuperAdmin 
    || Gate::allows('Demirbaş Yönetimi') 
    || Gate::allows('Demirbaş/Zimmet İşlemleri Sayfası') 
    || Gate::allows('demirbas/list') 
    || Gate::allows('demirbas_yonetimi') 
    || Gate::allows('demirbas')) {
    $allowedModules[] = 'demirbaslar';
}

if ($isSuperAdmin 
    || Gate::allows('Cari Takibi') 
    || Gate::allows('Cari Hesap Hareketleri') 
    || Gate::allows('cari_takibi') 
    || Gate::allows('cari_hesap_hareketleri') 
    || Gate::allows('cari/list') 
    || Gate::allows('cari')) {
    $allowedModules[] = 'cariler';
}

if ($isSuperAdmin 
    || Gate::allows('Evrak Takip') 
    || Gate::allows('Evrak Bilgileri Sekmesi') 
    || Gate::allows('evrak-takip/list') 
    || Gate::allows('evrak_bilgileri_sekmesi') 
    || Gate::allows('evrak-takip/gelen-evrak') 
    || Gate::allows('evrak-takip/giden-evrak')) {
    $allowedModules[] = 'evraklar';
}

if ($isSuperAdmin 
    || Gate::allows('Görevler') 
    || Gate::allows('Görev ve Bildirimler') 
    || Gate::allows('gorevler') 
    || Gate::allows('gorevler/list') 
    || Gate::allows('gorev_bildirim_log_kayitlari')) {
    $allowedModules[] = 'gorevler';
}

if ($isSuperAdmin 
    || Gate::allows('Kaçak İşlemleri') 
    || Gate::allows('Kaçak Bildirim Onayı') 
    || Gate::allows('kacak_islemleri') 
    || Gate::allows('kacak/list') 
    || Gate::allows('kacak_onay') 
    || Gate::allows('kacak_duzenle')) {
    $allowedModules[] = 'kacak';
}

if ($isSuperAdmin 
    || Gate::allows('Aparat Takip') 
    || Gate::allows('Aparat Deposu') 
    || Gate::allows('Aparat Tanımları') 
    || Gate::allows('aparat_takip') 
    || Gate::allows('aparat-takip/list') 
    || Gate::allows('aparat_tanim') 
    || Gate::allows('aparat_depo')) {
    $allowedModules[] = 'aparatlar';
}

if ($isSuperAdmin 
    || Gate::allows('E-Fatura & E-Arşiv Yönetimi') 
    || Gate::allows('Gelen Faturalar') 
    || Gate::allows('Taslak Faturalar') 
    || Gate::allows('Yeni Fatura Kesme') 
    || Gate::allows('E-Fatura Cari Listesi') 
    || Gate::allows('E-Fatura Mal/Hizmet Tanımları') 
    || Gate::allows('E-Fatura Ayarları') 
    || Gate::allows('E-Fatura Dashboard') 
    || Gate::allows('efatura/giden-list') 
    || Gate::allows('efatura/gelen-list') 
    || Gate::allows('efatura/taslak-list') 
    || Gate::allows('efatura/olustur') 
    || Gate::allows('efatura/dashboard')) {
    $allowedModules[] = 'faturalar';
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
