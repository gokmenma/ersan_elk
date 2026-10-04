<?php
namespace App\Service;

use App\Model\EInvoiceSyncJobModel;

class EInvoiceSyncWorker
{
    public function __construct(
        private EInvoiceSyncJobModel $model,
        private EInvoiceService $invoices,
        private \Closure $clientFactory,
        private ?\Closure $sleep = null,
    ) {}

    public function run(string $id): void
    {
        if (!$this->model->lock($id)) return;
        $job = null;
        $stage = 'job';
        try {
            $job = $this->model->findJob($id);
            if (!$job || !in_array($job['status'], ['queued','running'], true)) return;
            $job['status'] = 'running';
            $job['state']['failed_count'] ??= 0;
            $job['state']['validation_errors'] ??= [];
            unset($job['state']['last_error']);
            $job['state']['message'] = 'Faturalar arka planda aktarılıyor. Bu sayfayı kapatabilirsiniz.';
            $this->model->saveJob($job);
            $stage = 'settings';
            $client = ($this->clientFactory)((int)$job['firm_id']);
            while (true) {
                $state = &$job['state'];
                $stage = 'fetch';
                $items = $this->retry(function () use ($client, &$state) {
                    return $client->getInvoicePage('OUT', $state['start_date'], $state['end_date'], $state['offset'], 50, $state['created_before'] ?? null, $state['list_type'] ?? null);
                }, $job);
                $signature = hash('sha256', implode("\n", array_column($items, 'uuid')));
                if ($items && ($signature === $state['previous_signature'] || ($state['pending_signature'] !== null && $signature !== $state['pending_signature']))) {
                    throw new \InvalidArgumentException('EDM sayfası tekrarlandı veya devam edilecek kayıtlar değişti. Aktarımı kapatıp aynı tarih aralığını yeniden başlatın.');
                }
                if ($state['cursor'] > count($items)) throw new \InvalidArgumentException('EDM sayfa içeriği değişti. Aktarımı yeniden başlatın.');
                $state['pending_signature'] = $signature;
                $state['page_size'] ??= count($items);
                $this->model->saveJob($job);
                for ($i = $state['cursor']; $i < count($items); $i++) {
                    try {
                        $listType = $state['list_type'] ?? null;
                        $mapped = InvoiceStatusService::map(['status' => $items[$i]['status'] ?? '', 'status_desc' => $items[$i]['status_desc'] ?? '']);
                        if ($listType === 'giden' && $mapped['entegrator_durum_kodu'] === 'TASLAK') {
                            $state['cursor'] = $i + 1;
                            $this->model->saveJob($job);
                            continue;
                        }
                        if ($listType === 'taslak' && $mapped['entegrator_durum_kodu'] !== 'TASLAK') throw new \InvalidArgumentException('EDM taslak filtresi beklenmeyen bir durum döndürdü.');
                        if ($listType === 'giden') {
                            $stage = 'fetch';
                            $items[$i]['xml'] = $this->retry(fn() => ['xml' => $client->getInvoiceXml($items[$i]['uuid'], 'OUT')], $job)['xml'];
                        }
                        $stage = 'import';
                        $kind = $this->invoices->importSyncedInvoice((int)$job['firm_id'], $items[$i], (int)$job['user_id']);
                        $state[$kind]++;
                        $state['processed_count']++;
                    } catch (\InvalidArgumentException $e) {
                        // Invalid source data is isolated; infrastructure/database errors still pause the job.
                        $state['failed_count']++;
                        if (count($state['validation_errors']) < 50) {
                            $state['validation_errors'][] = ['uuid' => $items[$i]['uuid'], 'message' => $e->getMessage()];
                        }
                    }
                    $state['cursor'] = $i + 1;
                    $state['message'] = $state['processed_count'] . ' fatura işlendi'
                        . ($state['failed_count'] ? ', ' . $state['failed_count'] . ' fatura doğrulanamadı' : '')
                        . '. Aktarım arka planda sürüyor.';
                    $this->model->saveJob($job);
                }
                $state['offset'] += count($items);
                $state['cursor'] = 0;
                $state['pending_signature'] = null;
                $state['previous_signature'] = $signature;
                if (!$items || count($items) < $state['page_size']) {
                    $job['status'] = $state['failed_count'] > 0 ? 'partial' : 'completed';
                    $state['message'] = sprintf('Aktarım tamamlandı: %d yeni fatura, %d güncelleme.', $state['added_count'], $state['updated_count']);
                    if ($state['failed_count'] > 0) {
                        $state['message'] .= sprintf(' %d fatura doğrulanamadığı için aktarılmadı. İlk neden: %s EDM kaydını düzelttikten sonra aynı tarih aralığını yeniden çekebilirsiniz.', $state['failed_count'], $state['validation_errors'][0]['message']);
                    }
                    $this->model->saveJob($job);
                    break;
                }
                $this->model->saveJob($job);
                unset($state, $items);
            }
        } catch (\Throwable $e) {
            $diagnostic = ['type' => get_class($e), 'stage' => $stage, 'file' => basename($e->getFile()), 'line' => $e->getLine()];
            $databaseMessage = null;
            if ($e instanceof \PDOException) {
                $diagnostic['sqlstate'] = $e->errorInfo[0] ?? $e->getCode();
                $diagnostic['db_code'] = $e->errorInfo[1] ?? null;
                $databaseMessage = match ((int)($diagnostic['db_code'] ?? 0)) {
                    1054, 1146 => 'E-fatura veritabanında gerekli tablo veya alan eksik. E-fatura SQL güncellemelerini kontrol edin.',
                    1044, 1045, 1142 => 'Arka plan işçisinin veritabanı erişim yetkisi yetersiz. PHP CLI veritabanı ayarlarını kontrol edin.',
                    2002, 2003, 2006, 2013 => 'Arka plan işçisinin veritabanı bağlantısı kurulamadı veya kesildi. PHP CLI veritabanı bağlantısını kontrol edin.',
                    default => 'Aktarım sırasında veritabanı işlemi başarısız oldu.',
                };
                if (preg_match("/for column '([a-z_]+)'/i", $e->getMessage(), $match)) {
                    $diagnostic['column'] = $match[1];
                    $field = match ($match[1]) {
                        'fatura_profili' => 'fatura profili', 'fatura_tipi' => 'fatura tipi', default => 'fatura',
                    };
                    $databaseMessage = 'Veritabanındaki ' . $field . ' alanı EDM verisiyle uyumsuz. İlgili veritabanı güncellemesi kontrol edilmeli.';
                }
                $databaseMessage .= ' (SQLSTATE: ' . preg_replace('/[^A-Z0-9]/i', '', (string)$diagnostic['sqlstate'])
                    . ', kod: ' . (int)($diagnostic['db_code'] ?? 0) . ')';
            }
            $systemMessage = match ($e->getMessage()) {
                'Fatura dosya dizini oluşturulamadı.', 'Fatura XML dosyası kaydedilemedi.' => 'Fatura XML dosyası kaydedilemedi. Sunucuda storage/invoices dizininin yazma izinlerini ve boş disk alanını kontrol edin.',
                default => null,
            };
            if ($job) {
                $stageLabel = match ($stage) {
                    'settings' => 'EDM ayarlarını okuma', 'fetch' => 'EDM fatura listesini/XML içeriğini alma',
                    'import' => 'faturayı kaydetme', default => 'aktarım işini okuma/kaydetme',
                };
                $job['status'] = 'paused';
                $job['state']['last_error'] = $diagnostic;
                $job['state']['message'] = ($e instanceof EdmOperationException || $e instanceof \InvalidArgumentException)
                    ? $e->getMessage() . ' Aktarılan kayıtlar korundu.'
                    : (($databaseMessage ?? $systemMessage ?? 'Aktarım durdu. Sunucunun PHP hata günlüğündeki EDM background sync stopped kaydını kontrol edin.')
                        . ' Aşama: ' . $stageLabel . '. Aktarılan kayıtlar korundu; Devam et ile yeniden deneyin.');
                $this->model->saveJob($job);
            }
            error_log('EDM background sync stopped: ' . json_encode($diagnostic, JSON_UNESCAPED_UNICODE));
        } finally { $this->model->unlock($id); }
    }

    private function retry(\Closure $fetch, array &$job): array
    {
        for ($attempt = 0; ; $attempt++) {
            try { return $fetch(); }
            catch (EdmOperationException $e) {
                if ($e->kind !== 'connection' || $attempt >= 2) throw $e;
                $job['state']['message'] = 'EDM bağlantısı yeniden deneniyor (' . ($attempt + 1) . '/2).';
                $this->model->saveJob($job);
                if ($this->sleep) ($this->sleep)(2 ** ($attempt + 1));
                else sleep(2 ** ($attempt + 1));
            }
        }
    }
}
