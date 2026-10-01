<?php
/** Daily CLI task; only expired private video parts are removed. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
try {
    (new \App\Service\PwaChunkUploadService())->cleanup();
    echo "PWA geçici video temizliği tamamlandı.\n";
} catch (\Throwable $e) {
    error_log('PWA transfer cleanup: ' . get_class($e));
    fwrite(STDERR, "PWA geçici video temizliği tamamlanamadı.\n");
    exit(1);
}
