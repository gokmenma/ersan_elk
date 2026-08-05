<?php

namespace App\Helper;

/**
 * Yetki kontrolünden geçmiş medya dosyalarını tarayıcıya aktarır.
 *
 * Video oynatıcılar ileri/geri sarma için HTTP Range isteği gönderir; sunucu
 * 206 Partial Content ile yanıtlamazsa iOS Safari videoyu hiç oynatmaz.
 */
class MedyaServis
{
    private const MIME_HARITASI = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'webm' => 'video/webm',
        '3gp' => 'video/3gpp',
    ];

    public static function mimeBul(string $dosya): string
    {
        $uzanti = strtolower(pathinfo($dosya, PATHINFO_EXTENSION));
        return self::MIME_HARITASI[$uzanti] ?? 'application/octet-stream';
    }

    public static function videoMu(string $dosya): bool
    {
        return strpos(self::mimeBul($dosya), 'video/') === 0;
    }

    public static function gonder(string $dosya, int $cacheSaniye = 600): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $boyut = filesize($dosya);
        $mime = self::mimeBul($dosya);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($dosya) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=' . $cacheSaniye);
        header('Accept-Ranges: bytes');

        $aralik = $_SERVER['HTTP_RANGE'] ?? '';
        if ($aralik === '' || !preg_match('/^bytes=(\d*)-(\d*)$/', trim($aralik), $eslesme)) {
            header('Content-Length: ' . $boyut);
            readfile($dosya);
            return;
        }

        $bas = $eslesme[1] === '' ? null : (int) $eslesme[1];
        $bit = $eslesme[2] === '' ? null : (int) $eslesme[2];

        if ($bas === null && $bit === null) {
            header('Content-Length: ' . $boyut);
            readfile($dosya);
            return;
        }

        if ($bas === null) {
            $bas = max(0, $boyut - $bit);
            $bit = $boyut - 1;
        } elseif ($bit === null || $bit >= $boyut) {
            $bit = $boyut - 1;
        }

        if ($bas > $bit || $bas >= $boyut) {
            http_response_code(416);
            header('Content-Range: bytes */' . $boyut);
            return;
        }

        $uzunluk = $bit - $bas + 1;
        http_response_code(206);
        header('Content-Range: bytes ' . $bas . '-' . $bit . '/' . $boyut);
        header('Content-Length: ' . $uzunluk);

        $akis = fopen($dosya, 'rb');
        if ($akis === false) {
            http_response_code(500);
            return;
        }

        fseek($akis, $bas);
        $kalan = $uzunluk;
        while ($kalan > 0 && !feof($akis)) {
            $parca = fread($akis, (int) min(262144, $kalan));
            if ($parca === false) {
                break;
            }
            echo $parca;
            flush();
            $kalan -= strlen($parca);
        }
        fclose($akis);
    }
}
