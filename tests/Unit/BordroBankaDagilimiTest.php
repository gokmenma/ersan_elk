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

    public function testMaasaDahilKesintiOnceBankadanSonraEldenDusulur(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'hesaplaDahilBankaDagilimi');

        self::assertSame([
            'banka' => 22111.35,
            'elden' => 188.65,
            'banka_kesintisi' => 15000.0,
            'elden_kesintisi' => 0.0,
        ], $method->invoke($model, 37300, 37111.35, 15000));

        self::assertSame([
            'banka' => 0.0,
            'elden' => 0.0,
            'banka_kesintisi' => 37111.35,
            'elden_kesintisi' => 188.65,
        ], $method->invoke($model, 37300, 37111.35, 40000));
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

    public function testKarmaMaastaKarttakiYemekIsaretiBankaPriminiVeKesintiyiEngellemez(): void
    {
        $this->assertKayitGosterim('Prim Usülü', 1, '[Kaçak İhbar Primi] (6 adet x 100 ₺)', 500, false, false, true);
        $this->assertKayitGosterim('Prim Usülü', 0, 'Manuel prim', 500, false, false, true);
    }

    public function testMuhasebedeBankaSeciliPrimTamamenGizlenirVeListeExcelTutarlidir(): void
    {
        $this->assertKayitGosterim('Net', 1, 'Manuel prim', 0, false, false, false, true, 3000);
        $this->assertKayitGosterim('Net', 1, 'Manuel prim', 0, false, false, false, true, 600);
        $this->assertKayitGosterim('Net', 0, 'Manuel prim', 0, false, false, false, true, 3000);
    }

    public function testHariciNakitYemekMuhasebeListesindeGosterilir(): void
    {
        $this->assertKayitGosterim('Net', 1, 'Manuel prim', 0, false, false, false, false, 600, true);
    }

    public function testMuhasebeMutabakatOzetiPrimVeOdemeKanallariniAyirir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $ozet = $model->getMuhasebeOdemeOzeti([
            'mealAllowanceDeduction' => 8100,
            'muhasebeHariciYemekTutari' => 0,
            'muhasebeBankaYemekTutari' => 0,
            'sodexoOdemesi' => 0,
            'includedAllowanceFiiliGun' => 27,
            'calismaGunu' => 30,
            'spouseAllowanceDeduction' => 0,
            'icraKesintisi' => 0,
            'asgariHakedis' => 28075.50,
            'bankaOdemesi' => 22111.35,
            'eldenOdeme' => 188.65,
            'digerOdeme' => 0,
            'muhasebePrimTutari' => 0,
            'muhasebePrimHakedisi' => 100,
            'muhasebeBankaPrimTutari' => 0,
            'muhasebeDagilimaDahilPrim' => 100,
            'toplamAlacagi' => 37300,
            'netAlacagi' => 22300,
            'bankaOncelikliKesinti' => 0,
            'bankaAktarilanKesinti' => 15000,
            'bankaEkOdemeDetaylari' => [
                ['etiket' => 'Resmi Tatil Çalışması (Net)', 'tutar' => 935.85],
                ['etiket' => 'Hafta Tatili Çalışması (Net)', 'tutar' => 935.85],
                ['etiket' => 'Fazla Mesai (Net)', 'tutar' => 250],
            ],
            'manualDagitimVar' => false,
        ]);

        self::assertSame(100.0, $ozet['prim_hakedisi_bilgi']);
        self::assertSame(100.0, $ozet['dagilima_dahil_prim_bilgi']);
        self::assertSame(0.0, $ozet['prim']);
        self::assertSame(8100.0, $ozet['resmi_yemek_yardimi']);
        self::assertSame(0.0, $ozet['resmi_prim_ikramiye']);
        self::assertSame(935.85, $ozet['resmi_rtc_net']);
        self::assertSame(935.85, $ozet['resmi_htc_net']);
        self::assertSame(250.0, $ozet['resmi_fazla_mesai_net']);
        self::assertSame(37111.35, $ozet['resmi_banka_matrahi']);
        self::assertSame(15000.0, $ozet['bankadan_dusulen_kesinti']);
        self::assertSame(22111.35, $ozet['banka_odemesi']);
        self::assertSame(188.65, $ozet['elden_odeme']);
        self::assertSame(22300.0, $ozet['dagitim_toplami']);
        self::assertSame(0.0, $ozet['banka_kontrol_farki']);
        self::assertSame(0.0, $ozet['dagitim_farki']);
    }

    private function assertKayitGosterim(string $maasTuru, int $bankaSecimi, string $primAciklama, float $kesinti = 500, bool $eldenKesinti = false, bool $manuel = false, bool $karma = false, bool $inclusive = false, float $primAmount = 600, bool $hariciYemek = false): void
    {
        $record = (object) [
            'id' => 1, 'personel_id' => 1, 'donem_id' => 1,
            'baslangic_tarihi' => '2026-09-01', 'bitis_tarihi' => '2026-09-30',
            'ise_giris_tarihi' => '2020-01-01', 'isten_cikis_tarihi' => null,
            'maas_durumu' => $maasTuru, 'maas_tutari' => $inclusive ? 33000 : 30000,
            'yemek_yardimi_dahil' => ($karma || $inclusive) ? 1 : 0, 'yemek_yardimi_tutari' => 300, 'es_yardimi_dahil' => 0,
            'sodexo' => 0, 'sodexo_odemesi' => 0, 'diger_odeme' => 0,
            'guncel_toplam_kesinti' => $kesinti, 'sgk_yapilan_firma' => 'Firma',
            'dagitim_manuel' => $manuel ? 1 : 0, 'banka_odemesi' => $manuel ? 25000 : 17800,
            'hesaplama_tarihi' => '2026-09-01 12:00:00',
        ];
        $payments = [
            (object) ['id' => 1, 'tur' => 'prim', 'tutar' => $primAmount, 'resmi_tutar' => 0, 'aciklama' => $primAciklama, 'banka_matrahina_ekle' => $bankaSecimi],
            (object) ['id' => 2, 'tur' => 'diger', 'tutar' => $inclusive ? 0 : 1200, 'resmi_tutar' => 0, 'aciklama' => 'Diğer ödeme', 'banka_matrahina_ekle' => 0],
        ];
        if ($hariciYemek) {
            $payments[] = (object) ['id' => 3, 'tur' => 'yemek_yardimi_tum', 'tutar' => 300, 'resmi_tutar' => 0, 'aciklama' => '[Yemek Yardımı] Günlük', 'banka_matrahina_ekle' => 1];
        }
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
        $pdo->method('prepare')->willReturnCallback(function ($sql) use ($record, $maasTuru, $karma, $inclusive) {
            $stmt = $this->createMock(PDOStatement::class);
            $stmt->method('execute')->willReturn(true);
            $stmt->method('fetch')->willReturn(str_contains($sql, 'WHERE bp.id = ?') ? clone $record : false);
            $stmt->method('fetchAll')->willReturnCallback(function ($mode) use ($sql, $maasTuru, $karma, $inclusive) {
                if (str_contains($sql, 'FROM personel_gorev_gecmisi')) {
                    if ($karma) {
                        $rows = [
                            ['maas_durumu' => 'Net', 'maas_tutari' => 60000, 'baslangic_tarihi' => '2026-09-01', 'bitis_tarihi' => '2026-09-15'],
                            ['maas_durumu' => 'Prim Usülü', 'maas_tutari' => 0, 'baslangic_tarihi' => '2026-09-16', 'bitis_tarihi' => '2026-09-30'],
                        ];
                        return array_map(fn ($row) => $mode === PDO::FETCH_OBJ ? (object) $row : $row, $rows);
                    }
                    $row = ['maas_durumu' => $maasTuru, 'maas_tutari' => $inclusive ? 33000 : 30000, 'baslangic_tarihi' => '2026-09-01', 'bitis_tarihi' => '2026-09-30'];
                    return [$mode === PDO::FETCH_OBJ ? (object) $row : $row];
                }
                return [];
            });
            return $stmt;
        });
        $this->setProperty($model, 'db', $pdo);
        $params = [];
        foreach (['prim', 'diger', 'yemek_yardimi_tum'] as $code) {
            $params[$code] = (object) ['etiket' => $code, 'hesaplama_tipi' => 'net', 'odeme_yontemi' => 'elden', 'sgk_matrahi_dahil' => 0, 'gelir_vergisi_dahil' => 0, 'damga_vergisi_dahil' => 0];
        }
        $paramModel = $this->getMockBuilder(BordroParametreModel::class)->disableOriginalConstructor()->onlyMethods(['getByKod', 'getGenelAyar', 'hesaplaGelirVergisi', 'hesaplaAsgariUcretGelirVergisiIstisnasi'])->getMock();
        $paramModel->method('getByKod')->willReturn(null);
        $paramModel->method('getGenelAyar')->willReturn(0);
        $paramModel->method('hesaplaGelirVergisi')->willReturn(0.0);
        $paramModel->method('hesaplaAsgariUcretGelirVergisiIstisnasi')->willReturn(['aylik_matrah' => 0, 'istisna' => 0, 'toplam_matrah' => 0]);
        $this->setProperty($model, 'cachedParametreModel', $paramModel);
        $this->setProperty($model, 'genelAyarlarCache', ['asgari_ucret_net' => 28075.5]);
        $this->setProperty($model, 'parametrelerCache', $params);
        $this->setProperty($model, 'ekOdemelerCache', [1 => $payments]);

        self::assertTrue($model->hesaplaMaas(1));
        $display = $model->hesaplaOrtakGosterimDegerleri(clone $record, $record, 28075.5);
        if ($karma) {
            self::assertTrue($display['karisikMaasGecmisi']);
            self::assertFalse($display['isInclusive']);
        }
        $expectedBank = $manuel ? 25000.0 : max(0.0, 28075.5 + ($bankaSecimi ? 600 : 0) + ($hariciYemek ? 300 : 0) - ($eldenKesinti ? 0 : $kesinti));
        $expectedNet = 31800.0 + ($hariciYemek ? 300 : 0) - $kesinti;
        if ($inclusive) {
            $meal = $bankaSecimi ? ($primAmount === 3000.0 ? 7800.0 : 5538.0) : 4940.0;
            $expectedBank = 28075.5 + $meal;
            $expectedNet = 33000.0 + $primAmount + ($bankaSecimi && $primAmount === 3000.0 ? 0.0 : ($bankaSecimi ? 13.5 : 15.5));
            self::assertEquals($meal, $display['mealAllowanceDeduction']);
            self::assertEquals($bankaSecimi ? 0.0 : $primAmount, $display['muhasebePrimTutari']);
        }
        $excel = $model->getMuhasebeOdemeOzeti($display);
        self::assertSame($expectedBank, $excel['net_maas']);
        self::assertEquals(
            $display['mealAllowanceDeduction'] + ($hariciYemek ? 300 : 0),
            $excel['nakit_yemek']
        );
        if ($hariciYemek) {
            self::assertSame(300.0, $display['muhasebeHariciYemekTutari']);
            self::assertSame(300.0, $display['muhasebeBankaYemekTutari']);
            self::assertSame(300.0, $excel['resmi_yemek_yardimi']);
            self::assertSame(round(300 / $display['calismaGunu'], 2), $excel['gunluk_nakit']);
        }
        self::assertSame(
            (!$inclusive && $bankaSecimi ? $primAmount : 0.0),
            $excel['resmi_prim_ikramiye']
        );
        self::assertSame($display['muhasebePrimTutari'], $excel['prim']);
        self::assertSame(0.0, $excel['banka_kontrol_farki']);
        self::assertSame(0.0, $excel['dagitim_farki']);
        if (!$inclusive) {
            self::assertSame($primAmount, $excel['prim']);
        }
        self::assertSame($expectedBank, $saved['banka_odemesi']);
        self::assertSame($expectedBank, $display['bankaOdemesi']);
        self::assertSame($expectedNet - $expectedBank, $saved['elden_odeme']);
        self::assertSame($saved['elden_odeme'], $display['eldenOdeme']);
        self::assertSame($expectedNet, $display['netAlacagi']);
        $detail = json_decode($saved['hesaplama_detay'], true);
        self::assertEquals($expectedBank, $detail['odeme_dagilimi']['banka_net']);
    }
}
