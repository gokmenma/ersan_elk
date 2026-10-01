<?php
/** Her 5 dakikada bir CLI ile çalıştırın. Web üzerinden çağrı kabul edilmez. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once dirname(__DIR__, 2) . '/bootstrap.php';
date_default_timezone_set('Europe/Istanbul');
try {
    echo json_encode((new \App\Service\BordroYayinBildirimService())->calistir(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (\Throwable $e) {
    error_log('Bordro yayın cron: ' . $e->getMessage());
    fwrite(STDERR, 'Bordro bildirim kuyruğu çalıştırılamadı.' . PHP_EOL);
    exit(1);
}
