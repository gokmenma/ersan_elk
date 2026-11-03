<?php

namespace App\Service;

class DosyaIcerikDogrulayici
{
    private const OLE_IMZA = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    private const AZAMI_ZIP_GIRDI = 5000;
    private const AZAMI_ACILMIS_BOYUT = 209715200;
    private const AZAMI_ORAN = 200;

    public static function dogrula(string $yol, string $uzanti): void
    {
        $baslangic = (string) file_get_contents($yol, false, null, 0, 16);
        if (str_starts_with($baslangic, 'MZ') || str_starts_with($baslangic, "\x7FELF") || str_starts_with($baslangic, '#!')) {
            throw new \DomainException('Çalıştırılabilir dosyalar yüklenemez.');
        }

        match ($uzanti) {
            'pdf' => self::pdf($yol),
            'docx' => self::ooxml($yol, 'word/'),
            'xlsx' => self::ooxml($yol, 'xl/'),
            'doc', 'xls' => self::ole($yol),
            'jpg', 'jpeg' => self::goruntu($yol, "\xFF\xD8\xFF", IMAGETYPE_JPEG),
            'png' => self::goruntu($yol, "\x89PNG\r\n\x1A\n", IMAGETYPE_PNG),
            'txt', 'csv' => self::metin($yol),
            default => throw new \DomainException('Bu dosya türüne izin verilmiyor.'),
        };
    }

    private static function pdf(string $yol): void
    {
        $icerik = (string) file_get_contents($yol);
        if (!preg_match('/^.{0,1024}%PDF-/s', $icerik)) {
            throw new \DomainException('Geçerli bir PDF dosyası değil.');
        }
        if (preg_match('#/(JavaScript|JS|Launch)\b#', $icerik)) {
            throw new \DomainException('Betik veya program başlatma içeren PDF dosyaları yüklenemez.');
        }
    }

    private static function ooxml(string $yol, string $klasor): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \DomainException('Dosya doğrulanamadı.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($yol, \ZipArchive::RDONLY) !== true) {
            throw new \DomainException('Geçerli bir Office dosyası değil.');
        }
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::AZAMI_ZIP_GIRDI) {
                throw new \DomainException('Geçerli bir Office dosyası değil.');
            }
            $icerikTipi = false;
            $beklenen = false;
            $toplam = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $girdi = $zip->statIndex($i);
                $ad = (string) ($girdi['name'] ?? '');
                if ($ad === '' || str_contains($ad, '..') || str_starts_with($ad, '/') || str_contains($ad, '\\')) {
                    throw new \DomainException('Dosya içinde güvensiz yol bulundu.');
                }
                $toplam += (int) ($girdi['size'] ?? 0);
                if ($ad === '[Content_Types].xml') {
                    $icerikTipi = true;
                }
                if (str_starts_with($ad, $klasor)) {
                    $beklenen = true;
                }
                if (preg_match('#(^|/)vbaProject\.bin$#i', $ad) || preg_match('#\.(exe|dll|bat|cmd|ps1|vbs|js|jar|scr|com)$#i', $ad)) {
                    throw new \DomainException('Makro veya çalıştırılabilir içerik barındıran dosyalar yüklenemez.');
                }
            }
            if (!$icerikTipi || !$beklenen) {
                throw new \DomainException('Dosya yapısı uzantısıyla uyuşmuyor.');
            }
            $disBoyut = (int) filesize($yol);
            if ($toplam > self::AZAMI_ACILMIS_BOYUT || ($disBoyut > 0 && $toplam / $disBoyut > self::AZAMI_ORAN)) {
                throw new \DomainException('Dosya sıkıştırma oranı güvenlik sınırını aşıyor.');
            }
        } finally {
            $zip->close();
        }
    }

    private static function ole(string $yol): void
    {
        $icerik = (string) file_get_contents($yol);
        if (!str_starts_with($icerik, self::OLE_IMZA)) {
            throw new \DomainException('Geçerli bir Office dosyası değil.');
        }
        $vba = implode("\0", str_split('_VBA_PROJECT')) . "\0";
        $makro = implode("\0", str_split('Macros')) . "\0";
        if (str_contains($icerik, $vba) || str_contains($icerik, $makro)) {
            throw new \DomainException('Makro içeren eski biçim Office dosyaları yüklenemez. Dosyayı .docx/.xlsx olarak kaydedin.');
        }
    }

    private static function goruntu(string $yol, string $imza, int $tip): void
    {
        $baslangic = (string) file_get_contents($yol, false, null, 0, strlen($imza));
        $bilgi = @getimagesize($yol);
        if ($baslangic !== $imza || $bilgi === false || ($bilgi[2] ?? 0) !== $tip) {
            throw new \DomainException('Geçerli bir görsel dosyası değil.');
        }
    }

    private static function metin(string $yol): void
    {
        $parca = (string) file_get_contents($yol, false, null, 0, 8192);
        if (str_contains($parca, "\0")) {
            throw new \DomainException('Metin dosyası ikili içerik barındırıyor.');
        }
    }
}
