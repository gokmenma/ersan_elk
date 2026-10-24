<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\DestekModel;
use App\Model\PermissionPolicyModel;
use App\Model\SettingsModel;
use App\Service\Gate;
use App\Service\PushNotificationService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function supportResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function supportCsrf(): void
{
    $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
    $known = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
        supportResponse(['success' => false, 'message' => 'Güvenlik doğrulamasının süresi doldu. Lütfen sayfayı yenileyin.'], 419);
    }
}

function supportId(string $encrypted): int
{
    try { return (int) Security::decrypt($encrypted); } catch (Throwable) { return 0; }
}

function supportAvatar(object $conversation): ?string
{
    if ((int) ($conversation->personel_id ?? 0) <= 0) return null;
    if (trim((string) ($conversation->resim_yolu ?? '')) === '') return null;
    return '/api/personnel-photo.php?id=' . rawurlencode(Security::encrypt((string) $conversation->personel_id));
}

function supportConversation(object $item): array
{
    return [
        'id' => Security::encrypt((string) $item->id),
        'personnel_name' => (string) ($item->personel_adi ?? 'Bilinmeyen Personel'),
        'department' => (string) ($item->departman ?? ''),
        'phone' => (string) ($item->cep_telefonu ?? ''),
        'avatar' => supportAvatar($item),
        'subject' => (string) ($item->konu ?? 'Destek Talebi'),
        'status' => (string) ($item->durum ?? 'acik'),
        'priority' => (string) ($item->oncelik ?? 'normal'),
        'preview' => (string) ($item->son_mesaj_onizleme ?? ''),
        'unread' => (int) ($item->okunmamis_yonetici ?? 0),
        'last_message_at' => (string) ($item->son_mesaj_zamani ?? $item->created_at ?? ''),
        'created_at' => (string) ($item->created_at ?? ''),
    ];
}

function supportUpload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Görsel yüklenemedi.');
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('Görsel en fazla 5 MB olabilir.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Yalnızca JPG, PNG, WebP veya GIF yükleyebilirsiniz.');
    $directory = dirname(__DIR__, 3) . '/uploads/destek/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Yükleme klasörü hazırlanamadı.');
    $name = 'destek_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . $name)) throw new RuntimeException('Görsel kaydedilemedi.');
    $scriptBase = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/api/support.php'), 3));
    $base = $scriptBase === '/' ? '' : rtrim($scriptBase, '/');
    return ['url' => $base . '/uploads/destek/' . $name, 'type' => $mime];
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) supportResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
if ($firmaId <= 0) supportResponse(['success' => false, 'message' => 'Lütfen önce firma seçin.'], 400);

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');
$resource = 'mobile-spa/support';
$policy = new PermissionPolicyModel();
if ($policy->isReady()) {
    Gate::authorizeApiPolicy($resource, $action);
} elseif (!(Gate::allows('canli_sohbet_ayarlari_sekmesi') || Gate::isSuperAdmin())) {
    supportResponse(['success' => false, 'message' => 'Canlı destek yönetimi yetkiniz bulunmuyor.'], 403);
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'POST') supportCsrf();
$model = new DestekModel();
$settings = new SettingsModel();
$ownerId = (int) ($_SESSION['user']->owner_id ?? $_SESSION['user_id']);

try {
    if ($method === 'GET' && $action === 'list') {
        $status = trim((string) ($_GET['status'] ?? ''));
        if (!in_array($status, ['', 'aktif', 'beklemede', 'tamamlanan'], true)) $status = '';
        $statusFilter = match ($status) {
            'aktif' => ['acik', 'beklemede'],
            'tamamlanan' => ['cozuldu', 'kapali'],
            default => $status ?: null,
        };
        $search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 80);
        $items = $model->getAllConversations($statusFilter, 100, $firmaId, $search);
        $summary = $model->getAdminSummary($firmaId);
        supportResponse([
            'success' => true,
            'items' => array_map('supportConversation', $items),
            'summary' => array_map('intval', (array) $summary),
            'availability' => $settings->getSettings('canli_destek_admin_durum', $firmaId) ?: 'cevrimici',
            'working_hours' => $model->isWorkingHours($firmaId),
            'server_time' => date(DATE_ATOM),
        ]);
    }

    if ($method === 'GET' && $action === 'get_settings') {
        $values = $settings->getAllSettingsAsKeyValue($firmaId);
        $selectedUsers = array_values(array_filter(array_map('intval', explode(',', (string) ($values['canli_destek_yetkili_kullanicilar'] ?? '')))));
        supportResponse([
            'success' => true,
            'settings' => [
                'enabled' => ($values['canli_destek_aktif'] ?? '1') === '1',
                'authorized_user_ids' => $selectedUsers,
                'working_days' => array_values(array_filter(array_map('intval', explode(',', (string) ($values['canli_destek_gunler'] ?? '1,2,3,4,5,6'))))),
                'start_time' => (string) ($values['canli_destek_baslama_saati'] ?? '08:00'),
                'end_time' => (string) ($values['canli_destek_bitis_saati'] ?? '18:00'),
            ],
            'users' => array_map(static fn(object $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->adi_soyadi,
                'username' => (string) $user->user_name,
            ], $model->getSupportUsers($firmaId, $ownerId)),
        ]);
    }

    if ($method === 'GET' && in_array($action, ['detail', 'poll'], true)) {
        $id = supportId((string) ($_GET['id'] ?? ''));
        $conversation = $id > 0 ? $model->getConversation($id, $firmaId) : null;
        if (!$conversation) supportResponse(['success' => false, 'message' => 'Konuşma bulunamadı.'], 404);
        $afterId = max(0, (int) ($_GET['after_id'] ?? 0));
        $messages = $model->getMessages($id, $afterId, 100);
        if ($messages) $model->markMessagesAsRead($id, 'yonetici');
        $mapped = array_map(static fn(object $message): array => [
            'id' => (int) $message->id,
            'sender_type' => (string) $message->gonderen_tip,
            'sender_name' => (string) ($message->gonderen_adi ?? ''),
            'message' => (string) $message->mesaj,
            'file_url' => $message->dosya_url ? (string) $message->dosya_url : null,
            'file_type' => $message->dosya_tip ? (string) $message->dosya_tip : null,
            'read' => (bool) $message->okundu,
            'created_at' => (string) $message->created_at,
        ], $messages);
        supportResponse([
            'success' => true,
            'conversation' => supportConversation($conversation),
            'messages' => $mapped,
            'opponent_last_read_id' => $model->getOpponentLastReadId($id, 'yonetici'),
        ]);
    }

    $input = $_POST;
    if (str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
        $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
    }

    if ($method === 'POST' && $action === 'send') {
        $id = supportId((string) ($input['id'] ?? ''));
        $conversation = $id > 0 ? $model->getConversation($id, $firmaId) : null;
        if (!$conversation) supportResponse(['success' => false, 'message' => 'Konuşma bulunamadı.'], 404);
        $message = mb_substr(trim((string) ($input['message'] ?? '')), 0, 4000);
        $upload = isset($_FILES['image']) ? supportUpload($_FILES['image']) : null;
        if ($message === '' && !$upload) supportResponse(['success' => false, 'message' => 'Mesaj veya görsel ekleyin.'], 422);
        if ($message === '') $message = '📷 Görsel';
        $messageId = $model->sendMessage($id, ['tip' => 'yonetici', 'id' => (int) $_SESSION['user_id']], $message, $upload['url'] ?? null, $upload['type'] ?? null);
        $settings->upsertMultipleSettings(['canli_destek_admin_durum' => 'cevrimici'], $firmaId, null);
        try {
            (new PushNotificationService())->sendToPersonel((int) $conversation->personel_id, [
                'title' => 'Canlı Destek', 'body' => mb_substr($message, 0, 100), 'url' => '?page=ana-sayfa', 'tag' => 'destek-' . $id,
            ]);
        } catch (Throwable $pushError) { error_log('Mobil canlı destek push: ' . $pushError->getMessage()); }
        supportResponse(['success' => true, 'message_id' => (int) $messageId]);
    }

    if ($method === 'POST' && $action === 'update_status') {
        $id = supportId((string) ($input['id'] ?? ''));
        $conversation = $id > 0 ? $model->getConversation($id, $firmaId) : null;
        $status = (string) ($input['status'] ?? '');
        if (!$conversation || !in_array($status, ['acik', 'beklemede', 'cozuldu', 'kapali'], true)) supportResponse(['success' => false, 'message' => 'Geçersiz konuşma veya durum.'], 422);
        $model->updateStatus($id, $status);
        if (in_array($status, ['cozuldu', 'kapali'], true)) $model->sendSystemMessage($id, '✅ Konuşma kapatıldı. İyi günler dileriz!');
        supportResponse(['success' => true]);
    }

    if ($method === 'POST' && $action === 'set_availability') {
        $status = (string) ($input['status'] ?? '');
        if (!in_array($status, ['cevrimici', 'mesgul', 'cevrimdisi'], true)) supportResponse(['success' => false, 'message' => 'Geçersiz müsaitlik durumu.'], 422);
        $settings->upsertMultipleSettings(['canli_destek_admin_durum' => $status], $firmaId, null);
        supportResponse(['success' => true, 'availability' => $status]);
    }

    if ($method === 'POST' && $action === 'save_settings') {
        $enabled = !empty($input['enabled']) ? '1' : '0';
        $days = array_values(array_unique(array_filter(array_map('intval', (array) ($input['working_days'] ?? [])), static fn(int $day): bool => $day >= 1 && $day <= 7)));
        sort($days);
        if (!$days) supportResponse(['success' => false, 'message' => 'En az bir çalışma günü seçin.'], 422);
        $start = (string) ($input['start_time'] ?? '');
        $end = (string) ($input['end_time'] ?? '');
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) || $start >= $end) {
            supportResponse(['success' => false, 'message' => 'Mesai başlangıç ve bitiş saatlerini kontrol edin.'], 422);
        }
        $validUserIds = array_map(static fn(object $user): int => (int) $user->id, $model->getSupportUsers($firmaId, $ownerId));
        $userIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['authorized_user_ids'] ?? [])), static fn(int $id): bool => in_array($id, $validUserIds, true))));
        $saved = $settings->upsertMultipleSettings([
            'canli_destek_aktif' => $enabled,
            'canli_destek_yetkili_kullanicilar' => implode(',', $userIds),
            'canli_destek_gunler' => implode(',', $days),
            'canli_destek_baslama_saati' => $start,
            'canli_destek_bitis_saati' => $end,
        ], $firmaId, null);
        if (!$saved) supportResponse(['success' => false, 'message' => 'Canlı destek ayarları kaydedilemedi.'], 500);
        supportResponse(['success' => true, 'message' => 'Canlı destek ayarları kaydedildi.']);
    }

    supportResponse(['success' => false, 'message' => 'Geçersiz istek.'], 405);
} catch (Throwable $error) {
    error_log('Mobile support API: ' . $error->getMessage());
    supportResponse(['success' => false, 'message' => 'Canlı destek işlemi tamamlanamadı.'], 500);
}
