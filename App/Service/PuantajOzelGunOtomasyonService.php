<?php

namespace App\Service;

use App\Model\OkumaHtcAutomationModel;
use App\Model\ResmiTatilModel;
use App\Model\SettingsModel;
use DateTimeImmutable;
use RuntimeException;

/**
 * Pazar HTÇ, tam gün resmi tatil RTÇ ve arife (yarım gün) fazla mesaisini tek noktadan uzlaştırır.
 * Akşam çalıştırması aynı günü ön değerlendirir; sabah çalıştırması önceki günü kesinleştirir.
 */
class PuantajOzelGunOtomasyonService
{
    public const RULE_ARIFE_FM = 'arife_fm_v1';
    public const PHASE_AKSAM = 'aksam';
    public const PHASE_SABAH = 'sabah';

    public function __construct(
        private ?OkumaHtcAutomationModel $model = null,
        private ?ResmiTatilModel $holidays = null,
        private ?OkumaHtcAutomationService $dayService = null
    ) {
        $this->model ??= new OkumaHtcAutomationModel();
        $this->holidays ??= new ResmiTatilModel();
        $this->dayService ??= new OkumaHtcAutomationService($this->model, $this->holidays);
    }

    public function runDay(int $firmaId, string $date, string $phase, bool $dryRun = false): array
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$day || $day->format('Y-m-d') !== $date) {
            throw new RuntimeException('Geçersiz tarih: ' . $date);
        }
        if (!in_array($phase, [self::PHASE_AKSAM, self::PHASE_SABAH], true)) {
            throw new RuntimeException('Geçersiz çalıştırma evresi: ' . $phase);
        }

        $options = OkumaHtcAutomationService::optionsFromSettings((new SettingsModel())->getAllSettingsAsKeyValue($firmaId));
        $options['dry_run'] = $dryRun;
        $result = ['firma_id' => $firmaId, 'date' => $date, 'phase' => $phase, 'dry_run' => $dryRun, 'steps' => []];

        if (!$options['ozel_gun_enabled']) {
            $result['message'] = 'Özel gün otomasyonu firma ayarlarında kapalı.';
            return $result;
        }

        $holiday = $this->holidays->holidayFor($firmaId, $date);
        $result['holiday'] = $holiday;

        if (($holiday['tur'] ?? null) === ResmiTatilModel::TAM) {
            $result['steps']['rtc'] = $this->dayService->reconcileHoliday($firmaId, $date, $options);
            return $result;
        }

        if (($holiday['tur'] ?? null) === ResmiTatilModel::YARIM) {
            $result['steps']['arife_fm'] = $this->reconcileArifeOvertime($firmaId, $date, $holiday, $phase, $dryRun);
        }

        if (OkumaHtcAutomationService::isSunday($date)) {
            $result['steps']['htc'] = $options['htc_enabled']
                ? $this->dayService->reconcile($firmaId, $date, $options['minimum_okuma'], $options['operation_minimums'], $options)
                : ['message' => 'Pazar HTÇ otomasyonu firma ayarlarında kapalı.'];
        }

        if (!$result['steps']) {
            $result['message'] = 'Tarih Pazar, resmi tatil veya arife değil.';
        }
        return $result;
    }

    public function reconcileArifeOvertime(int $firmaId, string $date, array $holiday, string $phase, bool $dryRun = false): array
    {
        $cutoff = substr((string) ($holiday['yarim_gun_saati'] ?? '13:00:00'), 0, 8);
        $hours = max(0.5, (float) ($holiday['fazla_mesai_saat'] ?? 4));
        $tasks = $this->model->taskSummaries($firmaId, $date);
        $personelIds = array_values(array_unique(array_merge(
            array_keys($tasks),
            $this->model->automaticOvertimePersonnelIds($firmaId, $date, self::RULE_ARIFE_FM)
        )));
        $result = ['date' => $date, 'hours' => $hours, 'cutoff' => $cutoff, 'eligible_personnel' => count($personelIds),
            'created' => 0, 'created_ids' => [], 'removed' => 0, 'removed_ids' => [], 'unchanged' => 0,
            'pending' => 0, 'skipped_manual' => 0, 'skipped' => []];
        $description = sprintf('%s: %s sonrası görev kaydı ile otomatik %g saat fazla mesai.',
            (string) ($holiday['aciklama'] ?? 'Arife'), substr($cutoff, 0, 5), $hours);

        $ownTransaction = !$this->model->inTransaction();
        if ($ownTransaction) $this->model->begin();
        try {
            foreach ($personelIds as $personelId) {
                $decision = self::arifeDecision($tasks[$personelId] ?? null, $date, $cutoff, $phase);
                if ($decision === 'pending') {
                    $result['pending']++;
                    continue;
                }
                if ($this->model->hasManualLock($firmaId, $personelId, $date, self::RULE_ARIFE_FM)) {
                    $result['skipped_manual']++;
                    $result['skipped'][] = ['personel_id' => $personelId, 'code' => 'MANUEL_KİLİT'];
                    continue;
                }
                $overtime = $this->model->activeOvertime($personelId, $date);
                $isAutomatic = $overtime !== null && ($overtime['kaynak'] ?? '') === 'otomatik'
                    && ($overtime['otomatik_kural'] ?? '') === self::RULE_ARIFE_FM;

                if ($decision === 'no') {
                    if ($isAutomatic) {
                        $this->model->softDeleteAutomaticOvertime((int) $overtime['id'], self::RULE_ARIFE_FM);
                        $result['removed']++;
                        $result['removed_ids'][] = $personelId;
                    }
                    continue;
                }
                if ($overtime === null) {
                    $this->model->insertAutomaticOvertime($firmaId, $personelId, $date, $hours, self::RULE_ARIFE_FM, $description);
                    $result['created']++;
                    $result['created_ids'][] = $personelId;
                } elseif ($isAutomatic) {
                    $this->model->updateAutomaticOvertime((int) $overtime['id'], $hours, self::RULE_ARIFE_FM, $description);
                    $result['unchanged']++;
                } else {
                    $result['skipped_manual']++;
                    $result['skipped'][] = ['personel_id' => $personelId, 'code' => 'MANUEL_FM'];
                }
            }
            if ($ownTransaction) {
                $dryRun ? $this->model->rollBack() : $this->model->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->model->inTransaction()) $this->model->rollBack();
            throw $e;
        }
        return $result;
    }

    /** Görev öğleden sonra başlatıldıysa veya kesim saatinden sonra elle bitirildiyse arife mesaisi hak edilir. */
    public static function arifeDecision(?array $task, string $date, string $cutoff, string $phase): string
    {
        if ($task === null || empty($task['son_basla'])) {
            return 'no';
        }
        $cutoffAt = $date . ' ' . $cutoff;
        if ((string) $task['son_basla'] >= $cutoffAt) {
            return 'yes';
        }
        if (!empty($task['son_manuel_bitis']) && (string) $task['son_manuel_bitis'] > $cutoffAt) {
            return 'yes';
        }
        if (!empty($task['acik']) && $phase === self::PHASE_AKSAM) {
            return 'pending';
        }
        return 'no';
    }

    public static function lockOvertimeOverride(int $firmaId, int $personelId, string $date, int $userId, float $hours): void
    {
        if ($firmaId <= 0 || $personelId <= 0 || $date === '') {
            return;
        }
        if (((new ResmiTatilModel())->holidayFor($firmaId, $date)['tur'] ?? null) !== ResmiTatilModel::YARIM) {
            return;
        }
        (new OkumaHtcAutomationModel())->lockManualOverride($firmaId, $personelId, $date, self::RULE_ARIFE_FM, $userId, 'FM:' . $hours);
    }
}
