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
        array $bcc = [],
        ?array $customSettings = null
    ): bool {
        $mail = new PHPMailer(true);
        $debugOutput = '';
        $mail->Debugoutput = function ($str, $level) use (&$debugOutput) {
            $debugOutput .= "$str\n";
        };


        try {


            /**Gönderici mail ayarlarını al */
            $Settings = new SettingsModel();
            $firma_id = $_SESSION['firma_id'] ?? null;

            // CLI ortamında (test dosyası gibi) session olmayabilir, varsayılan 1 alalım
            if ($firma_id === null && php_sapi_name() === 'cli') {
                $firma_id = 1;
            }

            $allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

            // Eğer dışarıdan özel ayarlar geldiyse (test amaçlı), mevcut ayarların üzerine yaz
            if (is_array($customSettings)) {
                $allSettings = array_merge($allSettings, $customSettings);
            }

            // Sunucu Ayarları
            $mail->isSMTP();

            $mail->Host = $allSettings['smtp_host'] ?? $_ENV['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $allSettings['smtp_kullanici'] ?? $_ENV['SMTP_USER'];

            // Hem smtp_sifre hem de smtp_sifre_yeni kontrolü
            $mail->Password = $allSettings['smtp_sifre_yeni'] ?? $allSettings['smtp_sifre'] ?? $_ENV['SMTP_PASSWORD'];

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
            $mail->Timeout = 10;
            $mail->SMTPDebug = 2; // Hata durumunda detayları yakalamak için açıyoruz




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
            $mail->Encoding = 'quoted-printable';

            // Gönderen ve Alıcı Bilgileri
            $fromEmail = $allSettings['gonderen_eposta'] ?? $allSettings['smtp_kullanici'] ?? 'noreply@softran.online';
            $fromName = $allSettings['gonderen_adi'] ?? 'Ersan Elektrik | Personel Yönetim';

            $mail->setFrom($fromEmail, $fromName);
            $mail->Sender = $fromEmail; // Return-Path başlığını ayarlar (Teslimat için kritik)

            // Alıcıları ekle
            if (is_array($kime)) {
                $uniqueRecipients = array_unique(array_filter(array_map('trim', $kime)));
                $first = true;
                foreach ($uniqueRecipients as $email) {
                    if ($first) {
                        $mail->addAddress($email);
                        $first = false;
                    } else {
                        $mail->addBCC($email);
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

            // İçerik
            $mail->isHTML(true);
            $mail->Subject = $konu;
            $mail->Body = $icerik;
            $mail->AltBody = strip_tags($icerik);

            //eğer ekler boş değilse foreach ile ekleri ekle
            if (!empty($ekler)) {
                foreach ($ekler as $ek) {
                    if (is_array($ek) && !empty($ek['path'])) {
                        $mail->addAttachment($ek['path'], $ek['name'] ?? 'attachment');
                    } else {
                        $mail->addAttachment((string) $ek);
                    }
                }
            }

            if ($mail->send()) {
                // Başarılı olsa bile loglara kaydet (opsiyonel, debug için)
                // error_log("E-posta başarıyla gönderildi. Log: " . $debugOutput);
                return true;
            } else {
                throw new \Exception("E-posta gönderilemedi. SMTP Log: " . $debugOutput);
            }
        } catch (Exception $e) {
            $msg = $e->getMessage();
            error_log("Mail Gönderim Hatası: " . $msg);
            throw new \Exception($msg);
        }
    }
}
