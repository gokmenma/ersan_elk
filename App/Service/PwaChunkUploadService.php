<?php
namespace App\Service;

use RuntimeException;

/** Private, bounded video staging. No user-controlled paths are accepted. */
class PwaChunkUploadService
{
    public const CHUNK_SIZE = 262144;

    public static function root(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . '/ersan-pwa-' . substr(hash('sha256', dirname(__DIR__, 2)), 0, 16);
    }

    public static function isStagedFile(string $path): bool
    {
        $real = realpath($path);
        $root = realpath(self::root());
        return $real !== false && $root !== false && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && basename($real) === 'assembled';
    }

    public function run(int $firma, int $personel, string $key, callable $callback): array
    {
        \App\Model\PwaTransferModel::key($key);
        $root = self::root();
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) throw new RuntimeException('Video geçici alanı oluşturulamadı.');
        $dir = $root . '/' . hash('sha256', $firma . ':' . $personel . ':' . $key);
        if (!is_dir($dir) && !mkdir($dir, 0700) && !is_dir($dir)) throw new RuntimeException('Video geçici alanına yazılamıyor.');
        $lock = fopen($dir . '/lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Video yükleme kilidi alınamadı.');
        try {
            $meta = is_file($dir . '/meta.json') ? json_decode(file_get_contents($dir . '/meta.json'), true, 512, JSON_THROW_ON_ERROR) : null;
            if ($meta && (int) ($meta['updated'] ?? 0) < time() - 604800) {
                $this->removeParts($dir);
                $meta = null;
            }
            $result = $callback($dir, $meta);
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function save(string $dir, array $meta): void
    {
        $meta['updated'] = time();
        $json = json_encode($meta, JSON_THROW_ON_ERROR);
        if (file_put_contents($dir . '/meta.new', $json, LOCK_EX) !== strlen($json) || !rename($dir . '/meta.new', $dir . '/meta.json')) {
            throw new RuntimeException('Video ilerlemesi kaydedilemedi.');
        }
    }

    public function parts(string $dir, array $meta): array
    {
        $parts = [];
        for ($i = 0; $i < $meta['count']; $i++) {
            if (is_file($dir . '/' . $i . '.part')) $parts[] = $i;
        }
        return $parts;
    }

    public function put(string $dir, array $meta, int $index, array $file, string $hash): void
    {
        if ($index < 0 || $index >= $meta['count'] || !preg_match('/^[a-f0-9]{64}$/D', $hash)) throw new RuntimeException('Geçersiz video parçası.');
        $size = min(self::CHUNK_SIZE, $meta['size'] - $index * self::CHUNK_SIZE);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')
            || filesize($file['tmp_name']) !== $size || !hash_equals($hash, hash_file('sha256', $file['tmp_name']))) {
            throw new RuntimeException('Video parçasının boyutu veya bütünlüğü geçersiz.');
        }
        $path = $dir . '/' . $index . '.part';
        if (is_file($path)) {
            if (!hash_equals($hash, hash_file('sha256', $path))) throw new RuntimeException('Aynı video parçası farklı içerikle gönderildi.');
            return;
        }
        if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('Video parçası kaydedilemedi.');
        chmod($path, 0600);
    }

    public function assemble(string $dir, array $meta): string
    {
        if (count($this->parts($dir, $meta)) !== $meta['count']) throw new RuntimeException('Video parçaları eksik.');
        $path = $dir . '/assembled';
        $out = fopen($path, 'wb');
        if (!$out) throw new RuntimeException('Video birleştirilemedi.');
        try {
            for ($i = 0; $i < $meta['count']; $i++) {
                $in = fopen($dir . '/' . $i . '.part', 'rb');
                if (!$in) throw new RuntimeException('Video parçası okunamadı.');
                try {
                    $expected = min(self::CHUNK_SIZE, $meta['size'] - $i * self::CHUNK_SIZE);
                    if (stream_copy_to_stream($in, $out) !== $expected) throw new RuntimeException('Video parçası eksik okundu.');
                } finally { fclose($in); }
            }
        } finally { fclose($out); }
        chmod($path, 0600);
        if (filesize($path) !== $meta['size'] || !hash_equals($meta['hash'], hash_file('sha256', $path))) {
            unlink($path);
            throw new RuntimeException('Video bütünlük kontrolü başarısız.');
        }
        return $path;
    }

    public function removeParts(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $path) {
            if (basename($path) !== 'lock' && is_file($path)) unlink($path);
        }
    }

    public function cleanup(): void
    {
        foreach (glob(self::root() . '/*/meta.json') ?: [] as $path) {
            if (filemtime($path) >= time() - 604800) continue;
            $lock = fopen(dirname($path) . '/lock', 'c');
            if (!$lock) continue;
            if (flock($lock, LOCK_EX | LOCK_NB)) {
                clearstatcache(true, $path);
                if (is_file($path) && filemtime($path) < time() - 604800) $this->removeParts(dirname($path));
                flock($lock, LOCK_UN);
            }
            fclose($lock);
        }
    }
}
