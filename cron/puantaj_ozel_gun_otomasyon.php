<?php
/**
 * Puantaj özel gün otomasyonu: Pazar HTÇ, resmi tatil RTÇ ve arife fazla mesaisi.
 *
 * Kullanım:
 *   /opt/lampp/bin/php cron/puantaj_ozel_gun_otomasyon.php --run=aksam
 *   /opt/lampp/bin/php cron/puantaj_ozel_gun_otomasyon.php --run=sabah
 *   /opt/lampp/bin/php cron/puantaj_ozel_gun_otomasyon.php --date=2026-10-29 --firma-id=1 --dry-run
 *
 * aksam: bugünü ön değerlendirir (23:00). sabah: dünü kesinleştirir (08:00).
 * İdempotenttir; manuel kayıt ve kilitlere dokunmaz. Web erişimi kapalıdır.
 *   0 23 * * * /opt/lampp/bin/php /opt/lampp/htdocs/ersan_elk/cron/puantaj_ozel_gun_otomasyon.php --run=aksam >> /opt/lampp/htdocs/ersan_elk/cron/logs/puantaj_ozel_gun.log 2>&1
 *   0 8 * * * /opt/lampp/bin/php /opt/lampp/htdocs/ersan_elk/cron/puantaj_ozel_gun_otomasyon.php --run=sabah >> /opt/lampp/htdocs/ersan_elk/cron/logs/puantaj_ozel_gun.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Bu dosya yalnızca komut satırından çalıştırılabilir.';
    exit;
}

require_once dirname(__DIR__) . '/Autoloader.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Model\OkumaHtcAutomationModel;
use App\Model\SystemLogModel;
use App\Service\PuantajOzelGunOtomasyonService;

date_default_timezone_set('Europe/Istanbul');

function ozelGunOutput(array $payload, int $exitCode = 0): never
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $logDir = __DIR__ . '/logs';
    if (is_dir($logDir) && is_writable($logDir)) {
        @file_put_contents($logDir . '/puantaj_ozel_gun_' . date('Y-m') . '.log', '[' . date('Y-m-d H:i:s') . '] ' . $json . PHP_EOL, FILE_APPEND);
    }
    echo $json . PHP_EOL;
    exit($exitCode);
}

function ozelGunSummary(array $result): string
{
    $parts = [];
    foreach ($result['steps'] ?? [] as $step => $data) {
        if (isset($data['created'])) {
            $parts[] = sprintf('%s: %d eklendi, %d kaldırıldı, %d değişmedi, %d manuel atlandı%s',
                strtoupper($step), $data['created'], $data['removed'] ?? 0, $data['unchanged'] ?? 0,
                $data['skipped_manual'] ?? 0, isset($data['pending']) ? ', ' . $data['pending'] . ' beklemede' : '');
        } else {
            $parts[] = strtoupper($step) . ': ' . ($data['message'] ?? '-');
        }
    }
    return $parts ? implode(' | ', $parts) : ($result['message'] ?? 'İşlem yok.');
}

$options = getopt('', ['run::', 'date::', 'firma-id::', 'dry-run', 'help']);
if (isset($options['help'])) {
    ozelGunOutput(['usage' => 'php cron/puantaj_ozel_gun_otomasyon.php --run=aksam|sabah [--date=YYYY-MM-DD] [--firma-id=ID] [--dry-run]']);
}

$run = (string) ($options['run'] ?? '');
$phase = $run === PuantajOzelGunOtomasyonService::PHASE_AKSAM
    ? PuantajOzelGunOtomasyonService::PHASE_AKSAM
    : PuantajOzelGunOtomasyonService::PHASE_SABAH;
if ($run === '' && empty($options['date'])) {
    ozelGunOutput(['success' => false, 'message' => '--run=aksam|sabah veya --date parametresi gereklidir.'], 2);
}

$date = trim((string) ($options['date'] ?? ($phase === PuantajOzelGunOtomasyonService::PHASE_AKSAM
    ? date('Y-m-d')
    : date('Y-m-d', strtotime('-1 day')))));
$day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$day || $day->format('Y-m-d') !== $date) {
    ozelGunOutput(['success' => false, 'message' => 'Tarih YYYY-MM-DD biçiminde olmalıdır.', 'date' => $date], 2);
}

$requestedFirmId = isset($options['firma-id']) ? filter_var($options['firma-id'], FILTER_VALIDATE_INT) : null;
if (isset($options['firma-id']) && (!$requestedFirmId || $requestedFirmId < 1)) {
    ozelGunOutput(['success' => false, 'message' => 'firma-id pozitif bir tam sayı olmalıdır.'], 2);
}
$dryRun = isset($options['dry-run']);

try {
    $model = new OkumaHtcAutomationModel();
    $schema = $model->automationSchemaStatus();
    if (!$schema['ready']) {
        ozelGunOutput(['success' => false, 'message' => 'Puantaj otomasyon migrationı bu veritabanında eksik.', 'schema' => $schema], 3);
    }

    $firmIds = $model->activeFirmIds($requestedFirmId ?: null);
    if (!$firmIds) {
        ozelGunOutput(['success' => false, 'message' => 'Çalıştırılacak aktif firma bulunamadı.'], 4);
    }

    $service = new PuantajOzelGunOtomasyonService($model);
    $logger = new SystemLogModel();
    $results = [];
    $failed = false;

    foreach ($firmIds as $firmId) {
        try {
            $result = $service->runDay($firmId, $date, $phase, $dryRun);
            $results[] = $result;
            if (!$dryRun && !empty($result['steps'])) {
                $logger->logActionForFirm(
                    $firmId,
                    0,
                    'Cron - Puantaj Özel Gün',
                    sprintf('%s (%s): %s', $date, $phase, ozelGunSummary($result)),
                    SystemLogModel::LEVEL_IMPORTANT
                );
            }
        } catch (Throwable $e) {
            $failed = true;
            error_log('[puantaj_ozel_gun] Firma ' . $firmId . ' ' . $date . ': ' . $e->getMessage());
            $results[] = ['firma_id' => $firmId, 'date' => $date, 'error' => $e->getMessage()];
        }
    }

    ozelGunOutput(['success' => !$failed, 'date' => $date, 'phase' => $phase, 'dry_run' => $dryRun, 'results' => $results], $failed ? 1 : 0);
} catch (Throwable $e) {
    error_log('[puantaj_ozel_gun] ' . $e->getMessage());
    ozelGunOutput(['success' => false, 'message' => $e->getMessage(), 'date' => $date], 1);
}
