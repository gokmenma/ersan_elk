<?php

namespace App\Helper;

class PwaSifrePolitikasi
{
    public const UZUNLUK = 6;

    public static function ihlalNedeni(string $sifre, ?string $dogumTarihi = null): ?string
    {
        if (!preg_match('/^\d{' . self::UZUNLUK . '}$/', $sifre)) {
            return 'Şifre tam ' . self::UZUNLUK . ' haneli ve yalnızca rakamlardan oluşmalıdır.';
        }

        if (preg_match('/^(\d)\1+$/', $sifre)) {
            return 'Şifre aynı rakamın tekrarından oluşamaz.';
        }

        if (self::ardisikVarMi($sifre)) {
            return 'Şifre ardışık rakamlar (123, 321 gibi) içeremez.';
        }

        if (preg_match('/^(\d{2,3})\1+$/', $sifre)) {
            return 'Şifre tekrar eden bir kalıptan oluşamaz.';
        }

        if (self::dogumTarihiIleBenzer($sifre, $dogumTarihi)) {
            return 'Şifre doğum tarihinizle ilişkili olamaz.';
        }

        return null;
    }

    public static function zayifMi(string $sifre, ?string $dogumTarihi = null): bool
    {
        if (preg_match('/^\d{1,8}$/', $sifre) !== 1) {
            return true;
        }

        if (strlen($sifre) !== self::UZUNLUK) {
            return true;
        }

        return self::ihlalNedeni($sifre, $dogumTarihi) !== null;
    }

    public static function uret(): string
    {
        do {
            $sifre = '';
            for ($i = 0; $i < self::UZUNLUK; $i++) {
                $sifre .= (string) random_int(0, 9);
            }
        } while (self::ihlalNedeni($sifre) !== null);

        return $sifre;
    }

    private static function ardisikVarMi(string $sifre): bool
    {
        $rakamlar = array_map('intval', str_split($sifre));
        for ($i = 0; $i <= count($rakamlar) - 3; $i++) {
            $fark1 = $rakamlar[$i + 1] - $rakamlar[$i];
            $fark2 = $rakamlar[$i + 2] - $rakamlar[$i + 1];
            if (abs($fark1) === 1 && $fark1 === $fark2) {
                return true;
            }
        }

        return false;
    }

    private static function dogumTarihiIleBenzer(string $sifre, ?string $dogumTarihi): bool
    {
        if ($dogumTarihi === null || $dogumTarihi === '' || str_starts_with($dogumTarihi, '0000')) {
            return false;
        }

        $zaman = strtotime($dogumTarihi);
        if ($zaman === false) {
            return false;
        }

        $g = date('d', $zaman);
        $a = date('m', $zaman);
        $yil = date('Y', $zaman);
        $yy = date('y', $zaman);

        $adaylar = [
            $g . $a . $yy, $a . $g . $yy, $yy . $a . $g, $yy . $g . $a,
            $g . $a . $yil, $a . $g . $yil, $yil . $a . $g,
            $g . $a . substr($yil, 2), $yil . $g . $a,
            $yil . '00', '00' . $yil, $yil . $yy,
        ];

        foreach ($adaylar as $aday) {
            if (str_contains($aday, $sifre) || str_contains($sifre, $yil) || $sifre === $aday) {
                return true;
            }
        }

        return str_contains($sifre, $g . $a) || str_contains($sifre, $a . $g);
    }
}
