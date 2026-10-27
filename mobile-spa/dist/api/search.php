<?php

declare(strict_types=1);

use App\Model\MobileGlobalSearchModel;
use App\Model\SystemLogModel;
use App\Service\MobileSearchAccess;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function searchResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$firmaId = (int) ($_SESSION['firma_id'] ?? $_SESSION['firm_id'] ?? 0);
if ($userId <= 0 || empty($_SESSION['user'])) searchResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
if ($firmaId <= 0) searchResponse(['success' => false, 'message' => 'Lütfen önce firma seçin.'], 400);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') searchResponse(['success' => false, 'message' => 'Geçersiz istek.'], 405);

try {
    $modules = MobileSearchAccess::modules();
    $allowed = array_keys($modules);
    $action = (string) ($_GET['action'] ?? 'search');

    if ($action === 'modules') {
        searchResponse(['success' => true, 'allowed_modules' => $allowed]);
    }

    $query = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($query, 'UTF-8') < 2) {
        searchResponse(['success' => true, 'query' => $query, 'allowed_modules' => $allowed, 'counts' => ['all' => 0], 'results' => (object) []]);
    }
    $query = mb_substr($query, 0, 80, 'UTF-8');

    $category = (string) ($_GET['category'] ?? 'all');
    if ($category !== 'all') {
        $modules = isset($modules[$category]) && in_array($category, MobileSearchAccess::MODULES, true)
            ? [$category => $modules[$category]]
            : [];
    }

    $limit = max(1, min(10, (int) ($_GET['limit'] ?? 6)));
    $results = $modules === [] ? [] : (new MobileGlobalSearchModel())->search($query, $firmaId, $userId, $modules, $limit);

    $counts = array_map('count', $results);
    $counts['all'] = array_sum($counts);

    try {
        (new SystemLogModel())->logActionForFirm($firmaId, $userId, 'Global Arama', 'Mobil global arama yapıldı: ' . mb_substr($query, 0, 40, 'UTF-8'));
    } catch (Throwable $logError) {
        error_log('search.php log: ' . $logError->getMessage());
    }

    searchResponse([
        'success' => true,
        'query' => $query,
        'allowed_modules' => $allowed,
        'counts' => $counts,
        'results' => (object) $results,
    ]);
} catch (Throwable $e) {
    error_log('search.php: ' . $e->getMessage());
    searchResponse(['success' => false, 'message' => 'Arama yapılamadı.'], 500);
}
