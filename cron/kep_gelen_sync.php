<?php
/**
 * PTT KEP gelen kutusu otomatik alım cron scripti (15 dakikada bir önerilir)
 *
 * /opt/lampp/bin/php /opt/lampp/htdocs/ersan_elk/cron/kep_gelen_sync.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/Autoloader.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
date_default_timezone_set('Europe/Istanbul');
set_time_limit(0);

use App\Model\KepSettingsModel;
use App\Model\BildirimModel;
use App\Model\SystemLogModel;
use App\Service\KepService;

$lockPath = sys_get_temp_dir() . '/ersan_kep_gelen_sync_' . md5(dirname(__DIR__)) . '.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "KEP gelen senkronizasyonu zaten çalışıyor.\n");
    exit(0);
}

fwrite(STDOUT, sprintf("[%s] KEP gelen senkronizasyonu başlatıldı.\n", date('Y-m-d H:i:s')));

$targets = (new KepSettingsModel())->getAutoSyncTargets();
if (!$targets) {
    fwrite(STDOUT, "Otomatik alımı açık KEP firması yok.\n");
    fclose($lock);
    exit(0);
}

$failed = 0;
$auditLog = new SystemLogModel();

$writeAudit = static function (int $firmId, int $userId, string $message, int $level) use ($auditLog): void {
    try {
        $auditLog->logActionForFirm($firmId, $userId, 'Cron - KEP Gelen Kutusu Senkronizasyonu', mb_substr($message, 0, 1000), $level);
    } catch (\Throwable $e) {
        error_log('cron/kep_gelen_sync.php log: ' . $e->getMessage());
    }
};

$failStampPath = static fn (int $firmId): string => sys_get_temp_dir() . '/ersan_kep_gelen_ardisik_' . md5(dirname(__DIR__)) . '_' . $firmId;

$clearFailure = static function (int $firmId) use ($failStampPath): void {
    $path = $failStampPath($firmId);
    if (is_file($path)) {
        @unlink($path);
    }
};

$notifyFailure = static function (int $firmId) use ($failStampPath): void {
    $path = $failStampPath($firmId);
    $count = is_file($path) ? ((int) @file_get_contents($path)) + 1 : 1;
    @file_put_contents($path, (string) $count, LOCK_EX);
    if ($count < 2) {
        return;
    }

    $stampPath = sys_get_temp_dir() . '/ersan_kep_gelen_hata_' . md5(dirname(__DIR__)) . '_' . $firmId;
    if (is_file($stampPath) && (time() - (int) filemtime($stampPath)) < 21600) {
        return;
    }
    @touch($stampPath);
    try {
        (new BildirimModel())->broadcastByPermissionForFirm(
            $firmId,
            'evrak-takip/kep',
            'Otomatik KEP Alımı Başarısız',
            'Gelen KEP iletileri ardışık ' . $count . ' denemede otomatik alınamadı. KEP ayarlarını ve otomatik aktarım hesabını kontrol edin.',
            'evrak-takip/list',
            'error',
            'danger'
        );
    } catch (\Throwable $e) {
        error_log('cron/kep_gelen_sync.php bildirim: ' . $e->getMessage());
    }
};

$syncWithRetry = static function (int $firmId, int $userId): array {
    $maxAttempts = 3;
    $result = ['status' => 'error', 'message' => 'Bilinmeyen hata'];
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            $_SESSION = ['firma_id' => $firmId, 'firm_id' => $firmId, 'user_id' => $userId, 'id' => $userId];
            $result = (new KepService())->autoSyncIncoming($firmId, $userId);
            if (($result['status'] ?? '') === 'success') {
                return $result;
            }
        } catch (\Throwable $e) {
            error_log("cron/kep_gelen_sync.php [Firma {$firmId}] deneme {$attempt}: " . $e->getMessage());
            $result = ['status' => 'error', 'message' => $e->getMessage()];
        }
        if ($attempt < $maxAttempts) {
            sleep(30 * $attempt);
        }
    }
    $result['message'] = ($result['message'] ?? 'Bilinmeyen hata') . ' (' . $maxAttempts . ' deneme yapıldı)';
    return $result;
};

foreach ($targets as $target) {
    $firmId = (int) $target['firm_id'];
    $userId = (int) $target['user_id'];
    $result = $syncWithRetry($firmId, $userId);
    if (($result['status'] ?? '') === 'success') {
        $clearFailure($firmId);
        fwrite(STDOUT, sprintf("Firma %d: %s\n", $firmId, $result['message'] ?? ''));
        $uyarilar = $result['hatalar'] ?? [];
        $writeAudit(
            $firmId,
            $userId,
            'Firma ' . $firmId . ': ' . ($result['message'] ?? 'Tamamlandı') . ($uyarilar ? ' Uyarılar: ' . implode(' | ', array_map('strval', $uyarilar)) : ''),
            $uyarilar ? SystemLogModel::LEVEL_IMPORTANT : SystemLogModel::LEVEL_INFO
        );
        foreach ($uyarilar as $hata) {
            fwrite(STDERR, sprintf("Firma %d uyarı: %s\n", $firmId, $hata));
        }
    } else {
        $failed++;
        fwrite(STDERR, sprintf("Firma %d hata: %s\n", $firmId, $result['message'] ?? 'Bilinmeyen hata'));
        $writeAudit($firmId, $userId, 'Başarısız: ' . ($result['message'] ?? 'Bilinmeyen hata'), SystemLogModel::LEVEL_CRITICAL);
        $notifyFailure($firmId);
    }
}

fclose($lock);
exit($failed > 0 ? 1 : 0);
