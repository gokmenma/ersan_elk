<?php

use App\Model\BordroPersonelModel;
use PHPUnit\Framework\TestCase;

final class BordroKapanisButunlukTest extends TestCase
{
    public function testKapanisKontroluDonemKapatAksiyonununIcindeCalisir(): void
    {
        $source = file_get_contents(__DIR__ . '/../../views/bordro/api.php');
        self::assertIsString($source);
        $kapatPos = strpos($source, "case 'donem-kapat':");
        $acPos = strpos($source, "case 'donem-ac':", $kapatPos);
        $validationPos = strpos($source, 'validateDonemKapanis(', $kapatPos);

        self::assertNotFalse($kapatPos);
        self::assertNotFalse($acPos);
        self::assertNotFalse($validationPos);
        self::assertGreaterThan($kapatPos, $validationPos);
        self::assertLessThan($acPos, $validationPos);
        self::assertStringContainsString("'status' => 'validation_error'", $source);
        self::assertStringContainsString('\'errors\' => $hataSatirlari', $source);
        self::assertStringContainsString('BordroKapanisArsivModel', $source);
        self::assertStringContainsString('arsivleVeKapat(', $source);
    }

    public function testKapanisArsiviHashVeSurumZinciriTasir(): void
    {
        $source = file_get_contents(__DIR__ . '/../../App/Model/BordroKapanisArsivModel.php');
        self::assertIsString($source);
        self::assertStringContainsString('hash(\'sha256\', $snapshotJson)', $source);
        self::assertStringContainsString('hash(\'sha256\', $manifestJson)', $source);
        self::assertStringContainsString("'onceki_arsiv_hash'", $source);
        self::assertStringContainsString('beginTransaction()', $source);
        self::assertStringContainsString('verifyArchive(', $source);
    }

    public function testArsivExceliMuhurVeLegacyKayitlariAyirir(): void
    {
        $source = file_get_contents(__DIR__ . '/../../views/bordro/excel-arsiv-export.php');
        $modelSource = file_get_contents(__DIR__ . '/../../App/Model/BordroKapanisArsivModel.php');
        self::assertIsString($source);
        self::assertIsString($modelSource);
        foreach (['Arşiv Özeti', 'Maaş Bordroları', 'Ek Ödeme Detayları', 'Kesinti Detayları', 'Ödeme Mutabakatı', 'Maaş Kaynakları', 'Doğrulama'] as $sheet) {
            self::assertStringContainsString($sheet, $source);
        }
        self::assertStringContainsString('SHA256SUMS.txt', $source);
        self::assertStringContainsString('bordro-export-recovery-v1', $source);
        self::assertStringContainsString('geri_yukleme/manifest.json', $source);
        self::assertStringContainsString("'content' => (string) \$doc['snapshot_json']", $source);
        self::assertStringContainsString('Arşiv sistemi öncesi mevcut kayıt', $source);
        self::assertStringContainsString('Eski / Mühürsüz Kayıt', $modelSource);
        self::assertStringContainsString('getExportPeriods(', $modelSource);
        self::assertStringContainsString('$donemIds', $modelSource);
        self::assertStringContainsString("\$_GET['donem_ids']", $source);
        self::assertStringContainsString("preg_match('/^[=+\\-@]/u'", $source);
    }

    private function modelWithDatabase(float $nominalMaas): BordroPersonelModel
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE bordro_donemi (id INTEGER PRIMARY KEY, firma_id INTEGER, baslangic_tarihi TEXT, bitis_tarihi TEXT, silinme_tarihi TEXT)');
        $db->exec('CREATE TABLE personel (id INTEGER PRIMARY KEY, adi_soyadi TEXT)');
        $db->exec('CREATE TABLE bordro_personel (id INTEGER PRIMARY KEY, donem_id INTEGER, personel_id INTEGER, net_maas REAL, banka_odemesi REAL, sodexo_odemesi REAL, diger_odeme REAL, elden_odeme REAL, hesaplama_tarihi TEXT, hesaplama_detay TEXT, silinme_tarihi TEXT)');
        $db->exec('CREATE TABLE personel_gorev_gecmisi (id INTEGER PRIMARY KEY, personel_id INTEGER, departman TEXT, gorev TEXT, maas_durumu TEXT, maas_tutari REAL, baslangic_tarihi TEXT, bitis_tarihi TEXT)');
        $db->exec("INSERT INTO bordro_donemi VALUES (24,1,'2026-07-01','2026-07-31',NULL)");
        $db->exec("INSERT INTO personel VALUES (225,'ABDULLAH DOĞAN')");
        $db->exec("INSERT INTO personel_gorev_gecmisi VALUES (121,225,'Endeks Okuma','Memur','Net',33000,'2026-06-24','2026-08-17')");
        $detay = json_encode([
            'matrahlar' => ['nominal_maas' => $nominalMaas],
            'odeme_dagilimi' => ['icra_kesintisi' => 0, 'banka_net' => 29011.35, 'sodexo' => 0, 'elden' => 1564.15],
        ], JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare('INSERT INTO bordro_personel VALUES (1645,24,225,30575.50,29011.35,0,0,1564.15,?, ?, NULL)');
        $stmt->execute(['2026-08-19 17:52:25', $detay]);

        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(BordroPersonelModel::class, 'db'))->setValue($model, $db);
        return $model;
    }

    public function testDonemselMaasSnapshottaSifirsaKapanisEngellenir(): void
    {
        $errors = $this->modelWithDatabase(0)->validateDonemKapanis(24, 1);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('dönemsel sözleşme maaşı 33000.00 TL', implode("\n", $errors));
    }

    public function testMaasVeOdemeMutabikIseKapanisKontroluGecer(): void
    {
        self::assertSame([], $this->modelWithDatabase(33000)->validateDonemKapanis(24, 1));
    }
}
