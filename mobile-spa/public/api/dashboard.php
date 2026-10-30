<?php

declare(strict_types=1);

use App\Model\MobileDashboardModel;
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

function dashboardCan(array $permissionKeys): bool
{
    return Gate::allowsAny($permissionKeys);
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
    'personnel' => dashboardCan(['personel_listesi']),
    'vehicles' => dashboardCan(['arac_takip']),
    'reports' => dashboardCan(['puantaj/raporlar']),
    'illegal' => dashboardCan(['kacak_islemleri']),
    'notices' => dashboardCan(['ihbar/list']),
    'shifts' => dashboardCan(['nobet/list']),
    'requests' => dashboardCan(['talepler', 'avans_talepleri', 'izin_talepleri', 'ariza_talepleri']),
];

if (($_GET['section'] ?? '') === 'endeks_comparison') {
    if (!$permissions['reports']) {
        dashboardResponse(['success' => false, 'message' => 'Bu verilere erişim yetkiniz yok.'], 403);
    }
    try {
        dashboardResponse(['success' => true, 'data' => $model->endeksComparison()]);
    } catch (Throwable $error) {
        error_log('Mobile endeks comparison API: ' . $error->getMessage());
        dashboardResponse(['success' => false, 'message' => 'Endeks karşılaştırması alınamadı.'], 500);
    }
}

if (($_GET['section'] ?? '') === 'kesme_comparison') {
    if (!$permissions['reports']) {
        dashboardResponse(['success' => false, 'message' => 'Bu verilere erişim yetkiniz yok.'], 403);
    }
    try {
        dashboardResponse(['success' => true, 'data' => $model->kesmeComparison()]);
    } catch (Throwable $error) {
        error_log('Mobile kesme comparison API: ' . $error->getMessage());
        dashboardResponse(['success' => false, 'message' => 'Kesme/Açma karşılaştırması alınamadı.'], 500);
    }
}

try {
    $data = [
        'announcements' => $model->announcements(),
        'card_preferences' => $model->getCardSettings($userId, $firmaId),
    ];

    if (dashboardCan(['evrak-takip/list'])) {
        $evrakModel = new \App\Model\EvrakTakipModel();
        $signatureCount = $evrakModel->countPendingSignatureForUser($userId);
        if ($signatureCount > 0) {
            $data['evrak_signature'] = [
                'count' => $signatureCount,
                'items' => array_map(static fn(object $row): array => [
                    'id' => \App\Helper\Security::encrypt((int) $row->id),
                    'konu' => (string) ($row->konu ?: 'Belirtilmedi'),
                    'kurum_adi' => (string) ($row->kurum_adi ?: '-'),
                    'tarih' => !empty($row->tarih) ? date('d.m.Y', strtotime((string) $row->tarih)) : '',
                ], $evrakModel->pendingSignatureItemsForUser($userId, 5)),
            ];
        }
        $uetsUnread = (new \App\Model\EvrakSevkModel())->unreadUets($firmaId, $userId, 5);
        if ($uetsUnread['count'] > 0) {
            $data['uets_unread'] = [
                'count' => $uetsUnread['count'],
                'items' => array_map(static fn(object $row): array => [
                    'id' => \App\Helper\Security::encrypt((int) $row->id),
                    'konu' => (string) ($row->konu ?: 'Belirtilmedi'),
                    'kurum_adi' => (string) ($row->kurum_adi ?: '-'),
                    'tarih' => !empty($row->tarih) ? date('d.m.Y', strtotime((string) $row->tarih)) : '',
                ], $uetsUnread['items']),
            ];
        }
    }

    if ($permissions['personnel']) {
        $data['personnel'] = $model->personnel();
        $data['birthdays'] = $model->birthdays();
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

