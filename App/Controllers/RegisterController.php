<?php
// App/Controllers/RegisterController.php
namespace App\Controllers;

use App\Helper\Security;
use App\Services\FlashMessageService;
use App\Services\RegisterValidator;
use App\Services\MailGonderService;
use Model\UserModel;
use Database\Db;

class RegisterController
{
    public static function handleRegister(array $post)
    {
        $User = new UserModel();
        $db = Db::getInstance();
        $validator = new RegisterValidator($post);
        $email = $post['email'];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!$validator->passes()) {
            $_SESSION['registration_attempts'] = ($_SESSION['registration_attempts'] ?? 0) + 1;
            FlashMessageService::add('error', 'Hata!', $validator->getFirstError());
            return false;
        }
        
        if ($User->isEmailExists(trim($post['email']))) {
            $_SESSION['registration_attempts'] = ($_SESSION['registration_attempts'] ?? 0) + 1;
            FlashMessageService::add('error', 'Hata!', 'Bu email adresi ile daha önce kayıt olunmuş.');
            return false;
        }

        $attempts = $_SESSION['registration_attempts'] ?? 0;
        if ($attempts >= 3) {
            $recaptchaSecret = trim((string) ($_ENV['RECAPTCHA_SECRET'] ?? ''));
            $recaptchaResponse = trim((string) ($post['g-recaptcha-response'] ?? ''));

            if ($recaptchaSecret === '') {
                error_log('RECAPTCHA_SECRET .env dosyasında tanımlı değil; kayıt doğrulaması reddedildi.');
                FlashMessageService::add('error', 'Hata!', 'Doğrulama servisi şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyiniz.');
                return false;
            }

            if ($recaptchaResponse === '') {
                FlashMessageService::add('error', 'Hata!', 'Çok fazla deneme yaptınız. Lütfen reCAPTCHA doğrulamasını yapınız.');
                return false;
            }

            if (!self::verifyRecaptcha($recaptchaSecret, $recaptchaResponse)) {
                $_SESSION['registration_attempts']++;
                FlashMessageService::add('error', 'Hata!', 'reCAPTCHA doğrulaması başarısız oldu.');
                return false;
            }

            $_SESSION['registration_attempts'] = 0;
        }
        try {
            $db->beginTransaction();
            $data = [
                'id'            => 0,
                'full_name'     => Security::escape($post['full_name']),
                'email'         => Security::escape($post['email']),
                'status'        => 0,
                'roles'         => 1,
                'is_main_user'  => 1,
                'password'      => password_hash($post['password'], PASSWORD_DEFAULT),
            ];
            $lastInsertUserId = $User->saveWithAttr($data);
            $token = (Security::encrypt(time() + 3600));

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $baseUrl = $protocol . '://' . $host;

            $activate_link = "$baseUrl/register-activate.php?email=" . ($post['email']) . "&token=" . $token;
            $data = [
                'id' => Security::decrypt($lastInsertUserId),
                'activate_token' => ($token),
            ];
            $User->setActivateToken($data);
            $db->commit();
            FlashMessageService::add(
                'success',
                'İşlem Başarılı',
                'Kayıt başarıyla tamamlandı. Aktivasyon e-postası gönderildi.',
                'onay2.png'
            );
            MailGonderService::gonder(
                [$post['email']],
                'Hesap Aktivasyon',
                "Merhaba " . $post['full_name'] . ",<br><br>Kayıt işleminiz başarıyla tamamlandı. Hesabınızı aktifleştirmek için lütfen aşağıdaki linke tıklayınız:<br><a href='" . $activate_link . "'>Hesabımı Aktifleştir</a><br><br>Bu link 1 saat geçerlidir.<br><br>Teşekkürler,<br>Yönetim Ekibi"
            );
            $_SESSION['registration_attempts'] = 0;
            header('Location: /register-success.php');
            exit;
        } catch (\PDOException $e) {
            $db->rollBack();
            if ($e->errorInfo[1] == 1062) {
                FlashMessageService::add(
                    'error',
                    'Hata!',
                    'Bu email adresi ile daha önce kayıt olunmuş.'
                );
            } else {
                error_log('Kayıt işlemi başarısız: ' . $e->getMessage());
                FlashMessageService::add(
                    'error',
                    'Hata!',
                    'Kayıt işlemi sırasında bir hata oluştu. Lütfen tekrar deneyiniz.'
                );
            }
            return false;
        }
    }

    private static function verifyRecaptcha(string $secret, string $token): bool
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ]),
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);

        $yanit = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);

        if ($yanit === false) {
            error_log('reCAPTCHA doğrulama servisine ulaşılamadı.');
            return false;
        }

        $sonuc = json_decode($yanit, true);

        if (!is_array($sonuc) || empty($sonuc['success'])) {
            $hatalar = is_array($sonuc) && !empty($sonuc['error-codes'])
                ? implode(', ', (array) $sonuc['error-codes'])
                : 'bilinmeyen';
            error_log('reCAPTCHA doğrulaması reddedildi: ' . $hatalar);
            return false;
        }

        return true;
    }
}
