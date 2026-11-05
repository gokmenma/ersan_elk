<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\GorevModel;
use App\Model\SystemLogModel;
use App\Model\UserModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function gorevApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
    gorevApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş. Lütfen tekrar giriş yapın.'], 401);
}

if (!(Gate::allows('gorevler') || Gate::isSuperAdmin())) {
    gorevApiResponse(['success' => false, 'message' => 'Görevler modülüne erişim yetkiniz bulunmuyor.'], 403);
}

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($firmaId <= 0 || $userId <= 0) {
    gorevApiResponse(['success' => false, 'message' => 'Geçerli bir firma seçilmemiş.'], 400);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'list');

$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $json = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
    $body = is_array($json) ? $json : $_POST;
    if (isset($body['action'])) {
        $action = (string) $body['action'];
    }
}

$Gorev = new GorevModel();

try {
    if ($method === 'GET' && $action === 'list') {
        $adlar = [];
        foreach ((new UserModel())->getUsers() as $u) {
            $adlar[(int) $u->id] = (string) $u->adi_soyadi;
        }

        $gorevMap = static function (object $g, string $encListeId) use ($adlar): array {
            $atananlar = [];
            foreach (array_filter(array_map('intval', explode(',', (string) $g->gorev_kullanicilari))) as $uid) {
                if (isset($adlar[$uid])) {
                    $atananlar[] = $adlar[$uid];
                }
            }
            return [
                'id' => Security::encrypt((string) $g->id),
                'liste_id' => $encListeId,
                'baslik' => (string) $g->baslik,
                'aciklama' => $g->aciklama !== null ? (string) $g->aciklama : '',
                'tarih' => $g->tarih ?: null,
                'saat' => $g->saat ? substr((string) $g->saat, 0, 5) : null,
                'tamamlandi' => (int) $g->tamamlandi === 1,
                'atananlar' => $atananlar,
                'olusturan_adi' => $adlar[(int) $g->olusturan_id] ?? null,
            ];
        };

        $sonuc = [];
        foreach ($Gorev->getListeler($firmaId, $userId) as $liste) {
            $encListeId = Security::encrypt((string) $liste->id);
            $aktif = array_map(static fn($g) => $gorevMap($g, $encListeId), $Gorev->getGorevler((int) $liste->id, $userId, 0));
            $bitenler = array_map(static fn($g) => $gorevMap($g, $encListeId), $Gorev->getTamamlananlar((int) $liste->id, $userId));
            $sonuc[] = [
                'id' => $encListeId,
                'baslik' => (string) $liste->baslik,
                'renk' => $liste->renk,
                'erisim' => $liste->erisim,
                'gorevler' => array_merge($aktif, $bitenler),
            ];
        }

        gorevApiResponse(['success' => true, 'data' => $sonuc]);
    }

    if ($method === 'POST' && in_array($action, ['tamamla', 'geri-al'], true)) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $body['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if ($token === '' || $sessionToken === '' || !hash_equals((string) $sessionToken, (string) $token)) {
            gorevApiResponse(['success' => false, 'message' => 'Güvenlik doğrulaması geçersiz. Lütfen sayfayı yenileyin.'], 419);
        }

        $gorevId = (int) Security::decrypt((string) ($body['gorev_id'] ?? ''));
        if ($gorevId <= 0) {
            gorevApiResponse(['success' => false, 'message' => 'Geçersiz görev.'], 422);
        }
        if (!in_array($Gorev->gorevYetkisi($gorevId, $userId, $firmaId), ['sahip', 'duzenle'], true)) {
            gorevApiResponse(['success' => false, 'message' => 'Bu görevde değişiklik yapma yetkiniz yok.'], 403);
        }

        $tamamla = $action === 'tamamla';
        $ok = $tamamla ? $Gorev->tamamla($gorevId) : $Gorev->tamamlamayiGeriAl($gorevId);
        if (!$ok) {
            gorevApiResponse(['success' => false, 'message' => 'İşlem başarısız.'], 500);
        }

        (new SystemLogModel())->logAction(
            $userId,
            $tamamla ? 'Görev Tamamlama' : 'Görev Geri Alma',
            $tamamla ? "Görev #$gorevId mobilden tamamlandı." : "Görev #$gorevId mobilden geri alındı.",
            SystemLogModel::LEVEL_INFO
        );

        gorevApiResponse(['success' => true, 'message' => $tamamla ? 'Görev tamamlandı.' : 'Görev geri alındı.']);
    }

    gorevApiResponse(['success' => false, 'message' => 'Geçersiz işlem.'], 400);
} catch (Throwable $e) {
    error_log('Mobil görevler API hatası: ' . $e->getMessage());
    gorevApiResponse(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu.'], 500);
}
