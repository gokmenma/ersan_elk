<?php

use App\Model\BordroPersonelModel;
use App\Model\BordroParametreModel;
use PHPUnit\Framework\TestCase;

final class BordroBankaDagilimiTest extends TestCase
{
    private function setProperty(object $model, string $name, mixed $value): void
    {
        (new ReflectionProperty(BordroPersonelModel::class, $name))->setValue($model, $value);
    }

    public function testIhbarPrimiPuantajSayilmaz(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'isPuantajEkOdeme');
        foreach (['[Puantaj] Okuma', '[Sayaç] Değişim', '[Kaçak Kontrol] İşlem'] as $label) {
            self::assertTrue($method->invoke($model, $label));
        }
        self::assertFalse($method->invoke($model, '[Kaçak İhbar Primi] (6 adet x 100 ₺)'));
        self::assertFalse($method->invoke($model, 'Manuel prim'));
    }

    public function testEkranOrnegiVeBankaTavaniniAsanKesinti(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'hesaplaNormalBankaDagilimi');
        self::assertSame(['banka' => 26303.80, 'elden' => 2991.20],
            $method->invoke($model, 26203.80, 600, 500, 29295, 0, 0));
        self::assertSame(['banka' => 0.0, 'elden' => 1995.0],
            $method->invoke($model, 26203.80, 600, 27800, 1995, 0, 0));
        self::assertSame(['banka' => 24000.0, 'elden' => 0.0],
            $method->invoke($model, 26203.80, 600, 500, 25000, 800, 200));
    }

    public function testYemekGunlukTavaniVeYuvarlamaFarkiKorunur(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $params = $this->getMockBuilder(BordroParametreModel::class)->disableOriginalConstructor()
            ->onlyMethods(['getByKod', 'getGenelAyar'])->getMock();
        $params->method('getByKod')->willReturn(null);
        $params->method('getGenelAyar')->willReturn(0);
        $this->setProperty($model, 'cachedParametreModel', $params);
        $record = (object) ['yemek_yardimi_dahil' => 1, 'yemek_yardimi_tutari' => 300];
        $method = new ReflectionMethod($model, 'hesaplaMaasaDahilYardimDagilimi');
        $rounded = $method->invoke($model, $record, 28075.5, 30, 25, 0, 500, 0, 33000);
        self::assertEquals(197, $rounded['yemek_gunluk']);
        self::assertEquals(4925, $rounded['yemek_toplam']);
        self::assertEquals(0.5, $rounded['yuvarlama_farki']);
        $capped = $method->invoke($model, $record, 28075.5, 30, 25, 0, 500, 10000, 33000);
        self::assertEquals(300, $capped['yemek_gunluk']);
        self::assertEquals(7500, $capped['yemek_toplam']);
    }

    public function testKayitVeOrtakGosterimBankaSecimiVeKesintilerdeAyniSonucuVerir(): void
    {
        // HesaplaMaas gerçek hesap akışı çalışır; sadece veri kaynakları ve yazma sınırı sahtedir.
        foreach (['Net', 'Prim Usülü / Sabit Maaş'] as $maasTuru) {
            foreach ([1, 0] as $bankaSecimi) {
                foreach (['[Kaçak İhbar Primi] (6 adet x 100 ₺)', 'Manuel prim'] as $primAciklama) {
                    $this->assertKayitGosterim($maasTuru, $bankaSecimi, $primAciklama);
                }
            }
        }
    }

    public function testKesintiSinirlariVeManuelDagitimKorunur(): void
    {
        $this->assertKayitGosterim('Net', 1, 'Manuel prim', 0);
        $this->assertKayitGosterim('Net', 1, 'Manuel prim', 29000);
        $this->assertKayitGosterim('Net', 1, 'Manuel prim', 500, true);
        $this->assertKayitGosterim('Net', 1, 'Manuel prim', 500, false, true);
    }

    private function assertKayitGosterim(string $maasTuru, int $bankaSecimi, string $primAciklama, float $kesinti = 500, bool $eldenKesinti = false, bool $manuel = false): void
    {
        $record = (object) [
            'id' => 1, 'personel_id' => 1, 'donem_id' => 1,
            'baslangic_tarihi' => '2026-09-01', 'bitis_tarihi' => '2026-09-30',
            'ise_giris_tarihi' => '2020-01-01', 'isten_cikis_tarihi' => null,
            'maas_durumu' => $maasTuru, 'maas_tutari' => 30000,
            'yemek_yardimi_dahil' => 0, 'es_yardimi_dahil' => 0,
            'sodexo' => 0, 'sodexo_odemesi' => 0, 'diger_odeme' => 0,
            'guncel_toplam_kesinti' => $kesinti, 'sgk_yapilan_firma' => 'Firma',
            'dagitim_manuel' => $manuel ? 1 : 0, 'banka_odemesi' => 25000,
        ];
        $payments = [
            (object) ['id' => 1, 'tur' => 'prim', 'tutar' => 600, 'resmi_tutar' => 0, 'aciklama' => $primAciklama, 'banka_matrahina_ekle' => $bankaSecimi],
            (object) ['id' => 2, 'tur' => 'diger', 'tutar' => 1200, 'resmi_tutar' => 0, 'aciklama' => 'Diğer ödeme', 'banka_matrahina_ekle' => 0],
        ];
        $deductions = [(object) ['id' => 1, 'tur' => 'ozel_kesinti', 'tutar' => $kesinti, 'aciklama' => 'Özel kesinti', 'hesaplama_tipi' => $eldenKesinti ? 'elden_tutardan' : 'sabit']];
        $sources = [
            'getDonemEkOdemeleriListe', 'getDonemKesintileriListe', 'getHistoricalGorevGecmisi',
            'overrideWithHistoricalCalismaGecmisi', 'overrideWithHistoricalGorevGecmisi',
            'getUcretsizIzinGunuDirekt', 'getGunSayisiByKisaKod', 'getUcretliIzinGunu',
            'getPuantajXGunSayisi', 'getCalismaGunuSayisi', 'getOzelCalismaGunSayisi', 'getSgkFirmaDagilimi',
            'olusturSurekliKesintiler', 'olusturSurekliEkOdemeler', 'olusturPuantajOdemeleri',
            'olusturSayacDegisimOdemeleri', 'olusturNobetOdemeleri', 'olusturKacakKontrolPrimleri',
            'olusturKacakIhbarPrimleri', 'olusturAvansKesintileri', 'olusturIcraKesintileri',
            'olusturProfilBazliOdemeler', 'saveBordroHesaplama',
        ];
        $model = $this->getMockBuilder(BordroPersonelModel::class)->disableOriginalConstructor()->onlyMethods($sources)->getMock();
        $model->method('getDonemEkOdemeleriListe')->willReturn($payments);
        $model->method('getDonemKesintileriListe')->willReturn($deductions);
        foreach (['overrideWithHistoricalCalismaGecmisi', 'overrideWithHistoricalGorevGecmisi'] as $method) {
            $model->method($method)->willReturnCallback(fn ($p) => $p);
        }
        foreach (['getUcretsizIzinGunuDirekt', 'getGunSayisiByKisaKod', 'getUcretliIzinGunu', 'getOzelCalismaGunSayisi'] as $method) {
            $model->method($method)->willReturn(0);
        }
        $model->method('getPuantajXGunSayisi')->willReturn(26);
        $model->method('getCalismaGunuSayisi')->willReturn(30);
        $model->method('getSgkFirmaDagilimi')->willReturn(['non_kur_ratio' => 1.0]);
        $saved = null;
        $model->expects(self::once())->method('saveBordroHesaplama')->willReturnCallback(function ($id, $data) use (&$saved) {
            $saved = $data;
            return true;
        });
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function ($sql) use ($record, $maasTuru) {
            $stmt = $this->createMock(PDOStatement::class);
            $stmt->method('execute')->willReturn(true);
            $stmt->method('fetch')->willReturn(str_contains($sql, 'WHERE bp.id = ?') ? clone $record : false);
            $stmt->method('fetchAll')->willReturnCallback(function ($mode) use ($sql, $maasTuru) {
                if (str_contains($sql, 'FROM personel_gorev_gecmisi')) {
                    $row = ['maas_durumu' => $maasTuru, 'maas_tutari' => 30000, 'baslangic_tarihi' => '2026-09-01', 'bitis_tarihi' => '2026-09-30'];
                    return [$mode === PDO::FETCH_OBJ ? (object) $row : $row];
                }
                return [];
            });
            return $stmt;
        });
        $this->setProperty($model, 'db', $pdo);
        $params = [];
        foreach (['prim', 'diger'] as $code) {
            $params[$code] = (object) ['etiket' => $code, 'hesaplama_tipi' => 'net', 'odeme_yontemi' => 'elden', 'sgk_matrahi_dahil' => 0, 'gelir_vergisi_dahil' => 0, 'damga_vergisi_dahil' => 0];
        }
        $paramModel = $this->getMockBuilder(BordroParametreModel::class)->disableOriginalConstructor()->onlyMethods(['hesaplaGelirVergisi', 'hesaplaAsgariUcretGelirVergisiIstisnasi'])->getMock();
        $paramModel->method('hesaplaGelirVergisi')->willReturn(0.0);
        $paramModel->method('hesaplaAsgariUcretGelirVergisiIstisnasi')->willReturn(['aylik_matrah' => 0, 'istisna' => 0, 'toplam_matrah' => 0]);
        $this->setProperty($model, 'cachedParametreModel', $paramModel);
        $this->setProperty($model, 'genelAyarlarCache', ['asgari_ucret_net' => 28075.5]);
        $this->setProperty($model, 'parametrelerCache', $params);
        $this->setProperty($model, 'ekOdemelerCache', [1 => $payments]);

        self::assertTrue($model->hesaplaMaas(1));
        $display = $model->hesaplaOrtakGosterimDegerleri(clone $record, $record, 28075.5);
        $expectedBank = $manuel ? 25000.0 : max(0.0, 28075.5 + ($bankaSecimi ? 600 : 0) - ($eldenKesinti ? 0 : $kesinti));
        $expectedNet = 31800.0 - $kesinti;
        self::assertSame($expectedBank, $saved['banka_odemesi']);
        self::assertSame($expectedBank, $display['bankaOdemesi']);
        self::assertSame($expectedNet - $expectedBank, $saved['elden_odeme']);
        self::assertSame($saved['elden_odeme'], $display['eldenOdeme']);
        self::assertSame($expectedNet, $display['netAlacagi']);
        $detail = json_decode($saved['hesaplama_detay'], true);
        self::assertEquals($expectedBank, $detail['odeme_dagilimi']['banka_net']);
    }
}
