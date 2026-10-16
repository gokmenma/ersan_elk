<?php

use App\Model\BordroPersonelModel;
use App\Service\BordroPusulaService;
use PHPUnit\Framework\TestCase;

final class BordroPusulaTest extends TestCase
{
    private function row(): object
    {
        return (object) ['adi_soyadi' => '<Test Personeli>', 'departman' => 'Birim', 'gorev' => 'Görev',
            'banka_odemesi' => 31000, 'elden_odeme' => 9876.54, 'sodexo_odemesi' => 1234.56, 'diger_odeme' => 789.12,
            'sgk_isci' => 4624.20, 'issizlik_isci' => 330.30, 'gelir_vergisi' => 0, 'damga_vergisi' => 59.65,
            'hesaplama_tarihi' => '2026-10-15 12:00:00', 'hesaplama_detay' => json_encode([
                'matrahlar' => ['ssk_gunu' => 30, 'sgk_matrahi' => 33030], 'odeme_dagilimi' => ['banka_net' => 31000],
            ])];
    }

    private function donem(): object
    {
        return (object) ['donem_adi' => 'EYLÜL 2026', 'baslangic_tarihi' => '2026-09-01', 'bitis_tarihi' => '2026-09-30'];
    }

    private function model(): BordroPersonelModel
    {
        $model = $this->getMockBuilder(BordroPersonelModel::class)->disableOriginalConstructor()
            ->onlyMethods(['hesaplaOrtakGosterimDegerleri', 'getMuhasebeOdemeOzeti'])->getMock();
        $model->method('hesaplaOrtakGosterimDegerleri')->willReturn([
            'resmiNetTaban' => 28000, 'isInclusive' => true, 'yuvarlamaFarki' => 0.50,
            'includedAllowanceFiiliGun' => 22, 'mealAllowanceDeduction' => 4000,
            'bankaEkOdemeDetaylari' => [], 'bankaKesintiKalemleri' => [],
        ]);
        $model->method('getMuhasebeOdemeOzeti')->willReturn([
            'banka_odemesi' => 31000, 'resmi_banka_matrahi' => 32000, 'bankadan_dusulen_kesinti' => 1000,
            'resmi_yemek_yardimi' => 4000, 'es_yardimi' => 0, 'toplam_gun' => 30,
        ]);
        return $model;
    }

    public function testResmiNetKayitliBankaVeYasalKesintilerKorunur(): void
    {
        $row = $this->row(); $before = serialize($row);
        $p = BordroPusulaService::olustur($row, $this->donem(), $this->model());
        self::assertSame(3100000, $p['resmi']['banka_net_kurus']);
        self::assertSame(501415, $p['yasal_kesinti_kurus']);
        self::assertSame(100000, $p['ozel_kesinti_kurus']);
        self::assertSame($p['resmi']['banka_net_kurus'], $p['kazanc_kurus'] - $p['yasal_kesinti_kurus'] - $p['ozel_kesinti_kurus']);
        self::assertSame($before, serialize($row));
    }

    public function testEksikSnapshotCanliHesaplanmaz(): void
    {
        foreach ([null, '', 'null', '{}', '[]', '{bozuk'] as $value) {
            $row = $this->row(); $row->hesaplama_detay = $value;
            $model = $this->getMockBuilder(BordroPersonelModel::class)->disableOriginalConstructor()
                ->onlyMethods(['hesaplaOrtakGosterimDegerleri'])->getMock();
            $model->expects(self::never())->method('hesaplaOrtakGosterimDegerleri');
            try { BordroPusulaService::olustur($row, $this->donem(), $model); self::fail('Geçersiz snapshot reddedilmeli.'); }
            catch (DomainException $e) { self::assertStringContainsString('Hesaplanmadı', $e->getMessage()); }
        }
    }

    public function testHesaplamaTarihiOlmadanCiktiVerilmez(): void
    {
        $row = $this->row(); $row->hesaplama_tarihi = null;
        $this->expectException(DomainException::class);
        BordroPusulaService::olustur($row, $this->donem(), $this->model());
    }

    public function testSablonResmiDisiKanallariSizdirmazVeEksikAlanUydurmaz(): void
    {
        $row = $this->row();
        $p = BordroPusulaService::olustur($row, $this->donem(), $this->model());
        $p['resmi']['kalemler'][] = ['etiket' => 'Hafta içi nöbet (4 Saat)', 'kurus' => 299472];
        $html = BordroPusulaService::html($row, $this->donem(), (object) ['firma_adi' => 'Firma <A>'], $p);
        self::assertStringContainsString('&lt;Test Personeli&gt;', $html);
        self::assertStringContainsString('Firma &lt;A&gt;', $html);
        self::assertStringContainsString('31.000,00', $html);
        self::assertStringContainsString('4.624,20', $html);
        self::assertStringNotContainsString('Yemek günlük yuvarlama farkı', $html);
        self::assertMatchesRegularExpression('/Yemek yardımı<\/td><td class="p-money">4\.000,00 N/', $html);
        self::assertStringNotContainsString('Hafta içi nöbet', $html);
        self::assertMatchesRegularExpression('/Fazla Mesai Ücreti \(Net\)<\/td><td class="p-money">2\.994,72 N/', $html);
        foreach (['9.876,54', '1.234,56', '789,12', 'NAKDEN ALDIM'] as $text) self::assertStringNotContainsString($text, $html);
        self::assertStringContainsString('—', $html);
    }

    public function testKapaliVeAcikDonemAyniSnapshotuSunar(): void
    {
        $d = $this->donem(); $d->kapali_mi = 0;
        $acik = BordroPusulaService::olustur($this->row(), $d, $this->model());
        $d->kapali_mi = 1;
        self::assertSame($acik, BordroPusulaService::olustur($this->row(), $d, $this->model()));
    }

    public function testYayinIcerigiResmiPusulaOzetiniTasiyorVeIcDetayiSizdirmiyor(): void
    {
        $row = $this->row();
        $row->tc_kimlik_no = '12345678901';
        $row->sgk_no = 'SGK-42';
        $icerik = BordroPusulaService::yayinIcerigi($row, $this->donem(), (object) ['firma_adi' => 'Test Firma'], $this->model());
        self::assertSame('ucret_hesap_pusulasi', $icerik['icerik_turu']);
        self::assertSame(3701415, $icerik['kazanc_toplami_kurus']);
        self::assertSame(601415, $icerik['toplam_kesinti_kurus']);
        self::assertSame(3100000, $icerik['banka_net_kurus']);
        self::assertSame('SGK işçi payı', $icerik['yasal_kesintiler'][0]['etiket']);
        self::assertArrayNotHasKey('detay', $icerik);
        self::assertStringNotContainsString('9876.54', json_encode($icerik));
        self::assertSame('*******8901', $icerik['resmi_pusula']['personel']['tc_kimlik_no']);
        self::assertSame('SGK-42', $icerik['resmi_pusula']['personel']['sgk_no']);
        self::assertSame('Test Firma', $icerik['resmi_pusula']['firma']['firma_adi']);
        self::assertArrayNotHasKey('odeme_dagilimi', $icerik['resmi_pusula']['pusula']['detay']);

        $snapshot = $icerik['resmi_pusula'];
        $html = BordroPusulaService::html(
            (object) $snapshot['personel'],
            (object) $snapshot['donem'],
            (object) $snapshot['firma'],
            $snapshot['pusula']
        );
        self::assertStringContainsString('ÜCRET HESAP PUSULASI', $html);
        self::assertStringContainsString('*******8901', $html);
        self::assertStringContainsString('SGK-42', $html);
        self::assertStringNotContainsString('12345678901', $html);
    }
}
