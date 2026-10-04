<?php
namespace App\Service;

use App\Helper\Security;
use App\Model\EInvoiceSyncJobModel;

class EInvoiceSyncJobService
{
    public function __construct(private ?EInvoiceSyncJobModel $model = null, private ?\Closure $launcher = null)
    {
        $this->model ??= new EInvoiceSyncJobModel();
    }

    public function start(int $firm, int $user, string $start, string $end, string $listType = 'taslak'): array
    {
        if (!in_array($listType, ['taslak', 'giden'], true)) throw new \InvalidArgumentException('Geçersiz aktarım türü.');
        foreach ([$start, $end] as $date) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new \InvalidArgumentException('Geçerli tarih seçin.');
        }
        if ($start > $end) throw new \InvalidArgumentException('Başlangıç tarihi bitiş tarihinden sonra olamaz.');
        $job = $this->model->create($firm, $user, [
            'list_type' => $listType, 'start_date' => $start, 'end_date' => $end, 'offset' => 0, 'cursor' => 0,
            'created_before' => min($end . 'T23:59:59', date('Y-m-d\TH:i:s')),
            'processed_count' => 0, 'added_count' => 0, 'updated_count' => 0, 'failed_count' => 0, 'validation_errors' => [],
            'page_size' => null, 'previous_signature' => null, 'pending_signature' => null,
            'message' => 'Aktarım arka planda başlatılıyor.',
        ]);
        if ($job['status'] === 'queued') $this->launch($job);
        return $this->present($this->model->findJob($job['id']));
    }

    public function status(int $firm, int $user, ?string $token = null, ?string $listType = null): ?array
    {
        $job = $token ? $this->owned($firm, $user, $token) : ($listType === null ? $this->model->latest($firm, $user) : $this->model->latestForList($firm, $user, $listType));
        if (!$job) return null;
        // Restart an interrupted worker; its DB lock prevents two workers importing together.
        if (in_array($job['status'], ['queued', 'running'], true) && strtotime($job['updated_at']) < time() - 180) {
            $this->launch($job);
            $job = $this->model->findJob($job['id']);
        }
        return $this->present($job);
    }

    public function resume(int $firm, int $user, string $token): array
    {
        $job = $this->owned($firm, $user, $token);
        if ($job['status'] === 'paused') {
            if (!$this->model->lock($job['id'])) throw new \RuntimeException('Aktarım hâlâ sürüyor.');
            try {
                $job = $this->owned($firm, $user, $token);
                $job['status'] = 'queued';
                $job['state']['message'] = 'Aktarım kaldığı yerden devam ediyor.';
                $job['state']['launch_attempts'] = 0;
                $this->model->saveJob($job);
            } finally { $this->model->unlock($job['id']); }
            $this->launch($job);
        }
        return $this->present($this->model->findJob($job['id']));
    }

    public function cancel(int $firm, int $user, string $token): array
    {
        $job = $this->owned($firm, $user, $token);
        // A running worker must not overwrite a cancellation checkpoint.
        if (!$this->model->lock($job['id'])) throw new \RuntimeException('Aktarım sürüyor; durduktan sonra yeni aktarım başlatabilirsiniz.');
        try {
            $job['status'] = 'cancelled';
            $job['state']['message'] = 'Aktarım kapatıldı. Aktarılan faturalar korunuyor.';
            $this->model->saveJob($job);
        } finally { $this->model->unlock($job['id']); }
        return $this->present($job);
    }

    private function owned(int $firm, int $user, string $token): array
    {
        $id = Security::decrypt($token);
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) throw new \InvalidArgumentException('Geçersiz aktarım kimliği.');
        $job = $this->model->findJob($id);
        if (!$job || (int)$job['firm_id'] !== $firm || (int)$job['user_id'] !== $user) throw new \InvalidArgumentException('Aktarım bulunamadı.');
        return $job;
    }

    private function present(array $job): array
    {
        return array_intersect_key($job['state'], array_flip(['list_type','start_date','end_date','processed_count','added_count','updated_count','failed_count','message'])) + [
            'job_token' => Security::encrypt($job['id']), 'job_status' => $job['status'], 'updated_at' => $job['updated_at'], 'created_at' => $job['created_at'],
        ];
    }

    private function launch(array $job): void
    {
        // Do not rewrite a checkpoint while a worker owns it.
        if (!$this->model->lock($job['id'])) return;
        try {
            $job = $this->model->findJob($job['id']);
            if (!in_array($job['status'], ['queued', 'running'], true)) return;
            $attempts = ($job['state']['launch_attempts'] ?? 0) + 1;
            $job['state']['launch_attempts'] = $attempts;
            if ($attempts > 3) {
                $job['status'] = 'paused';
                $job['state']['message'] = 'Arka plan işçisi yanıt vermiyor. PHP CLI ayarını kontrol edin; Devam et ile yeniden deneyebilirsiniz.';
                $this->model->saveJob($job);
                return;
            }
            $job['status'] = 'queued';
            $this->model->saveJob($job);
        } finally { $this->model->unlock($job['id']); }
        $launched = false;
        try {
            if ($this->launcher) $launched = (bool)($this->launcher)($job['id']);
            elseif (PHP_OS_FAMILY !== 'Windows' && function_exists('exec')) {
                $binary = $_ENV['EFATURA_PHP_BINARY'] ?? (PHP_BINDIR . '/php');
                $script = dirname(__DIR__, 2) . '/cron/efatura_sync_worker.php';
                if (is_executable($binary)) {
                    exec('nohup ' . escapeshellarg($binary) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($job['id']) . ' > /dev/null 2>&1 < /dev/null &', $output, $code);
                    $launched = $code === 0;
                }
            }
        } catch (\Throwable $e) { error_log('EDM worker launch failed: ' . get_class($e)); }
        if (!$launched && $this->model->lock($job['id'])) {
            try {
                $job = $this->model->findJob($job['id']);
                if ($job['status'] === 'queued') {
                    $job['status'] = 'paused';
                    $job['state']['message'] = 'Arka plan işçisi başlatılamadı. Sunucudaki PHP CLI ayarını kontrol edin ve Devam et ile yeniden deneyin.';
                    $this->model->saveJob($job);
                }
            } finally { $this->model->unlock($job['id']); }
        }
    }
}
