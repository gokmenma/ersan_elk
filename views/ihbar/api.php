<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\IhbarModel;
use App\Model\UserModel;
use App\Model\BildirimModel;
use App\Helper\Security;
use App\Service\Gate;
use App\Service\PushNotificationService;

header('Content-Type: application/json; charset=utf-8');

function ihbarResponse($success, $message = '', $data = null)
{
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function ihbarNormalizeKonumLink(?string $link): ?string
{
    $link = trim((string) $link);
    if ($link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
        return null;
    }
    $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $link : null;
}

/**
 * "İhbar Yönetimi" yetkisine sahip (Kaçak Kontrol Sorumlusu vb.) kullanıcıları döndürür.
 */
function ihbarGetSorumluKullanicilar(): array
{
    $userModel = new UserModel();
    $db = $userModel->getDb();

    $stmt = $db->prepare("SELECT DISTINCT u.id, u.adi_soyadi, u.email_adresi
        FROM users u
        INNER JOIN user_role_permissions urp ON FIND_IN_SET(urp.role_id, REPLACE(u.roles, ' ', ''))
        INNER JOIN permissions p ON p.id = urp.permission_id
        WHERE p.auth_name = 'ihbar/list' AND u.durum = 'Aktif'");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
}

function ihbarNotifyYeniIhbar(int $ihbarId, string $ozetMetin): void
{
    $sorumlular = ihbarGetSorumluKullanicilar();
    if (empty($sorumlular)) {
        return;
    }

    $bildirimModel = new BildirimModel();
    foreach ($sorumlular as $kullanici) {
        try {
            $bildirimModel->createNotification(
                (int) $kullanici->id,
                '📣 Yeni İhbar',
                $ozetMetin,
                'index.php?p=ihbar/list',
                'alert-triangle',
                'danger'
            );

            $pushService = new PushNotificationService();
            $pushService->sendToUser((int) $kullanici->id, [
                'title' => '📣 Yeni İhbar',
                'body' => $ozetMetin,
                'url' => 'index.php?p=ihbar/list'
            ], true);
        } catch (Exception $e) {
            error_log('İhbar bildirim hatası (sorumlu): ' . $e->getMessage());
        }
    }
}

function ihbarHandleFotoUpload(int $ihbarId, IhbarModel $model): void
{
    if (empty($_FILES['fotograflar']) || empty($_FILES['fotograflar']['name'][0])) {
        return;
    }

    $uploadDir = dirname(__DIR__, 2) . '/uploads/ihbar/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $count = count($_FILES['fotograflar']['name']);
    $maxFiles = min($count, 10);

    for ($i = 0; $i < $maxFiles; $i++) {
        if (($_FILES['fotograflar']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $originalName = $_FILES['fotograflar']['name'][$i];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            continue;
        }

        $newName = 'ihbar_' . uniqid() . '_' . $i . '.' . $ext;
        if (move_uploaded_file($_FILES['fotograflar']['tmp_name'][$i], $uploadDir . $newName)) {
            $model->addFotograf($ihbarId, 'uploads/ihbar/' . $newName);
        }
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($currentUserId <= 0) {
    ihbarResponse(false, 'Oturum sonlanmış veya geçersiz.');
}

if (!empty($_POST['mobile_token'])) {
    $csrf = (string) ($_POST['_mobile_csrf'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
        ihbarResponse(false, 'Güvenlik doğrulaması başarısız. Sayfayı yenileyin.');
    }
    $_POST['id'] = (int) Security::decrypt((string) $_POST['mobile_token']);
    if (!empty($_POST['personel_tokens']) && is_array($_POST['personel_tokens'])) {
        $_POST['personel_ids'] = array_values(array_filter(array_map(
            static fn($token) => (int) Security::decrypt((string) $token),
            $_POST['personel_tokens']
        )));
        if (count($_POST['personel_ids']) > 2) {
            ihbarResponse(false, 'Bir ihbar en fazla iki personele yönlendirilebilir.');
        }
    }
}

try {
    $IhbarModel = new IhbarModel();

    switch ($action) {

        case 'foto':
            Gate::authorizeOrDie('ihbar/list');

            $fotoId = isset($_GET['token'])
                ? (int) Security::decrypt((string) $_GET['token'])
                : (int) ($_GET['foto_id'] ?? 0);
            $stmt = $IhbarModel->getDb()->prepare(
                "SELECT f.dosya_yolu
                 FROM ihbar_fotograflari f
                 INNER JOIN ihbarlar i ON i.id = f.ihbar_id
                 WHERE f.id = ? AND i.firma_id = ? AND i.silinme_tarihi IS NULL"
            );
            $stmt->execute([$fotoId, (int) ($_SESSION['firma_id'] ?? 1)]);
            $dosyaYolu = $stmt->fetchColumn();
            $uploadRoot = realpath(dirname(__DIR__, 2) . '/uploads/ihbar');
            $dosya = $dosyaYolu ? realpath(dirname(__DIR__, 2) . '/' . ltrim($dosyaYolu, '/')) : false;

            if (!$uploadRoot || !$dosya || strpos($dosya, $uploadRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($dosya)) {
                http_response_code(404);
                exit;
            }

            header_remove('Content-Type');
            header('Content-Type: ' . (mime_content_type($dosya) ?: 'application/octet-stream'));
            header('Content-Length: ' . filesize($dosya));
            header('Cache-Control: private, max-age=86400');
            readfile($dosya);
            exit;

        case 'create':
            Gate::authorizeOrDie('ihbar/list');

            $aciklama = trim($_POST['aciklama'] ?? '');
            if ($aciklama === '') {
                throw new Exception('Açıklama zorunludur.');
            }

            $ihbarId = $IhbarModel->create([
                'ilce' => trim($_POST['ilce'] ?? '') ?: null,
                'mahalle' => trim($_POST['mahalle'] ?? '') ?: null,
                'telefon' => trim($_POST['telefon'] ?? '') ?: null,
                'komsu_abone_no' => trim($_POST['komsu_abone_no'] ?? '') ?: null,
                'aciklama' => $aciklama,
                'konum_link' => ihbarNormalizeKonumLink($_POST['konum_link'] ?? null),
                'konum_lat' => is_numeric($_POST['konum_lat'] ?? null) ? (float) $_POST['konum_lat'] : null,
                'konum_lng' => is_numeric($_POST['konum_lng'] ?? null) ? (float) $_POST['konum_lng'] : null,
                'konum_dogruluk' => is_numeric($_POST['konum_dogruluk'] ?? null) ? (float) $_POST['konum_dogruluk'] : null,
                'olusturan_user_id' => $currentUserId,
            ]);

            ihbarHandleFotoUpload($ihbarId, $IhbarModel);

            try {
                ihbarNotifyYeniIhbar($ihbarId, 'Yeni bir ihbar kaydı oluşturuldu. Detaylar için ihbar yönetimi ekranını kontrol edin.');
            } catch (Exception $e) {
                error_log('İhbar bildirim hatası: ' . $e->getMessage());
            }

            ihbarResponse(true, 'İhbar başarıyla kaydedildi.', ['id' => $ihbarId]);
            break;

        case 'update':
            Gate::authorizeOrDie('ihbar/list');

            $id = (int) ($_POST['id'] ?? 0);
            $aciklama = trim($_POST['aciklama'] ?? '');
            if ($aciklama === '') {
                throw new Exception('Açıklama zorunludur.');
            }

            $IhbarModel->updateByYonetici($id, [
                'ilce' => trim($_POST['ilce'] ?? '') ?: null,
                'mahalle' => trim($_POST['mahalle'] ?? '') ?: null,
                'telefon' => trim($_POST['telefon'] ?? '') ?: null,
                'komsu_abone_no' => trim($_POST['komsu_abone_no'] ?? '') ?: null,
                'aciklama' => $aciklama,
                'konum_link' => ihbarNormalizeKonumLink($_POST['konum_link'] ?? null),
                'konum_lat' => is_numeric($_POST['konum_lat'] ?? null) ? (float) $_POST['konum_lat'] : null,
                'konum_lng' => is_numeric($_POST['konum_lng'] ?? null) ? (float) $_POST['konum_lng'] : null,
                'konum_dogruluk' => is_numeric($_POST['konum_dogruluk'] ?? null) ? (float) $_POST['konum_dogruluk'] : null,
            ], $currentUserId);

            ihbarResponse(true, 'İhbar bilgileri güncellendi.', ['id' => $id]);
            break;

        case 'delete':
            Gate::authorizeOrDie('ihbar/list');

            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0 || !$IhbarModel->softDeleteForDashboard($id)) {
                throw new Exception('İhbar bulunamadı veya daha önce silinmiş.');
            }

            ihbarResponse(true, 'İhbar başarıyla silindi.');
            break;

        case 'detay':
            Gate::authorizeOrDie('ihbar/list');

            $id = (int) ($_POST['id'] ?? 0);
            $ihbar = $IhbarModel->getById($id);
            if (!$ihbar) {
                throw new Exception('Kayıt bulunamadı.');
            }
            ihbarResponse(true, '', $ihbar);
            break;

        case 'assign':
            Gate::authorizeOrDie('ihbar/list');

            $id = (int) ($_POST['id'] ?? 0);
            $personelIds = $_POST['personel_ids'] ?? [];
            if (!is_array($personelIds)) {
                $personelIds = [$personelIds];
            }

            $IhbarModel->assignTeam($id, $personelIds, $currentUserId);

            try {
                $pushService = new PushNotificationService();
                foreach (array_map('intval', $personelIds) as $pId) {
                    if ($pId <= 0) {
                        continue;
                    }
                    $pushService->sendToPersonel($pId, [
                        'title' => '📣 Yeni İhbar Yönlendirildi',
                        'body' => 'Size yeni bir ihbar yönlendirildi. Detayları görmek için uygulamayı açın.',
                        'url' => '?page=ihbar'
                    ]);
                }
            } catch (Exception $e) {
                error_log('İhbar yönlendirme push hatası: ' . $e->getMessage());
            }

            ihbarResponse(true, 'İhbar ekibe yönlendirildi.');
            break;

        case 'addNote':
            Gate::authorizeOrDie('ihbar/list');

            $id = (int) ($_POST['id'] ?? 0);
            $aciklama = trim($_POST['aciklama'] ?? '');
            if ($aciklama === '') {
                throw new Exception('Not boş olamaz.');
            }

            $IhbarModel->addNote($id, $aciklama, 'user', $currentUserId);
            ihbarResponse(true, 'Not eklendi.');
            break;

        case 'close':
            Gate::authorizeOrDie('ihbar/list');

            $id = (int) ($_POST['id'] ?? 0);
            $durum = $_POST['durum'] ?? '';
            $tutanakNo = trim($_POST['tutanak_no'] ?? '') ?: null;
            $sebep = trim($_POST['sebep'] ?? '') ?: null;

            $IhbarModel->closeSonuc($id, $durum, $tutanakNo, $sebep, 'user', $currentUserId);
            ihbarResponse(true, 'İhbar sonuçlandırıldı.');
            break;

        case 'cancelResult':
            Gate::authorizeOrDie('ihbar/list');

            $id = (int) ($_POST['id'] ?? 0);
            $IhbarModel->cancelResult($id, $currentUserId);
            ihbarResponse(true, 'İhbar sonucu iptal edildi ve kayıt yeniden işlem bekliyor.');
            break;

        default:
            ihbarResponse(false, 'Geçersiz işlem.');
    }
} catch (Exception $e) {
    ihbarResponse(false, $e->getMessage());
}
