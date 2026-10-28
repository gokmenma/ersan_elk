<?php

namespace App\Service;

use App\Model\OkumaHtcAutomationModel;
use App\Model\ResmiTatilModel;
use App\Model\SettingsModel;
use DateTimeImmutable;
use RuntimeException;

/**
 * Pazar (HTÇ) ve resmi tatil (RTÇ) çalışmalarını görev kaydı ve iş sayısına göre puantaja işler.
 *
 * Bu servis yalnız Model/Service katmanında SQL çalıştırır ve tekrar çalıştırılabilir.
 * Kullanıcının manuel kilitlediği veya dönüştürülebilir kod dışında bir kod verdiği günü değiştirmez.
 */
class OkumaHtcAutomationService
{
    public const RULE = 'okuma_pazar_htc_v1';
    public const RULE_RTC = 'resmi_tatil_rtc_v1';
    public const DAY_RULES = [self::RULE, self::RULE_RTC];
    public const DEFAULT_COUNTED_DEPARTMENTS = 'okuma,kaçak,sökme,mühür';

    private const LABELS = ['okuma' => 'okuma', 'kacak' => 'kaçak işlemi', 'sokme_takma' => 'sökme-takma işlemi', 'muhurleme' => 'mühürleme işlemi'];

    public function __construct(
        private ?OkumaHtcAutomationModel $model = null,
        private ?ResmiTatilModel $holidays = null
    ) {
        $this->model ??= new OkumaHtcAutomationModel();
        $this->holidays ??= new ResmiTatilModel();
    }

    public static function optionsFromSettings(array $settings): array
    {
        $enabled = fn(string $key, string $default) => in_array((string) ($settings[$key] ?? $default), ['1', 'on', 'true'], true);
        $keywords = array_values(array_filter(array_map(
            fn($k) => mb_strtolower(trim($k), 'UTF-8'),
            explode(',', (string) ($settings['ozel_gun_sayili_departmanlar'] ?? self::DEFAULT_COUNTED_DEPARTMENTS))
        ), fn($k) => $k !== ''));

        return [
            'htc_enabled' => $enabled('okuma_htc_otomatik_aktif', '1'),
            'ozel_gun_enabled' => $enabled('puantaj_ozel_gun_otomatik_aktif', '1'),
            'require_task' => $enabled('htc_gorev_kaydi_zorunlu', '1'),
            'counted_departments' => $keywords,
            'minimum_okuma' => max(1, (int) ($settings['okuma_htc_minimum_okuma'] ?? 10)),
            'operation_minimums' => [
                'kacak' => max(1, (int) ($settings['kacak_htc_minimum_is'] ?? 2)),
                'sokme_takma' => max(1, (int) ($settings['sokme_takma_htc_minimum_is'] ?? 10)),
                'muhurleme' => max(1, (int) ($settings['muhurleme_htc_minimum_is'] ?? 10)),
            ],
        ];
    }

    public static function optionsForFirm(int $firmaId): array
    {
        return self::optionsFromSettings((new SettingsModel())->getAllSettingsAsKeyValue($firmaId));
    }

    public function reconcile(int $firmaId, string $date, int $minimumOkuma = 10, array $operationMinimums = [], ?array $options = null): array
    {
        self::assertDate($date);
        $minimumOkuma = max(1, $minimumOkuma);
        if (!self::isSunday($date)) {
            return $this->emptyResult($date, $minimumOkuma, 'Tarih pazar değil.');
        }
        $options ??= self::optionsForFirm($firmaId);

        if ($this->holidays->isFullHoliday($firmaId, $date)) {
            $result = $this->emptyResult($date, $minimumOkuma, 'Resmi tatil; RTÇ öncelikli olduğu için HTÇ uygulanmadı.');
            if (empty($options['dry_run'])) {
                $result['removed'] = $this->model->softDeleteAutomaticForDate($firmaId, $date, self::RULE,
                    'Gün resmi tatil olduğu için otomatik HTÇ yerine RTÇ uygulanır.');
            }
            return $result;
        }

        $minimums = array_merge(['okuma' => $minimumOkuma, 'kacak' => 2, 'sokme_takma' => 10, 'muhurleme' => 10], $operationMinimums);
        return $this->reconcileDay($firmaId, $date, 'HTÇ', self::RULE, ['HT'], 'Pazar günü', $minimums, $options);
    }

    public function reconcileHoliday(int $firmaId, string $date, ?array $options = null): array
    {
        self::assertDate($date);
        $options ??= self::optionsForFirm($firmaId);
        $minimums = array_merge(['okuma' => $options['minimum_okuma'] ?? 10], $options['operation_minimums'] ?? []);
        if (!$this->holidays->isFullHoliday($firmaId, $date)) {
            return $this->emptyResult($date, (int) $minimums['okuma'], 'Tarih tam gün resmi tatil değil.');
        }

        $ownTransaction = !$this->model->inTransaction();
        if ($ownTransaction) $this->model->begin();
        try {
            $this->model->softDeleteAutomaticForDate($firmaId, $date, self::RULE,
                'Gün resmi tatil olduğu için otomatik HTÇ yerine RTÇ uygulanır.');
            $result = $this->reconcileDay($firmaId, $date, 'RTÇ', self::RULE_RTC, ['HT', 'X'], 'Resmi tatilde', $minimums, $options);
            if ($ownTransaction) {
                empty($options['dry_run']) ? $this->model->commit() : $this->model->rollBack();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->model->inTransaction()) $this->model->rollBack();
            throw $e;
        }
        return $result;
    }

    private function reconcileDay(
        int $firmaId,
        string $date,
        string $targetCode,
        string $rule,
        array $convertibleCodes,
        string $dayLabel,
        array $minimums,
        array $options
    ): array {
        $ids = $this->model->definitionIds($firmaId);
        $targetId = (int) ($ids[$targetCode] ?? 0);
        if (empty($ids['HT']) || $targetId <= 0) {
            throw new RuntimeException("Aktif HT ve $targetCode puantaj tanımları bulunmalıdır.");
        }
        $requireTask = (bool) ($options['require_task'] ?? true);
        $keywords = $options['counted_departments'] ?? explode(',', self::DEFAULT_COUNTED_DEPARTMENTS);
        $dryRun = !empty($options['dry_run']);

        $workTotals = $this->workTotals($firmaId, $date);
        $tasks = $this->model->taskSummaries($firmaId, $date);
        $personelIds = array_values(array_unique(array_merge(
            array_keys($workTotals),
            $requireTask ? array_keys($tasks) : [],
            $this->model->automaticPersonnelIds($firmaId, $date, $rule)
        )));
        $departments = $this->model->personnelDepartments($firmaId, $date, $personelIds);

        $result = $this->emptyResult($date, (int) $minimums['okuma']);
        $result['code'] = $targetCode;
        $result['eligible_personnel'] = count($personelIds);

        $ownTransaction = !$this->model->inTransaction();
        if ($ownTransaction) $this->model->begin();

        try {
            foreach ($personelIds as $personelId) {
                $personelId = (int) $personelId;
                $decision = self::decide(
                    $workTotals[$personelId] ?? [],
                    $minimums,
                    isset($tasks[$personelId]),
                    self::isCountedDepartment($departments[$personelId] ?? '', $keywords),
                    $requireTask
                );
                $count = $decision['qualified_totals'] ? max($decision['qualified_totals']) : 0.0;

                if ($this->model->hasManualLock($firmaId, $personelId, $date, $rule)) {
                    $result['skipped_manual']++;
                    $result['skipped'][] = ['personel_id' => $personelId, 'code' => 'MANUEL_KİLİT', 'reading_count' => $count];
                    continue;
                }
                $classification = self::classifyRecords($this->model->activeRecordsForUpdate($personelId, $date), $date, $targetId, $convertibleCodes);
                $autoRecord = $classification['automatic_htc'];

                if (!$decision['qualified']) {
                    if ($autoRecord !== null) {
                        $this->model->softDeleteAutomatic((int) $autoRecord['id'], "$targetCode kriteri (görev kaydı / iş sayısı) artık karşılanmıyor.");
                        $result['removed']++;
                        $result['removed_ids'][] = $personelId;
                    }
                    continue;
                }
                if ($classification['blocking'] !== null) {
                    $result['skipped_manual']++;
                    $result['skipped'][] = ['personel_id' => $personelId, 'code' => (string) ($classification['blocking']['kisa_kod'] ?? ''), 'reading_count' => $count];
                    continue;
                }

                $description = self::describe($dayLabel, $targetCode, $decision);
                if ($autoRecord !== null) {
                    $this->model->updateAutomatic((int) $autoRecord['id'], $rule, $count, $description);
                    $result['unchanged']++;
                    continue;
                }
                foreach ($classification['convertible_ht'] as $record) {
                    $this->model->softDeleteAutomatic((int) $record['id'], "Puantaj otomasyonu tarafından $targetCode koduna dönüştürüldü.");
                    $result['converted_ht']++;
                }
                $this->model->insertAutomatic($personelId, $targetId, $date, $description, $rule, $count);
                $result['created']++;
                $result['created_ids'][] = $personelId;
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

    public function preview(int $firmaId, string $date, int $minimumOkuma = 10, array $operationMinimums = []): array
    {
        if (!self::isSunday($date)) throw new RuntimeException('Tarih geçerli bir Pazar günü olmalıdır.');
        $options = self::optionsForFirm($firmaId);
        $minimums = array_merge(['okuma' => max(1, $minimumOkuma), 'kacak' => 2, 'sokme_takma' => 10, 'muhurleme' => 10], $operationMinimums);
        $totals = $this->workTotals($firmaId, $date);
        $tasks = $this->model->taskSummaries($firmaId, $date);
        $people = $this->model->personnelSummaries($firmaId, array_merge(array_keys($totals), array_keys($tasks)));
        $departments = $this->model->personnelDepartments($firmaId, $date, array_keys($people));
        $ids = $this->model->definitionIds($firmaId);
        $holiday = $this->holidays->holidayFor($firmaId, $date);
        $fullHoliday = ($holiday['tur'] ?? null) === ResmiTatilModel::TAM;
        $rows = [];
        foreach ($people as $personelId => $person) {
            $records = $this->model->activeRecords($personelId, $date);
            $classification = self::classifyRecords($records, $date, (int) ($ids['HTÇ'] ?? 0));
            $counted = self::isCountedDepartment($departments[$personelId] ?? '', $options['counted_departments']);
            $decision = self::decide($totals[$personelId] ?? [], $minimums, isset($tasks[$personelId]), $counted, $options['require_task']);
            $currentCode = $records ? implode(', ', array_values(array_filter(array_map(fn($r) => $r['kisa_kod'] ?? '', $records)))) : 'HT';
            $manualLock = $this->model->hasManualLock($firmaId, $personelId, $date, self::RULE);
            $task = $tasks[$personelId] ?? null;
            $rows[] = array_merge($person, [
                'totals' => array_merge(['okuma' => 0, 'kacak' => 0, 'sokme_takma' => 0, 'muhurleme' => 0], $totals[$personelId] ?? []),
                'qualified' => $decision['qualified'] && !$fullHoliday,
                'basis' => $decision['basis'],
                'counted_department' => $counted,
                'gorev' => $task === null ? null : [
                    'basla' => substr((string) $task['ilk_basla'], 11, 5),
                    'bitis' => $task['son_bitis'] ? substr((string) $task['son_bitis'], 11, 5) : null,
                    'otomatik_bitis' => $task['otomatik_bitis'],
                    'acik' => $task['acik'],
                ],
                'current_code' => $currentCode ?: 'HT',
                'already_htc' => $classification['automatic_htc'] !== null || mb_strtoupper($currentCode) === 'HTÇ',
                'blocked' => $fullHoliday || $manualLock || $classification['blocking'] !== null,
                'block_reason' => $fullHoliday ? 'Resmi tatil (RTÇ uygulanır)'
                    : ($manualLock ? 'Manuel tercih kilitli' : ($classification['blocking'] !== null ? 'Mevcut manuel kod: ' . $currentCode : '')),
            ]);
        }
        return ['date' => $date, 'minimums' => $minimums, 'require_task' => $options['require_task'], 'holiday' => $holiday, 'rows' => $rows];
    }

    public function assignSelected(int $firmaId, string $date, array $personelIds): array
    {
        if (!self::isSunday($date)) throw new RuntimeException('Tarih geçerli bir Pazar günü olmalıdır.');
        if ($this->holidays->isFullHoliday($firmaId, $date)) throw new RuntimeException('Bu Pazar resmi tatil olduğu için HTÇ yerine RTÇ uygulanır.');
        $personelIds = array_values(array_unique(array_filter(array_map('intval', $personelIds))));
        if (!$personelIds) throw new RuntimeException('En az bir personel seçilmelidir.');
        $ids = $this->model->definitionIds($firmaId);
        $htcId = (int) ($ids['HTÇ'] ?? 0);
        if (empty($ids['HT']) || !$htcId) throw new RuntimeException('Aktif HT ve HTÇ tanımları bulunmalıdır.');
        $workTotals = $this->workTotals($firmaId, $date);
        $tasks = $this->model->taskSummaries($firmaId, $date);
        $result = ['created' => 0, 'created_ids' => [], 'unchanged' => 0, 'unchanged_ids' => [], 'skipped' => []];
        $this->model->begin();
        try {
            foreach ($personelIds as $personelId) {
                $hasWork = !empty(array_filter($workTotals[$personelId] ?? [], fn($v) => (float) $v > 0));
                if (!$hasWork && !isset($tasks[$personelId])) {
                    $result['skipped'][] = ['personel_id' => $personelId, 'reason' => 'Pazar çalışması bulunamadı.']; continue;
                }
                if ($this->model->hasManualLock($firmaId, $personelId, $date, self::RULE)) {
                    $result['skipped'][] = ['personel_id' => $personelId, 'reason' => 'Manuel tercih kilitli.']; continue;
                }
                $classification = self::classifyRecords($this->model->activeRecordsForUpdate($personelId, $date), $date, $htcId);
                if ($classification['blocking'] !== null) {
                    $result['skipped'][] = ['personel_id' => $personelId, 'reason' => 'Mevcut manuel puantaj kodu korunmuştur.']; continue;
                }
                $description = 'Pazar çalışması kontrol ekranından seçilerek HTÇ oluşturuldu.';
                if ($classification['automatic_htc'] !== null) {
                    $result['unchanged']++;
                    $result['unchanged_ids'][] = $personelId;
                    continue;
                }
                foreach ($classification['convertible_ht'] as $record) $this->model->softDeleteAutomatic((int) $record['id'], 'Pazar HTÇ kontrol ekranından HTÇ koduna dönüştürüldü.');
                $this->model->insertAutomatic($personelId, $htcId, $date, $description, self::RULE, $hasWork ? max(array_map('floatval', $workTotals[$personelId])) : 0.0);
                $result['created']++;
                $result['created_ids'][] = $personelId;
            }
            $this->model->commit();
        } catch (\Throwable $e) { if ($this->model->inTransaction()) $this->model->rollBack(); throw $e; }
        return $result;
    }

    public function lockManualOverride(
        int $firmaId,
        int $personelId,
        string $date,
        int $userId,
        string $selectedCode = ''
    ): void {
        if ($firmaId <= 0 || $personelId <= 0 || $date === '') {
            return;
        }
        foreach (self::DAY_RULES as $rule) {
            $this->model->lockManualOverride($firmaId, $personelId, $date, $rule, $userId, $selectedCode);
        }
    }

    public static function isSunday(string $date): bool
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $day !== false && $day->format('Y-m-d') === $date && (int) $day->format('w') === 0;
    }

    public static function isCountedDepartment(string $department, array $keywords): bool
    {
        $department = mb_strtolower($department, 'UTF-8');
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && mb_strpos($department, mb_strtolower($keyword, 'UTF-8')) !== false) {
                return true;
            }
        }
        return false;
    }

    /** İş sayısı üreten departmanda görev kaydı + eşik, diğer departmanlarda yalnız görev kaydı aranır. */
    public static function decide(array $totals, array $minimums, bool $hasTask, bool $countedDepartment, bool $requireTask): array
    {
        $qualified = self::qualifyingTotals($totals, $minimums);
        if (!$requireTask) {
            return ['qualified' => !empty($qualified), 'basis' => $qualified ? 'is' : null, 'qualified_totals' => $qualified];
        }
        if (!$hasTask) {
            return ['qualified' => false, 'basis' => null, 'qualified_totals' => []];
        }
        if ($countedDepartment) {
            return ['qualified' => !empty($qualified), 'basis' => $qualified ? 'is' : null, 'qualified_totals' => $qualified];
        }
        return ['qualified' => true, 'basis' => 'gorev', 'qualified_totals' => []];
    }

    /** Saf karar yardımcısı; otomasyonun manuel kayıt koruması unit testlerinde de kullanılır. */
    public static function classifyRecords(array $records, string $date, int $htcId, array $convertibleCodes = ['HT']): array
    {
        $result = ['automatic_htc' => null, 'convertible_ht' => [], 'blocking' => null];
        foreach ($records as $record) {
            $code = mb_strtoupper(trim((string) ($record['kisa_kod'] ?? '')));
            $isExactDay = ($record['baslangic_tarihi'] ?? null) === $date
                && ($record['bitis_tarihi'] ?? null) === $date;
            if (($record['kaynak'] ?? null) === 'otomatik' && (int) ($record['izin_tipi_id'] ?? 0) === $htcId) {
                $result['automatic_htc'] = $record;
                continue;
            }
            if (in_array($code, $convertibleCodes, true) && (int) ($record['manuel_kilit'] ?? 0) === 0 && $isExactDay) {
                $result['convertible_ht'][] = $record;
                continue;
            }
            $result['blocking'] = $record;
            break;
        }
        return $result;
    }

    public static function qualifyingTotals(array $totals, array $minimums): array
    {
        $qualified = [];
        foreach ($minimums as $category => $limit) {
            if ((float) ($totals[$category] ?? 0) >= max(1, (int) $limit)) {
                $qualified[$category] = (float) $totals[$category];
            }
        }
        return $qualified;
    }

    private static function describe(string $dayLabel, string $code, array $decision): string
    {
        if ($decision['basis'] === 'gorev') {
            return "$dayLabel görev başlatma kaydı ile otomatik $code oluşturuldu.";
        }
        $parts = [];
        foreach ($decision['qualified_totals'] as $category => $total) {
            $parts[] = sprintf('%g %s', $total, self::LABELS[$category] ?? $category);
        }
        return "$dayLabel " . implode(', ', $parts) . " ile otomatik $code oluşturuldu.";
    }

    private function workTotals(int $firmaId, string $date): array
    {
        $totals = $this->model->operationTotals($firmaId, $date);
        foreach ($this->model->eligibleReadingTotals($firmaId, $date) as $personelId => $total) {
            $totals[$personelId]['okuma'] = $total;
        }
        return $totals;
    }

    private static function assertDate(string $date): void
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$day || $day->format('Y-m-d') !== $date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new RuntimeException('Puantaj otomasyonu için geçersiz tarih: ' . $date);
        }
    }

    private function emptyResult(string $date, int $minimum, string $message = ''): array
    {
        return [
            'date' => $date,
            'minimum' => $minimum,
            'eligible_personnel' => 0,
            'created' => 0,
            'created_ids' => [],
            'converted_ht' => 0,
            'removed' => 0,
            'removed_ids' => [],
            'unchanged' => 0,
            'skipped_manual' => 0,
            'skipped' => [],
            'message' => $message,
        ];
    }
}
