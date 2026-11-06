<?php

namespace App\Service;

use App\Helper\EmailTemplateHelper;
use App\Helper\Security;
use App\Model\BildirimModel;
use App\Model\IkBasvuruModel;
use App\Model\UserModel;
use App\Model\UserNotificationPreferenceModel;
use Throwable;

class IkBasvuruBildirimService
{
    public function yeniBasvuru(int $firmaId, int $basvuruId, string $adSoyad, string $ilanBaslik, ?array $aiSonuc): void
    {
        $detayLink = 'index.php?p=ik-ise-alim/basvuru-detay&id=' . Security::encrypt($basvuruId);
        $baslik = 'Yeni iş başvurusu: ' . $adSoyad;
        $mesaj = $adSoyad . ' "' . $ilanBaslik . '" ilanına başvurdu.';
        if ($aiSonuc !== null) {
            $mesaj .= ' Yapay zekâ ön değerlendirmesi: ' . $aiSonuc['puan'] . '/100 (' . (IkBasvuruModel::ETIKETLER[$aiSonuc['etiket']] ?? $aiSonuc['etiket']) . ').';
        }

        $User = new UserModel();

        try {
            $Bildirim = new BildirimModel();
            foreach ($User->getIkBasvuruBildirimKullanicilari($firmaId) as $kullanici) {
                $Bildirim->createNotificationWithPush(
                    (int) $kullanici->id,
                    $baslik,
                    $mesaj,
                    $detayLink,
                    'user-plus',
                    'success',
                    UserNotificationPreferenceModel::TYPE_IK_APPLICATION
                );
            }
        } catch (Throwable $e) {
            error_log('İK başvuru uygulama bildirimi gönderilemedi: ' . $e->getMessage());
        }

        try {
            $appBase = trim((string) ($_ENV['APP_BASE'] ?? ''), " \t\n\r\0\x0B\"'");
            $tamLink = $appBase !== '' ? 'https://' . $appBase . '/' . $detayLink : null;
            foreach ($User->getIkBasvuruBildirimKullanicilari($firmaId, true) as $kullanici) {
                $icerik = '<p>Merhaba <b>' . htmlspecialchars((string) $kullanici->adi_soyadi, ENT_QUOTES, 'UTF-8') . '</b>,</p>'
                    . '<p>' . htmlspecialchars($mesaj, ENT_QUOTES, 'UTF-8') . '</p>';
                $govde = EmailTemplateHelper::getTemplate($baslik, $icerik, $tamLink ? 'Başvuruyu Görüntüle' : null, $tamLink);
                MailGonderService::gonder([trim((string) $kullanici->email_adresi)], $baslik, $govde, [], [], [], null, $firmaId);
            }
        } catch (Throwable $e) {
            error_log('İK başvuru e-postası gönderilemedi: ' . $e->getMessage());
        }
    }
}
