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

        // Session-based reCAPTCHA throttling
        $attempts = $_SESSION['registration_attempts'] ?? 0;
        if ($attempts >= 3) {
            $recaptchaSecret = $_ENV['RECAPTCHA_SECRET'] ?? '6LdHvNlsAAAAAI_8_P5v-NuLY3cd2rb4OMnOUIHI';
            $recaptchaResponse = $post['g-recaptcha-response'] ?? '';
            
            if (empty($recaptchaResponse)) {
                FlashMessageService::add('error', 'Hata!', 'Çok fazla deneme yaptınız. Lütfen reCAPTCHA doğrulamasını yapınız.');
                return false;
            }

            $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$recaptchaSecret&response=$recaptchaResponse");
            $responseKeys = json_decode($response, true);
            
            if (intval($responseKeys["success"] ?? 0) !== 1) {
                $_SESSION['registration_attempts']++;
                FlashMessageService::add('error', 'Hata!', 'reCAPTCHA doğrulaması başarısız oldu.');
                return false;
            }
            
            // On successful recaptcha, we can reset or just let it pass
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
                FlashMessageService::add(
                    'error',
                    'Hata!',
                    'Kayıt işlemi sırasında bir hata oluştu. Lütfen tekrar deneyiniz. Hata Mesajı :' . $e->getMessage()
                );
            }
            return false;
        }
    }
}
