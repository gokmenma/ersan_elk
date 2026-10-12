<?php

declare(strict_types=1);

use App\Helper\Security;
use App\Model\MobilePersonelModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function personnelResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function personnelInitials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts === []) {
        return '?';
    }
    $selected = count($parts) > 1 ? [$parts[0], $parts[count($parts) - 1]] : [$parts[0]];
    return implode('', array_map(static fn(string $part): string => mb_strtoupper(mb_substr($part, 0, 1)), $selected));
}

function personnelPublicRow(array $row): array
{
    $active = empty($row['isten_cikis_tarihi']) || $row['isten_cikis_tarihi'] === '0000-00-00';
    $name = trim((string) ($row['adi_soyadi'] ?? '')) ?: 'İsimsiz Personel';
    $encryptedId = Security::encrypt((string) $row['id']);
    $hasPhoto = trim((string) ($row['personel_resim_yolu'] ?? $row['resim_yolu'] ?? '')) !== '';
    return [
        'id' => $encryptedId,
        'avatar' => $hasPhoto ? '/api/personnel-photo.php?id=' . rawurlencode($encryptedId) : null,
        'name' => $name,
        'initials' => personnelInitials($name),
        'phone' => trim((string) ($row['cep_telefonu'] ?? '')),
        'email' => trim((string) ($row['email_adresi'] ?? '')),
        'job' => trim((string) ($row['gorev'] ?? '')),
        'department' => trim((string) ($row['departman'] ?? '')),
        'team' => trim((string) ($row['team'] ?? '')),
        'region' => trim((string) ($row['team_region'] ?? $row['ekip_bolge'] ?? '')),
        'start_date' => !empty($row['ise_giris_tarihi']) && $row['ise_giris_tarihi'] !== '0000-00-00' ? $row['ise_giris_tarihi'] : null,
        'end_date' => !$active ? $row['isten_cikis_tarihi'] : null,
        'active' => $active,
    ];
}

if (empty($_SESSION['user_id']) || empty($_SESSION['user']) || empty($_SESSION['firma_id'])) {
    personnelResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
}
if (!Gate::allows('personel_listesi')) {
    personnelResponse(['success' => false, 'message' => 'Personel listesini görüntüleme yetkiniz bulunmuyor.'], 403);
}

$firmaId = (int) $_SESSION['firma_id'];
$model = new MobilePersonelModel();
$action = (string) ($_GET['action'] ?? 'list');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $result = $model->mobileDirectory([
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? 'active',
            'department' => $_GET['department'] ?? '',
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 24,
        ], $firmaId);
        $result['items'] = array_map('personnelPublicRow', $result['items']);
        personnelResponse(['success' => true, 'can_edit' => Gate::allows('personel_duzenle'), ...$result]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'detail') {
        $personelId = (int) Security::decrypt((string) ($_GET['id'] ?? ''));
        if ($personelId <= 0) {
            personnelResponse(['success' => false, 'message' => 'Geçersiz personel kaydı.'], 422);
        }
        $row = $model->mobileDirectoryDetail($personelId, $firmaId);
        if (!$row) {
            personnelResponse(['success' => false, 'message' => 'Personel bulunamadı veya erişim yetkiniz yok.'], 404);
        }
        personnelResponse(['success' => true, 'can_edit' => Gate::allows('personel_duzenle'), 'personnel' => personnelPublicRow($row)]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
        if (!Gate::allows('personel_duzenle')) {
            personnelResponse(['success' => false, 'message' => 'Personel düzenleme yetkiniz bulunmuyor.'], 403);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $sentToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $knownToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sentToken === '' || $knownToken === '' || !hash_equals($knownToken, $sentToken)) {
            personnelResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
        }
        $personelId = empty($data['id']) ? 0 : (int) Security::decrypt((string) $data['id']);
        $savedId = $model->saveMobileProfile($data, $firmaId, $personelId);
        try {
            (new \App\Model\SystemLogModel())->logAction(
                (int) $_SESSION['user_id'],
                $personelId > 0 ? 'Personel Güncelleme (Mobil)' : 'Personel Ekleme (Mobil)',
                sprintf('%s personel kaydı mobil uygulamadan %s.', (string) ($data['name'] ?? ''), $personelId > 0 ? 'güncellendi' : 'oluşturuldu'),
                \App\Model\SystemLogModel::LEVEL_IMPORTANT
            );
        } catch (Throwable $ignored) {
        }
        $row = $model->mobileDirectoryDetail($savedId, $firmaId);
        personnelResponse(['success' => true, 'message' => 'Personel bilgileri kaydedildi.', 'personnel' => personnelPublicRow($row ?: [])]);
    }

    personnelResponse(['success' => false, 'message' => 'Geçersiz işlem.'], $_SERVER['REQUEST_METHOD'] === 'GET' ? 404 : 405);
} catch (InvalidArgumentException $error) {
    personnelResponse(['success' => false, 'message' => $error->getMessage()], 422);
} catch (RuntimeException $error) {
    personnelResponse(['success' => false, 'message' => $error->getMessage()], 403);
} catch (Throwable $error) {
    error_log('Mobile personnel API: ' . $error->getMessage());
    personnelResponse(['success' => false, 'message' => 'Personel bilgileri alınamadı.'], 500);
}
