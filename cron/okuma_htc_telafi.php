<?php
/**
 * Pazar operasyonlarından otomatik HTÇ kayıtlarını bağımsız olarak uzlaştırır.
 *
 * Kullanım:
 *   /opt/lampp/bin/php cron/okuma_htc_telafi.php --date=2026-10-18 --firma-id=1
 *   /opt/lampp/bin/php cron/okuma_htc_telafi.php --date=2026-10-18
 *
 * Tarih verilmezse en son tamamlanmış Pazar kullanılır. Dosya idempotenttir;
 * aynı tarih için tekrar çalıştırılabilir. Web erişimi güvenlik nedeniyle kapalıdır.
 * Önerilen bağımsız zamanlama (Pazartesi 08:10):
 *   10 8 * * 1 /opt/lampp/bin/php /opt/lampp/htdocs/ersan_elk/cron/okuma_htc_telafi.php >> /opt/lampp/htdocs/ersan_elk/cron/logs/okuma_htc_telafi.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Bu dosya yalnızca komut satırından çalıştırılabilir.';
    exit;
}

require_once dirname(__DIR__) . '/Autoloader.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Model\OkumaHtcAutomationModel;
use App\Model\SettingsModel;
use App\Model\SystemLogModel;
use App\Service\OkumaHtcAutomationService;

date_default_timezone_set('Europe/Istanbul');

function htcTelafiOutput(array $payload, int $exitCode = 0): never
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($exitCode);
}

function lastCompletedSunday(): string
{
    $today = new DateTimeImmutable('today');
    if ((int) $today->format('w') === 0) {
        return $today->modify('-7 days')->format('Y-m-d');
    }
    return $today->modify('last sunday')->format('Y-m-d');
}

$options = getopt('', ['date::', 'firma-id::', 'help']);
if (isset($options['help'])) {
    htcTelafiOutput([
        'usage' => 'php cron/okuma_htc_telafi.php [--date=YYYY-MM-DD] [--firma-id=ID]',
        'default_date' => lastCompletedSunday(),
    ]);
}

$date = trim((string) ($options['date'] ?? lastCompletedSunday()));
$day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
$dateErrors = DateTimeImmutable::getLastErrors();
if (!$day || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))
    || $day->format('Y-m-d') !== $date || (int) $day->format('w') !== 0) {
    htcTelafiOutput(['success' => false, 'message' => 'Tarih geçerli bir Pazar günü olmalıdır.', 'date' => $date], 2);
}

$requestedFirmId = isset($options['firma-id']) ? filter_var($options['firma-id'], FILTER_VALIDATE_INT) : null;
if (isset($options['firma-id']) && (!$requestedFirmId || $requestedFirmId < 1)) {
    htcTelafiOutput(['success' => false, 'message' => 'firma-id pozitif bir tam sayı olmalıdır.'], 2);
}

try {
    $model = new OkumaHtcAutomationModel();
    $schema = $model->automationSchemaStatus();
    if (!$schema['ready']) {
        htcTelafiOutput([
            'success' => false,
            'message' => 'HTÇ otomasyon migrationı bu veritabanında eksik.',
            'schema' => $schema,
        ], 3);
    }

    $firmIds = $model->activeFirmIds($requestedFirmId ?: null);
    if (!$firmIds) {
        htcTelafiOutput(['success' => false, 'message' => 'Çalıştırılacak aktif firma bulunamadı.'], 4);
    }

    $settings = new SettingsModel();
    $service = new OkumaHtcAutomationService($model);
    $results = [];

    foreach ($firmIds as $firmId) {
        $firmSettings = $settings->getAllSettingsAsKeyValue($firmId);
        $enabled = strtolower(trim((string) ($firmSettings['okuma_htc_otomatik_aktif'] ?? '1')));
        if (!in_array($enabled, ['1', 'on', 'true'], true)) {
            $results[] = ['firma_id' => $firmId, 'skipped' => true, 'message' => 'Otomatik HTÇ kapalı.'];
            continue;
        }

        $result = $service->reconcile(
            $firmId,
            $date,
            max(1, (int) ($firmSettings['okuma_htc_minimum_okuma'] ?? 10)),
            [
                'kacak' => max(1, (int) ($firmSettings['kacak_htc_minimum_is'] ?? 2)),
                'sokme_takma' => max(1, (int) ($firmSettings['sokme_takma_htc_minimum_is'] ?? 10)),
                'muhurleme' => max(1, (int) ($firmSettings['muhurleme_htc_minimum_is'] ?? 10)),
            ]
        );
        $result['firma_id'] = $firmId;
        $results[] = $result;

        (new SystemLogModel())->logAction(
            0,
            'Cron - HTÇ Telafi',
            sprintf(
                'Firma %d, tarih %s: %d oluşturuldu, %d HT dönüştürüldü, %d kaldırıldı, %d manuel kayıt atlandı.',
                $firmId,
                $date,
                $result['created'],
                $result['converted_ht'],
                $result['removed'],
                $result['skipped_manual']
            ),
            SystemLogModel::LEVEL_IMPORTANT
        );
    }

    htcTelafiOutput(['success' => true, 'date' => $date, 'results' => $results]);
} catch (Throwable $e) {
    htcTelafiOutput([
        'success' => false,
        'message' => $e->getMessage(),
        'date' => $date,
    ], 1);
}
