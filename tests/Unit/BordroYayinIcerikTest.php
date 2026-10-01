<?php

use App\Service\BordroYayinIcerikService as Icerik;
use PHPUnit\Framework\TestCase;

final class BordroYayinIcerikTest extends TestCase
{
    private function data(bool $dahil = true): array
    {
        return [
            (object) ['adi_soyadi' => 'Test Personeli', 'departman' => 'Birim', 'gorev' => 'Personel', 'banka_odemesi' => 31000, 'elden_odeme' => 9000, 'tc_kimlik_no' => 'secret'],
            (object) ['donem_adi' => '2026/09', 'baslangic_tarihi' => '2026-09-01', 'bitis_tarihi' => '2026-09-30'],
            ['resmiNetTaban' => 26000, 'isInclusive' => $dahil, 'yuvarlamaFarki' => 0.50, 'manualDagitimVar' => false, 'includedAllowanceFiiliGun' => 22,
                'bankaEkOdemeDetaylari' => [['etiket' => 'Resmî tatil (1 x 1000)', 'tutar' => 1000]],
                'bankaKesintiKalemleri' => [['etiket' => 'Avans', 'tutar' => 500], ['etiket' => 'İcra', 'tutar' => 500]]],
            ['banka_odemesi' => 31000, 'bankadan_dusulen_kesinti' => 1000, 'resmi_banka_matrahi' => 32000, 'resmi_yemek_yardimi' => 4000, 'es_yardimi' => 1000, 'toplam_gun' => 30, 'elden_odeme' => 9000],
        ];
    }

    public function testDahilYemekYuvarlamasiAyriVeKalemlerBankaNetineEsit(): void
    {
        $r = Icerik::olustur(...$this->data());
        self::assertSame(3100000, array_sum(array_column($r['kalemler'], 'kurus')));
        self::assertSame(50, $r['kalemler'][2]['kurus']);
        self::assertSame('Yemek günlük yuvarlama farkı', $r['kalemler'][2]['etiket']);
        self::assertSame('Resmî tatil', $r['kalemler'][4]['etiket']);
        self::assertSame('Avans kesintisi', $r['kalemler'][5]['etiket']);
        self::assertArrayNotHasKey('elden_odeme', $r);
        self::assertArrayNotHasKey('tc_kimlik_no', $r);
        self::assertStringNotContainsString('9000', json_encode($r));
    }

    public function testNetUcretteBankaYemekEkiIkiKereSayilmaz(): void
    {
        [$p, $d, $h, $o] = $this->data(false);
        $h['bankaEkOdemeDetaylari'][] = ['etiket' => 'Yemek yardımı', 'tutar' => 4000];
        $h['bankaEkOdemeDetaylari'][] = ['etiket' => 'Eş yardımı', 'tutar' => 1000];
        $r = Icerik::olustur($p, $d, $h, $o);
        self::assertSame(3100000, array_sum(array_column($r['kalemler'], 'kurus')));
        self::assertSame(1, count(array_filter($r['kalemler'], fn($k) => $k['etiket'] === 'Yemek yardımı')));
        self::assertNotContains('Yemek günlük yuvarlama farkı', array_column($r['kalemler'], 'etiket'));
    }

    public function testOrtakHesaptakiTavanAyricaGosterilir(): void
    {
        [$p, $d, $h, $o] = $this->data();
        $p->banka_odemesi = $o['banka_odemesi'] = 30000;
        $o['resmi_banka_matrahi'] = 31000;
        $r = Icerik::olustur($p, $d, $h, $o);
        self::assertContains('Resmî banka kazanç tavanı uygulaması', array_column($r['kalemler'], 'etiket'));
        self::assertSame(3000000, array_sum(array_column($r['kalemler'], 'kurus')));
    }

    public function testManuelDagilimAciklanamiyorsaYayinEngellenir(): void
    {
        [$p, $d, $h, $o] = $this->data();
        $h['manualDagitimVar'] = true;
        $p->banka_odemesi = $o['banka_odemesi'] = 30000;
        $o['resmi_banka_matrahi'] = 31000;
        $this->expectException(DomainException::class);
        Icerik::olustur($p, $d, $h, $o);
    }

    public function testKayitliBankaNetiFarkliysaYayinEngellenir(): void
    {
        $data = $this->data(); $data[0]->banka_odemesi = 30000;
        $this->expectException(DomainException::class);
        Icerik::olustur(...$data);
    }

    public function testAciklanamayanPozitifFarkTamamlamaKalemiOlmaz(): void
    {
        [$p, $d, $h, $o] = $this->data();
        $p->banka_odemesi = $o['banka_odemesi'] = 32000;
        $o['resmi_banka_matrahi'] = 33000;
        $this->expectException(DomainException::class);
        Icerik::olustur($p, $d, $h, $o);
    }

    public function testSifirBankaEldenVerisiniTasima(): void
    {
        [$p, $d, $h, $o] = $this->data();
        $p->banka_odemesi = $o['banka_odemesi'] = $o['resmi_banka_matrahi'] = $o['bankadan_dusulen_kesinti'] = 0;
        $r = Icerik::olustur($p, $d, $h, $o);
        self::assertSame([], $r['kalemler']); self::assertSame(0, $r['banka_net_kurus']);
    }

    public function testTavaniAsanKesintininEldenBileseniYayinlanmaz(): void
    {
        [$p, $d, $h, $o] = $this->data();
        $p->banka_odemesi = $o['banka_odemesi'] = 0;
        $o['resmi_banka_matrahi'] = $o['bankadan_dusulen_kesinti'] = 32000;
        $h['bankaKesintiKalemleri'] = [['etiket' => 'Avans', 'tutar' => 35000]];
        $r = Icerik::olustur($p, $d, $h, $o);
        self::assertSame(0, array_sum(array_column($r['kalemler'], 'kurus')));
        self::assertStringNotContainsString('35000', json_encode($r));
        self::assertSame(-3200000, array_slice($r['kalemler'], -1)[0]['kurus']);
    }
}
