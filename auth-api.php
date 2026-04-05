<?php
use App\Model\UserModel;
use App\Service\MailGonderService;

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/Autoloader.php';

    $action = $_POST['action'] ?? '';

    if ($action === 'forgot-password') {
        $identifier = trim($_POST['identifier'] ?? '');

        if (empty($identifier)) {
            echo json_encode(['status' => 'error', 'message' => 'Lütfen kullanıcı adı, telefon veya e-posta giriniz.']);
            exit;
        }

        $User = new UserModel();
        $user = $User->checkUser($identifier);

        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'Belirttiğiniz bilgilere uygun kullanıcı bulunamadı.']);
            exit;
        }

        if (empty($user->email_adresi)) {
            echo json_encode(['status' => 'error', 'message' => 'Bu kullanıcıya tanımlı bir e-posta adresi bulunamadı. Lütfen yönetici ile iletişime geçiniz.']);
            exit;
        }

        $token = bin2hex(random_bytes(32));
        if ($User->setResetToken($user->id, $token)) {
            
            $appBase = trim($_ENV['APP_BASE'] ?? $_SERVER['HTTP_HOST'], " \t\n\r\0\x0B\"'");
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            
            // APP_BASE zaten http/https içeriyor olabilir
            $baseUrl = (strpos($appBase, 'http') === false) ? "$protocol://$appBase" : $appBase;
            $baseUrl = rtrim($baseUrl, '/');
            
            $resetLink = "$baseUrl/reset-password.php?token=$token";
            $logoUrl = "$baseUrl/assets/images/logo.png";

            $konu = "Şifre Sıfırlama Talebi | Ersan Elektrik";
            $icerik = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px;'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <img src='$logoUrl' alt='Ersan Elektrik' style='max-height: 50px;'>
                    </div>
                    <h2 style='color: #2c3e50; text-align: center;'>Şifre Sıfırlama</h2>
                    <p>Merhaba <strong>{$user->adi_soyadi}</strong>,</p>
                    <p>Hesabınız için şifre sıfırlama talebinde bulunuldu. Şifrenizi sıfırlamak için aşağıdaki butona tıklayabilir veya bağlantıyı tarayıcınıza yapıştırabilirsiniz.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$resetLink' style='background-color: #5156be; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Şifremi Sıfırla</a>
                    </div>
                    <p style='color: #7f8c8d; font-size: 14px;'>Bu bağlantı 1 saat süreyle geçerlidir. Eğer bu talebi siz yapmadıysanız, bu e-postayı dikkate almayınız.</p>
                    <hr style='border: 0; border-top: 1px solid #e0e0e0; margin: 20px 0;'>
                    <p style='color: #bdc3c7; font-size: 12px; text-align: center;'>Ersan Elektrik - Personel Yönetim Sistemi</p>
                </div>
            ";

            try {
                $firmaId = (int)$user->owner_id;
                if ($firmaId === 0) $firmaId = 1; // Superadminler için varsayılan firma 1 (Ersan Elektrik) ayarlarını kullan

                MailGonderService::gonder([$user->email_adresi], $konu, $icerik, [], [], [], null, $firmaId);
                echo json_encode(['status' => 'success', 'message' => 'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi.']);
            } catch (\Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'E-posta gönderimi sırasında bir hata oluştu: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'İşlem sırasında bir veritabanı hatası oluştu.']);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Geçersiz işlem.']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Sistem hatası: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
    ]);
}
