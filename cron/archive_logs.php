<?php
/** Günlük 02:30: 30 2 * * * /opt/lampp/bin/php /path/to/cron/archive_logs.php */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu script sadece CLI üzerinden çalışabilir.');
}
require_once dirname(__DIR__) . '/Autoloader.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Aynı sunucuda çakışan görevleri engelle; en fazla 100 parti çalıştır.
$lock = fopen(sys_get_temp_dir() . '/ersan_log_archive_' . md5(dirname(__DIR__)) . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit("Arşivleme zaten çalışıyor.\n");
try {
    $model = new App\Model\SystemLogModel();
    $totals = [];
    for ($i = 0; $i < 100; $i++) {
        $batch = $model->archiveExpiredActivities(1000);
        foreach ($batch as $table => $count) $totals[$table] = ($totals[$table] ?? 0) + $count;
        if (array_sum($batch) === 0) break;
    }
    echo json_encode($totals, JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    error_log('[archive_logs] ' . $e->getMessage());
    fwrite(STDERR, "Log arşivleme tamamlanamadı; hata günlüğünü kontrol edin.\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
