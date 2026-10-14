<?php

use App\Model\BordroPersonelModel;
use PHPUnit\Framework\TestCase;

final class BordroSnapshotAuditTest extends TestCase
{
    private function row(float $elden): object
    {
        return (object) [
            'hesaplama_tarihi' => '2026-10-14 16:34:00',
            'net_maas' => 30000, 'banka_odemesi' => 24832,
            'elden_odeme' => $elden, 'sodexo_odemesi' => 0, 'diger_odeme' => 0,
            'hesaplama_detay' => json_encode([
                'kesintiler' => [['kod' => 'diger', 'tutar' => 1100]],
                'odeme_dagilimi' => ['banka_net' => 24832, 'elden' => $elden],
            ]),
        ];
    }

    public function testEksikOdemeSaptanirVeKayitDegismez(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $row = $this->row(0);
        $before = serialize($row);
        $audit = $model->auditKayitliBordro($row);
        self::assertFalse($audit['mutabik']);
        self::assertEquals(4068, $audit['fark']);
        self::assertSame($before, serialize($row));
    }

    public function testBirKurusFarkiBileSaptanir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        self::assertTrue($model->auditKayitliBordro($this->row(4068))['mutabik']);
        self::assertFalse($model->auditKayitliBordro($this->row(4067.99))['mutabik']);
    }

    public function testToplamTutarkenKanalFarkiSaptanir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $row = $this->row(4068);
        $row->banka_odemesi = 24831;
        $row->elden_odeme = 4069;
        $audit = $model->auditKayitliBordro($row);
        self::assertEquals(0, $audit['fark']);
        self::assertContains('snapshot_kanal_farki:banka_odemesi', $audit['hatalar']);
    }

    public function testSurumluSonucKayitliKanallariAynenTasir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $row = $this->row(4068);
        $before = serialize($row);
        $result = $model->getSurumluBordroKarsilastirma($row);
        self::assertSame('mutabik', $result['durum']);
        self::assertSame(3000000, $result['toplam_hakedis_kurus']);
        self::assertSame(2890000, $result['odenecek_net_kurus']);
        self::assertSame(['banka' => 2483200, 'elden' => 406800, 'kart' => 0, 'diger' => 0], $result['kanallar_kurus']);
        self::assertNull($result['kalemler'][0]['resmi_net_kurus']);
        self::assertSame($before, serialize($row));
    }

    public function testBelirsizEskiKayittaHakedisTahminEdilmez(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $result = $model->getSurumluBordroKarsilastirma($this->row(0));
        self::assertSame('inceleme_gerekli', $result['durum']);
        self::assertNull($result['toplam_hakedis_kurus']);
        self::assertSame(2483200, $result['kanallar_kurus']['banka']);
        self::assertEquals(4068, $result['denetim']['fark']);
    }

    public function testOrtakSonucGuncelParametreyeBakmadanOkunur(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $row = $this->row(4068);
        $common = [
            'hesaplanmis' => true, 'gosterimKaynagi' => 'kayitli',
            'toplamAlacagi' => 30000.0, 'netAlacagi' => 28900.0,
            'kesintiHaricIcra' => 1100.0, 'icraKesintisi' => 0.0,
            'bankaOdemesi' => 24832.0, 'eldenOdeme' => 4068.0,
            'sodexoOdemesi' => 0.0, 'digerOdeme' => 0.0,
            'mealAllowanceDeduction' => 2535.75, 'htcGun' => 1,
        ];
        $detay = json_decode($row->hesaplama_detay, true);
        $detay['ortak_sonuc'] = ['surum' => 1, 'gosterim' => $common];
        $row->hesaplama_detay = json_encode($detay);
        self::assertEquals($common, $model->hesaplaOrtakGosterimDegerleri($row, null, 99999));
        self::assertTrue($model->auditKayitliBordro($row)['mutabik']);
        $row->banka_odemesi = 24831;
        self::assertContains('ortak_sonuc_kanal_farki:banka_odemesi', $model->auditKayitliBordro($row)['hatalar']);
        $this->expectException(DomainException::class);
        $model->hesaplaOrtakGosterimDegerleri($row, null, 99999);
    }

    public function testTutarsizOrtakSonucVeritabaninaUlasmadanEngellenir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $row = $this->row(0);
        $snapshot = json_decode($row->hesaplama_detay, true);
        $snapshot['ortak_sonuc'] = ['surum' => 1, 'gosterim' => []];
        $row->hesaplama_detay = json_encode($snapshot);
        $this->expectException(DomainException::class);
        $model->saveBordroHesaplama(1, (array) $row);
    }
}
