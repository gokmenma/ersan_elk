<?php

namespace App\Services;

use Exception;
use Model\SettingsModel;
use App\Services\FileLogger;

$logger = new FileLogger('sms_gonder.log');



class SmsGonderService
{
    public static function gonder(array $alicilar, string $mesaj, string $gondericiBaslik = null): bool
    {
        $ch = null;
        $logger = \getLogger();
        try {
            $testMode = !empty($_ENV['SMS_TEST_MODE']);
            // Validasyon
            if (empty($alicilar)) {
                throw new Exception("Alıcı listesi boş olamaz.");
            }
            if (empty(trim($mesaj))) {
                throw new Exception("Mesaj metni boş olamaz.");
            }

            if ($testMode) {
                $logger->info('SMS TEST MODE: Gönderim simüle edildi: '.json_encode(['count'=>count($alicilar),'msgheader'=>$gondericiBaslik]));
                return true;
            }

            // API bilgilerini al (öncelik: env > ayarlar > varsayılan)
            $Settings = new SettingsModel();
            $allSettings = $Settings->getAllSettingsAsKeyValue();
            $logger->info(json_encode([
                "site_id" => $_SESSION['site_id'] ?? null,
                "settings" => $allSettings
            ]));

            $username = ($allSettings['sms_api_kullanici'] ?? '');
            $password =  ($allSettings['sms_api_sifre'] ?? '');


            /** Username veya şifre boş ise uyarı ver */
            if (empty($username) || empty($password)) {
                
                throw new Exception("SMS API kimlik bilgileri eksik. Lütfen ayarları kontrol edin.");
            }



            $msgheader = $gondericiBaslik ?? ($_ENV['SMS_SENDER_ID'] ?? ($allSettings['sms_baslik'] ?? 'YONAPP'));

            if (empty($username) || empty($password)) {
                try {
                    $pdo = \getDbConnection();
                    $stmt = $pdo->prepare('SELECT set_value FROM settings WHERE set_name = ? ORDER BY id DESC LIMIT 1');
                    if (empty($username)) { $stmt->execute(['sms_api_kullanici']); $username = $stmt->fetchColumn() ?: $username; }
                    if (empty($password)) { $stmt->execute(['sms_api_sifre']); $password = $stmt->fetchColumn() ?: $password; }
                    if (empty($msgheader) || $msgheader === 'YONAPP') { $stmt->execute(['sms_baslik']); $mh = $stmt->fetchColumn(); if ($mh) { $msgheader = $mh; } }
                } catch (\Throwable $e) { /* yoksay */ }
            }
         
            if (empty($username) || empty($password)) {
                $logger->error('SMS API kimlik bilgileri eksik.');
                return false;
            }

            // Mesaj dizisini oluştur
            $messagesPayload = [];
            foreach ($alicilar as $numara) {
                $messagesPayload[] = [
                    'msg' => $mesaj,
                    'no' => (string)$numara
                ];
            }
            
            // API verisini hazırla
            $data = [
                "msgheader" => $msgheader,
                "messages" => $messagesPayload,
                "encoding" => "TR",
            ];

            // cURL ile gönder
            $url = "https://api.netgsm.com.tr/sms/rest/v2/send";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($username . ':' . $password)
            ]);

            /**Gerçek kullanımda cURL ile yanıtı al */
            $response = curl_exec($ch);

            /** test için json veri oluştur */
            // $response = json_encode([
            //     'code' => '00',
            //     'description' => 'Başarılı',
            //     'messageId' => uniqid(),
            // ]);

            if (curl_errno($ch)) {
                throw new Exception('cURL Hatası: ' . curl_error($ch));
            }

            $netgsmResult = json_decode($response, true);

            if (isset($netgsmResult['code']) && $netgsmResult['code'] == '00') {
                $logger->info(count($alicilar) . " alıcıya başarıyla SMS gönderildi.");
                return true;
            } else {
                throw new Exception('Netgsm API Hatası: ' . ($netgsmResult['description'] ?? 'Bilinmeyen hata.'));
            }

        } catch (Exception $e) {
            $logger->error("SMS gönderim hatası: " . $e->getMessage());
            return false;
        } finally {
            if (isset($ch) && is_resource($ch)) {
                curl_close($ch);
            }
        }
    }
}