<?php

use App\Model\BordroPersonelModel;
use PHPUnit\Framework\TestCase;

final class BordroCalismaGecmisiGunSayisiTest extends TestCase
{
    public function testIskurDonemiMaasVeSgkGunKapsaminaAlinmaz(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $pdo->expects(self::once())->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['ise_giris_tarihi' => '2026-08-11', 'isten_cikis_tarihi' => '2026-08-25', 'sgk_yapilan_firma' => 'İŞKUR'],
            ['ise_giris_tarihi' => '2026-08-26', 'isten_cikis_tarihi' => null, 'sgk_yapilan_firma' => 'Ersan Elektrik'],
        ]);
        (new ReflectionProperty(BordroPersonelModel::class, 'db'))->setValue($model, $pdo);

        $method = new ReflectionMethod(BordroPersonelModel::class, 'getCalismaGecmisiAktifTarihleri');
        $method->setAccessible(true);
        $gunler = $method->invoke($model, 1, '2026-08-01', '2026-08-31');

        self::assertCount(6, $gunler);
        self::assertArrayHasKey('2026-08-26', $gunler);
        self::assertArrayHasKey('2026-08-31', $gunler);
        self::assertArrayNotHasKey('2026-08-25', $gunler);
    }

    public function testYalnizIskurGecmisiVarsaMaasVeSgkGunuSifirdir(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $pdo->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([
            ['ise_giris_tarihi' => '2026-08-11', 'isten_cikis_tarihi' => '2026-08-31', 'sgk_yapilan_firma' => 'İŞKUR'],
        ]);
        (new ReflectionProperty(BordroPersonelModel::class, 'db'))->setValue($model, $pdo);

        $method = new ReflectionMethod(BordroPersonelModel::class, 'getCalismaGecmisiAktifTarihleri');
        $method->setAccessible(true);
        $gunler = $method->invoke($model, 1, '2026-08-01', '2026-08-31');

        self::assertSame([], $gunler);
    }

    public function testBirdenFazlaCalismaDonemininBirlesikGunleriniHesaplar(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(BordroPersonelModel::class, 'birlesikTarihAraligiGunSayisi');
        $method->setAccessible(true);

        $gunSayisi = $method->invoke($model, [
            ['ise_giris_tarihi' => '2025-11-05', 'isten_cikis_tarihi' => '2026-07-25'],
            ['ise_giris_tarihi' => '2026-07-27', 'isten_cikis_tarihi' => '2026-07-31'],
        ], '2026-07-01', '2026-07-31');

        self::assertSame(30, $gunSayisi);
    }

    public function testCakisanCalismaDonemlerindeAyniGunuIkiKezSaymaz(): void
    {
        $model = (new ReflectionClass(BordroPersonelModel::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(BordroPersonelModel::class, 'birlesikTarihAraligiGunSayisi');
        $method->setAccessible(true);

        $gunSayisi = $method->invoke($model, [
            ['ise_giris_tarihi' => '2026-07-01', 'isten_cikis_tarihi' => '2026-07-20'],
            ['ise_giris_tarihi' => '2026-07-15', 'isten_cikis_tarihi' => null],
        ], '2026-07-01', '2026-07-31');

        self::assertSame(31, $gunSayisi);
    }
}
