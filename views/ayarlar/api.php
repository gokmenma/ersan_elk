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
        $checkboxKeys = ['email_gonderim_aktif', 'sms_gonderim_aktif', 'online_sorgulama_aktif', 'canli_destek_aktif'];
        foreach ($checkboxKeys as $cbKey) {
            if (!isset($settingsToUpdate[$cbKey])) {
                $settingsToUpdate[$cbKey] = '0';
            }
        }

        // Boş şifre alanlarının mevcut şifreyi silmesini engelle
        $passwordKeys = [
            'smtp_sifre_yeni' => 'smtp_sifre',
            'sms_api_sifre_yeni' => 'sms_api_sifre',
            'online_sorgulama_api_sifre_yeni' => 'online_sorgulama_api_sifre',
            'api_endeks_sifre_yeni' => 'api_endeks_sifre',
            'api_puantaj_sifre_yeni' => 'api_puantaj_sifre',
            'api_sayac_degisim_sifre_yeni' => 'api_sayac_degisim_sifre',
            'openai_api_key_yeni' => 'openai_api_key'
        ];
        foreach ($passwordKeys as $passKey => $dbKey) {
            if (isset($settingsToUpdate[$passKey])) {
                if (!empty(trim($settingsToUpdate[$passKey]))) {
                    $settingsToUpdate[$dbKey] = $settingsToUpdate[$passKey];
                }
                unset($settingsToUpdate[$passKey]);
            }
        }

        // Ayar olmayan alanları temizle
        $excludeKeys = ['action', 'firma_id', 'user_id', 'config_id', 'online_sorgulama_endeks_saat_select', 'online_sorgulama_puantaj_saat_select', 'online_sorgulama_sayac_degisim_saat_select'];
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
            $firma_id = !empty($_POST['firma_id']) ? (int) $_POST['firma_id'] : null;

            $uploadDir = dirname(__DIR__, 2) . '/uploads/logos/';
            $publicBase = 'uploads/logos/';

            $allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

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
                $deleteOldLogoIfExists($allSettings['sol_logo_yolu'] ?? null);
                $Settings->upsertMultipleSettings(['sol_logo_yolu' => ''], $firma_id, null);
            } elseif ($side === 'sag') {
                $deleteOldLogoIfExists($allSettings['sag_logo_yolu'] ?? null);
                $Settings->upsertMultipleSettings(['sag_logo_yolu' => ''], $firma_id, null);
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

    case 'save_sgk_ayarlari':
        try {
            $firma_id = !empty($_POST['firma_id']) ? (int) $_POST['firma_id'] : null;

            $settingsToUpdate = [];

            // SGK Kullanıcı Adı
            if (isset($_POST['sgk_kullanici_adi'])) {
                $settingsToUpdate['sgk_kullanici_adi'] = trim($_POST['sgk_kullanici_adi']);
            }

            // SGK İşyeri Kodu
            if (isset($_POST['sgk_isyeri_kodu'])) {
                $settingsToUpdate['sgk_isyeri_kodu'] = trim($_POST['sgk_isyeri_kodu']);
            }

            // SGK İşyeri Şifresi - şifrelenmiş olarak kaydedilecek
            if (!empty($_POST['sgk_isyeri_sifresi_yeni'])) {
                $settingsToUpdate['sgk_isyeri_sifresi'] = Security::encrypt(trim($_POST['sgk_isyeri_sifresi_yeni']));
            }

            // Otomatik Rapor Onaylama
            $settingsToUpdate['sgk_otomatik_rapor_onaylama'] = isset($_POST['sgk_otomatik_rapor_onaylama']) ? '1' : '0';

            if ($Settings->upsertMultipleSettings($settingsToUpdate, $firma_id, null)) {
                $response = [
                    'status' => 'success',
                    'message' => 'SGK Vizite ayarları başarıyla kaydedildi.'
                ];
            } else {
                $response['message'] = 'Ayarlar kaydedilemedi.';
            }
        } catch (\Throwable $e) {
            $response['message'] = 'Hata: ' . $e->getMessage();
        }
        break;

    case 'test_sgk_baglantisi':
        try {
            require_once dirname(__DIR__, 2) . '/App/Service/SgkViziteService.php';

            $firma_id = !empty($_POST['firma_id']) ? (int) $_POST['firma_id'] : null;

            // Formdan gelen bilgileri kullan
            $kullaniciAdi = trim($_POST['sgk_kullanici_adi'] ?? '');
            $isyeriKodu = trim($_POST['sgk_isyeri_kodu'] ?? '');
            $isyeriSifresi = trim($_POST['sgk_isyeri_sifresi_yeni'] ?? '');

            // Eğer yeni şifre girilmemişse, kayıtlı şifreyi kullan
            if (empty($isyeriSifresi)) {
                $allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);
                $encryptedPassword = $allSettings['sgk_isyeri_sifresi'] ?? '';
                if (!empty($encryptedPassword)) {
                    $isyeriSifresi = Security::decrypt($encryptedPassword);
                }
            }

            if (empty($kullaniciAdi) || empty($isyeriKodu) || empty($isyeriSifresi)) {
                throw new \Exception("Lütfen tüm SGK bilgilerini doldurun.");
            }

            // SgkViziteService ile bağlantı testi
            $sgkService = new SgkViziteService($kullaniciAdi, $isyeriKodu, $isyeriSifresi);
            $result = $sgkService->bilgileriDogrula($kullaniciAdi, $isyeriKodu, $isyeriSifresi);

            if (isset($result->sonucKod) && $result->sonucKod == '0') {
                $basariMesaji = isset($result->sonucAciklama) && stripos((string) $result->sonucAciklama, 'aktif SGK oturumu') !== false
                    ? (string) $result->sonucAciklama
                    : 'SGK bağlantısı başarılı! Bilgileriniz doğrulandı.';

                $response = [
                    'status' => 'success',
                    'message' => $basariMesaji
                ];
            } else {
                $hataMesaji = isset($result->sonucAciklama) ? (string) $result->sonucAciklama : 'Bilinmeyen hata';
                throw new \Exception(SgkViziteService::hataMesajiniCevir($hataMesaji));
            }
        } catch (\Throwable $e) {
            $response['message'] = $e->getMessage();
        }
        break;

    case 'save_evrak_ayarlari':
        try {
            $firma_id = !empty($_POST['firma_id']) ? (int) $_POST['firma_id'] : null;

            $settingsToUpdate = [];

            $textKeys = [
                'evrak_antet_baslik_1',
                'evrak_antet_baslik_2',
                'evrak_antet_baslik_3',
                'evrak_antet_baslik_4',
                'evrak_alt_bilgi_1',
                'evrak_alt_bilgi_2',
                'evrak_alt_bilgi_3',
                'evrak_alt_bilgi_4',
            ];

            foreach ($textKeys as $key) {
                if (isset($_POST[$key])) {
                    $settingsToUpdate[$key] = trim($_POST[$key]);
                }
            }

            $settingsToUpdate['evrak_eimza_goster'] = isset($_POST['evrak_eimza_goster']) ? '1' : '0';

            $uploadDir = dirname(__DIR__, 2) . '/uploads/logos/';
            $publicBase = 'uploads/logos/';

            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            @chmod($uploadDir, 0777);

            $handleLogoUpload = function (array $fileInfo, string $sidePrefix) use ($uploadDir, $publicBase, $Settings, $firma_id): ?string {
                if (!isset($fileInfo['error']) || $fileInfo['error'] === UPLOAD_ERR_NO_FILE) {
                    return null;
                }

                if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
                    $uploadErrors = [
                        UPLOAD_ERR_INI_SIZE => 'Yüklenen logo php.ini upload_max_filesize sınırını aşıyor.',
                        UPLOAD_ERR_FORM_SIZE => 'Yüklenen logo form MAX_FILE_SIZE sınırını aşıyor.',
                        UPLOAD_ERR_PARTIAL => 'Logo dosyası sadece kısmen yüklendi.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Geçici yükleme klasörü bulunamadı.',
                        UPLOAD_ERR_CANT_WRITE => 'Logo dosyası diske yazılamadı.',
                        UPLOAD_ERR_EXTENSION => 'PHP eklentisi yüklemeyi durdurdu.'
                    ];
                    $errText = $uploadErrors[$fileInfo['error']] ?? ('Dosya yükleme hatası (Kod: ' . $fileInfo['error'] . ')');
                    throw new \Exception($errText);
                }

                if (empty($fileInfo['tmp_name']) || !is_uploaded_file($fileInfo['tmp_name'])) {
                    return null;
                }

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
                $ext = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions, true)) {
                    throw new \Exception("Geçersiz dosya formatı ({$ext}). Lütfen görsel dosyası yükleyin.");
                }

                $mime = mime_content_type($fileInfo['tmp_name']);
                if (strpos($mime, 'image/') !== 0 && $ext !== 'svg') {
                    throw new \Exception("Yüklenen dosya bir görsel değil.");
                }

                // Eski dosyayı sil
                $allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);
                $oldPath = $allSettings[$sidePrefix . '_logo_yolu'] ?? null;
                if ($oldPath && strpos(str_replace('\\', '/', $oldPath), $publicBase) === 0) {
                    $oldFileName = basename($oldPath);
                    if ($oldFileName && is_file($uploadDir . $oldFileName)) {
                        @unlink($uploadDir . $oldFileName);
                    }
                }

                $newFileName = $sidePrefix . '_logo_' . ($firma_id ?: 'global') . '_' . time() . '.' . $ext;
                $targetFile = $uploadDir . $newFileName;

                if (!move_uploaded_file($fileInfo['tmp_name'], $targetFile)) {
                    throw new \Exception("Logo dosyası kaydedilemedi. Lütfen klasör izinlerini kontrol edin.");
                }

                @chmod($targetFile, 0666);

                return $publicBase . $newFileName;
            };

            if (!empty($_FILES['sol_logo']['tmp_name'])) {
                $solPath = $handleLogoUpload($_FILES['sol_logo'], 'sol');
                if ($solPath) {
                    $settingsToUpdate['sol_logo_yolu'] = $solPath;
                }
            }

            if (!empty($_FILES['sag_logo']['tmp_name'])) {
                $sagPath = $handleLogoUpload($_FILES['sag_logo'], 'sag');
                if ($sagPath) {
                    $settingsToUpdate['sag_logo_yolu'] = $sagPath;
                }
            }

            if ($Settings->upsertMultipleSettings($settingsToUpdate, $firma_id, null)) {
                $userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
                if ($userId > 0 && class_exists('\App\Model\SystemLogModel')) {
                    (new \App\Model\SystemLogModel())->logAction(
                        $userId,
                        'Evrak Ayarları Güncelleme',
                        'Evrak antet ve alt bilgi ayarları güncellendi.',
                        \App\Model\SystemLogModel::LEVEL_INFO
                    );
                }

                $response = [
                    'status' => 'success',
                    'message' => 'Evrak ayarları başarıyla kaydedildi.'
                ];
            } else {
                $response['message'] = 'Ayarlar kaydedilemedi.';
            }
        } catch (\Throwable $e) {
            $response['message'] = 'Hata: ' . $e->getMessage();
        }
        break;

    case 'save_avans_ayarlari':
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (!\App\Service\Gate::allows('avans_ayarlari_sekmesi')) {
                $response['message'] = 'Bu işlem için yetkiniz bulunmuyor.';
                break;
            }

            $firma_id = !empty($_POST['firma_id']) ? (int) $_POST['firma_id'] : null;
            $serbest = isset($_POST['avans_talep_serbest']) ? '1' : '0';

            if ($Settings->upsertMultipleSettings(['avans_talep_serbest' => $serbest], $firma_id, null)) {
                $systemLog = new \App\Model\SystemLogModel();
                $systemLog->logAction(
                    $_SESSION['user_id'] ?? 0,
                    'Avans Ayarları',
                    'Avans talep serbestliği ayarı ' . ($serbest === '1' ? 'açıldı' : 'kapatıldı') . '.',
                    \App\Model\SystemLogModel::LEVEL_IMPORTANT
                );

                $response = [
                    'status' => 'success',
                    'message' => 'Avans ayarları başarıyla kaydedildi.'
                ];
            } else {
                $response['message'] = 'Ayarlar kaydedilemedi.';
            }
        } catch (\Throwable $e) {
            error_log('Avans ayarları kaydetme hatası: ' . $e->getMessage());
            $response['message'] = 'Ayarlar kaydedilirken bir hata oluştu.';
        }
        break;

    default:
        $response['message'] = 'Geçersiz işlem.';
        break;
}

echo json_encode($response);