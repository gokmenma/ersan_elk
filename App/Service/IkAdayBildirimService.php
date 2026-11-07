<?php

namespace App\Service;

use App\Helper\EmailTemplateHelper;

class IkAdayBildirimService
{
    public const SABLONLAR = [
        'gorusme' => 'Görüşmeye davet',
        'olumlu' => 'Olumlu dönüş',
        'olumsuz' => 'Olumsuz dönüş',
        'serbest' => 'Serbest mesaj',
    ];

    public static function hazirla(string $sablon, string $adSoyad, string $ilanBaslik, string $firmaAdi, ?string $mulakatTarihi): array
    {
        $firma = $firmaAdi !== '' ? $firmaAdi : 'Firmamız';
        $selam = 'Sayın ' . $adSoyad . ',';
        $imza = "Saygılarımızla,\n" . $firma . ' İnsan Kaynakları';

        switch ($sablon) {
            case 'gorusme':
                $zaman = $mulakatTarihi !== null ? date('d.m.Y H:i', strtotime($mulakatTarihi)) : '[tarih ve saat]';
                return [
                    $ilanBaslik . ' Başvurunuz Hakkında - Görüşme Daveti',
                    $selam . "\n\n" . $ilanBaslik . ' pozisyonuna yaptığınız başvuru için teşekkür ederiz. Başvurunuzu değerlendirdik ve sizinle bir görüşme yapmak istiyoruz.'
                    . "\n\nGörüşme zamanı: " . $zaman . "\nGörüşme yeri: [adres / bağlantı]\n\nUygun olup olmadığınızı bu mesaja yanıt vererek bildirebilirsiniz.\n\n" . $imza,
                ];
            case 'olumlu':
                return [
                    $ilanBaslik . ' Başvurunuz Hakkında',
                    $selam . "\n\n" . $ilanBaslik . ' pozisyonuna yaptığınız başvuru olumlu değerlendirilmiştir. Süreçle ilgili sonraki adımlar için sizinle ayrıca iletişime geçeceğiz.'
                    . "\n\nİlginiz için teşekkür ederiz.\n\n" . $imza,
                ];
            case 'olumsuz':
                return [
                    $ilanBaslik . ' Başvurunuz Hakkında',
                    $selam . "\n\n" . $ilanBaslik . ' pozisyonuna gösterdiğiniz ilgi için teşekkür ederiz. Yaptığımız değerlendirme sonucunda bu pozisyon için başvurunuzla ilerleyemeyeceğimizi üzülerek bildiririz.'
                    . "\n\nBilgilerinizi uygun bir pozisyon açıldığında değerlendirmek üzere saklayabiliriz. İş hayatınızda başarılar dileriz.\n\n" . $imza,
                ];
            default:
                return [$ilanBaslik . ' Başvurunuz Hakkında', $selam . "\n\n\n" . $imza];
        }
    }

    public static function epostaGonder(string $alici, string $konu, string $mesaj, int $firmaId): bool
    {
        $icerik = '<p style="white-space:normal">' . nl2br(htmlspecialchars($mesaj, ENT_QUOTES, 'UTF-8')) . '</p>';
        $govde = EmailTemplateHelper::getTemplate($konu, $icerik);
        return MailGonderService::gonder([$alici], $konu, $govde, [], [], [], null, $firmaId);
    }
}
