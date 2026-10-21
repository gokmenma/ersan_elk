<?php

namespace App\Service;

use App\Model\OkumaHtcAutomationModel;
use DateTimeImmutable;
use RuntimeException;

/**
 * Endeks okuma verisinden pazar günü HTÇ puantajı üretir.
 *
 * Bu servis yalnız Model/Service katmanında SQL çalıştırır ve tekrar çalıştırılabilir.
 * Kullanıcının manuel kilitlediği veya HT dışındaki bir kod verdiği günü değiştirmez.
 */
class OkumaHtcAutomationService
{
    public const RULE = 'okuma_pazar_htc_v1';

    public function __construct(private ?OkumaHtcAutomationModel $model = null)
    {
        $this->model ??= new OkumaHtcAutomationModel();
    }

    public function reconcile(int $firmaId, string $date, int $minimumOkuma = 10, array $operationMinimums = []): array
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$day || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new RuntimeException('HTÇ değerlendirmesi için geçersiz tarih: ' . $date);
        }
        if (!self::isSunday($date)) {
            return $this->emptyResult($date, max(1, $minimumOkuma), 'Tarih pazar değil.');
        }

        $minimumOkuma = max(1, $minimumOkuma);
        $minimums = array_merge(['okuma' => $minimumOkuma, 'kacak' => 2, 'sokme_takma' => 10, 'muhurleme' => 10], $operationMinimums);
        $ids = $this->model->definitionIds($firmaId);
        $htcId = (int) ($ids['HTÇ'] ?? 0);
        if (empty($ids['HT']) || $htcId <= 0) {
            throw new RuntimeException('Aktif HT ve HTÇ puantaj tanımları bulunmalıdır.');
        }
        $workTotals = $this->model->operationTotals($firmaId, $date);
        foreach ($this->model->eligibleReadingTotals($firmaId, $date) as $personelId => $total) {
            $workTotals[$personelId]['okuma'] = $total;
        }

        $result = $this->emptyResult($date, $minimumOkuma);
        $result['eligible_personnel'] = count($workTotals);

        $ownTransaction = !$this->model->inTransaction();
        if ($ownTransaction) {
            $this->model->begin();
        }

        try {
            $personelIds = array_values(array_unique(array_merge(
                array_keys($workTotals),
                $this->model->automaticPersonnelIds($firmaId, $date, self::RULE)
            )));

            foreach ($personelIds as $personelId) {
                $personTotals = $workTotals[$personelId] ?? [];
                $qualified = self::qualifyingTotals($personTotals, $minimums);
                $readingCount = empty($qualified) ? 0.0 : max($qualified);
                if ($this->model->hasManualLock($firmaId, (int) $personelId, $date, self::RULE)) {
                    $result['skipped_manual']++;
                    $result['skipped'][] = [
                        'personel_id' => (int) $personelId,
                        'code' => 'MANUEL_KİLİT',
                        'reading_count' => $readingCount,
                    ];
                    continue;
                }
                $records = $this->model->activeRecordsForUpdate((int) $personelId, $date);

                $classification = self::classifyRecords($records, $date, $htcId);
                $autoHtc = $classification['automatic_htc'];
                $convertibleHt = $classification['convertible_ht'];
                $blocking = $classification['blocking'];

                if (empty($qualified)) {
                    if ($autoHtc !== null) {
                        $this->model->softDeleteAutomatic((int) $autoHtc['id'], 'Okuma sayısı firma HTÇ alt limitinin altına düştü.');
                        $result['removed']++;
                    }
                    continue;
                }

                if ($blocking !== null) {
                    $result['skipped_manual']++;
                    $result['skipped'][] = [
                        'personel_id' => (int) $personelId,
                        'code' => (string) ($blocking['kisa_kod'] ?? ''),
                        'reading_count' => $readingCount,
                    ];
                    continue;
                }

                $labels = ['okuma' => 'okuma', 'kacak' => 'kaçak işlemi', 'sokme_takma' => 'sökme-takma işlemi', 'muhurleme' => 'mühürleme işlemi'];
                $parts = [];
                foreach ($qualified as $category => $total) {
                    $parts[] = sprintf('%g %s', $total, $labels[$category] ?? $category);
                }
                $description = 'Pazar günü ' . implode(', ', $parts) . ' ile otomatik HTÇ oluşturuldu.';
                if ($autoHtc !== null) {
                    $this->model->updateAutomatic((int) $autoHtc['id'], self::RULE, $readingCount, $description);
                    $result['unchanged']++;
                    continue;
                }

                foreach ($convertibleHt as $htRecord) {
                    $this->model->softDeleteAutomatic((int) $htRecord['id'], 'Okuma HTÇ otomasyonu tarafından HTÇ koduna dönüştürüldü.');
                    $result['converted_ht']++;
                }

                $this->model->insertAutomatic((int) $personelId, $htcId, $date, $description, self::RULE, $readingCount);
                $result['created']++;
            }

            if ($ownTransaction) {
                $this->model->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->model->inTransaction()) {
                $this->model->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    public function preview(int $firmaId, string $date, int $minimumOkuma = 10, array $operationMinimums = []): array
    {
        if (!self::isSunday($date)) throw new RuntimeException('Tarih geçerli bir Pazar günü olmalıdır.');
        $minimums = array_merge(['okuma' => max(1, $minimumOkuma), 'kacak' => 2, 'sokme_takma' => 10, 'muhurleme' => 10], $operationMinimums);
        $totals = $this->model->operationTotals($firmaId, $date);
        foreach ($this->model->eligibleReadingTotals($firmaId, $date) as $personelId => $total) $totals[$personelId]['okuma'] = $total;
        $people = $this->model->personnelSummaries($firmaId, array_keys($totals));
        $ids = $this->model->definitionIds($firmaId);
        $rows = [];
        foreach ($people as $personelId => $person) {
            $records = $this->model->activeRecords($personelId, $date);
            $classification = self::classifyRecords($records, $date, (int) ($ids['HTÇ'] ?? 0));
            $qualified = self::qualifyingTotals($totals[$personelId] ?? [], $minimums);
            $currentCode = $records ? implode(', ', array_values(array_filter(array_map(fn($r) => $r['kisa_kod'] ?? '', $records)))) : 'HT';
            $manualLock = $this->model->hasManualLock($firmaId, $personelId, $date, self::RULE);
            $rows[] = array_merge($person, [
                'totals' => array_merge(['okuma' => 0, 'kacak' => 0, 'sokme_takma' => 0, 'muhurleme' => 0], $totals[$personelId] ?? []),
                'qualified' => !empty($qualified), 'current_code' => $currentCode ?: 'HT',
                'already_htc' => $classification['automatic_htc'] !== null || mb_strtoupper($currentCode) === 'HTÇ',
                'blocked' => $manualLock || $classification['blocking'] !== null,
                'block_reason' => $manualLock ? 'Manuel tercih kilitli' : ($classification['blocking'] !== null ? 'Mevcut manuel kod: ' . $currentCode : ''),
            ]);
        }
        return ['date' => $date, 'minimums' => $minimums, 'rows' => $rows];
    }

    public function assignSelected(int $firmaId, string $date, array $personelIds): array
    {
        if (!self::isSunday($date)) throw new RuntimeException('Tarih geçerli bir Pazar günü olmalıdır.');
        $personelIds = array_values(array_unique(array_filter(array_map('intval', $personelIds))));
        if (!$personelIds) throw new RuntimeException('En az bir personel seçilmelidir.');
        $ids = $this->model->definitionIds($firmaId);
        $htcId = (int) ($ids['HTÇ'] ?? 0);
        if (empty($ids['HT']) || !$htcId) throw new RuntimeException('Aktif HT ve HTÇ tanımları bulunmalıdır.');
        $workTotals = $this->model->operationTotals($firmaId, $date);
        foreach ($this->model->eligibleReadingTotals($firmaId, $date) as $pid => $total) $workTotals[$pid]['okuma'] = $total;
        $result = ['created' => 0, 'created_ids' => [], 'unchanged' => 0, 'unchanged_ids' => [], 'skipped' => []];
        $this->model->begin();
        try {
            foreach ($personelIds as $personelId) {
                if (empty(array_filter($workTotals[$personelId] ?? [], fn($v) => (float) $v > 0))) {
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
                $this->model->insertAutomatic($personelId, $htcId, $date, $description, self::RULE, max(array_map('floatval', $workTotals[$personelId])));
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
        $this->model->lockManualOverride($firmaId, $personelId, $date, self::RULE, $userId, $selectedCode);
    }

    public static function isSunday(string $date): bool
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $day !== false && $day->format('Y-m-d') === $date && (int) $day->format('w') === 0;
    }

    /** Saf karar yardımcısı; otomasyonun manuel kayıt koruması unit testlerinde de kullanılır. */
    public static function classifyRecords(array $records, string $date, int $htcId): array
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
            if ($code === 'HT' && (int) ($record['manuel_kilit'] ?? 0) === 0 && $isExactDay) {
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

    private function emptyResult(string $date, int $minimum, string $message = ''): array
    {
        return [
            'date' => $date,
            'minimum' => $minimum,
            'eligible_personnel' => 0,
            'created' => 0,
            'converted_ht' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'skipped_manual' => 0,
            'skipped' => [],
            'message' => $message,
        ];
    }
}
