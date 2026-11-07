<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\PermissionPolicyModel;
use App\Model\SiteMesajModel;
use App\Model\SystemLogModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function siteMesajResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function siteMesajId(string $encrypted): int
{
    try { return (int) Security::decrypt($encrypted); } catch (Throwable) { return 0; }
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) siteMesajResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');
$resource = 'mobile-spa/site-mesajlar';
if ((new PermissionPolicyModel())->isReady()) {
    Gate::authorizeApiPolicy($resource, $action);
} elseif (!(Gate::allows('site_mesaj_yonetimi') || Gate::isSuperAdmin())) {
    siteMesajResponse(['success' => false, 'message' => 'Site mesajları yetkiniz bulunmuyor.'], 403);
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'POST') {
    $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
    $known = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sent === '' || $known === '' || !hash_equals($known, $sent)) {
        siteMesajResponse(['success' => false, 'message' => 'Güvenlik doğrulamasının süresi doldu. Lütfen sayfayı yenileyin.'], 419);
    }
}

$model = new SiteMesajModel();
$userId = (int) $_SESSION['user_id'];

try {
    if ($method === 'GET' && $action === 'list') {
        $durum = (string) ($_GET['status'] ?? 'all');
        $arama = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 80);
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
        $sayfa = max(1, (int) ($_GET['page'] ?? 1));
        $sonuc = $model->ajaxList([
            'draw' => 0,
            'start' => ($sayfa - 1) * $limit,
            'length' => $limit,
            'search' => ['value' => $arama],
            'durum_filtre' => $durum,
        ]);
        $items = array_map(static fn(object $m): array => [
            'id' => (string) $m->enc_id,
            'name' => (string) $m->ad_soyad,
            'phone' => (string) ($m->telefon ?? ''),
            'email' => (string) ($m->email ?? ''),
            'subject' => (string) ($m->konu ?? ''),
            'preview' => (string) ($m->ozet ?? ''),
            'status' => (string) $m->durum,
            'created_at' => (string) $m->created_at,
        ], $sonuc['data']);
        $ozet = $model->summary();
        siteMesajResponse([
            'success' => true,
            'items' => $items,
            'total' => (int) $sonuc['recordsFiltered'],
            'has_more' => ($sayfa * $limit) < (int) $sonuc['recordsFiltered'],
            'summary' => ['toplam' => $ozet->toplam, 'yeni' => $ozet->yeni, 'okundu' => $ozet->okundu, 'cevaplandi' => $ozet->cevaplandi],
        ]);
    }

    if ($method === 'GET' && $action === 'detail') {
        $m = $model->bul(siteMesajId((string) ($_GET['id'] ?? '')));
        if (!$m) siteMesajResponse(['success' => false, 'message' => 'Mesaj bulunamadı.'], 404);
        siteMesajResponse(['success' => true, 'item' => [
            'id' => Security::encrypt((int) $m->id),
            'name' => (string) $m->ad_soyad,
            'phone' => (string) ($m->telefon ?? ''),
            'email' => (string) ($m->email ?? ''),
            'subject' => (string) ($m->konu ?? ''),
            'message' => (string) $m->mesaj,
            'status' => (string) $m->durum,
            'note' => (string) ($m->yonetici_notu ?? ''),
            'created_at' => (string) $m->created_at,
        ]]);
    }

    if ($method === 'POST' && $action === 'update_status') {
        $input = $_POST;
        if (str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
        }
        $id = siteMesajId((string) ($input['id'] ?? ''));
        $durum = (string) ($input['status'] ?? '');
        $not = mb_substr(trim((string) ($input['note'] ?? '')), 0, 2000);
        if ($id <= 0 || !$model->bul($id) || !isset(SiteMesajModel::DURUMLAR[$durum])) {
            siteMesajResponse(['success' => false, 'message' => 'Geçersiz mesaj veya durum.'], 422);
        }
        if (!$model->durumGuncelle($id, $durum, $userId, $not)) {
            siteMesajResponse(['success' => false, 'message' => 'Mesaj güncellenemedi.'], 422);
        }
        (new SystemLogModel())->logAction($userId, 'Site Mesajı', 'Mesaj durumu değişti (Mobil, Mesaj ID: ' . $id . ') → ' . $durum, SystemLogModel::LEVEL_INFO);
        siteMesajResponse(['success' => true, 'message' => 'Mesaj güncellendi.']);
    }

    siteMesajResponse(['success' => false, 'message' => 'Geçersiz istek.'], 405);
} catch (Throwable $error) {
    error_log('Mobile site mesajlar API: ' . $error->getMessage());
    siteMesajResponse(['success' => false, 'message' => 'Site mesajı işlemi tamamlanamadı.'], 500);
}
