<?php

use App\Model\BordroPersonelModel;
use PHPUnit\Framework\TestCase;

final class BordroCalismaGecmisiGunSayisiTest extends TestCase
{
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
