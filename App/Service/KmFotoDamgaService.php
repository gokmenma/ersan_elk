<?php

namespace App\Service;

class KmFotoDamgaService
{
    private const FONT_PATHS = [
        'vendor/mpdf/mpdf/ttfonts/DejaVuSans-Bold.ttf',
        'vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf',
    ];

    public function uygula(string $path, array $lines): bool
    {
        $lines = array_values(array_filter(array_map('trim', $lines), static fn($l) => $l !== ''));
        $font = $this->findFont();
        if (!$lines || !$font || !function_exists('imagettftext') || !is_file($path)) {
            return false;
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (!$image) {
            return false;
        }

        $w = imagesx($image);
        $h = imagesy($image);
        $fontSize = max(14, (int) floor($w / 45));
        $lineHeight = $fontSize + 8;
        $padding = 20;

        $maxTextWidth = 0;
        foreach ($lines as $line) {
            $box = imagettfbbox($fontSize, 0, $font, $line);
            $maxTextWidth = max($maxTextWidth, (int) abs($box[2] - $box[0]));
        }

        $bgWidth = $maxTextWidth + 20;
        $bgHeight = count($lines) * $lineHeight + 10;
        $bgRight = $w - $padding + 5;
        $bgBottom = $h - $padding + 5;
        imagealphablending($image, true);
        imagefilledrectangle(
            $image,
            max(0, $bgRight - $bgWidth),
            max(0, $bgBottom - $bgHeight),
            $bgRight,
            $bgBottom,
            imagecolorallocatealpha($image, 0, 0, 0, 64)
        );

        $white = imagecolorallocate($image, 255, 255, 255);
        $y = $h - $padding - (count($lines) - 1) * $lineHeight;
        foreach ($lines as $line) {
            $box = imagettfbbox($fontSize, 0, $font, $line);
            $x = $w - $padding - (int) abs($box[2] - $box[0]);
            imagettftext($image, $fontSize, 0, $x, $y, $white, $font, $line);
            $y += $lineHeight;
        }

        $saved = $mime === 'image/webp' ? imagewebp($image, $path, 85) : imagejpeg($image, $path, 88);
        imagedestroy($image);
        return (bool) $saved;
    }

    private function findFont(): ?string
    {
        foreach (self::FONT_PATHS as $relative) {
            $full = PROJECT_ROOT . '/' . $relative;
            if (is_file($full)) {
                return $full;
            }
        }
        return null;
    }
}
