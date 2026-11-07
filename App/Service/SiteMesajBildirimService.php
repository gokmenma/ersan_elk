<?php

namespace App\Service;

use App\Helper\MobileSpa;
use App\Model\BildirimModel;
use App\Model\UserModel;
use App\Model\UserNotificationPreferenceModel;
use Throwable;

class SiteMesajBildirimService
{
    public function yeniMesaj(string $adSoyad, string $konu, string $mesaj): void
    {
        $firmaId = (int) ($_ENV['SITE_FIRMA_ID'] ?? 0);
        $ozet = mb_strlen($mesaj, 'UTF-8') > 120 ? mb_substr($mesaj, 0, 117, 'UTF-8') . '...' : $mesaj;
        $baslik = 'Yeni site mesajı: ' . $adSoyad;
        $icerik = ($konu !== '' ? $konu . ' — ' : '') . $ozet;

        try {
            $Bildirim = new BildirimModel();
            foreach ((new UserModel())->getSiteMesajBildirimKullanicilari($firmaId) as $kullanici) {
                $Bildirim->createNotificationWithPush(
                    (int) $kullanici->id,
                    $baslik,
                    $icerik,
                    'index.php?p=site-yonetimi/mesajlar',
                    'mail',
                    'primary',
                    UserNotificationPreferenceModel::TYPE_SITE_MESSAGE,
                    null,
                    MobileSpa::url('/site-mesajlar')
                );
            }
        } catch (Throwable $e) {
            error_log('Site mesajı bildirimi gönderilemedi: ' . $e->getMessage());
        }
    }
}
