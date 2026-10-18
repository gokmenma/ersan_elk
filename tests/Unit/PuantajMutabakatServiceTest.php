<?php

use App\Service\PuantajMutabakatService;
use App\Model\PuantajMutabakatModel;
use PHPUnit\Framework\TestCase;

final class PuantajMutabakatServiceTest extends TestCase
{
    public function testReadingRowsWithSameNaturalKeyAreAggregated(): void
    {
        $service=(new ReflectionClass(PuantajMutabakatService::class))->newInstanceWithoutConstructor();
        $rows=[
            ['BOLGE'=>'MERKEZ','DEFTER'=>'100','OKUYUCUNO'=>'12','OKUYUCUADI'=>'Ekip 12','SAYACDURUM'=>'OK','ABONE_SAYISI'=>4],
            ['BOLGE'=>'MERKEZ','DEFTER'=>'100','OKUYUCUNO'=>'12','OKUYUCUADI'=>'Ekip 12','SAYACDURUM'=>'OK','ABONE_SAYISI'=>6],
        ];
        $items=$service->normalizeReadingRows($rows,'2026-09-30');
        self::assertCount(1,$items);
        self::assertSame(10.0,$items[0]['miktar']);
        self::assertSame(md5('2026-09-30|MERKEZ|100|12|OK'),$items[0]['anahtar']);
    }

    public function testCutRowsIgnoreMeterChangesAndAggregateAmounts(): void
    {
        $service=(new ReflectionClass(PuantajMutabakatService::class))->newInstanceWithoutConstructor();
        $rows=[
            ['ISEMRITIPI'=>'KESME','EKIP'=>'EKİP 3','SONUC'=>'KESİLDİ','SONUCLANMIS'=>2,'ACIK'=>1],
            ['ISEMRITIPI'=>'KESME','EKIP'=>'EKİP 3','SONUC'=>'KESİLDİ','SONUCLANMIS'=>3,'ACIK'=>4],
            ['ISEMRITIPI'=>'DEĞİŞME SÖKME TAKMA','EKIP'=>'EKİP 3','SONUC'=>'TAKILDI','SONUCLANMIS'=>9,'ACIK'=>0],
        ];
        $items=$service->normalizeCutRows($rows,'2026-09-30');
        self::assertCount(1,$items);
        self::assertSame(5.0,$items[0]['miktar']);
        self::assertSame(5.0,$items[0]['acik']);
        self::assertSame(5.0,$items[0]['payload']['acik']);
    }

    public function testAllDifferenceTypesAreClassified(): void
    {
        $old=['miktar'=>10,'veri_hash'=>'a'];
        self::assertSame('silinmis',PuantajMutabakatModel::classifyDifference($old,null));
        self::assertSame('eklenmis',PuantajMutabakatModel::classifyDifference(null,$old));
        self::assertSame('miktar_azalmis',PuantajMutabakatModel::classifyDifference($old,['miktar'=>9,'veri_hash'=>'a']));
        self::assertSame('miktar_artmis',PuantajMutabakatModel::classifyDifference($old,['miktar'=>11,'veri_hash'=>'a']));
        self::assertSame('alan_degismis',PuantajMutabakatModel::classifyDifference($old,['miktar'=>10,'veri_hash'=>'b']));
        self::assertNull(PuantajMutabakatModel::classifyDifference($old,$old));
    }

    public function testSealRowsAreSeparatedFromCutRows(): void
    {
        $service=(new ReflectionClass(PuantajMutabakatService::class))->newInstanceWithoutConstructor();
        $items=$service->normalizeCutRows([
            ['ISEMRITIPI'=>'KESME','EKIP'=>'EKİP 1','SONUC'=>'KESİLDİ','SONUCLANMIS'=>2,'ACIK'=>0],
            ['ISEMRITIPI'=>'MÜHÜRLEME','EKIP'=>'EKİP 2','SONUC'=>'MÜHÜR TAKILDI','SONUCLANMIS'=>3,'ACIK'=>0],
        ],'2026-10-10');
        self::assertSame(['kesme','muhurleme'],array_column($items,'kaynak'));
    }

    public function testMeterNaturalKeyMatchesProductionImporter(): void
    {
        $service=(new ReflectionClass(PuantajMutabakatService::class))->newInstanceWithoutConstructor();
        $items=$service->normalizeMeterRows([['ISEMRI_NO'=>'I-1','ABONE_NO'=>'A-2','TAKILAN_SAYACNO'=>'S-3','EKIP'=>'EKİP 4']],'2026-10-10');
        self::assertCount(1,$items);
        self::assertSame(md5('I-1|A-2|S-3|EKİP 4'),$items[0]['anahtar']);
        self::assertSame(1.0,$items[0]['miktar']);
    }
}
