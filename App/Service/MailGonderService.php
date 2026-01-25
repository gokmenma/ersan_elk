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
            $allSettings = $Settings->getAllSettingsAsKeyValue();
            // Sunucu Ayarları
            $mail->isSMTP();

            $mail->Host = $allSettings['smtp_host'] ?? $_ENV['SMTP_HOST']; // Kendi SMTP sunucunuz (örn: smtp.gmail.com)
            $mail->SMTPAuth = true;
            $mail->Username = $allSettings['smtp_kullanici'] ?? $_ENV['SMTP_USER']; // SMTP kullanıcı adınız

            $mail->Password = $allSettings['smtp_sifre_yeni'] ?? $_ENV['SMTP_PASSWORD'];           // SMTP şifreniz

            $secureType = $allSettings['smtp_guvenlik'] ?? 'tls';
            $port = $allSettings['smtp_port'] ?? $_ENV['SMTP_PORT']; // Veya 465

            // Port 465 genellikle SSL/SMTPS gerektirir. Kullanıcı TLS seçse bile SSL zorlayalım.
            if ($port == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secureType == 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->Port = $allSettings['smtp_port'] ?? $_ENV['SMTP_PORT']; // Veya 465
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

            // ÖNEMLİ DEĞİŞİKLİK: BCC kullan (alıcılar birbirini görmez)
            // TO alanına bir dummy adres koy (zorunlu)
            // $mail->addAddress('bilgi@yonapp.com.tr'); // Görünen alıcı (dummy)

            // Asıl alıcıları BCC'ye ekle, böylece birbirlerini görmezler
            if (is_array($kime)) {
                // TO alanına göndericiyi ekleyelim ki boş kalmasın (bazı sunucular reddeder)
                $mail->addAddress($fromEmail, $fromName);

                foreach ($kime as $email) {
                    $mail->addBCC(trim($email)); // BCC ile ekle - birbirlerini görmezler
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
