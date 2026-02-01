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
        $checkboxKeys = ['email_gonderim_aktif', 'sms_gonderim_aktif', 'online_sorgulama_aktif'];
        foreach ($checkboxKeys as $cbKey) {
            if (!isset($settingsToUpdate[$cbKey])) {
                $settingsToUpdate[$cbKey] = '0';
            }
        }

        // Boş şifre alanlarının mevcut şifreyi silmesini engelle
        $passwordKeys = ['smtp_sifre_yeni', 'sms_api_sifre_yeni', 'online_sorgulama_api_sifre_yeni'];
        foreach ($passwordKeys as $passKey) {
            if (isset($settingsToUpdate[$passKey]) && empty(trim($settingsToUpdate[$passKey]))) {
                unset($settingsToUpdate[$passKey]);
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

    case 'test_email_ayarlari':
        try {
            $to = $_POST['test_email_adresi'] ?? '';
            if (empty($to)) {
                throw new \Exception("Test e-posta adresi belirtilmedi.");
            }

            // Formdan gelen anlık ayarları topla
            $currentSettings = [
                'smtp_host' => $_POST['smtp_host'] ?? null,
                'smtp_port' => $_POST['smtp_port'] ?? null,
                'smtp_kullanici' => $_POST['smtp_kullanici'] ?? null,
                'smtp_sifre_yeni' => $_POST['smtp_sifre_yeni'] ?? null,
                'smtp_guvenlik' => $_POST['smtp_guvenlik'] ?? null,
                'gonderen_eposta' => $_POST['gonderen_eposta'] ?? null,
                'gonderen_adi' => $_POST['gonderen_adi'] ?? null,
            ];

            // Null olanları temizle (eğer formda yoksa DB'dekini kullansın)
            $currentSettings = array_filter($currentSettings, fn($v) => !is_null($v));

            $subject = "Sistem E-posta Testi (Anlık Ayarlar)";
            $message = "Bu bir test e-postasıdır. Eğer bu mesajı alıyorsanız e-posta bilgileriniz DOĞRU demektir.<br><br>Tarih: " . date('d.m.Y H:i:s');

            if (\App\Service\MailGonderService::gonder([$to], $subject, $message, [], [], [], $currentSettings)) {
                $response = [
                    'status' => 'success',
                    'message' => 'Test e-postası başarıyla gönderildi. Lütfen gelen kutunuzu kontrol edin.'
                ];
            } else {
                throw new \Exception("E-posta gönderilemedi. Lütfen ayarlarınızı (Host, Port, Şifre vb.) kontrol edin.");
            }
        } catch (\Throwable $e) {
            $response['message'] = $e->getMessage();
        }
        break;

    case 'test_sms_ayarlari':
        try {
            $recipient = $_POST['sms_test_numarasi'] ?? '';
            $firma_id = !empty($_POST['firma_id']) ? (int) $_POST['firma_id'] : null;

            if (empty($recipient)) {
                throw new \Exception("Test numarası belirtilmedi.");
            }

            $allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

            // Eğer formdan yeni bilgiler geldiyse onları kullan, yoksa kayıtlı olanları
            $username = $_POST['sms_api_kullanici'] ?? $allSettings['sms_api_kullanici'] ?? '';
            $password = $_POST['sms_api_sifre_yeni'] ?? $allSettings['sms_api_sifre'] ?? '';
            $msgheader = $_POST['sms_baslik'] ?? $allSettings['sms_baslik'] ?? '';

            if (empty($username) || empty($password)) {
                throw new \Exception("SMS API kullanıcı adı veya şifresi eksik.");
            }

            $messageText = "Ersan Elektrik SMS Test Mesajıdır. Tarih: " . date('d.m.Y H:i');

            $data = [
                "msgheader" => $msgheader,
                "messages" => [["msg" => $messageText, "no" => $recipient]],
                "encoding" => "TR"
            ];

            $ch = curl_init("https://api.netgsm.com.tr/sms/rest/v2/send");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($username . ':' . $password)
            ]);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new \Exception('Bağlantı Hatası: ' . curl_error($ch));
            }
            curl_close($ch);

            $netgsmResult = json_decode($result, true);
            if (isset($netgsmResult['code']) && $netgsmResult['code'] == '00') {
                $response = [
                    'status' => 'success',
                    'message' => 'Test SMS\'i başarıyla gönderildi.'
                ];
            } else {
                $errMsg = $netgsmResult['description'] ?? "Bilinmeyen API hatası.";
                throw new \Exception("Netgsm Hatası: " . $errMsg);
            }

        } catch (\Throwable $e) {
            $response['message'] = $e->getMessage();
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