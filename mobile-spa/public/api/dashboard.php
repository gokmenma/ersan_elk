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

$permissions = [
    'personnel' => dashboardCan(['personel_listesi'], ['personel/list']),
    'vehicles' => dashboardCan(['arac_takip_yonetim'], ['arac-takip/list']),
    'reports' => dashboardCan(['puantaj_raporlama', 'puantaj_yonetim', 'puantaj/raporlar', 'puantaj/list'], ['puantaj/raporlar', 'puantaj/list']),
    'illegal' => dashboardCan(['kacak_islemleri', 'kacak/list'], ['kacak/list']),
    'notices' => dashboardCan(['ihbar/list'], ['ihbar/list']),
];

try {
    $model = new MobileDashboardModel();
    $data = ['announcements' => $model->announcements()];
    if ($permissions['personnel']) $data['personnel'] = $model->personnel();
    if ($permissions['vehicles']) $data['vehicles'] = $model->vehicles();
    $operations = [];
    if ($permissions['reports']) $operations = [...$model->workOperations(), $model->readings()];
    if ($permissions['illegal']) $operations[] = $model->illegalUse();
    if ($permissions['notices']) $operations[] = $model->notices();
    if ($operations !== []) $data['operations'] = $operations;
    dashboardResponse(['success' => true, 'data' => $data]);
} catch (Throwable $error) {
    error_log('Mobile dashboard API: ' . $error->getMessage());
    dashboardResponse(['success' => false, 'message' => 'Ana sayfa verileri alınamadı.'], 500);
}
