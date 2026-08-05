<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\IhbarModel;
use App\Model\UserModel;
use App\Model\BildirimModel;
use App\Model\UserNotificationPreferenceModel;
use App\Model\SettingsModel;
use App\Model\PersonelHareketleriModel;
use App\Helper\Security;
use App\Service\Gate;
use App\Service\PushNotificationService;

const IHBAR_MAX_FOTO = IhbarModel::MAX_FOTO;

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
                'danger',
                UserNotificationPreferenceModel::TYPE_IHBAR_CREATED
            );

            $pushService = new PushNotificationService();
            $pushService->sendToUser((int) $kullanici->id, [
                'title' => '📣 Yeni İhbar',
                'body' => $ozetMetin,
                'url' => 'index.php?p=ihbar/list'
            ], true, UserNotificationPreferenceModel::TYPE_IHBAR_CREATED);
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

    $maxFiles = min(count($_FILES['fotograflar']['name']), IHBAR_MAX_FOTO);

    for ($i = 0; $i < $maxFiles; $i++) {
        if (($_FILES['fotograflar']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        try {
            $sonuc = IhbarModel::storeUploadedFoto([
                'name' => $_FILES['fotograflar']['name'][$i],
                'tmp_name' => $_FILES['fotograflar']['tmp_name'][$i],
                'error' => $_FILES['fotograflar']['error'][$i],
                'size' => $_FILES['fotograflar']['size'][$i],
            ], $ihbarId);

            $model->addFotograf($ihbarId, $sonuc['yol'], $sonuc['kucuk']);
        } catch (Throwable $e) {
            error_log('İhbar fotoğrafı yüklenemedi (ihbar ' . $ihbarId . '): ' . $e->getMessage());
        }
    }
}

function ihbarHandleVideoUpload(int $ihbarId, IhbarModel $model): void
{
    if (empty($_FILES['videolar']) || empty($_FILES['videolar']['name'][0])) {
        return;
    }

    $sureler = $_POST['video_sureleri'] ?? [];
    $kapaklar = $_POST['video_kapaklari'] ?? [];
    $maxFiles = min(count($_FILES['videolar']['name']), IhbarModel::MAX_VIDEO);

    for ($i = 0; $i < $maxFiles; $i++) {
        if (($_FILES['videolar']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        try {
            $sonuc = $model->storeUploadedVideo(
                [
                    'name' => $_FILES['videolar']['name'][$i],
                    'tmp_name' => $_FILES['videolar']['tmp_name'][$i],
                    'error' => $_FILES['videolar']['error'][$i],
                    'size' => $_FILES['videolar']['size'][$i],
                ],
                $ihbarId,
                isset($sureler[$i]) && is_numeric($sureler[$i]) ? (int) ceil((float) $sureler[$i]) : null,
                isset($kapaklar[$i]) ? (string) $kapaklar[$i] : null
            );

            $model->addVideo($ihbarId, $sonuc['yol'], $sonuc['kapak'], $sonuc['sure_saniye']);
        } catch (Throwable $e) {
            error_log('İhbar videosu yüklenemedi (ihbar ' . $ihbarId . '): ' . $e->getMessage());
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
                "SELECT f.dosya_yolu, f.kucuk_yol
                 FROM ihbar_fotograflari f
                 INNER JOIN ihbarlar i ON i.id = f.ihbar_id
                 WHERE f.id = ? AND i.firma_id = ? AND i.silinme_tarihi IS NULL"
            );
            $stmt->execute([$fotoId, (int) ($_SESSION['firma_id'] ?? 1)]);
            $satir = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $dosyaYolu = $satir['dosya_yolu'] ?? '';

            if ($dosyaYolu !== '' && ($_GET['boyut'] ?? '') === 'kucuk' && !empty($satir['kucuk_yol'])) {
                $kucukTam = realpath(dirname(__DIR__, 2) . '/' . ltrim($satir['kucuk_yol'], '/'));
                if ($kucukTam && is_file($kucukTam)) {
                    $dosyaYolu = $satir['kucuk_yol'];
                }
            }

            $uploadRoot = realpath(dirname(__DIR__, 2) . '/uploads/ihbar');
            $dosya = $dosyaYolu !== '' ? realpath(dirname(__DIR__, 2) . '/' . ltrim($dosyaYolu, '/')) : false;

            if (!$uploadRoot || !$dosya || strpos($dosya, $uploadRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($dosya)) {
                http_response_code(404);
                exit;
            }

            header_remove('Content-Type');
            App\Helper\MedyaServis::gonder($dosya, 86400);
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
            ihbarHandleVideoUpload($ihbarId, $IhbarModel);

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

        case 'reassignPreview':
            Gate::authorizeOrDie('ihbar/list');
            $personelTokenMap = [];
            $personelTokens = json_decode((string) ($_POST['personel_tokens'] ?? '[]'), true);
            if (is_array($personelTokens)) {
                foreach ($personelTokens as $token) {
                    $personelId = (int) Security::decrypt((string) $token);
                    if ($personelId > 0) $personelTokenMap[$personelId] = (string) $token;
                }
            }
            $rows = $IhbarModel->getReassignmentCandidates();
            foreach ($rows as $row) {
                $row->token = Security::encrypt((int) $row->id);
                $row->onerilen_personel_token = $row->onerilen_personel_id
                    ? ($personelTokenMap[(int) $row->onerilen_personel_id] ?? '') : '';
                unset($row->onerilen_personel_id);
            }
            ihbarResponse(true, '', $rows);
            break;

        case 'requestFreshLocations':
            Gate::authorizeOrDie('ihbar/list');
            $hareketModel = new PersonelHareketleriModel();
            $hareketModel->requestKacakPersonelKonumlari((int) ($_SESSION['firma_id'] ?? 1));
            ihbarResponse(true, '', ['bekleyen' => $hareketModel->getBekleyenKacakKonumIstegiSayisi((int) ($_SESSION['firma_id'] ?? 1))]);
            break;

        case 'freshLocationStatus':
            Gate::authorizeOrDie('ihbar/list');
            $hareketModel = new PersonelHareketleriModel();
            ihbarResponse(true, '', ['bekleyen' => $hareketModel->getBekleyenKacakKonumIstegiSayisi((int) ($_SESSION['firma_id'] ?? 1))]);
            break;

        case 'bulkReassign':
            Gate::authorizeOrDie('ihbar/list');
            $payload = json_decode((string) ($_POST['assignments'] ?? ''), true);
            if (!is_array($payload) || empty($payload)) {
                throw new Exception('Yönlendirilecek en az bir ihbar seçmelisiniz.');
            }
            $assignments = [];
            foreach ($payload as $item) {
                $ihbarId = (int) Security::decrypt((string) ($item['ihbar_token'] ?? ''));
                $personelId = (int) Security::decrypt((string) ($item['personel_token'] ?? ''));
                if ($ihbarId <= 0 || $personelId <= 0) throw new Exception('Geçersiz yönlendirme bilgisi.');
                $assignments[$ihbarId] = $personelId;
            }
            $personelIds = $IhbarModel->bulkReassign($assignments, $currentUserId);
            try {
                $pushService = new PushNotificationService();
                foreach (array_unique($personelIds) as $pId) {
                    $pushService->sendToPersonel((int) $pId, [
                        'title' => '📣 İhbarlar Yeniden Yönlendirildi',
                        'body' => 'Bekleyen ihbarlar size yönlendirildi. Detaylar için uygulamayı açın.',
                        'url' => '?page=ihbar'
                    ]);
                }
            } catch (Exception $e) {
                error_log('Toplu ihbar yönlendirme push hatası: ' . $e->getMessage());
            }
            ihbarResponse(true, count($assignments) . ' ihbar yeniden yönlendirildi.');
            break;

        case 'saveSettings':
            Gate::authorizeOrDie('ihbar/list');
            if (!Gate::allows('is_takip_ayarlar') && !Gate::isSuperAdmin()) {
                throw new Exception('İhbar ayarlarını değiştirme yetkiniz bulunmamaktadır.');
            }
            $limit = (int) ($_POST['ihbar_personel_eszamanli_limit'] ?? 0);
            if ($limit < 1 || $limit > 100) throw new Exception('Personel ihbar limiti 1 ile 100 arasında olmalıdır.');
            $settings = new SettingsModel();
            $ok = $settings->upsertMultipleSettings([
                'ihbar_personel_eszamanli_limit' => (string) $limit,
                'ihbar_ayni_bolge_onceligi' => ($_POST['ihbar_ayni_bolge_onceligi'] ?? '0') === '1' ? '1' : '0',
            ], (int) ($_SESSION['firma_id'] ?? 1));
            if (!$ok) throw new Exception('Ayarlar kaydedilemedi.');
            ihbarResponse(true, 'İhbar yönlendirme ayarları kaydedildi.');
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
