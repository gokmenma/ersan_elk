<?php

use App\Service\ManuelFaturaService;
use PHPUnit\Framework\TestCase;

final class ManuelFaturaServiceTest extends TestCase
{
    private ManuelFaturaService $servis;

    protected function setUp(): void
    {
        $this->servis = new ManuelFaturaService();
    }

    public function testTurkceSayiBicimleriCozulur(): void
    {
        self::assertSame(2740.0, $this->servis->sayi('2.740,00'));
        self::assertSame(2740.0, $this->servis->sayi('2,740.00'));
        self::assertSame(1234567.5, $this->servis->sayi('1.234.567,50'));
        self::assertSame(117.0, $this->servis->sayi('117TL'));
        self::assertSame(1.2345, $this->servis->sayi('1.2345'));
        self::assertSame(23.4, $this->servis->sayi(23.4));
        self::assertNull($this->servis->sayi(''));
        self::assertNull($this->servis->sayi(['x']));
    }

    public function testTarihBicimleriVeriTabaniBicimineCevrilir(): void
    {
        self::assertSame('2026-11-04', $this->servis->tarih('04.11.2026'));
        self::assertSame('2026-11-04', $this->servis->tarih('2026-11-04'));
        self::assertSame('2026-11-04', $this->servis->tarih('04/11/2026'));
        self::assertSame('', $this->servis->tarih('31.02.2026'));
    }

    public function testTuvturkFaturasiBelgeToplamiylaAyniHesaplanir(): void
    {
        $normal = $this->servis->normalize([
            'belge_turu' => 'EFATURA',
            'fatura_no' => 'CKF2026000062689',
            'fatura_tarihi' => '04.11.2026',
            'ettn' => '130BF87F-163D-49B1-B9DE-861BE248A754',
            'alici_unvan' => 'ÇAKMAKLI TAŞIT MUAYENE İSTASYONLARI YAPIM VE İŞLETİM A.Ş.',
            'alici_vkn_tckn' => '225 128 6226',
            'satirlar' => [
                ['urun_hizmet_adi' => '1002-Otomobil TM', 'miktar' => 1, 'birim' => 'C62', 'birim_fiyat' => '2740', 'kdv_orani' => 20],
                ['urun_hizmet_adi' => 'Ödeme Kuruluşu Hizmet Bedeli', 'miktar' => 1, 'birim' => 'XYZ', 'birim_fiyat' => '117', 'kdv_orani' => 20],
            ],
        ]);

        self::assertSame('2026-11-04', $normal['header']['fatura_tarihi']);
        self::assertSame('130bf87f-163d-49b1-b9de-861be248a754', $normal['header']['ettn']);
        self::assertSame('2251286226', $normal['header']['alici_vkn_tckn']);
        self::assertSame('C62', $normal['lines'][1]['birim']);

        $this->servis->dogrula($normal['header'], $normal['lines']);
        $hesap = $this->servis->hesapla($normal['header'], $normal['lines']);
        self::assertSame(2857.0, $hesap['totals']['kdv_matrahi']);
        self::assertSame(571.4, $hesap['totals']['hesaplanan_kdv']);
        self::assertSame(3428.4, $hesap['totals']['odenecek_tutar']);
        self::assertSame([], $this->servis->karsilastir(
            ['mal_hizmet_toplami' => 2857.0, 'kdv_toplami' => 571.4, 'stopaj_tutari' => null, 'odenecek_tutar' => 3428.4],
            $hesap['totals']
        ));
    }

    public function testSerbestMeslekMakbuzundaStopajOdenecektenDusulur(): void
    {
        $normal = $this->servis->normalize([
            'belge_turu' => 'SMM',
            'fatura_tarihi' => '28.10.2026',
            'alici_unvan' => 'Av. Mehmet Yılmaz',
            'alici_vkn_tckn' => '12345678950',
            'stopaj_orani' => 20,
            'satirlar' => [['urun_hizmet_adi' => 'Danışmanlık', 'miktar' => 1, 'birim_fiyat' => 25000, 'kdv_orani' => 20]],
        ]);
        $hesap = $this->servis->hesapla($normal['header'], $normal['lines']);
        self::assertSame(5000.0, $hesap['totals']['stopaj_tutari']);
        self::assertSame(5000.0, $hesap['totals']['hesaplanan_kdv']);
        self::assertSame(25000.0, $hesap['totals']['odenecek_tutar']);
    }

    public function testGiderPusulasiVeMakbuzdaKdvSifirlanir(): void
    {
        foreach (['GIDER_PUSULASI' => 800.0, 'MAKBUZ' => 1000.0] as $tur => $beklenen) {
            $normal = $this->servis->normalize([
                'belge_turu' => $tur,
                'fatura_tarihi' => '02.11.2026',
                'alici_unvan' => 'Ali Veli',
                'stopaj_orani' => 20,
                'satirlar' => [['urun_hizmet_adi' => 'Hurda kablo', 'miktar' => 1, 'birim_fiyat' => 1000, 'kdv_orani' => 20]],
            ]);
            self::assertSame(0.0, $normal['lines'][0]['kdv_orani']);
            $hesap = $this->servis->hesapla($normal['header'], $normal['lines']);
            self::assertSame($beklenen, $hesap['totals']['odenecek_tutar'], $tur);
        }
    }

    public function testToplamFarkiUyariOlarakDoner(): void
    {
        $uyarilar = $this->servis->karsilastir(
            ['mal_hizmet_toplami' => 2857.0, 'kdv_toplami' => null, 'stopaj_tutari' => null, 'odenecek_tutar' => 3428.4],
            ['kdv_matrahi' => 2740.0, 'hesaplanan_kdv' => 548.0, 'stopaj_tutari' => 0.0, 'odenecek_tutar' => 3288.0]
        );
        self::assertCount(2, $uyarilar);
    }

    public function testZorunluAlanlarSunucudaDogrulanir(): void
    {
        $normal = $this->servis->normalize(['belge_turu' => 'KAGIT', 'fatura_tarihi' => '02.11.2026', 'alici_unvan' => 'Satıcı A.Ş.', 'satirlar' => []]);
        $this->expectExceptionMessage('VKN/TCKN');
        $this->servis->dogrula($normal['header'], $normal['lines']);
    }
}
