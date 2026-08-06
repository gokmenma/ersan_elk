<?php

namespace App\Service;

use Exception;

/**
 * Yüklenen raster görselleri doğrular, EXIF yönünü düzeltir, küçültür ve
 * metadata içermeyen standart bir WebP/JPEG dosyası üretir.
 */
class ImageUploadService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_PIXELS = 40000000;

    public function store(
        array $file,
        string $destinationDirectory,
        string $filePrefix,
        int $maxDimension = 1920,
        int $quality = 85,
        int $maxUploadBytes = 15728640,
        int $thumbDimension = 0,
        int $thumbQuality = 72
    ): array {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception($this->uploadErrorMessage($error));
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxUploadBytes) {
            throw new Exception('Resim boyutu en fazla ' . round($maxUploadBytes / 1048576) . ' MB olabilir.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new Exception('Sadece JPEG, PNG veya WebP formatında resim yüklenebilir.');
        }

        // GD WebP desteği olmadan derlenmiş olabilir; bu durumda WebP dosya
        // okunamaz, kullanıcıya anlaşılır bir yönlendirme verilir.
        if ($mime === 'image/webp' && !function_exists('imagecreatefromwebp')) {
            throw new Exception('Bu resim WebP formatında ve sunucu WebP açamıyor. Lütfen JPEG veya PNG olarak yükleyin.');
        }

        $info = @getimagesize($file['tmp_name']);
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        if ($width < 1 || $height < 1 || ($width * $height) > self::MAX_PIXELS) {
            throw new Exception('Resim boyutları geçersiz veya işlenemeyecek kadar büyük.');
        }

        if (!function_exists('imagecreatefromstring')) {
            throw new Exception('Sunucuda resim işleme desteği etkin değil.');
        }

        $binary = @file_get_contents($file['tmp_name']);
        $source = is_string($binary) ? @imagecreatefromstring($binary) : false;
        if (!$source) {
            throw new Exception('Resim sunucu tarafından işlenemedi. Lütfen JPEG veya PNG olarak tekrar deneyin.');
        }

        if ($mime === 'image/jpeg') {
            $source = $this->applyExifOrientation($source, $file['tmp_name']);
        }

        $target = $this->resample($source, $maxDimension);
        if (!$target) {
            imagedestroy($source);
            throw new Exception('Resim için çalışma alanı oluşturulamadı.');
        }

        $targetWidth = imagesx($target);
        $targetHeight = imagesy($target);

        $destinationDirectory = rtrim($destinationDirectory, '/\\');
        if (!is_dir($destinationDirectory)
            && !mkdir($destinationDirectory, 0775, true)
            && !is_dir($destinationDirectory)) {
            imagedestroy($source);
            imagedestroy($target);
            throw new Exception('Resim yükleme dizini oluşturulamadı.');
        }
        if (!is_writable($destinationDirectory)) {
            imagedestroy($source);
            imagedestroy($target);
            throw new Exception('Resim yükleme dizinine yazılamıyor.');
        }

        $useWebp = function_exists('imagewebp') && ($mime !== 'image/webp' || $source !== false);
        $extension = $useWebp ? 'webp' : 'jpg';
        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filePrefix) ?: 'image';
        $baseName = $safePrefix . '_' . bin2hex(random_bytes(10));
        $fileName = $baseName . '.' . $extension;
        $destination = $destinationDirectory . DIRECTORY_SEPARATOR . $fileName;

        $saved = $useWebp
            ? imagewebp($target, $destination, $quality)
            : imagejpeg($target, $destination, $quality);

        imagedestroy($source);

        if (!$saved || !is_file($destination) || filesize($destination) === 0) {
            imagedestroy($target);
            @unlink($destination);
            throw new Exception('Optimize edilmiş resim kaydedilemedi.');
        }

        @chmod($destination, 0644);

        $thumbName = null;
        $thumbSize = 0;
        if ($thumbDimension > 0) {
            $thumbName = $baseName . '_k.' . $extension;
            $thumbPath = $destinationDirectory . DIRECTORY_SEPARATOR . $thumbName;
            $thumb = $this->resample($target, $thumbDimension);

            $thumbSaved = false;
            if ($thumb) {
                $thumbSaved = $useWebp
                    ? imagewebp($thumb, $thumbPath, $thumbQuality)
                    : imagejpeg($thumb, $thumbPath, $thumbQuality);
                imagedestroy($thumb);
            }

            if (!$thumbSaved || !is_file($thumbPath) || filesize($thumbPath) === 0) {
                @unlink($thumbPath);
                error_log('Küçük boyut üretilemedi, orijinal kullanılacak: ' . $fileName);
                $thumbName = null;
            } else {
                @chmod($thumbPath, 0644);
                $thumbSize = (int) filesize($thumbPath);
            }
        }

        imagedestroy($target);

        return [
            'filename' => $fileName,
            'path' => $destination,
            'mime' => $useWebp ? 'image/webp' : 'image/jpeg',
            'width' => $targetWidth,
            'height' => $targetHeight,
            'size' => filesize($destination),
            'thumb_filename' => $thumbName,
            'thumb_size' => $thumbSize,
        ];
    }

    private function resample($source, int $maxDimension)
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, $maxDimension / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));

        $target = imagecreatetruecolor($width, $height);
        if (!$target) {
            return false;
        }

        // JPEG çıktıda PNG şeffaf bölgelerin siyaha dönüşmesini engeller.
        $white = imagecolorallocate($target, 255, 255, 255);
        imagefill($target, 0, 0, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        return $target;
    }

    private function applyExifOrientation($image, string $path)
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $rotated = false;

        if ($orientation === 3) {
            $rotated = imagerotate($image, 180, 0);
        } elseif ($orientation === 6) {
            $rotated = imagerotate($image, -90, 0);
        } elseif ($orientation === 8) {
            $rotated = imagerotate($image, 90, 0);
        }

        if ($rotated !== false) {
            imagedestroy($image);
            return $rotated;
        }

        return $image;
    }

    private function uploadErrorMessage(int $error): string
    {
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return 'Resim sunucunun yükleme boyutu sınırını aşıyor.';
        }
        if ($error === UPLOAD_ERR_PARTIAL) {
            return 'Resim eksik yüklendi. Lütfen tekrar deneyin.';
        }
        return 'Geçerli bir resim yüklenemedi.';
    }
}
