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

    public function testResmiManuelEkOdemeKullaniciSecimindenBagimsizBankayaAlinir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'resolveEkOdemeYontemi');

        self::assertSame('banka', $method->invoke($model, 0, 'elden', true, false, false));
        self::assertSame('elden', $method->invoke($model, 0, 'elden', true, true, false));
        self::assertSame('elden', $method->invoke($model, 0, 'elden', false, false, false));
    }

    public function testPrimUsuluEkCalismaAsgariTabanIcindeEritilmez(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'hesaplaPrimUsuluTabanliHakedis');

        self::assertSame(5179.25, $method->invoke($model, 4120, 500, 4679.25));
        self::assertSame(5500.0, $method->invoke($model, 5000, 500, 4679.25));
        self::assertSame(4679.25, $method->invoke($model, 4120, 0, 4679.25));
    }

    public function testYukseltilmisNobetNetiHamTutaraGeriDonmez(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'ekOdemeUygulananNet');

        self::assertSame(748.68, $method->invoke($model, [
            'tutar' => 500,
            'net_etki' => 748.68,
            'resmi_banka_net' => 748.68,
        ]));
        self::assertSame(500.0, $method->invoke($model, ['tutar' => 500]));
    }

    public function testMuhasebeHedefiHesaplanirAmaEskiSnapshotBankaSatiriKorunur(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $bordro = (object) [
            'personel_id' => 10,
            'donem_id' => 26,
            'hesaplama_tarihi' => '2026-10-09 14:48:00',
            'hesaplama_detay' => json_encode([
                'parametreler' => ['asgari_ucret_net' => 28075.50],
                'ek_odemeler' => [[
                    'kod' => 'hafta_ici_nobet',
                    'aciklama' => '[Nöbet] Hafta İçi (1 Gün x 4 Saat x 500,00 ₺)',
                    'tutar' => 1500.00,
                    'net_etki' => 1500.00,
                ]],
            ], JSON_UNESCAPED_UNICODE),
        ];
        $donem = (object) ['baslangic_tarihi' => '2026-09-01'];

        $ozet = $model->getMuhasebeFazlaMesaiNetOzeti($bordro, $donem);

        self::assertSame('Fazla Mesai Ücreti (Net)', $ozet['etiket']);
        self::assertSame(748.68, $ozet['tutar']);
        self::assertSame(4, $ozet['saat']);
        self::assertTrue($ozet['snapshot']);

        $bankaDetayi = $model->getBankaOdemeDetayDagilimi([
            'bankaOdemesi' => 25680.78,
            'bankaOncelikliKesinti' => 0,
            'bankaAktarilanKesinti' => 6000,
            'asgariYatacak' => 24332.10,
            'bankaAsgariGun' => 26,
            'calismaGunu' => 26,
            'spouseAllowanceDeduction' => 0,
            'mealAllowanceDeduction' => 6600,
            'sozlesmeHakedisi' => 0,
            'bankaEkOdemeDetaylari' => [[
                'etiket' => 'Hafta İçi Nöbet',
                'tutar' => 500,
                'resmi_alacagina_dahil' => true,
            ]],
            'tumEkOdemeDetaylari' => [],
            'bankaKesintiKalemleri' => [],
        ], $ozet);

        self::assertSame(25680.78, $bankaDetayi['net_banka']);
        self::assertSame(31680.78, $bankaDetayi['brut_banka']);
        self::assertSame('Hafta İçi Nöbet', $bankaDetayi['ek_odemeler'][0]['etiket']);
        self::assertSame(500.0, $bankaDetayi['ek_odemeler'][0]['tutar']);
    }

    public function testResmiManuelPrimMaasaDahilYardimdaDogrudanBankaKalemidir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'resolveEkOdemeYontemi');

        self::assertSame('banka', $method->invoke($model, 1, 'diger', true, false, false));
    }

    public function testSnapshotBankaSecimiResmiOlmayanNobetiResmiDetayaTasimasin(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'isSnapshotResmiAlacakKalemi');
        $parametre = (object) ['resmi_alacagina_dahil' => 0];

        self::assertFalse($method->invoke($model, [
            'banka_matrahina_ekle' => 1,
            'resmi_alacagina_dahil' => 0,
        ], 'hafta_sonu_nobet', $parametre));

        self::assertTrue($method->invoke($model, [
            'banka_matrahina_ekle' => 1,
            'resmi_alacagina_dahil' => 1,
        ], 'hafta_ici_nobet', $parametre));

        self::assertTrue($method->invoke($model, [
            'banka_matrahina_ekle' => 1,
            'resmi_alacagina_dahil' => 0,
        ], 'mesai', $parametre));

        self::assertFalse($method->invoke($model, [
            'banka_matrahina_ekle' => 1,
            'resmi_alacagina_dahil' => 1,
        ], 'yemek_yardimi_dengeleme', (object) ['resmi_alacagina_dahil' => 1]));
    }

    public function testSgkFirmaDagilimiIseGirisOncesiniSaymazVeEksikGunleriGercekFirmadanDuser(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $pdo->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['ise_giris_tarihi' => '2026-08-11', 'isten_cikis_tarihi' => '2026-08-25', 'sgk_yapilan_firma' => 'İŞKUR'],
            ['ise_giris_tarihi' => '2026-08-26', 'isten_cikis_tarihi' => null, 'sgk_yapilan_firma' => 'Ersan Elektrik'],
        ]);
        $this->setProperty($model, 'db', $pdo);

        $dagilim = $model->getSgkFirmaDagilimi(
            1,
            '2026-08-01',
            '2026-08-31',
            'Ersan Elektrik',
            '2026-08-11',
            null,
            ['2026-08-20' => true, '2026-08-27' => true]
        );

        self::assertSame(15, $dagilim['kur_days']);
        self::assertSame(6, $dagilim['non_kur_days']);
        self::assertSame(21, $dagilim['total_days']);
        self::assertSame(14, $dagilim['payable_kur_days']);
        self::assertSame(5, $dagilim['payable_non_kur_days']);
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
        ], $method->invoke($model, 37300, 37111.35, 15000, 0));

        self::assertSame([
            'banka' => 0.0,
            'elden' => 0.0,
            'banka_kesintisi' => 37111.35,
            'elden_kesintisi' => 188.65,
        ], $method->invoke($model, 37300, 37111.35, 40000, 0));
    }

    public function testResmiOlmayanEkOdemelerBankaMatrahiniAsmazKalanEldenOdenir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'hesaplaDahilBankaDagilimi');

        // Net hakediş 17.300; resmî matrah yalnız asgari 13.101,90 + yemek 3.600.
        // Diğer gelir 700 + araç kirası 1.200 toplam hakedişte kalır fakat resmî matraha girmez.
        self::assertSame([
            'banka' => 10701.90,
            'elden' => 598.10,
            'banka_kesintisi' => 6000.0,
            'elden_kesintisi' => 0.0,
        ], $method->invoke($model, 17300, 16701.90, 6000, 0));
    }

    public function testBankaDetayindaDogrudanPayOnceResmiKalemeYazilir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $result = $model->getBankaOdemeDetayDagilimi([
            'bankaOdemesi' => 30000,
            'bankaOncelikliKesinti' => 0,
            'bankaAktarilanKesinti' => 0,
            'asgariYatacak' => 23396.25,
            'bankaAsgariGun' => 25,
            'mealAllowanceDeduction' => 6300,
            'spouseAllowanceDeduction' => 0,
            'bankaEkOdemeDetaylari' => [
                ['etiket' => 'Aylık Araç Kirası', 'tutar' => 2100, 'resmi_alacagina_dahil' => false],
                ['etiket' => 'Olumlu Kaçak İhbar Primi', 'tutar' => 400, 'resmi_alacagina_dahil' => true],
            ],
        ]);

        self::assertSame('Olumlu Kaçak İhbar Primi', $result['ek_odemeler'][0]['etiket']);
        self::assertSame(400.0, $result['ek_odemeler'][0]['tutar']);
        self::assertSame(0.0, $result['ek_odemeler'][0]['yemek_dahil_tutar']);
        self::assertSame('Aylık Araç Kirası', $result['ek_odemeler'][1]['etiket']);
        self::assertSame(0.0, $result['ek_odemeler'][1]['tutar']);
        self::assertSame(2100.0, $result['ek_odemeler'][1]['yemek_dahil_tutar']);
        self::assertSame(6203.75, $result['yemek']);
        self::assertSame(30000.0, $result['brut_banka']);
    }

    public function testBankaDetayiHtcPayiniYemektenAyirarakMuhasebeExceliyleEsitler(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $detay = $model->getBankaOdemeDetayDagilimi([
            'bankaOdemesi' => 14850.05,
            'bankaOncelikliKesinti' => 0,
            'bankaAktarilanKesinti' => 0,
            'asgariYatacak' => 12166.05,
            'bankaAsgariGun' => 13,
            'mealAllowanceDeduction' => 2684.0,
            'spouseAllowanceDeduction' => 0,
            'bankaEkOdemeDetaylari' => [[
                'etiket' => 'Hafta Tatili Çalışması (Net)',
                'tutar' => 935.85,
                'resmi_alacagina_dahil' => true,
            ]],
        ]);

        self::assertSame(12166.05, $detay['asgari']);
        self::assertSame(1748.15, $detay['yemek']);
        self::assertSame(935.85, $detay['ek_odemeler'][0]['tutar']);
        self::assertSame(0.0, $detay['ek_odemeler'][0]['yemek_dahil_tutar']);
        self::assertSame(14850.05, round(
            $detay['asgari']
            + $detay['yemek']
            + array_sum(array_column($detay['ek_odemeler'], 'tutar')),
            2
        ));
        self::assertSame(14850.05, $detay['brut_banka']);
    }

    public function testBankaDetayiHtcPayiniNobettenOnceAyirir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $detay = $model->getBankaOdemeDetayDagilimi([
            'bankaOdemesi' => 12120.0,
            'bankaOncelikliKesinti' => 0,
            'bankaAktarilanKesinti' => 0,
            'asgariYatacak' => 10294.35,
            'mealAllowanceDeduction' => 891.0,
            'spouseAllowanceDeduction' => 0,
            'bankaEkOdemeDetaylari' => [
                ['etiket' => 'Hafta Sonu Nöbet', 'tutar' => 1000.0, 'resmi_alacagina_dahil' => true],
                ['etiket' => 'Hafta Tatili Çalışması (Net)', 'tutar' => 935.85, 'resmi_alacagina_dahil' => true],
            ],
        ]);

        self::assertSame('Hafta Tatili Çalışması (Net)', $detay['ek_odemeler'][0]['etiket']);
        self::assertSame(935.85, $detay['ek_odemeler'][0]['tutar']);
        self::assertSame('Hafta Sonu Nöbet', $detay['ek_odemeler'][1]['etiket']);
        self::assertSame(889.8, $detay['ek_odemeler'][1]['tutar']);
        self::assertSame(0.0, $detay['yemek']);
    }

    public function testMuhasebeResmiKalemDagilimiTekMerkezdenOncelikliVeMutabikUretilir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $dagilim = $model->getMuhasebeResmiKalemDagilimi([
            'muhasebe_resmi_banka_matrahi' => 14850.05,
            'resmi_alacak_asgari' => 12166.05,
            'es_yardimi' => 0,
            'resmi_rtc_net' => 0,
            'resmi_htc_net' => 935.85,
            'muhasebe_fazla_mesai_net' => 0,
            'resmi_prim_ikramiye' => 0,
            'resmi_yemek_yardimi' => 2684,
        ]);

        self::assertSame([
            'asgari' => 12166.05,
            'es_yardimi' => 0.0,
            'rtc' => 0.0,
            'htc' => 935.85,
            'fazla_mesai' => 0.0,
            'prim' => 0.0,
            'yemek' => 1748.15,
            'diger' => 0.0,
            'toplam' => 14850.05,
        ], $dagilim);
    }

    public function testMuhasebeExceliMerkeziResmiKalemDagiliminiKullanir(): void
    {
        $source = file_get_contents(__DIR__ . '/../../views/bordro/excel-yemek-export.php');

        self::assertIsString($source);
        self::assertStringContainsString('getMuhasebeResmiKalemDagilimi($veri)', $source);
        self::assertStringNotContainsString('$resmiEklenenPayi', $source);
    }

    public function testDahilMaasKesintileriSecilenKanaldanOncelikliDuser(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'hesaplaDahilBankaDagilimi');

        // Toplam Hakediş: 37300, Banka Matrahı: 37111.35, Elden Matrahı: 188.65
        // Elden işaretli kesinti önce elden hakedişten düşer.
        self::assertSame([
            'banka' => 37111.35,
            'elden' => 88.65,
            'banka_kesintisi' => 0.0,
            'elden_kesintisi' => 100.0,
        ], $method->invoke($model, 37300, 37111.35, 0, 100));

        // Banka matrahını aşan bölüm ancak bu durumda elden hakedişten düşer.
        self::assertSame([
            'banka' => 0.0,
            'elden' => 0.0,
            'banka_kesintisi' => 37111.35,
            'elden_kesintisi' => 188.65,
        ], $method->invoke($model, 37300, 37111.35, 0, 40000));

        self::assertSame([
            'banka' => 16706.94,
            'elden' => 1093.56,
            'banka_kesintisi' => 13019.50,
            'elden_kesintisi' => 0.0,
        ], $method->invoke($model, 30820, 29726.44, 13019.50, 0));
    }

    public function testNormalMaasEldenKesintiOncelikliEldenDuserKalanBankadanDuser(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'hesaplaNormalBankaDagilimi');

        // Asgari taban: 28075.50, Banka ekleri: 0 -> Banka matrahı: 28075.50
        // Toplam net alacağı: 35000 (kesinti sonrası: 33000)
        // 1. Elden kesinti 2000 TL: Önceden elden matrahı: 35000 - 28075.50 = 6924.50.
        // Kesinti 2000 TL elden'den düşer -> Kalan elden: 4924.50, Kalan banka: 28075.50
        self::assertSame([
            'banka' => 28075.50,
            'elden' => 4924.50,
        ], $method->invoke($model, 28075.50, 0, 0, 33000, 0, 0, 2000));

        // 2. Elden kesinti 8000 TL: Elden matrahı 6924.50 sıfırlanır, kalan 1075.50 bankadan düşer.
        // Kalan banka: 28075.50 - 1075.50 = 27000.00, Kalan elden: 0
        self::assertSame([
            'banka' => 27000.00,
            'elden' => 0.0,
        ], $method->invoke($model, 28075.50, 0, 0, 27000, 0, 0, 8000));

        // 3. Banka kesinti 2000 TL (Elden kesinti: 0): Bankadan düşer -> Banka: 26075.50, Elden: 6924.50
        self::assertSame([
            'banka' => 26075.50,
            'elden' => 6924.50,
        ], $method->invoke($model, 28075.50, 0, 2000, 33000, 0, 0, 0));
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

    public function testBankaMatrahinaDahilEkCalismaFarkiOnceYemekTavaniniDoldurur(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $params = $this->getMockBuilder(BordroParametreModel::class)->disableOriginalConstructor()
            ->onlyMethods(['getByKod', 'getGenelAyar'])->getMock();
        $params->method('getByKod')->willReturn(null);
        $params->method('getGenelAyar')->willReturn(0);
        $this->setProperty($model, 'cachedParametreModel', $params);
        $record = (object) ['yemek_yardimi_dahil' => 1, 'yemek_yardimi_tutari' => 300];
        $method = new ReflectionMethod($model, 'hesaplaMaasaDahilYardimDagilimi');

        $normal = $method->invoke($model, $record, 28075.50, 8, 7, 0, 0, 0, 7486.80, 0, 0);
        $ekCalismali = $method->invoke($model, $record, 28075.50, 8, 7, 0, 0, 0, 7486.80, 1184.53, 684.53);

        self::assertSame(0.0, $normal['yemek_toplam']);
        self::assertSame(686.0, $ekCalismali['yemek_toplam']);
        self::assertSame(98.0, $ekCalismali['yemek_gunluk']);

        $murataBenzer = $method->invoke($model, $record, 28075.50, 20, 17, 0, 0, 0, 22000, 0, 164.15);
        self::assertSame(3451.0, $murataBenzer['yemek_toplam']);
        self::assertSame(203.0, $murataBenzer['yemek_gunluk']);
    }

    public function testHaftaTatiliResmiNetiDigerFazlaCalismadanBagimsizGarantiEdilir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'garantiResmiOzelCalismaNeti');

        self::assertSame(1871.70, $method->invoke($model, 935.85, 935.85, 935.85));
        self::assertSame(1871.70, $method->invoke($model, 1871.70, 935.85, 935.85));
    }

    public function testOtomatikDagitimdaEksikBakiyeEldenOdemeyeTamamlanir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'eksikEldenOdemeTamamlama');

        self::assertSame(4068.0, $method->invoke($model, 30000, 24832, 0, 0, 0, 1100));
        self::assertSame(0.0, $method->invoke($model, 23115, 22950.85, 164.15, 0, 0, 0));
    }

    public function testEskiSnapshotMuhasebeHedefiyleEkrandaDegistirilmez(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $detay = $model->getBankaOdemeDetayDagilimi([
            'bankaOdemesi' => 10522.65,
            'bankaOncelikliKesinti' => 0,
            'bankaAktarilanKesinti' => 0,
            'asgariYatacak' => 7486.80,
            'mealAllowanceDeduction' => 2100,
            'spouseAllowanceDeduction' => 0,
            'bankaEkOdemeDetaylari' => [
                ['etiket' => 'Hafta İçi Nöbet', 'tutar' => 500],
                ['etiket' => 'Hafta Tatili Çalışması (Net)', 'tutar' => 435.85],
            ],
        ], ['tutar' => 748.68, 'etiket' => 'Fazla Mesai Ücreti (Net)']);

        self::assertSame('Hafta Tatili Çalışması (Net)', $detay['ek_odemeler'][0]['etiket']);
        self::assertSame(435.85, $detay['ek_odemeler'][0]['tutar']);
        self::assertSame('Hafta İçi Nöbet', $detay['ek_odemeler'][1]['etiket']);
        self::assertSame(500.0, $detay['ek_odemeler'][1]['tutar']);
        self::assertSame($detay['brut_banka'], round(
            $detay['asgari'] + $detay['yemek'] + $detay['es_yardimi']
            + array_sum(array_column($detay['ek_odemeler'], 'tutar'))
            + $detay['sozlesme_farki'],
            2
        ));
    }

    public function testMaasaDahilYardimdaYemekEsYardimindanOnceKullanilir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $params = $this->getMockBuilder(BordroParametreModel::class)->disableOriginalConstructor()
            ->onlyMethods(['getByKod', 'getGenelAyar'])->getMock();
        $params->method('getByKod')->willReturn(null);
        $params->method('getGenelAyar')->willReturn(0);
        $this->setProperty($model, 'cachedParametreModel', $params);
        $record = (object) [
            'yemek_yardimi_dahil' => 1,
            'yemek_yardimi_tutari' => 300,
            'es_yardimi_dahil' => 1,
            'es_yardimi_tutari' => 3300,
        ];
        $method = new ReflectionMethod($model, 'hesaplaMaasaDahilYardimDagilimi');
        $result = $method->invoke($model, $record, 28075.5, 30, 20, 0, 0, 0, 35000);

        self::assertSame(6000.0, $result['yemek_toplam']);
        self::assertSame(924.5, $result['es_toplam']);
        self::assertEquals(0.0, $result['kalan_fark']);
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

    public function testDisaridanSigortaliOlmakBankaOdemesiniSifirlamaz(): void
    {
        $this->assertKayitGosterim(
            'Net',
            1,
            'Manuel prim',
            disardanSigortali: true
        );
    }

    public function testHesaplanmisSatirKayitliSnapshotDegerleriniKullanir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $record = (object) [
            'hesaplama_tarihi' => '2026-09-30 12:00:00',
            'hesaplama_detay' => json_encode([
                'maas_durumu' => 'Net',
                'matrahlar' => ['nominal_maas' => 30000, 'maas_hesap_gunu' => 19],
                'odeme_dagilimi' => ['icra_kesintisi' => 0, 'banka_net' => 20901.15, 'elden' => 0, 'sodexo' => 0],
                'ozet' => [],
                'parametreler' => ['asgari_ucret_net' => 28075.50],
            ], JSON_UNESCAPED_UNICODE),
            'net_maas' => 20901.15, 'kesinti_tutar' => 0,
            'banka_odemesi' => 20901.15, 'sodexo_odemesi' => 0,
            'diger_odeme' => 0, 'elden_odeme' => 0, 'prim_tutar' => 0,
        ];

        $hesap = $model->hesaplaOrtakGosterimDegerleri($record, null, 99999);

        self::assertTrue($hesap['hesaplanmis']);
        self::assertSame('kayitli', $hesap['gosterimKaynagi']);
        self::assertSame(20901.15, $hesap['bankaOdemesi']);
        self::assertSame(19, $hesap['calismaGunu']);
    }

    public function testKapanmisBordronunKayitliOdemeDagilimiGosterimdeYenidenDengelenmez(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $record = (object) [
            'hesaplama_tarihi' => '2026-01-31 18:00:00',
            'hesaplama_detay' => json_encode([
                'maas_durumu' => 'Net',
                'matrahlar' => ['nominal_maas' => 30000, 'maas_hesap_gunu' => 30],
                'odeme_dagilimi' => ['icra_kesintisi' => 0, 'banka_net' => 30000, 'elden' => 1250, 'sodexo' => 0],
                'ozet' => ['dahil_yemek_yardimi' => 1000],
                'parametreler' => ['asgari_ucret_net' => 20000],
                'ek_odemeler' => [],
                'kesintiler' => [],
            ], JSON_UNESCAPED_UNICODE),
            'net_maas' => 31250, 'kesinti_tutar' => 0,
            'banka_odemesi' => 30000, 'sodexo_odemesi' => 0,
            'diger_odeme' => 0, 'elden_odeme' => 1250, 'prim_tutar' => 0,
            'dagitim_manuel' => 1,
        ];

        $hesap = $model->hesaplaOrtakGosterimDegerleri($record, null, 99999);

        self::assertSame('kayitli', $hesap['gosterimKaynagi']);
        self::assertSame(30000.0, $hesap['bankaOdemesi']);
        self::assertSame(1250.0, $hesap['eldenOdeme']);
    }

    public function testHesaplanmamisSatirCanliHesaplanmaz(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $hesap = $model->hesaplaOrtakGosterimDegerleri((object) [
            'hesaplama_tarihi' => null,
            'maas_tutari' => 50000,
        ], null, 28075.50);

        self::assertFalse($hesap['hesaplanmis']);
        self::assertSame('hesaplanmadi', $hesap['gosterimKaynagi']);
        self::assertSame(0.0, $hesap['toplamAlacagi']);
        self::assertSame(0.0, $hesap['bankaOdemesi']);
    }

    public function testBrutUcretteKaydedilenNetHakedisBankaDagilimindaKullanilir(): void
    {
        $this->assertKayitGosterim('Brüt', 0, 'Manuel prim', 0);
    }

    public function testTekYemekParametreliKarmaMaastaYemekHesaplanir(): void
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
            'ucretliIzinGunu' => 2,
            'ucretsizIzinGunu' => 1,
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
            'bankaKesintiKalemleri' => [
                ['kod' => 'icra', 'etiket' => 'İcra', 'tutar' => 9000],
                ['kod' => 'diger_kesinti', 'etiket' => 'Diğer Kesinti', 'tutar' => 2500],
            ],
            'bankaEkOdemeDetaylari' => [
                ['etiket' => 'Resmi Tatil Çalışması (Net)', 'tutar' => 935.85],
                ['etiket' => 'Hafta Tatili Çalışması (Net)', 'tutar' => 935.85],
                ['etiket' => 'Fazla Mesai (Net)', 'tutar' => 250],
            ],
            'manualDagitimVar' => false,
        ]);

        self::assertSame(100.0, $ozet['prim_hakedisi_bilgi']);
        self::assertSame(30, $ozet['toplam_gun']);
        self::assertSame(27, $ozet['fiili_gun']);
        self::assertSame(3, $ozet['izin_gun']);
        self::assertSame(100.0, $ozet['dagilima_dahil_prim_bilgi']);
        self::assertSame(0.0, $ozet['prim']);
        self::assertSame(8100.0, $ozet['resmi_yemek_yardimi']);
        self::assertSame(0.0, $ozet['resmi_prim_ikramiye']);
        self::assertSame(935.85, $ozet['resmi_rtc_net']);
        self::assertSame(935.85, $ozet['resmi_htc_net']);
        self::assertSame(250.0, $ozet['resmi_fazla_mesai_net']);
        self::assertSame(37111.35, $ozet['resmi_banka_matrahi']);
        self::assertSame(15000.0, $ozet['bankadan_dusulen_kesinti']);
        self::assertSame(0.0, $ozet['muhasebe_banka_avans']);
        self::assertSame(9000.0, $ozet['muhasebe_banka_icra']);
        self::assertSame(2500.0, $ozet['muhasebe_banka_diger_kesinti']);
        self::assertSame(11500.0, $ozet['muhasebe_bankadan_dusulecek']);
        self::assertSame(33611.35, $ozet['muhasebe_resmi_banka_matrahi']);
        self::assertSame(22111.35, $ozet['banka_odemesi']);
        self::assertSame(188.65, $ozet['elden_odeme']);
        self::assertSame(22300.0, $ozet['dagitim_toplami']);
        self::assertSame(0.0, $ozet['banka_kontrol_farki']);
        self::assertSame(0.0, $ozet['dagitim_farki']);
    }

    public function testBankaDetayindaAyriGosterilenPrimYemekteIkinciKezSayilmaz(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $detay = $model->getBankaOdemeDetayDagilimi([
            'bankaOdemesi' => 33115.65,
            'bankaOncelikliKesinti' => 0,
            'bankaAktarilanKesinti' => 0,
            'asgariYatacak' => 27139.65,
            'mealAllowanceDeduction' => 5976,
            'spouseAllowanceDeduction' => 0,
            'bankaEkOdemeDetaylari' => [
                ['etiket' => 'Olumlu Kaçak İhbar Primi', 'tutar' => 1200],
            ],
        ]);

        self::assertSame(27139.65, $detay['asgari']);
        self::assertSame(5976.0, $detay['yemek']);
        self::assertSame(0.0, $detay['ek_odemeler'][0]['tutar']);
        self::assertSame(1200.0, $detay['ek_odemeler'][0]['yemek_dahil_tutar']);
        self::assertSame(0.0, $detay['sozlesme_farki']);
        self::assertSame(33115.65, round(
            $detay['asgari'] + $detay['yemek'] + $detay['ek_odemeler'][0]['tutar'],
            2
        ));
    }

    public function testBankaDetayindakiAciklanmayanBakiyeSozlesmeFarkidir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $detay = $model->getBankaOdemeDetayDagilimi([
            'bankaOdemesi' => 36000,
            'bankaOncelikliKesinti' => 0,
            'bankaAktarilanKesinti' => 0,
            'asgariYatacak' => 28075.50,
            'mealAllowanceDeduction' => 7263,
            'spouseAllowanceDeduction' => 0,
            'bankaEkOdemeDetaylari' => [],
        ]);

        self::assertSame(661.5, $detay['sozlesme_farki']);
        self::assertSame(36000.0, round(
            $detay['asgari'] + $detay['yemek'] + $detay['sozlesme_farki'],
            2
        ));
    }

    public function testBankaDetayindaAvansAsgariUcretSatiriniKucultmez(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $detay = $model->getBankaOdemeDetayDagilimi([
            'bankaOdemesi' => 20760.40,
            'bankaOncelikliKesinti' => 0,
            'bankaAktarilanKesinti' => 8000,
            'asgariYatacak' => 21524.55,
            'bankaAsgariGun' => 23,
            'mealAllowanceDeduction' => 3000,
            'spouseAllowanceDeduction' => 3300,
            'bankaEkOdemeDetaylari' => [
                ['etiket' => 'Hafta Tatili Çalışması (Net)', 'tutar' => 935.85],
            ],
            'bankaKesintiKalemleri' => [
                ['etiket' => 'Avans', 'tutar' => 8000],
            ],
        ]);

        self::assertSame(21524.55, $detay['asgari']);
        self::assertSame(23, $detay['asgari_gun']);
        self::assertSame(935.85, $detay['asgari_gunluk']);
        self::assertSame(['etiket' => 'Avans', 'tutar' => 8000.0], $detay['kesinti_kalemleri'][0]);
        self::assertSame(20760.40, round(
            $detay['asgari']
            + $detay['yemek']
            + $detay['es_yardimi']
            + array_sum(array_column($detay['ek_odemeler'], 'tutar'))
            + $detay['sozlesme_farki']
            - $detay['kesinti']
            - $detay['matrah_siniri'],
            2
        ));
    }

    public function testBankaKesintisiAciklamasiDetayDagilimindaKorunur(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $detay = $model->getBankaOdemeDetayDagilimi([
            'bankaOdemesi' => 900,
            'bankaOncelikliKesinti' => 0,
            'bankaAktarilanKesinti' => 100,
            'asgariYatacak' => 1000,
            'mealAllowanceDeduction' => 0,
            'spouseAllowanceDeduction' => 0,
            'bankaKesintiKalemleri' => [[
                'etiket' => 'Diğer Kesinti',
                'tutar' => 100,
                'aciklama' => 'Kırılan ekipman bedeli',
            ]],
        ]);

        self::assertSame('Kırılan ekipman bedeli', $detay['kesinti_kalemleri'][0]['aciklama']);
    }

    public function testKesintiTuruyleCelisenIcraAciklamasiGizlenir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'normalizeKesintiAciklamasi');

        self::assertSame('', $method->invoke($model, 'avans', '[İcra] ANKARA BANKA ALACAKLARI'));
        self::assertSame('[İcra] ANKARA BANKA ALACAKLARI', $method->invoke($model, 'icra', '[İcra] ANKARA BANKA ALACAKLARI'));
        self::assertSame('26.09.2026 ilaç alacağı', $method->invoke($model, 'avans', '26.09.2026 ilaç alacağı'));
    }

    public function testBankaDetayiFazlaMesaiVeHaftaTatiliniSozlesmeFarkinaDonusturmez(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $detay = $model->getBankaOdemeDetayDagilimi([
            'bankaOdemesi' => 38311.35,
            'bankaOncelikliKesinti' => 0,
            'bankaAktarilanKesinti' => 0,
            'asgariYatacak' => 28075.50,
            'mealAllowanceDeduction' => 8100,
            'spouseAllowanceDeduction' => 0,
            'bankaEkOdemeDetaylari' => [
                ['etiket' => 'Fazla Mesai', 'tutar' => 1200],
                ['etiket' => 'Hafta Tatili Çalışması (Net)', 'tutar' => 935.85],
                ['etiket' => 'Prim', 'tutar' => 600],
            ],
        ]);

        self::assertSame('Hafta Tatili Çalışması (Net)', $detay['ek_odemeler'][0]['etiket']);
        self::assertSame(935.85, $detay['ek_odemeler'][0]['tutar']);
        self::assertSame('Fazla Mesai', $detay['ek_odemeler'][1]['etiket']);
        self::assertSame(1200.0, $detay['ek_odemeler'][1]['tutar']);
        self::assertSame(0.0, $detay['ek_odemeler'][2]['tutar']);
        self::assertSame(600.0, $detay['ek_odemeler'][2]['yemek_dahil_tutar']);
        self::assertSame(0.0, $detay['sozlesme_farki']);
    }

    private function assertKayitGosterim(string $maasTuru, int $bankaSecimi, string $primAciklama, float $kesinti = 500, bool $eldenKesinti = false, bool $manuel = false, bool $karma = false, bool $inclusive = false, float $primAmount = 600, bool $hariciYemek = false, bool $disardanSigortali = false, bool $nobet = false): void
    {
        $isKacakIhbarPrimi = str_starts_with($primAciklama, '[Kaçak İhbar Primi]');
        $record = (object) [
            'id' => 1, 'personel_id' => 1, 'donem_id' => 1,
            'baslangic_tarihi' => '2026-09-01', 'bitis_tarihi' => '2026-09-30',
            'ise_giris_tarihi' => '2020-01-01', 'isten_cikis_tarihi' => null,
            'maas_durumu' => $maasTuru, 'maas_tutari' => $inclusive ? 33000 : 30000,
            'yemek_yardimi_dahil' => ($karma || $inclusive) ? 1 : 0, 'yemek_yardimi_tutari' => 300, 'es_yardimi_dahil' => 0,
            'sodexo' => 0, 'sodexo_odemesi' => 0, 'diger_odeme' => 0,
            'guncel_toplam_kesinti' => $kesinti, 'sgk_yapilan_firma' => 'Firma',
            'disardan_sigortali' => $disardanSigortali ? 1 : 0,
            'dagitim_manuel' => $manuel ? 1 : 0, 'banka_odemesi' => $manuel ? 25000 : 17800,
            'hesaplama_tarihi' => '2026-09-01 12:00:00',
        ];
        $payments = [
            (object) ['id' => 1, 'tur' => $nobet ? 'hafta_ici_nobet' : ($isKacakIhbarPrimi ? 'kacak_ihbar_primi' : 'prim'), 'tutar' => $primAmount, 'resmi_tutar' => $nobet ? 7000 : 0, 'aciklama' => $primAciklama, 'banka_matrahina_ekle' => $bankaSecimi],
            (object) ['id' => 2, 'tur' => 'diger', 'tutar' => ($inclusive || $nobet) ? 0 : 1200, 'resmi_tutar' => 0, 'aciklama' => 'Diğer ödeme', 'banka_matrahina_ekle' => 0],
        ];
        if ($hariciYemek) {
            $payments[] = (object) ['id' => 3, 'tur' => 'yemek_yardimi_tum', 'tutar' => 300, 'resmi_tutar' => 0, 'aciklama' => '[Yemek Yardımı] Günlük', 'banka_matrahina_ekle' => 1];
        }
        $deductions = [(object) ['id' => 1, 'tur' => 'ozel_kesinti', 'tutar' => $kesinti, 'aciklama' => 'Özel kesinti', 'hesaplama_tipi' => $eldenKesinti ? 'elden_tutardan' : 'sabit', 'banka_matrahina_ekle' => $eldenKesinti ? 0 : 1]];
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
        foreach (['prim', 'kacak_ihbar_primi', 'diger', 'yemek_yardimi_tum', 'hafta_ici_nobet'] as $code) {
            $params[$code] = (object) ['etiket' => $code, 'hesaplama_tipi' => 'net', 'odeme_yontemi' => $code === 'kacak_ihbar_primi' ? 'banka' : 'elden', 'resmi_alacagina_dahil' => in_array($code, ['kacak_ihbar_primi', 'hafta_ici_nobet'], true) ? 1 : 0, 'sgk_matrahi_dahil' => 0, 'gelir_vergisi_dahil' => 0, 'damga_vergisi_dahil' => 0];
        }
        $paramModel = $this->getMockBuilder(BordroParametreModel::class)->disableOriginalConstructor()->onlyMethods(['getByKod', 'getGenelAyar', 'hesaplaGelirVergisi', 'hesaplaAsgariUcretGelirVergisiIstisnasi'])->getMock();
        $paramModel->method('getByKod')->willReturn(null);
        $paramModel->method('getGenelAyar')->willReturnCallback(fn ($code) => $nobet && $code === 'asgari_ucret_net' ? 28075.5 : 0);
        $paramModel->method('hesaplaGelirVergisi')->willReturn(0.0);
        $paramModel->method('hesaplaAsgariUcretGelirVergisiIstisnasi')->willReturn(['aylik_matrah' => 0, 'istisna' => 0, 'toplam_matrah' => 0]);
        $this->setProperty($model, 'cachedParametreModel', $paramModel);
        $this->setProperty($model, 'genelAyarlarCache', ['asgari_ucret_net' => 28075.5]);
        $this->setProperty($model, 'parametrelerCache', $params);
        $this->setProperty($model, 'ekOdemelerCache', [1 => $payments]);

        self::assertTrue($model->hesaplaMaas(1));
        $record->net_maas = $saved['net_maas'];
        $record->banka_odemesi = $saved['banka_odemesi'];
        if ($nobet) {
            $record = (object) array_merge((array) $record, $saved);
        }
        $display = $model->hesaplaOrtakGosterimDegerleri(clone $record, $record, 28075.5);
        if ($nobet) {
            $snapshot = json_decode($saved['hesaplama_detay'], true);
            self::assertEquals(3500.0, $snapshot['ek_odemeler'][0]['net_etki']);
            self::assertSame(5240.76, $snapshot['ek_odemeler'][0]['resmi_banka_net']);
            self::assertEquals($inclusive ? 1740.76 : 0.0, $snapshot['ozet']['resmi_taban_tamamlama']);
            self::assertSame(33316.26, $saved['banka_odemesi']);
            self::assertEquals($inclusive ? 33316.26 : 33500.0, $display['toplamAlacagi']);
            self::assertSame($inclusive ? 31575.5 : 33500.0, $display['sozlesmeToplamAlacagi']);
            self::assertSame($inclusive ? 0.0 : 183.74, $saved['elden_odeme']);
            $excel = $model->getMuhasebeOdemeOzeti($display);
            self::assertSame(33316.26, $excel['banka_odemesi']);
            self::assertSame(0.0, $excel['dagitim_farki']);
            $bankaDetay = $model->getBankaOdemeDetayDagilimi($display);
            self::assertSame(33316.26, $bankaDetay['net_banka']);
            self::assertSame(5240.76, $bankaDetay['ek_odemeler'][0]['tutar']);
            self::assertTrue($model->auditKayitliBordro((object) array_merge($saved, [
                'hesaplama_tarihi' => $snapshot['hesaplama_tarihi'],
            ]))['mutabik']);
            return;
        }
        if ($karma) {
            self::assertTrue($display['karisikMaasGecmisi']);
            self::assertTrue($display['isInclusive']);
        }
        $inclusive = $inclusive || $karma;
        $expectedBank = $manuel ? 25000.0 : max(0.0, 28075.5 + ($hariciYemek ? 300 : 0) - ($eldenKesinti ? 0 : $kesinti));
        $expectedNet = 31800.0 + ($hariciYemek ? 300 : 0) - $kesinti;
        if (mb_strtolower($maasTuru, 'UTF-8') === 'brüt') {
            $expectedBank = (float) $saved['banka_odemesi'];
            $expectedNet = (float) $saved['net_maas'] - $kesinti;
            self::assertGreaterThan(0, $expectedBank);
        }
        if ($inclusive) {
            $meal = $karma
                ? ($bankaSecimi ? 2548.0 : 1950.0)
                : ($bankaSecimi ? ($primAmount === 3000.0 ? 7800.0 : 5538.0) : 4940.0);
            $expectedBank = 28075.5 + $meal - ($karma && !$eldenKesinti ? $kesinti : 0);
            $expectedNet = 33000.0 + $primAmount
                + ($bankaSecimi && $primAmount === 3000.0 ? 0.0 : ($bankaSecimi ? 13.5 : 15.5));
            if ($karma) {
                $expectedNet = $display['netAlacagi'];
            }
            self::assertEquals($meal, $display['mealAllowanceDeduction']);
            self::assertEquals($isKacakIhbarPrimi ? 0.0 : ($bankaSecimi ? 0.0 : $primAmount), $display['muhasebePrimTutari']);
        }
        if ($isKacakIhbarPrimi && !$inclusive) {
            $expectedBank += $primAmount;
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
            0.0,
            $excel['resmi_prim_ikramiye']
        );
        self::assertSame($display['muhasebePrimTutari'], $excel['prim']);
        self::assertSame(0.0, $excel['banka_kontrol_farki']);
        self::assertSame(0.0, $excel['dagitim_farki']);
        if (!$inclusive) {
            self::assertSame($isKacakIhbarPrimi ? 0.0 : $primAmount, $excel['prim']);
        }
        self::assertSame($expectedBank, $saved['banka_odemesi']);
        self::assertSame($expectedBank, $display['bankaOdemesi']);
        self::assertSame($expectedNet - $expectedBank, $saved['elden_odeme']);
        self::assertSame($saved['elden_odeme'], $display['eldenOdeme']);
        self::assertSame($expectedNet, $display['netAlacagi']);
        $detail = json_decode($saved['hesaplama_detay'], true);
        self::assertEquals($expectedBank, $detail['odeme_dagilimi']['banka_net']);
    }

    public function testEldenKesintiEldenHakedisiAstigindaBankadanKesilirVeMuhasebedeKesintiOlarakGosterilir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();

        // Ömer Faruk Dağ vakası:
        // Sözleşme: 30.800 TL, Prim: 3.100 TL -> Toplam: 33.900 TL
        // Resmî Asgari: 26.203,80 TL, Yemek: 7.200 TL -> Resmî Banka Matrahı: 33.403,80 TL
        // Elden Hakedişi: 33.900 - 33.403,80 = 496,20 TL
        // Elden Kesinti: 4.624,00 TL
        // Beklenen: Elden kesilen 496,20 TL, Bankadan kesilen 4.127,80 TL, Net Banka: 29.276,00 TL
        $hesap = [
            'hesaplanmis' => true,
            'gosterimKaynagi' => 'kayitli',
            'toplamAlacagi' => 33900.0,
            'netAlacagi' => 29276.0,
            'sozlesmeHakedisi' => 30800.0,
            'muhasebePrimTutari' => 3100.0,
            'muhasebeBankaPrimTutari' => 0.0,
            'muhasebeHariciYemekTutari' => 0.0,
            'muhasebeBankaYemekTutari' => 0.0,
            'asgariHakedis' => 26203.80,
            'asgariYatacak' => 26203.80,
            'mealAllowanceDeduction' => 7200.0,
            'spouseAllowanceDeduction' => 0.0,
            'includedAllowanceFiiliGun' => 24,
            'calismaGunu' => 28,
            'ucretsizIzinGunu' => 2,
            'bankaOdemesi' => 29276.0,
            'eldenOdeme' => 0.0,
            'sodexoOdemesi' => 0.0,
            'digerOdeme' => 0.0,
            'kesintiHaricIcra' => 4624.0,
            'icraKesintisi' => 0.0,
            'bankaMatrahi' => 33403.80,
            'bankaKesintiKalemleri' => [
                [
                    'kod' => 'malzeme zarar - ziyan',
                    'etiket' => 'Malzeme Zarar - Ziyan',
                    'tutar' => 4127.80,
                    'aciklama' => 'Ömer Faruk Dağ tablet ekranı kırık',
                ],
            ],
            'bankaEkOdemeDetaylari' => [],
        ];

        $muhasebe = $model->getMuhasebeOdemeOzeti($hesap);

        // 1. Resmî kalemler (Asgari Ücret ve Yemek) eksilmemeli, tam kalmalı
        self::assertEquals(26203.80, $muhasebe['resmi_alacak_asgari']);
        self::assertEquals(7200.0, $muhasebe['resmi_yemek_yardimi']);
        self::assertEquals(33403.80, $muhasebe['resmi_banka_matrahi']);
        self::assertEquals(33403.80, $muhasebe['muhasebe_resmi_banka_matrahi']);

        // 2. Bankadan kesilen bakiye muhasebe kesinti sütununa (kırmızı) yazılmalı
        self::assertEquals(4127.80, $muhasebe['muhasebe_banka_diger_kesinti']);
        self::assertEquals(4127.80, $muhasebe['muhasebe_bankadan_dusulecek']);
        self::assertEquals(4127.80, $muhasebe['bankadan_dusulen_kesinti']);

        // 3. Eldenden kesilen tutar doğru hesaplanmalı
        self::assertEquals(496.20, $muhasebe['elden_dusulen_kesinti']);

        // 4. Net banka ve elden ödemeler
        self::assertEquals(29276.0, $muhasebe['banka_odemesi']);
        self::assertEquals(29276.0, $muhasebe['net_maas']);
        self::assertEquals(0.0, $muhasebe['elden_odeme']);
        self::assertEquals(0.0, $muhasebe['dagitim_farki']);
        self::assertEquals(0.0, $muhasebe['banka_kontrol_farki']);
    }

    public function testKesintiOzetiResmiVeEldenDetayiniAyristirir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $row = (object) [
            'hesaplama_detay' => json_encode([
                'kesintiler' => [
                    [
                        'kod' => 'malzeme zarar - ziyan',
                        'etiket' => 'Malzeme Zarar - Ziyan',
                        'tutar' => 4624.0,
                        'aciklama' => 'Tablet ekranı',
                        'banka_matrahina_ekle' => 0,
                    ],
                ],
                'ortak_sonuc' => [
                    'gosterim' => [
                        'bankaKesintiKalemleri' => [
                            [
                                'kod' => 'malzeme zarar - ziyan',
                                'etiket' => 'Malzeme Zarar - Ziyan',
                                'tutar' => 4127.80,
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        $ozet = $model->getPersonelKesintiOzeti($row);
        self::assertEquals(4624.0, $ozet['toplam']);
        self::assertCount(1, $ozet['kalemler']);
        $kalem = $ozet['kalemler'][0];
        self::assertEquals(4624.0, $kalem['tutar']);
        self::assertEquals(4127.80, $kalem['resmi_kesinti']);
        self::assertEquals(496.20, $kalem['elden_kesinti']);
        self::assertEquals('elden', $kalem['kanal']);
    }

    public function testHaftaIciNobetSozlesmeNetiKorunurkenResmiBankaKirpilmaz(): void
    {
        $this->assertKayitGosterim('Prim Usülü', 1, '[Nöbet] Hafta İçi (7 Gün x 4 Saat x 500,00 ₺)',
            kesinti: 0, inclusive: true, primAmount: 3500, nobet: true);
    }

    public function testYardimsizPrimUsuluNobetteResmiBankaKirpilmaz(): void
    {
        $this->assertKayitGosterim('Prim Usülü', 1, '[Nöbet] Hafta İçi (7 Gün x 4 Saat x 500,00 ₺)',
            kesinti: 0, primAmount: 3500, nobet: true);
    }

    public function testNobetResmiTabaniYeterliEldenHakedisteEkKazancUretmez(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'nobetResmiTabanTamamlama');
        $ekler = [['kod' => 'hafta_ici_nobet', 'resmi_banka_net' => 5240.76]];
        self::assertSame(1740.76, $method->invoke($model, 30639.65, 32380.41, $ekler));
        self::assertSame(0.0, $method->invoke($model, 40000, 32380.41, $ekler));
        self::assertSame(0.0, $method->invoke($model, 30639.65, 32380.41, []));
    }

    public function testPrimUsuluBordroKontrolundeGercekPuantajVeNobetGosterilir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $row = (object) [
            'hesaplama_detay' => json_encode([
                'maas_durumu' => 'Prim Usülü',
                'ek_odemeler' => [[
                    'id' => 91,
                    'kod' => 'hafta_ici_nobet',
                    'aciklama' => '[Nöbet] Hafta İçi (7 Gün x 4 Saat x 500,00 ₺)',
                    'tutar' => 3500,
                    'net_etki' => 3500,
                    'resmi_banka_net' => 5240.76,
                ]],
            ], JSON_UNESCAPED_UNICODE),
        ];
        $hesap = [
            'maasDurumu' => 'Prim Usülü',
            'sozlesmeToplamAlacagi' => 30639.65,
            'toplamAlacagi' => 32380.41,
            'yuvarlamaFarki' => 0,
        ];

        $ozet = $model->getSozlesmeAlacagiKalemleri($hesap, $row);

        self::assertSame(3500.0, $ozet['toplam']);
        self::assertSame([
            ['etiket' => 'Puantaj Hakedişi', 'tutar' => 0.0],
            ['etiket' => 'Nöbet Ödemeleri', 'tutar' => 3500.0],
        ], $ozet['kalemler']);
        self::assertSame(
            $ozet['toplam'],
            round(array_sum(array_column($ozet['kalemler'], 'tutar')), 2)
        );
    }

    public function testPrimUsuluSozlesmeOzetindeSnapshotPuantajHedefNetiKullanilir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $row = (object) [
            'hesaplama_detay' => json_encode([
                'maas_durumu' => 'Prim Usülü',
                'ek_odemeler' => [
                    [
                        'kod' => 'prim',
                        'aciklama' => '[Puantaj] Sayaç Montajı',
                        'tutar' => 12000,
                        'hedef_net_adayi' => 12000,
                        'net_etki' => 0,
                    ],
                    [
                        'kod' => 'hafta_ici_nobet',
                        'aciklama' => '[Nöbet] Hafta İçi',
                        'tutar' => 3500,
                        'net_etki' => 3500,
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
        ];

        $ozet = $model->getSozlesmeAlacagiKalemleri([
            'maasDurumu' => 'Prim Usülü',
            'sozlesmeToplamAlacagi' => 30639.65,
            'toplamAlacagi' => 32380.41,
            'yuvarlamaFarki' => 0,
        ], $row);

        self::assertSame(15500.0, $ozet['toplam']);
        self::assertSame([
            ['etiket' => 'Puantaj Hakedişi', 'tutar' => 12000.0],
            ['etiket' => 'Nöbet Ödemeleri', 'tutar' => 3500.0],
        ], $ozet['kalemler']);
    }

    public function testEskiSnapshotAsiriKalemlerindeNegatifDengelemeGosterilmez(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $row = (object) [
            'hesaplama_detay' => json_encode([
                'maas_durumu' => 'Net',
                'ek_odemeler' => [
                    ['kod' => 'prim', 'net_etki' => 20000, 'aciklama' => 'Eski prim'],
                    ['kod' => 'diger', 'net_etki' => 15000, 'aciklama' => 'Eski diğer ödeme'],
                ],
            ], JSON_UNESCAPED_UNICODE),
        ];

        $ozet = $model->getSozlesmeAlacagiKalemleri([
            'maasDurumu' => 'Net',
            'sozlesmeToplamAlacagi' => 30000,
            'yuvarlamaFarki' => 0,
        ], $row);

        self::assertSame(30000.0, $ozet['toplam']);
        self::assertSame([
            ['etiket' => 'Sözleşme Hakedişi', 'tutar' => 30000.0],
        ], $ozet['kalemler']);
    }

    public function testBordroKontrolKesintiKalemleriFooterToplaminaTamamlanir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $row = (object) [
            'hesaplama_detay' => json_encode([
                'kesintiler' => [[
                    'kod' => 'avans',
                    'etiket' => 'Avans',
                    'tutar' => 500,
                ]],
            ], JSON_UNESCAPED_UNICODE),
        ];

        $ozet = $model->getBordroKontrolKesintiKalemleri([
            'toplamAlacagi' => 30000,
            'netAlacagi' => 28900,
        ], $row);

        self::assertSame(1100.0, $ozet['toplam']);
        self::assertSame([
            ['kod' => 'avans', 'etiket' => 'Avans', 'aciklama' => '', 'tutar' => 500.0],
            ['kod' => '', 'etiket' => 'Diğer Kesintiler', 'aciklama' => '', 'tutar' => 600.0],
        ], $ozet['kalemler']);
    }
}
