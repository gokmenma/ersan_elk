<?php
/**
 * UETS (Ulusal Elektronik Tebligat Sistemi) Periyodik Senkronizasyon Cron Scripti
 * 
 * Çalıştırma örneği:
 * /opt/lampp/bin/php /opt/lampp/htdocs/ersan_elk/cron/uets_sync.php
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

use App\Model\UetsSettingsModel;
use App\Service\UetsSyncService;
use App\Model\SystemLogModel;

$lockPath = sys_get_temp_dir() . '/ersan_uets_sync_' . md5(dirname(__DIR__)) . '.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "UETS senkronizasyonu zaten çalışıyor.\n");
    exit(0);
}

$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul'));
fwrite(STDOUT, sprintf("[%s] UETS Senkronizasyonu başlatıldı.\n", $now->format('Y-m-d H:i:s')));

$failed = 0;
$settingsModel = new UetsSettingsModel();
$syncService = new UetsSyncService();
$activeFirmIds = $settingsModel->getActiveFirmIds();

if (empty($activeFirmIds)) {
    fwrite(STDOUT, "Otomatik senkronizasyonu aktif olan UETS firması bulunamadı.\n");
    fclose($lock);
    exit(0);
}

foreach ($activeFirmIds as $firmId) {
    try {
        fwrite(STDOUT, sprintf("Firma %d için senkronizasyon çalıştırılıyor...\n", $firmId));
        $result = $syncService->syncIncoming($firmId);
        
        if (($result['status'] ?? '') === 'success') {
            fwrite(STDOUT, sprintf(
                "Firma %d: Başarılı. %d yeni tebligat, %d güncellenen kayıt.\n",
                $firmId,
                (int) ($result['yeni_gelen'] ?? 0),
                (int) ($result['guncellenen'] ?? 0)
            ));
        } else {
            $failed++;
            fwrite(STDERR, sprintf("Firma %d Hata: %s\n", $firmId, $result['message'] ?? 'Bilinmeyen hata'));
        }
    } catch (\Throwable $e) {
        $failed++;
        fwrite(STDERR, sprintf("Firma %d Exception: %s\n", $firmId, $e->getMessage()));
        error_log("cron/uets_sync.php Error [Firm {$firmId}]: " . $e->getMessage());
    }

    try {
        $firmSettings = $settingsModel->getSettings($firmId);
        $sinceDate = trim((string) ($firmSettings['otomatik_aktarim_baslangic'] ?? ''));
        if ($sinceDate !== '') {
            $reminded = (new \App\Model\EvrakSevkModel())->remindUnforwarded($firmId, $sinceDate);
            fwrite(STDOUT, sprintf("Firma %d: Sevk bekleyen UETS evrakı hatırlatması %d kullanıcıya gönderildi.\n", $firmId, $reminded));
        }
    } catch (\Throwable $e) {
        error_log("cron/uets_sync.php Hatırlatma Hatası [Firm {$firmId}]: " . $e->getMessage());
    }
}

fwrite(STDOUT, sprintf("[%s] UETS Senkronizasyonu tamamlandı. Hata sayısı: %d\n", date('Y-m-d H:i:s'), $failed));
fclose($lock);
exit($failed > 0 ? 1 : 0);
