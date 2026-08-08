<?php

namespace App\Service;

use Exception;

/**
 * Yüklenen videoyu doğrulayıp diske yazar ve istemcide üretilen kapak karesini
 * küçültülmüş görsel olarak kaydeder.
 *
 * Paylaşımlı hostingde ffmpeg bulunmadığı için video yeniden kodlanmaz;
 * boyut ve süre sınırı istemcide dayatılır, burada tekrar doğrulanır.
 */
class VideoUploadService
{
    private const UZANTI_HARITASI = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
        'video/3gpp' => '3gp',
    ];

    public function store(
        array $file,
        string $destinationDirectory,
        string $filePrefix,
        array $allowedMimes,
        int $maxUploadBytes,
        int $maxSureSaniye,
        ?int $sureSaniye = null,
        ?string $kapakVerisi = null,
        int $kapakKenar = 320
    ): array {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception(self::uploadErrorMessage($error, $maxUploadBytes));
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxUploadBytes) {
            throw new Exception('Video boyutu en fazla ' . round($maxUploadBytes / 1048576) . ' MB olabilir.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedMimes, true)) {
            throw new Exception('Desteklenmeyen video formatı. MP4 veya MOV yükleyin.');
        }

        if ($sureSaniye !== null && $sureSaniye > $maxSureSaniye) {
            throw new Exception('Video en fazla ' . $maxSureSaniye . ' saniye olabilir.');
        }

        $destinationDirectory = rtrim($destinationDirectory, '/\\');
        if (!is_dir($destinationDirectory)
            && !mkdir($destinationDirectory, 0775, true)
            && !is_dir($destinationDirectory)) {
            throw new Exception('Video yükleme dizini oluşturulamadı.');
        }
        if (!is_writable($destinationDirectory)) {
            throw new Exception('Video yükleme dizinine yazılamıyor.');
        }

        $extension = self::UZANTI_HARITASI[$mime] ?? 'mp4';
        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filePrefix) ?: 'video';
        $baseName = $safePrefix . '_' . bin2hex(random_bytes(10));
        $fileName = $baseName . '.' . $extension;
        $destination = $destinationDirectory . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Video kaydedilemedi.');
        }

        @chmod($destination, 0644);

        // Sunucuda FFmpeg yüklü ise videoyu otomatik sıkıştırıp optimize et
        $this->optimizeVideoWithFfmpeg($destination);

        $kapakAdi = $this->kapakKaydet($kapakVerisi, $destinationDirectory, $baseName, $kapakKenar);

        return [
            'filename' => $fileName,
            'path' => $destination,
            'mime' => $mime,
            'size' => filesize($destination),
            'sure_saniye' => $sureSaniye,
            'kapak_filename' => $kapakAdi,
        ];
    }

    /**
     * Sunucuda FFmpeg varsa videoyu H.264 / AAC 720p CRF 28 ile optimize ederek boyutunu düşürür.
     */
    private function optimizeVideoWithFfmpeg(string $filePath): void
    {
        if (!function_exists('exec')) {
            return;
        }

        $ffmpegBin = null;
        if (is_executable('/usr/bin/ffmpeg')) {
            $ffmpegBin = '/usr/bin/ffmpeg';
        } elseif (is_executable('/usr/local/bin/ffmpeg')) {
            $ffmpegBin = '/usr/local/bin/ffmpeg';
        } else {
            $which = @shell_exec('which ffmpeg 2>/dev/null');
            if ($which && trim($which)) {
                $ffmpegBin = trim($which);
            }
        }

        if (!$ffmpegBin) {
            return;
        }

        $tempPath = $filePath . '.opt.mp4';
        $cmd = sprintf(
            '%s -y -i %s -vcodec libx264 -crf 28 -preset faster -maxrate 1500k -bufsize 3000k -vf %s -acodec aac -b:a 128k %s 2>&1',
            escapeshellcmd($ffmpegBin),
            escapeshellarg($filePath),
            escapeshellarg('scale=\'min(1280,iw)\':\'min(720,ih)\':force_original_aspect_ratio=decrease,pad=ceil(iw/2)*2:ceil(ih/2)*2'),
            escapeshellarg($tempPath)
        );

        $output = [];
        $returnCode = 0;
        @exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && is_file($tempPath) && filesize($tempPath) > 0) {
            $originalSize = filesize($filePath);
            $optSize = filesize($tempPath);
            if ($optSize < $originalSize) {
                @rename($tempPath, $filePath);
                @chmod($filePath, 0644);
            } else {
                @unlink($tempPath);
            }
        } else {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * İstemcide canvas ile üretilen kapak karesini (data URI) diske yazar.
     * Kapak üretilemezse null döner; arayüz bu durumda genel video ikonu gösterir.
     */
    private function kapakKaydet(?string $kapakVerisi, string $dizin, string $baseName, int $kapakKenar): ?string
    {
        if (empty($kapakVerisi) || !function_exists('imagecreatefromstring')) {
            return null;
        }

        if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', $kapakVerisi, $eslesme)) {
            return null;
        }

        $ham = base64_decode(substr($kapakVerisi, strlen($eslesme[0])), true);
        if ($ham === false || strlen($ham) > 2097152) {
            return null;
        }

        $kaynak = @imagecreatefromstring($ham);
        if (!$kaynak) {
            return null;
        }

        $g = imagesx($kaynak);
        $y = imagesy($kaynak);
        $oran = min(1, $kapakKenar / max($g, $y));
        $hedefG = max(1, (int) round($g * $oran));
        $hedefY = max(1, (int) round($y * $oran));

        $hedef = imagecreatetruecolor($hedefG, $hedefY);
        if (!$hedef) {
            imagedestroy($kaynak);
            return null;
        }

        imagefill($hedef, 0, 0, imagecolorallocate($hedef, 255, 255, 255));
        imagecopyresampled($hedef, $kaynak, 0, 0, 0, 0, $hedefG, $hedefY, $g, $y);
        imagedestroy($kaynak);

        $useWebp = function_exists('imagewebp');
        $kapakAdi = $baseName . '_k.' . ($useWebp ? 'webp' : 'jpg');
        $kapakYolu = $dizin . DIRECTORY_SEPARATOR . $kapakAdi;

        $kaydedildi = $useWebp
            ? imagewebp($hedef, $kapakYolu, 72)
            : imagejpeg($hedef, $kapakYolu, 72);
        imagedestroy($hedef);

        if (!$kaydedildi || !is_file($kapakYolu) || filesize($kapakYolu) === 0) {
            @unlink($kapakYolu);
            error_log('Video kapak karesi kaydedilemedi: ' . $baseName);
            return null;
        }

        @chmod($kapakYolu, 0644);

        return $kapakAdi;
    }

    public static function uploadErrorMessage(int $error, int $maxUploadBytes): string
    {
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return 'Video sunucunun yükleme boyutu sınırını aşıyor (en fazla '
                . round($maxUploadBytes / 1048576) . ' MB).';
        }
        if ($error === UPLOAD_ERR_PARTIAL) {
            return 'Video eksik yüklendi. Bağlantınızı kontrol edip tekrar deneyin.';
        }
        return 'Geçerli bir video yüklenemedi.';
    }
}
