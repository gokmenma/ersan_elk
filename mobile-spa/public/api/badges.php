<?php

declare(strict_types=1);

use App\Model\AvansModel;
use App\Model\DestekModel;
use App\Model\MobilRozetModel;
use App\Model\PersonelIzinleriModel;
use App\Model\TalepModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function badgesResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$firmaId = (int) ($_SESSION['firma_id'] ?? $_SESSION['firm_id'] ?? 0);
if ($userId <= 0 || empty($_SESSION['user'])) badgesResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
if ($firmaId <= 0) badgesResponse(['success' => false, 'message' => 'Lütfen önce firma seçin.'], 400);

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = (string) ($_GET['action'] ?? ($method === 'POST' ? 'seen' : 'list'));

$canAvans = Gate::allows('avans_talepleri');
$canIzin = Gate::allows('izin_talepleri');
$canAriza = Gate::allows('ariza_talepleri');
if (!$canAvans && !$canIzin && !$canAriza && Gate::allows('talepler')) {
    $canAvans = $canIzin = $canAriza = true;
}

$permissions = [
    'live_support' => Gate::isSuperAdmin() || Gate::allows('canli_sohbet_ayarlari_sekmesi'),
    'efatura_gelen' => Gate::isSuperAdmin() || Gate::allows('efatura/gelen-list'),
    'efatura_giden' => Gate::isSuperAdmin() || Gate::allows('efatura/giden-list'),
    'km_approvals' => Gate::isSuperAdmin() || Gate::allows('arac_km_onaylari'),
    'kacak' => Gate::isSuperAdmin() || Gate::allows('kacak_onay'),
    'ihbar' => Gate::isSuperAdmin() || Gate::allows('ihbar/list'),
    'evrak' => Gate::allowsAny(['evrak-takip/list', 'evrak-takip/gelen-evrak', 'evrak-takip/giden-evrak']),
    'requests' => $canAvans || $canIzin || $canAriza,
];

function badgesRowIds(array $rows): array
{
    return array_map(static fn ($row): int => (int) (is_object($row) ? $row->id : ($row['id'] ?? 0)), $rows);
}

function badgesIdSets(string $key, MobilRozetModel $model, int $userId, int $firmaId, bool $canAvans, bool $canIzin, bool $canAriza): array
{
    switch ($key) {
        case 'km_approvals':
            return ['km_approvals' => $model->kmIdleri($firmaId)];
        case 'kacak':
            return ['kacak' => $model->kacakIdleri($firmaId)];
        case 'ihbar':
            return ['ihbar' => $model->ihbarIdleri($firmaId)];
        case 'evrak':
            return ['evrak' => $model->evrakIdleri($firmaId, $userId)];
        case 'requests':
            $sets = [];
            if ($canAvans) $sets['requests_avans'] = badgesRowIds((new AvansModel())->getButunBekleyenAvanslar());
            if ($canIzin) {
                try { $sets['requests_izin'] = badgesRowIds((new PersonelIzinleriModel())->getButunBekleyenIzinler()); } catch (Throwable) {}
            }
            if ($canAriza) $sets['requests_talep'] = badgesRowIds((new TalepModel())->getButunBekleyenTalepler());
            return $sets;
    }
    return [];
}

$idSetKeys = ['km_approvals', 'kacak', 'ihbar', 'evrak', 'requests'];

try {
    $rozetModel = new MobilRozetModel();

    if ($action === 'seen' && $method === 'POST') {
        $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $known = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
            badgesResponse(['success' => false, 'message' => 'Güvenlik doğrulamasının süresi doldu. Lütfen sayfayı yenileyin.'], 419);
        }
        $payload = json_decode((string) file_get_contents('php://input'), true);
        $key = (string) ($payload['key'] ?? '');
        if (empty($permissions[$key])) {
            badgesResponse(['success' => false, 'message' => 'Geçersiz işlem.'], 403);
        }
        if (in_array($key, MobilRozetModel::faturaAnahtarlari(), true)) {
            $rozetModel->faturaGoruldu($userId, $firmaId, $key);
        } elseif (in_array($key, $idSetKeys, true)) {
            foreach (badgesIdSets($key, $rozetModel, $userId, $firmaId, $canAvans, $canIzin, $canAriza) as $setKey => $ids) {
                $rozetModel->goruldu($userId, $firmaId, $setKey, $ids);
            }
        } else {
            badgesResponse(['success' => false, 'message' => 'Geçersiz işlem.'], 403);
        }
        badgesResponse(['success' => true]);
    }

    $badges = [];
    if ($permissions['live_support']) {
        $badges['live_support'] = (int) (new DestekModel())->getTotalUnreadForAdmin($firmaId);
    }
    foreach (MobilRozetModel::faturaAnahtarlari() as $key) {
        if ($permissions[$key]) {
            $badges[$key] = $rozetModel->faturaOkunmamisSayisi($userId, $firmaId, $key);
        }
    }
    foreach ($idSetKeys as $key) {
        if (!$permissions[$key]) continue;
        $total = 0;
        foreach (badgesIdSets($key, $rozetModel, $userId, $firmaId, $canAvans, $canIzin, $canAriza) as $setKey => $ids) {
            $total += $rozetModel->yeniSayisi($userId, $firmaId, $setKey, $ids);
        }
        $badges[$key] = $total;
    }
    if (Gate::allows('evrak-takip/list')) {
        $badges['uets_unread'] = (int) (new \App\Model\EvrakSevkModel())->unreadUets($firmaId, $userId, 0)['count'];
    }
    badgesResponse(['success' => true, 'badges' => $badges]);
} catch (Throwable $e) {
    error_log('badges.php: ' . $e->getMessage());
    badgesResponse(['success' => false, 'message' => 'Rozet bilgisi alınamadı.'], 500);
}
