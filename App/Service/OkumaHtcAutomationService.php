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
                        $this->model->softDelete((int) $autoHtc['id'], 'Okuma sayısı firma HTÇ alt limitinin altına düştü.');
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
                    $this->model->softDelete((int) $htRecord['id'], 'Okuma HTÇ otomasyonu tarafından HTÇ koduna dönüştürüldü.');
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
