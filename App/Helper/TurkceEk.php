<?php

namespace App\Helper;

final class TurkceEk
{
    private const UNLULER = ['a', 'e', 'ı', 'i', 'o', 'ö', 'u', 'ü'];
    private const KALIN_UNLULER = ['a', 'ı', 'o', 'u'];
    private const SERT_SESSIZLER = ['ç', 'f', 'h', 'k', 'p', 's', 'ş', 't'];

    private const ILGI_EKLERI = ['a' => 'ın', 'ı' => 'ın', 'e' => 'in', 'i' => 'in', 'o' => 'un', 'u' => 'un', 'ö' => 'ün', 'ü' => 'ün'];
    private const BELIRTME_EKLERI = ['a' => 'ı', 'ı' => 'ı', 'e' => 'i', 'i' => 'i', 'o' => 'u', 'u' => 'u', 'ö' => 'ü', 'ü' => 'ü'];

    private const KISALTMA_OKUNUSLARI = [
        'a.ş' => 'aşe', 'aş' => 'aşe', 't.a.ş' => 'aşe', 'a.s' => 'aşe',
        'şti' => 'şirketi', 'ltd' => 'limitet',
        'a.g' => 'age', 'gmbh' => 'gmbeha',
    ];

    private const IYELIK_SONLARI = [
        'dairesi', 'müdürlüğü', 'müdüriyeti', 'başkanlığı', 'bakanlığı', 'genel müdürlüğü',
        'şirketi', 'bankası', 'kurumu', 'kuruluşu', 'belediyesi', 'mahkemesi', 'savcılığı',
        'şubesi', 'amirliği', 'komutanlığı', 'valiliği', 'kaymakamlığı', 'rektörlüğü',
        'fakültesi', 'hastanesi', 'merkezi', 'birimi', 'servisi', 'odası', 'derneği',
        'vakfı', 'kooperatifi', 'ortaklığı', 'işletmesi', 'idaresi', 'başkanı', 'temsilciliği',
    ];

    public static function ekle(string $kelime, string $hal, ?bool $iyelik = null, string $ayirac = ''): string
    {
        $ek = self::ek($kelime, $hal, $iyelik);
        if ($ek === '') {
            return $kelime;
        }
        if (self::buyukHarfliMi($kelime)) {
            $ek = self::buyut($ek);
            $ayirac = self::buyut($ayirac);
        }
        return $kelime . $ayirac . $ek;
    }

    public static function buyut(string $metin): string
    {
        return mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $metin), 'UTF-8');
    }

    public static function buyukHarfliMi(string $kelime): bool
    {
        $harfler = (string) preg_replace('/[^\p{L}]/u', '', $kelime);
        if ($harfler === '') {
            return false;
        }
        return self::buyut($harfler) === $harfler;
    }

    public static function ilgi(string $kelime, ?bool $iyelik = null): string
    {
        return self::ek($kelime, 'ilgi', $iyelik);
    }

    public static function yonelme(string $kelime, ?bool $iyelik = null): string
    {
        return self::ek($kelime, 'yonelme', $iyelik);
    }

    public static function belirtme(string $kelime, ?bool $iyelik = null): string
    {
        return self::ek($kelime, 'belirtme', $iyelik);
    }

    public static function bulunma(string $kelime, ?bool $iyelik = null): string
    {
        return self::ek($kelime, 'bulunma', $iyelik);
    }

    public static function ayrilma(string $kelime, ?bool $iyelik = null): string
    {
        return self::ek($kelime, 'ayrilma', $iyelik);
    }

    public static function ek(string $kelime, string $hal, ?bool $iyelik = null): string
    {
        $temiz = self::temizle($kelime);
        if ($temiz === '') {
            return '';
        }

        $sonUnlu = self::sonUnlu($temiz);
        $sonHarf = self::sonHarf($temiz);
        $unluIleBitiyor = in_array($sonHarf, self::UNLULER, true);
        $kalin = in_array($sonUnlu, self::KALIN_UNLULER, true);
        if ($iyelik === null) {
            $iyelik = self::iyelikIleBitiyorMu($temiz);
        }

        switch ($hal) {
            case 'ilgi':
                return ($unluIleBitiyor ? 'n' : '') . self::ILGI_EKLERI[$sonUnlu];
            case 'belirtme':
                return ($unluIleBitiyor ? ($iyelik ? 'n' : 'y') : '') . self::BELIRTME_EKLERI[$sonUnlu];
            case 'yonelme':
                return ($unluIleBitiyor ? ($iyelik ? 'n' : 'y') : '') . ($kalin ? 'a' : 'e');
            case 'bulunma':
                return (in_array($sonHarf, self::SERT_SESSIZLER, true) ? 't' : 'd') . ($kalin ? 'a' : 'e');
            case 'ayrilma':
                return (in_array($sonHarf, self::SERT_SESSIZLER, true) ? 't' : 'd') . ($kalin ? 'an' : 'en');
        }

        return '';
    }

    public static function iyelikIleBitiyorMu(string $kelime): bool
    {
        $temiz = self::temizle($kelime);
        foreach (self::IYELIK_SONLARI as $son) {
            if (str_ends_with($temiz, $son)) {
                return true;
            }
        }
        return false;
    }

    private static function temizle(string $kelime): string
    {
        $kelime = str_replace(['I', 'İ'], ['ı', 'i'], trim($kelime));
        $kelime = mb_strtolower($kelime, 'UTF-8');
        $kelime = (string) preg_replace('/[^\p{L}\p{N}\s]+$/u', '', $kelime);

        $parcalar = preg_split('/\s+/u', $kelime, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parcalar === []) {
            return $kelime;
        }
        $son = end($parcalar);
        if (isset(self::KISALTMA_OKUNUSLARI[$son])) {
            $parcalar[count($parcalar) - 1] = self::KISALTMA_OKUNUSLARI[$son];
            return implode(' ', $parcalar);
        }
        return $kelime;
    }

    private static function sonHarf(string $kelime): string
    {
        $harfler = preg_split('//u', $kelime, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        for ($i = count($harfler) - 1; $i >= 0; $i--) {
            if (preg_match('/\p{L}/u', $harfler[$i]) === 1) {
                return $harfler[$i];
            }
        }
        return '';
    }

    private static function sonUnlu(string $kelime): string
    {
        $harfler = preg_split('//u', $kelime, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        for ($i = count($harfler) - 1; $i >= 0; $i--) {
            if (in_array($harfler[$i], self::UNLULER, true)) {
                return $harfler[$i];
            }
        }
        return 'a';
    }
}
