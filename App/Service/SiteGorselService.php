<?php

namespace App\Service;

class SiteGorselService
{
    private const KLASORLER = ['hizmetler', 'projeler', 'referanslar'];
    private const TABAN = 'uploads/site';
    private const AZAMI_BOYUT = 5242880;
    private const AZAMI_GENISLIK = 1600;

    public static function dizinYolu(string $klasor = 'hizmetler'): string
    {
        return dirname(__DIR__, 2) . '/' . self::TABAN . '/' . $klasor;
    }

    public static function kaydet(array $dosya, string $klasor = 'hizmetler'): array
    {
        if (!in_array($klasor, self::KLASORLER, true)) {
            return ['ok' => false, 'mesaj' => 'Görsel kaydedilemedi.'];
        }

        if (($dosya['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($dosya['tmp_name'] ?? ''))) {
            return ['ok' => false, 'mesaj' => 'Görsel yüklenemedi.'];
        }
        if ((int) $dosya['size'] > self::AZAMI_BOYUT) {
            return ['ok' => false, 'mesaj' => 'Görsel en fazla 5 MB olabilir.'];
        }

        $bilgi = @getimagesize($dosya['tmp_name']);
        $tur = $bilgi[2] ?? 0;
        if ($tur === IMAGETYPE_JPEG) {
            $kaynak = @imagecreatefromjpeg($dosya['tmp_name']);
        } elseif ($tur === IMAGETYPE_PNG) {
            $kaynak = @imagecreatefrompng($dosya['tmp_name']);
        } else {
            return ['ok' => false, 'mesaj' => 'Yalnızca JPEG veya PNG görsel yüklenebilir.'];
        }
        if (!$kaynak) {
            return ['ok' => false, 'mesaj' => 'Görsel okunamadı.'];
        }

        $genislik = imagesx($kaynak);
        $yukseklik = imagesy($kaynak);
        if ($genislik > self::AZAMI_GENISLIK) {
            $yeniYukseklik = (int) round($yukseklik * self::AZAMI_GENISLIK / $genislik);
            $olcekli = imagescale($kaynak, self::AZAMI_GENISLIK, $yeniYukseklik);
            if ($olcekli) {
                imagedestroy($kaynak);
                $kaynak = $olcekli;
            }
        }

        $zemin = imagecreatetruecolor(imagesx($kaynak), imagesy($kaynak));
        imagefill($zemin, 0, 0, imagecolorallocate($zemin, 255, 255, 255));
        imagecopy($zemin, $kaynak, 0, 0, 0, 0, imagesx($kaynak), imagesy($kaynak));
        imagedestroy($kaynak);

        $dizin = self::dizinYolu($klasor);
        if (!is_dir($dizin) && !@mkdir($dizin, 0775, true) && !is_dir($dizin)) {
            error_log('SiteGorselService: dizin oluşturulamadı: ' . $dizin);
            return ['ok' => false, 'mesaj' => 'Görsel kaydedilemedi.'];
        }
        $korumaDosyasi = dirname($dizin) . '/.htaccess';
        if (!is_file($korumaDosyasi)) {
            @file_put_contents($korumaDosyasi, "<FilesMatch \"\\.(php|phtml|phar|php[0-9])$\">\n    Require all denied\n</FilesMatch>\nOptions -Indexes\n");
        }

        $ad = bin2hex(random_bytes(12)) . '.jpg';
        $hedef = $dizin . '/' . $ad;
        $yazildi = imagejpeg($zemin, $hedef, 82);
        imagedestroy($zemin);
        if (!$yazildi) {
            error_log('SiteGorselService: dosya yazılamadı: ' . $hedef);
            return ['ok' => false, 'mesaj' => 'Görsel kaydedilemedi.'];
        }

        return ['ok' => true, 'yol' => self::TABAN . '/' . $klasor . '/' . $ad];
    }

    public static function sil(?string $yol): void
    {
        if (!$yol || !preg_match('#^' . preg_quote(self::TABAN, '#') . '/(' . implode('|', self::KLASORLER) . ')/[a-f0-9]{24}\.jpg$#', $yol)) {
            return;
        }
        $tam = dirname(__DIR__, 2) . '/' . $yol;
        if (is_file($tam)) {
            @unlink($tam);
        }
    }
}
