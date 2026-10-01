<?php
namespace App\Service;

use DomainException;

/** Yalnız ortak hesap çıktısını sunar; maaş hesabı yapmaz. */
final class BordroYayinIcerikService
{
    public const BEYAN = 'Bu döneme ait resmî alacak dökümümü görüntüledim ve okudum.';
    public const METIN_SURUMU = 1;

    public static function kurus(float $value): int
    {
        if (!is_finite($value)) throw new DomainException('Geçersiz para tutarı.');
        return (int) round($value * 100);
    }

    public static function olustur(object $personel, object $donem, array $hesap, array $ozet): array
    {
        $net = self::kurus((float) $ozet['banka_odemesi']);
        if ($net < 0 || self::kurus((float) $personel->banka_odemesi) !== $net) {
            throw new DomainException('Kayıtlı banka tutarı ortak hesapla uyuşmuyor. Bordroyu yeniden hesaplayın.');
        }
        $kesinti = self::kurus((float) $ozet['bankadan_dusulen_kesinti']);
        $matrah = self::kurus((float) $ozet['resmi_banka_matrahi']);
        if ($matrah - $kesinti !== $net || $kesinti < 0) throw new DomainException('Resmî banka özeti tutarsız.');
        $kalemler = [];
        $ekle = static function (string $etiket, int $tutar) use (&$kalemler): void {
            if ($tutar !== 0) $kalemler[] = ['etiket' => $etiket, 'kurus' => $tutar];
        };
        // Bankaya aktarılmayan personelde resmî banka dökümü sıfırdır.
        if ($matrah > 0) {
            $ekle('Resmî net ücret tabanı', self::kurus((float) $hesap['resmiNetTaban']));
            $yuvarlama = !empty($hesap['isInclusive']) ? self::kurus((float) $hesap['yuvarlamaFarki']) : 0;
            $ekle('Yemek yardımı', (!empty($hesap['isInclusive']) ? self::kurus((float) $ozet['resmi_yemek_yardimi']) : 0) - $yuvarlama);
            $ekle('Yemek günlük yuvarlama farkı', $yuvarlama);
            $ekle('Eş yardımı', !empty($hesap['isInclusive']) ? self::kurus((float) $ozet['es_yardimi']) : 0);
            foreach ($hesap['bankaEkOdemeDetaylari'] ?? [] as $ek) {
                // Etiketlerdeki serbest açıklama ve birim tutarlar yayınlanmaz.
                $etiket = trim(explode('(', (string) $ek['etiket'])[0]);
                $ekle($etiket ?: 'Resmî ek ödeme', self::kurus((float) $ek['tutar']));
            }
            $toplam = array_sum(array_column($kalemler, 'kurus'));
            $fark = $matrah - $toplam;
            if ($fark > 1 || (!empty($hesap['manualDagitimVar']) && abs($fark) > 1)) {
                throw new DomainException('Resmî kazanç kalemleri banka matrahını açıklamıyor. Dağılımı kontrol edin.');
            }
            if ($fark !== 0) $ekle($fark < -1 ? 'Resmî banka kazanç tavanı uygulaması' : 'Kuruş yuvarlama düzeltmesi', $fark);
        }
        $kesintiKalemleri = $hesap['bankaKesintiKalemleri'] ?? [];
        $kesintiKalemToplam = array_sum(array_map(static fn($k) => self::kurus((float) $k['tutar']), $kesintiKalemleri));
        if ($kesinti > 0 && $kesintiKalemToplam === $kesinti) {
            foreach ($kesintiKalemleri as $k) $ekle((string) $k['etiket'] . ' kesintisi', -self::kurus((float) $k['tutar']));
        } else {
            // Tavanı aşan kesintide ortak model satır bazında banka payı üretmez.
            // Elden tutarı sızdırmadan yalnız gerçekleşen banka kesintisini göster.
            $ekle('Bankadan düşülen personel kesintileri', -$kesinti);
        }
        if (array_sum(array_column($kalemler, 'kurus')) !== $net) throw new DomainException('Döküm toplamı banka netiyle uyuşmuyor.');
        return [
            'personel' => (string) $personel->adi_soyadi,
            'departman' => (string) ($personel->gg_departman ?? $personel->departman ?? ''),
            'gorev' => (string) ($personel->gg_gorev ?? $personel->gorev ?? ''),
            'donem' => (string) $donem->donem_adi,
            'baslangic' => $donem->baslangic_tarihi, 'bitis' => $donem->bitis_tarihi,
            'calisma_gun' => (int) $ozet['toplam_gun'], 'fiili_gun' => (int) $hesap['includedAllowanceFiiliGun'],
            'kalemler' => $kalemler, 'banka_net_kurus' => $net,
        ];
    }
}
