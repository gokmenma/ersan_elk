<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\BildirimModel;
use App\Model\PushSubscriptionModel;
use App\Model\SystemLogModel;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function notifResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
    notifResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
}

$currentUserId = (int) $_SESSION['user_id'];
$bildirimModel = new BildirimModel();
$pushModel = new PushSubscriptionModel();
$systemLog = new SystemLogModel();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? ($method === 'GET' ? 'list' : ''));

try {
    if ($method === 'GET') {
        switch ($action) {
            case 'unread-count':
                $count = (int) $bildirimModel->getUnreadCount($currentUserId);
                notifResponse(['success' => true, 'count' => $count]);
                break;

            case 'vapid-key':
                $configPath = dirname(__DIR__, 3) . '/App/Config/vapid.php';
                $publicKey = '';
                if (file_exists($configPath)) {
                    $config = require $configPath;
                    $publicKey = (string) ($config['publicKey'] ?? '');
                } else {
                    $publicKey = (string) ($_ENV['VAPID_PUBLIC_KEY'] ?? '');
                }
                notifResponse(['success' => true, 'publicKey' => $publicKey]);
                break;

            case 'push-status':
                $isSubscribed = $pushModel->checkUserSubscription($currentUserId);
                notifResponse(['success' => true, 'subscribed' => $isSubscribed]);
                break;

            case 'list':
            default:
                $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 30;
                $unreadOnly = !empty($_GET['unread_only']);
                $notifications = $unreadOnly
                    ? $bildirimModel->getUnreadNotifications($currentUserId)
                    : $bildirimModel->getRecentNotifications($currentUserId, $limit);

                $unreadCount = (int) $bildirimModel->getUnreadCount($currentUserId);

                $formatted = array_map(function ($row) {
                    return [
                        'id' => (int) $row->id,
                        'title' => htmlspecialchars((string) ($row->title ?? ''), ENT_QUOTES, 'UTF-8'),
                        'message' => htmlspecialchars((string) ($row->message ?? ''), ENT_QUOTES, 'UTF-8'),
                        'link' => (string) ($row->link ?? ''),
                        'icon' => htmlspecialchars((string) ($row->icon ?? 'bell'), ENT_QUOTES, 'UTF-8'),
                        'color' => htmlspecialchars((string) ($row->color ?? 'primary'), ENT_QUOTES, 'UTF-8'),
                        'is_read' => ((int) ($row->is_read ?? 0)) === 1,
                        'created_at' => (string) ($row->created_at ?? ''),
                    ];
                }, $notifications ?: []);

                notifResponse([
                    'success' => true,
                    'notifications' => $formatted,
                    'unread_count' => $unreadCount,
                ]);
                break;
        }
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;

        switch ($action) {
            case 'subscribe':
                $endpoint = trim((string) ($data['endpoint'] ?? ''));
                $p256dh = (string) ($data['keys']['p256dh'] ?? $data['p256dh'] ?? '');
                $authKey = (string) ($data['keys']['auth'] ?? $data['auth'] ?? '');
                $encoding = (string) ($data['contentEncoding'] ?? 'aes128gcm');

                if (empty($endpoint) || empty($p256dh) || empty($authKey)) {
                    notifResponse(['success' => false, 'message' => 'Geçersiz abonelik verisi.'], 422);
                }

                $saved = $pushModel->saveUserSubscription($currentUserId, $endpoint, $p256dh, $authKey, $encoding);
                if ($saved) {
                    $systemLog->logAction($currentUserId, 'Push Bildirim', 'Mobil SPA push bildirimi aboneliği kaydedildi.');
                    notifResponse(['success' => true, 'message' => 'Bildirim aboneliği başarıyla oluşturuldu.']);
                }
                notifResponse(['success' => false, 'message' => 'Abonelik kaydedilemedi.'], 500);
                break;

            case 'unsubscribe':
                $endpoint = trim((string) ($data['endpoint'] ?? ''));
                if (!empty($endpoint)) {
                    $pushModel->deleteByEndpoint($endpoint);
                } else {
                    $pushModel->deleteByUser($currentUserId);
                }
                $systemLog->logAction($currentUserId, 'Push Bildirim', 'Mobil SPA push bildirimi aboneliği iptal edildi.');
                notifResponse(['success' => true, 'message' => 'Abonelik iptal edildi.']);
                break;

            case 'mark-read':
                $id = (int) ($data['id'] ?? 0);
                if ($id <= 0) {
                    notifResponse(['success' => false, 'message' => 'Geçersiz bildirim ID.'], 422);
                }
                $bildirimModel->markAsRead($id, $currentUserId);
                $unreadCount = (int) $bildirimModel->getUnreadCount($currentUserId);
                notifResponse(['success' => true, 'unread_count' => $unreadCount]);
                break;

            case 'mark-all-read':
                $bildirimModel->markAllAsRead($currentUserId);
                $systemLog->logAction($currentUserId, 'Bildirim Okundu', 'Tüm bildirimler okundu olarak işaretlendi.');
                notifResponse(['success' => true, 'unread_count' => 0]);
                break;

            default:
                notifResponse(['success' => false, 'message' => 'Geçersiz işlem.'], 400);
        }
    }

    notifResponse(['success' => false, 'message' => 'Geçersiz istek metodu.'], 405);
} catch (Throwable $e) {
    error_log('Mobile SPA Notifications API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    notifResponse(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu.'], 500);
}
