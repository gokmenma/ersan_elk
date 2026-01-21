<?php

namespace App\Service;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use App\Model\PushSubscriptionModel;

class PushNotificationService
{
    private $webPush;
    private $subscriptionModel;

    public function __construct()
    {
        $configPath = dirname(__DIR__) . '/Config/vapid.php';

        if (file_exists($configPath)) {
            $config = require $configPath;

            $auth = [
                'VAPID' => [
                    'subject' => $config['subject'],
                    'publicKey' => $config['publicKey'],
                    'privateKey' => $config['privateKey'],
                ],
            ];
            $this->webPush = new WebPush($auth);
        } else {
            // Fallback to .env if config file doesn't exist
            $publicKey = $_ENV['VAPID_PUBLIC_KEY'] ?? '';
            $privateKey = $_ENV['VAPID_PRIVATE_KEY'] ?? '';

            if ($publicKey && $privateKey) {
                $auth = [
                    'VAPID' => [
                        'subject' => 'mailto:info@ersanelektrik.com',
                        'publicKey' => $publicKey,
                        'privateKey' => $privateKey,
                    ],
                ];
                $this->webPush = new WebPush($auth);
            }
        }

        $this->subscriptionModel = new PushSubscriptionModel();
    }

    /**
     * Personele bildirim gönderir
     * 
     * @param int $personelId
     * @param array $payload ['title' => '...', 'body' => '...', 'url' => '...']
     * @return bool
     */
    public function sendToPersonel($personelId, $payload)
    {
        $pushSent = false;

        if ($this->webPush) {
            $subscriptions = $this->subscriptionModel->getSubscriptionsByPersonel($personelId);

            if (!empty($subscriptions)) {
                foreach ($subscriptions as $subData) {
                    $subscription = Subscription::create([
                        'endpoint' => $subData['endpoint'],
                        'publicKey' => $subData['public_key'],
                        'authToken' => $subData['auth_token'],
                        'contentEncoding' => $subData['content_encoding'] ?? 'aes128gcm',
                    ]);

                    $this->webPush->queueNotification(
                        $subscription,
                        json_encode($payload)
                    );
                }

                // Bildirimleri gönder
                $results = $this->webPush->flush();

                $successCount = 0;

                // Sonuçları işle ve geçersiz abonelikleri temizle
                foreach ($results as $report) {
                    if ($report->isSuccess()) {
                        $successCount++;
                    } else {
                        // Endpoint'i al
                        $endpoint = $report->getRequest()->getUri()->__toString();

                        // Log error
                        error_log("Push Notification Error for {$endpoint}: " . $report->getReason());

                        // Eğer abonelik süresi dolmuşsa veya geçersizse veritabanından sil
                        if ($report->isSubscriptionExpired()) {
                            $this->subscriptionModel->deleteByEndpoint($endpoint);
                        }
                    }
                }

                if ($successCount > 0) {
                    $pushSent = true;
                }
            }
        }

        // Eğer push bildirimi gönderilemediyse mail at
        if (!$pushSent) {
            $this->sendEmailFallback($personelId, $payload);
        }

        return $pushSent;
    }

    /**
     * Push bildirimi başarısız olursa mail gönderir
     */
    private function sendEmailFallback($personelId, $payload)
    {
        try {
            $personelModel = new \App\Model\PersonelModel();
            $personel = $personelModel->find($personelId);

            if ($personel && !empty($personel->email_adresi)) {
                $subject = $payload['title'] ?? 'Yeni Bildirim';
                $body = $payload['body'] ?? '';
                $url = $payload['url'] ?? '';

                // Mail içeriği oluştur
                $content = "<h3>Merhaba {$personel->adi_soyadi},</h3>";
                $content .= "<p>{$body}</p>";

                if ($url) {
                    // Base URL'i dinamik almaya çalışalım, yoksa varsayılanı kullanalım
                    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                    // Eğer url relative ise base url ekle
                    if (strpos($url, 'http') === false) {
                        $fullUrl = $baseUrl . '/' . ltrim($url, '/');
                    } else {
                        $fullUrl = $url;
                    }

                    $content .= "<p><a href='{$fullUrl}'>Detayları görüntülemek için tıklayınız</a></p>";
                }

                $content .= "<br><p>Saygılarımızla,<br>Ersan Elektrik</p>";

                \App\Service\MailGonderService::gonder(
                    $personel->email_adresi,
                    $subject,
                    $content
                );
            }
        } catch (\Exception $e) {
            error_log("Email fallback error: " . $e->getMessage());
        }
    }
}
