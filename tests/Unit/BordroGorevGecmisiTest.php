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
}
