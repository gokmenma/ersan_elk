<?php

declare(strict_types=1);

use App\Model\MobileDashboardModel;
use App\Model\MenuModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function dashboardResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function dashboardCan(array $permissionKeys, array $menuLinks = []): bool
{
    if (Gate::allowsAny($permissionKeys)) return true;
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) return false;
    $menus = new MenuModel();
    foreach ($menuLinks as $link) {
        if ($menus->userCanAccessMenuLink($userId, $link)) return true;
    }
    return false;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user']) || empty($_SESSION['firma_id'])) {
    dashboardResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
}

$userId = (int) $_SESSION['user_id'];
$firmaId = (int) $_SESSION['firma_id'];
$model = new MobileDashboardModel();

// Kart sıralaması ve ayarlarını kaydetme isteği (POST action=save_card_settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = $input['action'] ?? '';
    if ($action === 'save_card_settings') {
        $order = is_array($input['order'] ?? null) ? $input['order'] : [];
        $settings = is_array($input['settings'] ?? null) ? $input['settings'] : [];
        $saved = $model->saveCardSettings($userId, $firmaId, $order, $settings);
        dashboardResponse(['success' => $saved, 'message' => $saved ? 'Ayarlar kaydedildi.' : 'Ayarlar kaydedilemedi.']);
    }
}

$permissions = [
    'personnel' => dashboardCan(['personel_listesi'], ['personel/list']),
    'vehicles' => dashboardCan(['arac_takip_yonetim'], ['arac-takip/list']),
    'reports' => dashboardCan(['puantaj_raporlama', 'puantaj_yonetim', 'puantaj/raporlar', 'puantaj/list'], ['puantaj/raporlar', 'puantaj/list']),
    'illegal' => dashboardCan(['kacak_islemleri', 'kacak/list'], ['kacak/list']),
    'notices' => dashboardCan(['ihbar/list'], ['ihbar/list']),
    'shifts' => dashboardCan(['nobet_goruntuleme', 'nobet_onaylama', 'nobet_yonetim', 'nobet/list', 'nobetler'], ['nobet/list', 'nobet-onay/list']),
    'requests' => dashboardCan(['talepler', 'talep_goruntuleme', 'avans_goruntuleme', 'izin_goruntuleme', 'talepler/list'], ['talepler/list']),
];

try {
    $data = [
        'announcements' => $model->announcements(),
        'card_preferences' => $model->getCardSettings($userId, $firmaId),
    ];

    if ($permissions['personnel']) {
        $data['personnel'] = $model->personnel();
    }

    if ($permissions['vehicles']) {
        $data['vehicles'] = $model->vehicles();
    }

    if ($permissions['shifts']) {
        $data['shifts'] = $model->shifts();
    }

    if ($permissions['requests']) {
        $data['pending_approvals'] = $model->pendingApprovals();
    }

    $operations = [];
    if ($permissions['reports']) {
        $operations = [$model->readings(), ...$model->workOperations()];
    }
    if ($permissions['illegal']) {
        $operations[] = $model->illegalUse();
    }
    if ($permissions['notices']) {
        $operations[] = $model->notices();
    }
    if ($operations !== []) {
        $data['operations'] = $operations;
    }

    dashboardResponse(['success' => true, 'data' => $data]);
} catch (Throwable $error) {
    error_log('Mobile dashboard API: ' . $error->getMessage());
    dashboardResponse(['success' => false, 'message' => 'Ana sayfa verileri alınamadı.'], 500);
}

