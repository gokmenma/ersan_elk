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
            $job['state']['worker_control_version'] = 1;
            $job['state']['failed_count'] ??= 0;
            $job['state']['validation_errors'] ??= [];
            unset($job['state']['last_error']);
            $job['state']['message'] = 'Faturalar arka planda aktarılıyor. Bu sayfayı kapatabilirsiniz.';
            $this->model->saveJob($job);
            $stage = 'settings';
            $client = ($this->clientFactory)((int)$job['firm_id']);
            while (true) {
                if ($this->pauseIfRequested($job)) return;
                $state = &$job['state'];
                // EDM can reshuffle OFFSET results for broad HEADER_ONLY searches. Build
                // durable windows and split every full first page before importing it.
                // This keeps normal incoming/outgoing scans on offset zero; only a single
                // day containing 50+ invoices needs OFFSET pagination.
                $windowedList = in_array($state['list_type'] ?? null, ['gelen', 'giden'], true) && empty($state['retry_mode']);
                if ($windowedList) {
                    if (($state['date_window_version'] ?? 0) < 3) {
                        $state['date_windows'] = $this->dateWindows($state['start_date'], $state['end_date']);
                        $state['date_window'] = array_shift($state['date_windows']);
                        $state['date_window_version'] = 3;
                        // A job paused by the old OFFSET strategy safely rechecks the
                        // range. ETTN upsert turns its first 50 records into updates.
                        $this->resetPage($state);
                    }
                    if (empty($state['date_window'])) {
                        $state['date_window'] = array_shift($state['date_windows']);
                    }
                    $state['window_start'] = $state['date_window']['start'];
                    $state['window_end'] = $state['date_window']['end'];
                    $this->model->saveJob($job);
                }
                $stage = 'fetch';
                $items = $this->retry(function () use ($client, &$state) {
                    if (!empty($state['retry_mode'])) return array_slice($state['retry_items'], $state['offset'], 50);
                    if (($state['list_type'] ?? null) === 'gelen') return $client->getIncomingInvoicePage($state['window_start'], $state['window_end'], $state['offset'], $state['date_type'] ?? 'ISSUE');
                    $start = ($state['list_type'] ?? null) === 'giden' ? $state['window_start'] : $state['start_date'];
                    $end = ($state['list_type'] ?? null) === 'giden' ? $state['window_end'] : $state['end_date'];
                    return $client->getInvoicePage('OUT', $start, $end, $state['offset'], 50, $state['created_before'] ?? null, $state['list_type'] ?? null);
                }, $job);
                if ($windowedList && $state['offset'] === 0 && count($items) >= 50 && $state['window_start'] < $state['window_end']) {
                    [$left, $right] = $this->splitWindow($state['window_start'], $state['window_end']);
                    $state['date_window'] = $left;
                    array_unshift($state['date_windows'], $right);
                    $state['window_start'] = $left['start'];
                    $state['window_end'] = $left['end'];
                    $this->resetPage($state);
                    $state['message'] = 'EDM kayıt yoğunluğu nedeniyle tarih aralığı küçültülüyor. Aktarım arka planda sürüyor.';
                    $this->model->saveJob($job);
                    unset($state, $items);
                    continue;
                }
                $dateWindowComplete = $windowedList && $state['offset'] === 0 && count($items) < 50;
                $signature = hash('sha256', implode("\n", array_column($items, 'uuid')));
                if ($items && ($signature === $state['previous_signature'] || ($state['pending_signature'] !== null && $signature !== $state['pending_signature']))) {
                    throw new \InvalidArgumentException('EDM sayfası tekrarlandı veya devam edilecek kayıtlar değişti. Aktarımı kapatıp aynı tarih aralığını yeniden başlatın.');
                }
                if ($state['cursor'] > count($items)) throw new \InvalidArgumentException('EDM sayfa içeriği değişti. Aktarımı yeniden başlatın.');
                $state['pending_signature'] = $signature;
                // EDM draft searches repeat a short final page even with a larger OFFSET.
                // LIMIT is honored: a draft page below the requested 50 is the final page.
                // Also replace old checkpoints that used the first short page as page size.
                if (($state['list_type'] ?? null) === 'taslak') $state['page_size'] = 50;
                else $state['page_size'] ??= count($items);
                $this->model->saveJob($job);
                for ($i = $state['cursor']; $i < count($items); $i++) {
                    if ($this->pauseIfRequested($job)) return;
                    try {
                        $listType = $state['list_type'] ?? null;
                        $mapped = InvoiceStatusService::map(['status' => $items[$i]['status'] ?? '', 'status_desc' => $items[$i]['status_desc'] ?? '']);
                        if ($listType === 'giden' && $mapped['entegrator_durum_kodu'] === 'TASLAK') {
                            $state['cursor'] = $i + 1;
                            $this->model->saveJob($job);
                            continue;
                        }
                        if ($listType === 'taslak' && $mapped['entegrator_durum_kodu'] !== 'TASLAK') throw new \InvalidArgumentException('EDM taslak filtresi beklenmeyen bir durum döndürdü.');
                        if (in_array($listType, ['giden', 'gelen'], true) || !empty($state['retry_mode'])) {
                            $stage = 'fetch';
                            $items[$i]['xml'] = $this->retry(fn() => ['xml' => $client->getInvoiceXml($items[$i]['uuid'], $listType === 'gelen' ? 'IN' : 'OUT')], $job)['xml'];
                        }
                        $items[$i]['direction'] = $listType === 'gelen' ? 'GELEN' : 'GIDEN';
                        $stage = 'import';
                        $kind = $this->invoices->importSyncedInvoice((int)$job['firm_id'], $items[$i], (int)$job['user_id']);
                        $state[$kind]++;
                        $state['processed_count']++;
                    } catch (\InvalidArgumentException $e) {
                        // Invalid source data is isolated; infrastructure/database errors still pause the job.
                        $state['failed_count']++;
                        $state['validation_errors'][] = array_intersect_key($items[$i], array_flip(['uuid', 'fatura_no', 'issue_date', 'supplier', 'status', 'status_desc']))
                            + ['message' => $e->getMessage()];
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
                if ($dateWindowComplete || !$items || count($items) < $state['page_size']) {
                    if ($windowedList && !empty($state['date_windows'])) {
                        $state['date_window'] = array_shift($state['date_windows']);
                        $state['window_start'] = $state['date_window']['start'];
                        $state['window_end'] = $state['date_window']['end'];
                        $this->resetPage($state);
                        $this->model->saveJob($job);
                        unset($state, $items);
                        continue;
                    }
                    $job['status'] = $state['failed_count'] > 0 ? 'partial' : 'completed';
                    $state['message'] = sprintf('Aktarım tamamlandı: %d yeni fatura, %d güncelleme.', $state['added_count'], $state['updated_count']);
                    if ($state['failed_count'] > 0) {
                        $state['message'] .= sprintf(' %d fatura doğrulanamadığı için aktarılmadı. İlk neden: %s Dökümü inceleyip Aktarılamayanları yeniden dene butonunu kullanabilirsiniz.', $state['failed_count'], $state['validation_errors'][0]['message']);
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
                        'fatura_profili' => 'fatura profili', 'fatura_tipi' => 'fatura tipi', 'notlar' => 'fatura notları (notlar)',
                        default => $match[1],
                    };
                    $databaseMessage = 'Veritabanındaki ' . $field . ' alanı EDM verisiyle uyumsuz. İlgili veritabanı güncellemesi kontrol edilmeli.';
                    if ((int)$diagnostic['db_code'] === 1406 && $match[1] === 'notlar') {
                        $databaseMessage = 'Fatura notları veritabanındaki alan sınırını aşıyor. 2026_10_06_efatura_notlar_mediumtext.sql güncellemesini uygulayın.';
                    }
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

    private function pauseIfRequested(array &$job): bool
    {
        $latest = $this->model->findJob($job['id']);
        if (empty($latest['state']['pause_requested'])) return false;
        $job['status'] = 'paused';
        $job['state']['pause_requested'] = true;
        $job['state']['message'] = 'Aktarım isteğiniz üzerine durduruldu. Aktarılan faturalar korundu; Devam et ile sürdürebilirsiniz.';
        $this->model->saveJob($job);
        return true;
    }

    private function dateWindows(string $start, string $end): array
    {
        $windows = [];
        $cursor = new \DateTimeImmutable($start);
        $last = new \DateTimeImmutable($end);
        while ($cursor <= $last) {
            $windowEnd = min($last->format('Y-m-d'), $cursor->modify('+89 days')->format('Y-m-d'));
            $windows[] = ['start' => $cursor->format('Y-m-d'), 'end' => $windowEnd];
            $cursor = (new \DateTimeImmutable($windowEnd))->modify('+1 day');
        }
        return $windows;
    }

    private function splitWindow(string $start, string $end): array
    {
        $first = new \DateTimeImmutable($start);
        $last = new \DateTimeImmutable($end);
        $days = (int)$first->diff($last)->format('%a');
        $leftEnd = $first->modify('+' . intdiv($days, 2) . ' days');
        return [
            ['start' => $start, 'end' => $leftEnd->format('Y-m-d')],
            ['start' => $leftEnd->modify('+1 day')->format('Y-m-d'), 'end' => $end],
        ];
    }

    private function resetPage(array &$state): void
    {
        $state['offset'] = 0;
        $state['cursor'] = 0;
        $state['previous_signature'] = null;
        $state['pending_signature'] = null;
        $state['page_size'] = null;
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
