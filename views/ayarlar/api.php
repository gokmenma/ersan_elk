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
        $firma_id = !empty($_POST['firma_id']) ? (int) $_POST['firma_id'] : null;
        $settings = $Settings->getAllSettingsAsKeyValue($firma_id);
        $response = [
            'status' => 'success',
            'message' => 'Ayarlar başarıyla alındı.',
            'data' => $settings
        ];
        break;

    case 'save':

        $settingsToUpdate = $_POST ?? [];
        $firma_id = !empty($_POST['firma_id']) ? (int) $_POST['firma_id'] : null;

        // Checkbox'lar işaretlenmediğinde POST içinde gönderilmez. 
        // Burada listelenen checkbox'lar eğer POST içinde yoksa '0' olarak kaydedilir.
        $checkboxKeys = ['email_gonderim_aktif', 'sms_gonderim_aktif', 'online_sorgulama_aktif'];
        foreach ($checkboxKeys as $cbKey) {
            if (!isset($settingsToUpdate[$cbKey])) {
                $settingsToUpdate[$cbKey] = '0';
            }
        }

        // Ayar olmayan alanları temizle
        $excludeKeys = ['action', 'firma_id', 'user_id', 'config_id'];
        foreach ($excludeKeys as $key) {
            unset($settingsToUpdate[$key]);
        }

        try {
            if ($Settings->upsertMultipleSettings($settingsToUpdate, $firma_id, null)) {
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

            $deleteOldLogoIfExists = function (?string $storedPath) use ($publicBase, $uploadDir): void {
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