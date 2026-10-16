<?php
namespace App\Service;

use App\Helper\Security;
use App\Model\BordroYayinModel;
use App\Model\PushSubscriptionModel;

final class BordroYayinBildirimService
{
    public function calistir(int $limit = 50): array
    {
        $model = new BordroYayinModel();
        $push = new PushNotificationService();
        $abonelik = new PushSubscriptionModel();
        $sayac = ['gonderildi' => 0, 'atlandi' => 0, 'bekliyor' => 0];
        for ($n = 0; $n < $limit; $n++) {
            $q = $model->bildirimAl();
            if (!$q) break;
            try {
                $durum = $model->bildirimUygula($q, function (array $guncel) use ($push, $abonelik) {
                    if (!$abonelik->getSubscriptionsByPersonel((int) $guncel['personel_id'])) return 'atlandi';
                    $_SESSION['firma_id'] = (int) $guncel['firma_id'];
                    $revizyon = (int) $guncel['gun'] === 0 && (int) ($guncel['surum'] ?? 1) > 1;
                    $sonuc = $push->sendToPersonel((int) $guncel['personel_id'], [
                        'title' => (int) $guncel['gun'] === 0 ? ($revizyon ? 'Ücret pusulanız revize edildi' : 'Ücret pusulanız yayınlandı') : 'Ücret pusulası onay hatırlatması',
                        'body' => $revizyon ? 'Yeni sürümü inceleyip yeniden onaylamanız gerekmektedir.' : 'Ücret hesap pusulanızı inceleyip onayınızı kaydedebilirsiniz.',
                        'url' => '?page=bordro&dokum=' . Security::encrypt((int) $guncel['dokum_id']),
                        'tag' => 'bordro-yayin-' . $guncel['dokum_id'] . '-' . $guncel['gun'],
                    ], true);
                    return $sonuc ? 'gonderildi' : 'bekliyor';
                });
                $sayac[$durum]++;
            } catch (\Throwable $e) {
                error_log('Bordro yayın bildirim hatası: ' . $e->getMessage());
                $model->bildirimBitir($q, 'bekliyor', 'Bildirim gönderimi tamamlanamadı.');
                $sayac['bekliyor']++;
            }
        }
        return $sayac;
    }
}
