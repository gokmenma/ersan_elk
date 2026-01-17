<?php 

require_once dirname(__DIR__, 2) . '/Autoloader.php';

header('Content-Type: application/json; charset=utf-8');


use App\Model\SettingsModel;
use App\Helper\Security;

$Settings = new SettingsModel();
$response = [
    'status' => 'error',
    'message' => 'Bilinmeyen bir hata oluştu.',
    'data' => null,
];

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        $settings = $Settings->getAllSettingsAsKeyValue();
        $response = [
            'status' => 'success',
            'message' => 'Ayarlar başarıyla alındı.',
            'data' => $settings
        ];
        break;

    case 'save':
        $settingsToUpdate = $_POST['settings'] ?? [];
        try {
            if ($Settings->upsertMultipleSettings($settingsToUpdate)) {

                /** Sağ logo ve sol logoyu upload klasörüne yükle */
                $uploadDir = dirname(__DIR__, 2) . '/uploads/logos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Web'den erişilecek göreli path'ler
                $publicBase = 'uploads/logos/';

                $allowExt = ['png', 'jpg', 'jpeg', 'webp'];
                $makeUniqueName = function(string $originalName, string $prefix) use ($allowExt): array {
                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowExt, true)) {
                        // Güvenli tarafta kal: bilinmeyen uzantıları png yap
                        $ext = 'png';
                    }
                    $uniq = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6));
                    return [$uniq . '.' . $ext, $ext];
                };

                $deleteOldLogoIfExists = function(?string $storedPath) use ($publicBase, $uploadDir): void {
                    if (!$storedPath) {
                        return;
                    }

                    // Sadece uploads/logos/ altındaki dosyaları sil (güvenlik)
                    $storedPath = str_replace('\\', '/', $storedPath);
                    if (strpos($storedPath, $publicBase) !== 0) {
                        return;
                    }

                    $fileName = basename($storedPath);
                    if ($fileName === '' || $fileName === '.' || $fileName === '..') {
                        return;
                    }

                    $fullPath = $uploadDir . $fileName;
                    if (is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                };

                // Kaldır checkbox'ları: hem DB alanını temizle hem de dosyayı sil
                $removeSol = isset($settingsToUpdate['sol_logo_kaldir']) && (string)$settingsToUpdate['sol_logo_kaldir'] === '1';
                $removeSag = isset($settingsToUpdate['sag_logo_kaldir']) && (string)$settingsToUpdate['sag_logo_kaldir'] === '1';

                if ($removeSol) {
                    $deleteOldLogoIfExists($Settings->getSettings('sol_logo_yolu'));
                    $Settings->upsertSetting('sol_logo_yolu', '');
                }

                if ($removeSag) {
                    $deleteOldLogoIfExists($Settings->getSettings('sag_logo_yolu'));
                    $Settings->upsertSetting('sag_logo_yolu', '');
                }

                if (isset($_FILES['sol_logo_yeni']) && $_FILES['sol_logo_yeni']['error'] === UPLOAD_ERR_OK) {
                    // Önce eski dosyayı sil
                    $deleteOldLogoIfExists($Settings->getSettings('sol_logo_yolu'));

                    [$fileName] = $makeUniqueName($_FILES['sol_logo_yeni']['name'] ?? 'sol_logo.png', 'sol_logo');
                    $solLogoPath = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['sol_logo_yeni']['tmp_name'], $solLogoPath)) {
                        $Settings->upsertSetting('sol_logo_yolu', $publicBase . $fileName);
                    }
                }

                // Formda input adı: sag_logo_input
                if (isset($_FILES['sag_logo_input']) && $_FILES['sag_logo_input']['error'] === UPLOAD_ERR_OK) {
                    // Önce eski dosyayı sil
                    $deleteOldLogoIfExists($Settings->getSettings('sag_logo_yolu'));

                    [$fileName] = $makeUniqueName($_FILES['sag_logo_input']['name'] ?? 'sag_logo.png', 'sag_logo');
                    $sagLogoPath = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['sag_logo_input']['tmp_name'], $sagLogoPath)) {
                        $Settings->upsertSetting('sag_logo_yolu', $publicBase . $fileName);
                    }
                }

                $response = [
                    'status' => 'success',
                    'message' => 'Ayarlar başarıyla güncellendi.',
                    'data' => $settingsToUpdate
                ];
            } else {
                $response['message'] = 'Ayarlar kaydedilemedi (upsertMultipleSettings false döndü).';
                $response['data'] = [
                    'received_keys' => is_array($settingsToUpdate) ? array_keys($settingsToUpdate) : null,
                ];
            }
        } catch (\Throwable $e) {
            $response['message'] = 'Ayarlar güncellenirken hata: ' . $e->getMessage();
            $response['data'] = [
                'type' => get_class($e),
                'received_keys' => is_array($settingsToUpdate) ? array_keys($settingsToUpdate) : null,
            ];
        }
        break;

    case 'remove_logo':
        try {
            $side = $_POST['side'] ?? '';

            $uploadDir = dirname(__DIR__, 2) . '/uploads/logos/';
            $publicBase = 'uploads/logos/';

            $deleteOldLogoIfExists = function(?string $storedPath) use ($publicBase, $uploadDir): void {
                if (!$storedPath) {
                    return;
                }
                $storedPath = str_replace('\\', '/', $storedPath);
                if (strpos($storedPath, $publicBase) !== 0) {
                    return;
                }
                $fileName = basename($storedPath);
                if ($fileName === '' || $fileName === '.' || $fileName === '..') {
                    return;
                }
                $fullPath = $uploadDir . $fileName;
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            };

            if ($side === 'sol') {
                $deleteOldLogoIfExists($Settings->getSettings('sol_logo_yolu'));
                $Settings->upsertSetting('sol_logo_yolu', '');
            } elseif ($side === 'sag') {
                $deleteOldLogoIfExists($Settings->getSettings('sag_logo_yolu'));
                $Settings->upsertSetting('sag_logo_yolu', '');
            } else {
                $response['message'] = 'Geçersiz logo tarafı.';
                break;
            }

            $response = [
                'status' => 'success',
                'message' => 'Logo kaldırıldı.',
                'data' => ['side' => $side]
            ];
        } catch (\Throwable $e) {
            $response['message'] = 'Logo kaldırılırken hata: ' . $e->getMessage();
        }
        break;

    default:
        $response['message'] = 'Geçersiz işlem.';
        break;
}

echo json_encode($response);