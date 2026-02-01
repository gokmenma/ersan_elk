<?php

namespace App\Service;


use App\Model\SettingsModel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



class MailGonderService
{
    public static function gonder(
        array $kime,
        string $konu,
        string $icerik,
        array $ekler = [],
        array $cc = [],
        array $bcc = []
    ): bool {
        $mail = new PHPMailer(true);


        try {


            /**Gönderici mail ayarlarını al */
            $Settings = new SettingsModel();
            $firma_id = $_SESSION['firma_id'] ?? null;

            // CLI ortamında (test dosyası gibi) session olmayabilir, varsayılan 1 alalım
            if ($firma_id === null && php_sapi_name() === 'cli') {
                $firma_id = 1;
            }

            $allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

            // Sunucu Ayarları
            $mail->isSMTP();

            $mail->Host = $allSettings['smtp_host'] ?? $_ENV['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $allSettings['smtp_kullanici'] ?? $_ENV['SMTP_USER'];

            $mail->Password = $allSettings['smtp_sifre_yeni'] ?? $_ENV['SMTP_PASSWORD'];

            $secureType = $allSettings['smtp_guvenlik'] ?? 'tls';
            $port = $allSettings['smtp_port'] ?? $_ENV['SMTP_PORT'] ?? 587;

            if ($port == 465 || $secureType == 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secureType == 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                // Güvenlik yoksa
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            $mail->Port = $port;
            $mail->Timeout = 10; // 10 saniye zaman aşımı
            $mail->SMTPDebug = 0; // Debug kapalı




            // SSL Doğrulama Ayarları (Önemli!)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            // KARAKTER SETİ AYARI (ÇOK ÖNEMLİ)
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64'; // İçeriği base64 ile kodlamak uyumluluğu artırır

            // Gönderen ve Alıcı Bilgileri
            $fromEmail = $allSettings['gonderen_eposta'] ?? 'noreply@softran.online';
            $fromName = $allSettings['gonderen_adi'] ?? 'Ersan Elektrik | Personel Yönetim';
            $mail->setFrom($fromEmail, $fromName); // Gönderen e-posta ve isim

            // Alıcıları ekle
            if (is_array($kime)) {
                $first = true;
                foreach ($kime as $email) {
                    if ($first) {
                        $mail->addAddress(trim($email));
                        $first = false;
                    } else {
                        $mail->addBCC(trim($email));
                    }
                }
            } else {
                $mail->addAddress(trim($kime));
            }


            // CC ekleme
            if (!empty($cc)) {
                if (!is_array($cc)) {
                    $cc = [$cc];
                }
                foreach ($cc as $ccEmail) {
                    $mail->addCC(trim($ccEmail));
                }
            }

            // BCC ekleme
            if (!empty($bcc)) {
                if (!is_array($bcc)) {
                    $bcc = [$bcc];
                }
                foreach ($bcc as $bccEmail) {
                    $mail->addBCC(trim($bccEmail));
                }
            }

            //$mail->addAddress($kime); // Alıcı e-posta adresi

            // İçerik
            $mail->isHTML(true);
            $mail->Subject = $konu;
            $mail->Body = $icerik;
            $mail->AltBody = strip_tags($icerik); // HTML desteklemeyen istemciler için

            //eğer ekler boş değilse foreach ile ekleri ekle
            if (!empty($ekler)) {
                foreach ($ekler as $ek) {

                    // Support both:
                    //  - string file path
                    //  - ['path' => '...', 'name' => '...']
                    if (is_array($ek) && !empty($ek['path'])) {
                        $mail->addAttachment($ek['path'], $ek['name'] ?? 'attachment');
                    } else {
                        $mail->addAttachment((string) $ek);
                    }
                }
            }

            if ($mail->send()) {

                return true;
            } else {
                error_log("E-posta gönderilemedi. Hata: {$mail->ErrorInfo}");
                return false;
            }
        } catch (Exception $e) {
            error_log("Mail gönderme hatası: " . $mail->ErrorInfo);
            error_log("Exception: " . $e->getMessage());
            return false;
        }
    }
}
