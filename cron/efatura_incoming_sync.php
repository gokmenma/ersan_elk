<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/Autoloader.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
date_default_timezone_set('Europe/Istanbul');
set_time_limit(0);

use App\Model\EInvoiceSettingsModel;
use App\Model\SystemLogModel;
use App\Service\EInvoiceService;

$lockPath = sys_get_temp_dir() . '/ersan_efatura_incoming_' . md5(dirname(__DIR__)) . '.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "E-Fatura senkronizasyonu zaten çalışıyor.\n");
    exit(0);
}

$failed = 0;
$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul'));
$startDate = $now->modify('-1 day')->format('Y-m-d');
$endDate = $now->format('Y-m-d');

try {
    $settingsModel = new EInvoiceSettingsModel();
    $auditLog = new SystemLogModel();

    foreach ($settingsModel->getActiveFirmIds() as $firmId) {
        try {
            $result = (new EInvoiceService())->syncIncomingInvoices($firmId, $startDate, $endDate, 'CREATE');
            $notificationCount = (int)($result['notification_count'] ?? 0);

            fwrite(STDOUT, sprintf(
                "[%s] Firma %d: %d yeni, %d güncellenen fatura; %d bildirim.\n",
                $now->format('Y-m-d H:i:s'),
                $firmId,
                (int)($result['added_count'] ?? 0),
                (int)($result['updated_count'] ?? 0),
                $notificationCount
            ));

            if (empty($result['success'])) {
                $failed++;
            }
            $auditLog->logActionForFirm(
                $firmId,
                0,
                'EDM Gelen Fatura Senkronizasyonu',
                sprintf('CREATE %s - %s aralığı: %d yeni, %d güncellenen fatura, %d bildirim. Tamamlandı: %s.', $startDate, $endDate, (int)($result['added_count'] ?? 0), (int)($result['updated_count'] ?? 0), $notificationCount, !empty($result['complete']) ? 'Evet' : 'Hayır'),
                empty($result['success']) ? SystemLogModel::LEVEL_IMPORTANT : SystemLogModel::LEVEL_INFO
            );
        } catch (Throwable $e) {
            $failed++;
            error_log(sprintf('[efatura_incoming_sync] Firma %d: %s', $firmId, $e->getMessage()));
            try {
                $auditLog->logActionForFirm($firmId, 0, 'EDM Gelen Fatura Senkronizasyonu', 'Başarısız: ' . mb_substr($e->getMessage(), 0, 1000), SystemLogModel::LEVEL_CRITICAL);
            } catch (Throwable $logError) {
                error_log('[efatura_incoming_sync] Audit kaydı yazılamadı: ' . $logError->getMessage());
            }
            fwrite(STDERR, sprintf("Firma %d senkronize edilemedi; hata günlüğünü kontrol edin.\n", $firmId));
        }
    }
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

exit($failed > 0 ? 1 : 0);
