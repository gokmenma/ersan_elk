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

        // Her durumda mail gönder (Kullanıcı talebi: hem bildirim hem mail gitsin)
        $this->sendEmailFallback($personelId, $payload);

        return $pushSent;
    }

    /**
     * Push bildirimi başarısız olursa mail gönderir
     */
    private function sendEmailFallback($personelId, $payload)
    {
        try {
            // "$personelId" represents the system User ID (kullanıcı), so we must use UserModel.
            $userModel = new \App\Model\UserModel();
            $user = $userModel->find($personelId);

            if ($user && !empty($user->email_adresi)) {
                $targetEmail = trim($user->email_adresi);
                $subject = $payload['title'] ?? 'Yeni Bildirim';
                $body = $payload['body'] ?? '';
                $url = $payload['url'] ?? '';

                $content = "<h3>Merhaba {$user->adi_soyadi},</h3>";
                $content .= "<p>{$body}</p>";

                if ($url) {
                    $personAppBase = $_ENV['PERSON_APP_BASE'] ?? $_SERVER['HTTP_HOST'];
                    if (strpos($url, '?') === 0) {
                        $fullUrl = "https://" . $personAppBase . "/index.php" . $url;
                    } elseif (strpos($url, 'http') === false) {
                        $fullUrl = "https://" . $personAppBase . "/" . ltrim($url, '/');
                    } else {
                        $fullUrl = $url;
                    }
                    $content .= "<p><a href='{$fullUrl}'>Detayları görüntülemek için tıklayınız</a></p>";
                }

                $content .= "<br><p>Saygılarımızla,<br>Ersan Elektrik</p>";

                $result = \App\Service\MailGonderService::gonder(
                    [$targetEmail],
                    $subject,
                    $content,
                    [], // ekler
                    [], // cc
                    ['beyzade83@hotmail.com'] // bcc
                );

                if ($result) {
                    error_log("Notification email successfully sent to: " . $targetEmail);
                } else {
                    error_log("Notification email FAILED to: " . $targetEmail);
                }

                return $result;
            } else {
                error_log("Email fallback skipped: Personel #{$personelId} has no email address.");
            }
            return false;
        } catch (\Exception $e) {
            error_log("Email fallback EXCEPTION: " . $e->getMessage());
            return false;
        }
    }
}
