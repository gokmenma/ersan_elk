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

use App\Model\BildirimModel;
use App\Model\EInvoiceSettingsModel;
use App\Model\UserNotificationPreferenceModel;
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
    $notificationModel = new BildirimModel();

    foreach ($settingsModel->getActiveFirmIds() as $firmId) {
        try {
            $result = (new EInvoiceService())->syncIncomingInvoices($firmId, $startDate, $endDate, 'CREATE');
            $notificationCount = 0;

            foreach ($result['new_invoices'] ?? [] as $invoice) {
                $invoiceNo = trim((string)($invoice['fatura_no'] ?? '')) ?: 'Numarasız fatura';
                $sender = trim((string)($invoice['gonderici_unvan'] ?? '')) ?: 'Bilinmeyen gönderici';
                $amount = number_format((float)($invoice['odenecek_tutar'] ?? 0), 2, ',', '.');
                $currency = trim((string)($invoice['para_birimi'] ?? 'TRY')) ?: 'TRY';
                $link = 'index.php?p=efatura/gelen-list&search=' . rawurlencode($invoiceNo);

                $notificationCount += $notificationModel->broadcastByPermissionForFirm(
                    $firmId,
                    'efatura/gelen-list',
                    'Yeni gelen e-Fatura',
                    sprintf('%s tarafından düzenlenen %s numaralı %s %s tutarındaki fatura sisteme alındı.', $sender, $invoiceNo, $amount, $currency),
                    $link,
                    'file-plus',
                    'success',
                    UserNotificationPreferenceModel::TYPE_EINVOICE
                );
            }

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
        } catch (Throwable $e) {
            $failed++;
            error_log(sprintf('[efatura_incoming_sync] Firma %d: %s', $firmId, $e->getMessage()));
            fwrite(STDERR, sprintf("Firma %d senkronize edilemedi; hata günlüğünü kontrol edin.\n", $firmId));
        }
    }
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

exit($failed > 0 ? 1 : 0);
