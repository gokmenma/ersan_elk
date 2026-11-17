<?php

namespace Tests\Unit;

use App\Model\BordroPersonelModel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use PDO;
use PDOStatement;

final class BordroGorevGecmisiTest extends TestCase
{
    private function createModelWithMockDb(array $fetchResults): BordroPersonelModel
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($fetchResults);
        $stmt->method('fetch')->willReturn($fetchResults[0] ?? false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $dbProp = new ReflectionProperty(BordroPersonelModel::class, 'db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($model, $pdo);

        return $model;
    }

    public function testTekGorevKaydindaDonemGorevVeMaasiDoner(): void
    {
        $kayitlar = [
            (object) [
                'id' => 131,
                'personel_id' => 237,
                'departman' => 'Endeks Okuma',
                'gorev' => 'Memur',
                'maas_durumu' => 'Net',
                'maas_tutari' => 33000.00,
                'baslangic_tarihi' => '2026-07-29',
                'bitis_tarihi' => '2026-08-31'
            ]
        ];

        $model = $this->createModelWithMockDb($kayitlar);
        $hist = $model->getHistoricalGorevGecmisi(237, '2026-08-01', '2026-08-31');

        self::assertNotNull($hist);
        self::assertSame('Memur', $hist->gorev);
        self::assertSame('Endeks Okuma', $hist->departman);
        self::assertSame(33000.00, $hist->maas_tutari);
        self::assertFalse($hist->is_parcali);
    }

    public function testDonemIciTarihBazliParcaliGecisHesaplamasi(): void
    {
        // 1-15 Ağustos arası Memur (33.000 TL), 16-31 Ağustos arası Şef (36.000 TL)
        $kayitlar = [
            (object) [
                'id' => 131,
                'personel_id' => 237,
                'departman' => 'Endeks Okuma',
                'gorev' => 'Memur',
                'maas_durumu' => 'Net',
                'maas_tutari' => 33000.00,
                'baslangic_tarihi' => '2026-08-01',
                'bitis_tarihi' => '2026-08-15'
            ],
            (object) [
                'id' => 170,
                'personel_id' => 237,
                'departman' => 'Endeks Okuma',
                'gorev' => 'Şef',
                'maas_durumu' => 'Net',
                'maas_tutari' => 36000.00,
                'baslangic_tarihi' => '2026-08-16',
                'bitis_tarihi' => '2026-08-31'
            ]
        ];

        $model = $this->createModelWithMockDb($kayitlar);
        $hist = $model->getHistoricalGorevGecmisi(237, '2026-08-01', '2026-08-31');

        self::assertNotNull($hist);
        self::assertTrue($hist->is_parcali);
        self::assertSame(31, $hist->toplam_gun);
        self::assertCount(2, $hist->parca_detay);
        self::assertSame('Memur / Şef', $hist->gorev);
        self::assertSame('Şef', $hist->son_gorev);

        // Ağırlıklı hesap: ( (33000/30 * 15) + (36000/30 * 16) ) / 31 * 30
        $beklenenAgirlikli = round((35700 / 31) * 30, 2);
        self::assertEqualsWithDelta($beklenenAgirlikli, $hist->maas_tutari, 0.05);
    }

    public function testOverrideWithHistoricalGorevGecmisiRecordNesnesiniGunceller(): void
    {
        $kayitlar = [
            (object) [
                'id' => 131,
                'personel_id' => 237,
                'departman' => 'Endeks Okuma',
                'gorev' => 'Memur',
                'maas_durumu' => 'Net',
                'maas_tutari' => 33000.00,
                'baslangic_tarihi' => '2026-07-29',
                'bitis_tarihi' => '2026-08-31'
            ]
        ];

        $model = $this->createModelWithMockDb($kayitlar);

        // Personel tablosundaki anlık kaydı Şef ve 36.000 TL olsun
        $record = (object) [
            'personel_id' => 237,
            'departman' => 'Endeks Okuma',
            'gorev' => 'Şef',
            'maas_durumu' => 'Net',
            'maas_tutari' => 36000.00
        ];

        $updated = $model->overrideWithHistoricalGorevGecmisi($record, '2026-08-01', '2026-08-31');

        self::assertSame('Memur', $updated->gorev);
        self::assertSame(33000.00, $updated->maas_tutari);
        self::assertSame('Memur', $updated->gg_gorev);
        self::assertSame(1, $updated->gorev_gecmisi_var);
    }

    private function parcaliDagilim(BordroPersonelModel $model, array $kayitlar, int $gun, array $eksik = []): ?array
    {
        $method = new \ReflectionMethod(BordroPersonelModel::class, 'hesaplaParcaliSabitMaasDagilimi');
        $method->setAccessible(true);
        return $method->invoke($model, $kayitlar, '2026-10-01', '2026-10-31', $gun, $eksik, null, null, null, 28075.50);
    }

    private function yusufKayitlari(): array
    {
        return [
            (object) ['id' => 183, 'gorev' => 'Memur', 'maas_durumu' => 'Net', 'maas_tutari' => 33000, 'baslangic_tarihi' => '2026-09-19', 'bitis_tarihi' => '2026-10-02'],
            (object) ['id' => 200, 'gorev' => 'Şef', 'maas_durumu' => 'Net', 'maas_tutari' => 36000, 'baslangic_tarihi' => '2026-10-03', 'bitis_tarihi' => '2026-10-26'],
            (object) ['id' => 208, 'gorev' => 'Memur', 'maas_durumu' => 'Net', 'maas_tutari' => 33000, 'baslangic_tarihi' => '2026-10-27', 'bitis_tarihi' => null],
        ];
    }

    public function testParcaliSabitMaasGunleriKendiUcretiyleHesaplanir(): void
    {
        $model = $this->createModelWithMockDb([]);
        $eksik = ['2026-10-10' => true, '2026-10-28' => true, '2026-10-29' => true];
        $sonuc = $this->parcaliDagilim($model, $this->yusufKayitlari(), 28, $eksik);

        self::assertNotNull($sonuc);
        self::assertSame([2, 23, 3], array_column($sonuc['parcalar'], 'gun'));
        self::assertSame(33100.0, $sonuc['toplam']);
        self::assertEqualsWithDelta(33100.0, round($sonuc['efektif_maas'] / 30 * 28, 2), 0.001);
    }

    public function testTamAydaOtuzBirinciGunSonParcadanDusulur(): void
    {
        $model = $this->createModelWithMockDb([]);
        $sonuc = $this->parcaliDagilim($model, $this->yusufKayitlari(), 30);

        self::assertSame([2, 24, 4], array_column($sonuc['parcalar'], 'gun'));
        self::assertSame(35400.0, $sonuc['toplam']);
    }

    public function testHtcGunuDenkGeldigiGorevUcretiyleHesaplanir(): void
    {
        $model = $this->createModelWithMockDb([]);
        $sonuc = $this->parcaliDagilim($model, $this->yusufKayitlari(), 30);
        $method = new \ReflectionMethod(BordroPersonelModel::class, 'ozelCalismaGunDetayi');
        $method->setAccessible(true);
        $detay = $method->invoke($model, $sonuc, ['2026-10-02', '2026-10-18'], 1180.0);

        self::assertSame([1100.0, 1200.0], array_column($detay, 'gunluk'));
        self::assertSame(['Memur', 'Şef'], array_column($detay, 'gorev'));
    }

    public function testCakisanVeyaAyniUcretliGecmisteEskiHesapKorunur(): void
    {
        $model = $this->createModelWithMockDb([]);
        $cakisan = [
            (object) ['gorev' => 'Memur', 'maas_durumu' => 'Net', 'maas_tutari' => 33000, 'baslangic_tarihi' => '2025-10-09', 'bitis_tarihi' => null],
            (object) ['gorev' => 'Şef', 'maas_durumu' => 'Net', 'maas_tutari' => 36000, 'baslangic_tarihi' => '2025-11-05', 'bitis_tarihi' => '2026-10-08'],
        ];
        $ayniUcret = [
            (object) ['gorev' => 'Memur', 'maas_durumu' => 'Net', 'maas_tutari' => 33000, 'baslangic_tarihi' => '2026-10-01', 'bitis_tarihi' => '2026-10-15'],
            (object) ['gorev' => 'Okuyucu', 'maas_durumu' => 'Net', 'maas_tutari' => 33000, 'baslangic_tarihi' => '2026-10-16', 'bitis_tarihi' => null],
        ];
        $primli = [
            (object) ['gorev' => 'Memur', 'maas_durumu' => 'Net', 'maas_tutari' => 33000, 'baslangic_tarihi' => '2026-10-01', 'bitis_tarihi' => '2026-10-15'],
            (object) ['gorev' => 'Okuyucu', 'maas_durumu' => 'Prim Usülü', 'maas_tutari' => 0, 'baslangic_tarihi' => '2026-10-16', 'bitis_tarihi' => null],
        ];

        self::assertNull($this->parcaliDagilim($model, $cakisan, 30));
        self::assertNull($this->parcaliDagilim($model, $ayniUcret, 30));
        self::assertNull($this->parcaliDagilim($model, $primli, 30));
    }
}
