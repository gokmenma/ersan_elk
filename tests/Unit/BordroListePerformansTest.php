<?php

use App\Model\BordroPersonelModel;
use PHPUnit\Framework\TestCase;

final class BordroListePerformansTest extends TestCase
{
    private function model(): BordroPersonelModel
    {
        return (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
    }

    public function testBulkMatrahEskiAylariTekSayarakFallbackIleAyniSonucuVerir(): void
    {
        $m = $this->model();
        $db = $this->createMock(PDO::class);
        $history = $this->createMock(PDOStatement::class);
        $transfer = $this->createMock(PDOStatement::class);
        $payroll = $this->createMock(PDOStatement::class);
        $singleTransfer = $this->createMock(PDOStatement::class);
        $singlePayroll = $this->createMock(PDOStatement::class);
        $rows = [
            (object) ['personel_id'=>1, 'baslangic_tarihi'=>'2026-01-01', 'hesaplama_detay'=>'{"matrahlar":{"gelir_vergisi_matrahi":125.25}}'],
            (object) ['personel_id'=>1, 'baslangic_tarihi'=>'2026-01-15', 'hesaplama_detay'=>'{"matrahlar":{"gelir_vergisi_matrahi":999}}'],
            (object) ['personel_id'=>1, 'baslangic_tarihi'=>'2026-02-01', 'hesaplama_detay'=>null, 'brut_maas'=>200, 'sgk_isci'=>28, 'issizlik_isci'=>2],
        ];
        $db->expects(self::exactly(5))->method('prepare')->willReturnOnConsecutiveCalls($history, $transfer, $payroll, $singleTransfer, $singlePayroll);
        foreach ([$history, $transfer, $payroll, $singleTransfer, $singlePayroll] as $stmt) $stmt->method('execute')->willReturn(true);
        $history->method('fetchAll')->willReturn([]);
        $transfer->method('fetchAll')->willReturn([(object) ['id'=>1, 'kumulatif_matrah_devir'=>10]]);
        $payroll->method('fetchAll')->willReturn($rows);
        $singleTransfer->method('fetch')->willReturn((object) ['kumulatif_matrah_devir'=>10]);
        $singlePayroll->method('fetchAll')->willReturn($rows);
        $m->db = $db;
        (new ReflectionMethod($m, 'preloadListeHesapKaynaklari'))->invoke($m, [1, 2], '2026-03-01');
        $method = new ReflectionMethod($m, 'getKumulatifMatrah');
        self::assertSame(305.25, $method->invoke($m, 1, 2026, 3));
        self::assertSame(305.25, $method->invoke($m, 1, 2026, 3));
        self::assertSame(0.0, $method->invoke($m, 2, 2026, 3));
        $fresh = $this->model(); $fresh->db = $db;
        self::assertSame(305.25, $method->invoke($fresh, 1, 2026, 3));
        // Yüklenen boş geçmiş de tamamdır; SGK hesabı tekrar sorgu çalıştırmaz.
        $bulk = $m->getSgkFirmaDagilimi(2, '2026-03-01', '2026-03-31', 'Ersan Elektrik');
        self::assertIsArray($bulk);
    }

    public function testBulkSgkDagilimiTamGecmisVeEksikGunlerdeTekilYollaAynidir(): void
    {
        $m = $this->model();
        $db = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $rows = [
            ['ise_giris_tarihi'=>'2025-01-01', 'isten_cikis_tarihi'=>'2026-03-10', 'sgk_yapilan_firma'=>'İŞKUR'],
            ['ise_giris_tarihi'=>'2026-03-11', 'isten_cikis_tarihi'=>null, 'sgk_yapilan_firma'=>'Ersan Elektrik'],
        ];
        $db->expects(self::once())->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);
        $m->db = $db;
        $expected = $m->getSgkFirmaDagilimi(1, '2026-03-01', '2026-03-31', 'Yok', null, null, ['2026-03-15']);
        $key = ($_SESSION['firma_id'] ?? 0) . '|1';
        (new ReflectionProperty($m, 'sgkGecmisiCache'))->setValue($m, [$key=>$rows]);
        self::assertSame($expected, $m->getSgkFirmaDagilimi(1, '2026-03-01', '2026-03-31', 'Yok', null, null, ['2026-03-15']));
    }

    public function testGrossUpPaylasimiGirdileriVeParametreDegisiklikleriniAyirir(): void
    {
        App\Model\BordroParametreModel::clearRequestCache();
        $calls = 0;
        $model = $this->getMockBuilder(App\Model\BordroParametreModel::class)
            ->disableOriginalConstructor()->onlyMethods(['hesaplaGelirVergisi'])->getMock();
        $model->method('hesaplaGelirVergisi')->willReturnCallback(function () use (&$calls) { $calls++; return 0.0; });
        $result = $model->bruteUpForNetTarget(100, 0, 0.14, 0.01, 0.00759, 2026);
        $initialCalls = $calls;
        self::assertGreaterThan(0, $initialCalls);
        self::assertSame($result, $model->bruteUpForNetTarget(100, 0, 0.14, 0.01, 0.00759, 2026));
        self::assertSame($initialCalls, $calls);
        $model->bruteUpForNetTarget(101, 0, 0.14, 0.01, 0.00759, 2026);
        self::assertGreaterThan($initialCalls, $calls);
        $beforeClear = $calls;
        App\Model\BordroParametreModel::clearRequestCache();
        self::assertSame($result, $model->bruteUpForNetTarget(100, 0, 0.14, 0.01, 0.00759, 2026));
        self::assertGreaterThan($beforeClear, $calls);
        App\Model\BordroParametreModel::clearRequestCache();
    }

    public function testYeniListeOkumasiAyniDonemdeBileEskiKaynaklariTemizler(): void
    {
        $m = $this->model();
        $reset = new ReflectionMethod($m, 'resetListeCacheContext');
        foreach (['1|26|2026-09-01|2026-09-30', '1|26|2026-09-01|2026-09-30', '2|27|2026-10-01|2026-10-31'] as $context) {
            foreach (['sgkGecmisiCache', 'kumulatifMatrahCache', 'donemKesintileriCache', 'calismaGecmisiBulkCache', 'gorevGecmisiBulkCache', 'izinlerBulkCache'] as $name) {
                (new ReflectionProperty($m, $name))->setValue($m, [1=>['old']]);
            }
            (new ReflectionProperty($m, 'ekOdemelerCache'))->setValue($m, [1=>['old']]);
            (new ReflectionProperty($m, 'parametrelerCache'))->setValue($m, ['old'=>(object) []]);
            $reset->invoke($m, $context);
            foreach (['sgkGecmisiCache', 'kumulatifMatrahCache', 'donemKesintileriCache', 'calismaGecmisiBulkCache', 'gorevGecmisiBulkCache', 'izinlerBulkCache'] as $name) {
                self::assertSame([], (new ReflectionProperty($m, $name))->getValue($m));
            }
            self::assertNull((new ReflectionProperty($m, 'ekOdemelerCache'))->getValue($m));
            self::assertNull((new ReflectionProperty($m, 'parametrelerCache'))->getValue($m));
        }
    }
}
