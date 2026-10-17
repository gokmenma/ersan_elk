<?php

declare(strict_types=1);

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\SystemLogModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function logsApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function checkLogsAuth(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
        logsApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
    }
    if (!Gate::allows('log_kayitlari') && !Gate::isSuperAdmin()) {
        logsApiResponse(['success' => false, 'message' => 'Sistem loglarını görüntüleme yetkiniz bulunmuyor.'], 403);
    }
}

checkLogsAuth();

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$currentUserId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
$isSuperAdmin = Gate::isSuperAdmin();

$SystemLog = new SystemLogModel();

function parseIpFromDescription(?string $text): ?string
{
    if (empty($text)) {
        return null;
    }
    if (preg_match('/\bIP:\s*([0-9a-fA-F:.]+)/i', $text, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function formatRelativeTime(string $datetime): string
{
    $time = strtotime($datetime);
    if (!$time) {
        return $datetime;
    }
    $diff = time() - $time;
    if ($diff < 60) {
        return 'Az önce';
    }
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return "{$mins} dk önce";
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return "{$hours} saat önce";
    }
    if ($diff < 172800) {
        return 'Dün ' . date('H:i', $time);
    }
    return date('d.m.Y H:i', $time);
}

function getLevelInfo(?int $level): array
{
    switch ($level) {
        case SystemLogModel::LEVEL_CRITICAL:
            return [
                'name' => 'Kritik',
                'color' => 'danger',
                'icon' => 'AlertCircle',
                'badge_class' => 'badge-danger',
            ];
        case SystemLogModel::LEVEL_IMPORTANT:
            return [
                'name' => 'Önemli',
                'color' => 'warning',
                'icon' => 'AlertTriangle',
                'badge_class' => 'badge-warning',
            ];
        case SystemLogModel::LEVEL_PAGE_VIEW:
            return [
                'name' => 'Sayfa Ziyareti',
                'color' => 'secondary',
                'icon' => 'Eye',
                'badge_class' => 'badge-secondary',
            ];
        case SystemLogModel::LEVEL_INFO:
        default:
            return [
                'name' => 'Bilgi',
                'color' => 'info',
                'icon' => 'Info',
                'badge_class' => 'badge-info',
            ];
    }
}

try {
    // 1. META BİLGİLERİ (Filtre seçenekleri: İşlem tipleri ve kullanıcılar)
    if ($action === 'meta') {
        $meta = $SystemLog->getMobileFilterOptions($firmaId, $isSuperAdmin);

        $userList = array_map(static function ($u) {
            return [
                'id' => (int) $u->user_id,
                'name' => (string) ($u->adi_soyadi ?? 'Kullanıcı #' . $u->user_id),
            ];
        }, $meta['users'] ?? []);

        logsApiResponse([
            'success' => true,
            'action_types' => $meta['action_types'] ?? [],
            'users' => $userList,
        ]);
    }

    // 2. LOG LİSTESİ VE KPI ÖZETİ
    if ($action === 'list') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 30)));
        $offset = ($page - 1) * $limit;

        $search = trim((string) ($_GET['search'] ?? ''));
        $preset = trim((string) ($_GET['preset'] ?? '2days'));
        $dateStart = trim((string) ($_GET['date_start'] ?? ''));
        $dateEnd = trim((string) ($_GET['date_end'] ?? ''));
        $level = isset($_GET['level']) && $_GET['level'] !== '' && $_GET['level'] !== 'all' ? (string) $_GET['level'] : '';
        $actionType = trim((string) ($_GET['action_type'] ?? ''));
        $userId = trim((string) ($_GET['user_id'] ?? ''));

        // Tarih önayarı belirleme (Kullanıcı isteği: Varsayılan son iki günlük kayıtlar gelsin)
        if ($preset === '2days' || (empty($dateStart) && empty($dateEnd) && $preset !== 'all')) {
            $preset = '2days';
            $dateStart = date('Y-m-d', strtotime('-1 day'));
            $dateEnd = date('Y-m-d');
        } elseif ($preset === 'today') {
            $dateStart = date('Y-m-d');
            $dateEnd = date('Y-m-d');
        } elseif ($preset === '7days') {
            $dateStart = date('Y-m-d', strtotime('-6 days'));
            $dateEnd = date('Y-m-d');
        } elseif ($preset === '30days') {
            $dateStart = date('Y-m-d', strtotime('-29 days'));
            $dateEnd = date('Y-m-d');
        } elseif ($preset === 'all') {
            $dateStart = '';
            $dateEnd = '';
        }

        $filters = [
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'level' => $level,
            'action_type' => $actionType,
            'user_id' => $userId,
            'search' => $search,
            'limit' => $limit,
            'offset' => $offset,
        ];

        $rawLogs = $SystemLog->getMobileLogs($filters, $firmaId, $isSuperAdmin);
        $total = $SystemLog->getMobileLogsCount($filters, $firmaId, $isSuperAdmin);

        // KPI İstatistikleri (Tarih ve arama filtrelerine göre)
        $stats = $SystemLog->getMobileLogStats([
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'action_type' => $actionType,
            'user_id' => $userId,
            'search' => $search,
        ], $firmaId, $isSuperAdmin);

        $items = [];
        foreach ($rawLogs as $log) {
            $levelInfo = getLevelInfo(isset($log->level) ? (int) $log->level : null);
            $userName = (string) ($log->user_name ?: 'Sistem');
            $cleanName = trim($userName);
            $nameParts = preg_split('/\s+/u', $cleanName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $initials = '';
            foreach (array_slice($nameParts, 0, 2) as $part) {
                $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
            }
            if ($initials === '') {
                $initials = 'S';
            }

            $createdAt = (string) $log->created_at;
            $timestamp = strtotime($createdAt);

            $isLogin = mb_stripos((string) $log->action_type, 'Giriş') !== false;

            $items[] = [
                'id' => (int) $log->id,
                'action_type' => (string) $log->action_type,
                'description' => (string) ($log->description ?? ''),
                'level' => isset($log->level) ? (int) $log->level : 0,
                'level_name' => $levelInfo['name'],
                'level_color' => $levelInfo['color'],
                'level_icon' => $isLogin ? 'LogIn' : $levelInfo['icon'],
                'badge_class' => $levelInfo['badge_class'],
                'created_at' => $createdAt,
                'formatted_date' => $timestamp ? date('d.m.Y', $timestamp) : '',
                'formatted_time' => $timestamp ? date('H:i:s', $timestamp) : '',
                'relative_time' => formatRelativeTime($createdAt),
                'user_id' => (int) $log->user_id,
                'user_name' => $userName,
                'username' => (string) ($log->username ?? ''),
                'user_initials' => $initials,
                'ip_address' => parseIpFromDescription((string) $log->description),
                'is_login' => $isLogin,
            ];
        }

        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 1;

        logsApiResponse([
            'success' => true,
            'items' => $items,
            'stats' => $stats,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
            'filters' => [
                'preset' => $preset,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'level' => $level,
                'action_type' => $actionType,
                'user_id' => $userId,
                'search' => $search,
            ],
        ]);
    }

    // 3. TEKİL LOG DETAYI
    if ($action === 'detail') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            logsApiResponse(['success' => false, 'message' => 'Geçersiz log ID.'], 400);
        }

        $sql = "SELECT l.*, COALESCE(NULLIF(u.adi_soyadi, ''), 'Sistem') AS user_name, u.user_name AS username
                FROM system_logs l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.id = :id";
        $stmt = $SystemLog->getDb()->prepare($sql);
        $stmt->execute([':id' => $id]);
        $log = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$log) {
            logsApiResponse(['success' => false, 'message' => 'Log kaydı bulunamadı.'], 404);
        }

        // Firma izolasyon kontrolü
        if (!$isSuperAdmin && $firmaId > 0 && (int) $log->firma_id !== $firmaId && (int) $log->firma_id !== 0) {
            logsApiResponse(['success' => false, 'message' => 'Bu kaydı görüntüleme yetkiniz yok.'], 403);
        }

        $levelInfo = getLevelInfo(isset($log->level) ? (int) $log->level : null);
        $createdAt = (string) $log->created_at;
        $timestamp = strtotime($createdAt);

        logsApiResponse([
            'success' => true,
            'log' => [
                'id' => (int) $log->id,
                'firma_id' => (int) $log->firma_id,
                'action_type' => (string) $log->action_type,
                'description' => (string) ($log->description ?? ''),
                'level' => isset($log->level) ? (int) $log->level : 0,
                'level_name' => $levelInfo['name'],
                'level_color' => $levelInfo['color'],
                'created_at' => $createdAt,
                'formatted_date' => $timestamp ? date('d.m.Y H:i:s', $timestamp) : '',
                'relative_time' => formatRelativeTime($createdAt),
                'user_id' => (int) $log->user_id,
                'user_name' => (string) $log->user_name,
                'username' => (string) ($log->username ?? ''),
                'ip_address' => parseIpFromDescription((string) $log->description),
            ],
        ]);
    }

    logsApiResponse(['success' => false, 'message' => 'Bilinmeyen işlem.'], 400);
} catch (\Throwable $e) {
    error_log('Mobile Logs API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    logsApiResponse(['success' => false, 'message' => 'Sunucu hatası oluştu.'], 500);
}
