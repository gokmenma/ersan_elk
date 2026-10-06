<?php

use App\Helper\Security;
use App\Model\EInvoiceSyncJobModel;
use App\Service\EdmOperationException;
use App\Service\EdmSoapClient;
use App\Service\EInvoiceService;
use App\Service\EInvoiceSyncJobService;
use App\Service\EInvoiceSyncWorker;
use PHPUnit\Framework\TestCase;

final class SyncMemoryJobs extends EInvoiceSyncJobModel
{
    public array $job;
    private array $locks = [];
    public function __construct()
    {
        $this->job = ['id' => str_repeat('a', 32), 'firm_id' => 2, 'user_id' => 3, 'status' => 'queued', 'updated_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'), 'state' => [
            'start_date' => '2026-10-01', 'end_date' => '2026-10-04', 'offset' => 0, 'cursor' => 0,
            'processed_count' => 0, 'added_count' => 0, 'updated_count' => 0, 'page_size' => null,
            'pending_signature' => null, 'previous_signature' => null, 'message' => '',
        ]];
    }
    public function lock(string $name): bool { if (isset($this->locks[$name])) return false; $this->locks[$name] = true; return true; }
    public function unlock(string $name): void { unset($this->locks[$name]); }
    public function findJob(string $id): ?array { return $id === $this->job['id'] ? unserialize(serialize($this->job)) : null; }
    public function latest(int $firmId, int $userId): ?array { return $this->job['firm_id'] === $firmId && $this->job['user_id'] === $userId ? $this->findJob($this->job['id']) : null; }
    public function requestPause(string $id, int $firmId, int $userId, bool $requested): void { $this->job['state']['pause_requested'] = $requested; }
    public function saveJob(array $job): void { $job['state']['pause_requested'] = $this->job['state']['pause_requested'] ?? false; $this->job = unserialize(serialize($job)); $this->job['updated_at'] = date('Y-m-d H:i:s'); }
}
final class SyncOfflinePages extends EdmSoapClient
{
    public array $offsets = [];
    public array $xmlRequests = [];
    public array $xmlDirections = [];
    public array $incomingRanges = [];
    public function __construct(private Closure $fetch) {}
    public function getInvoicePage(string $direction, string $startDate, string $endDate, int $offset = 0, int $limit = 50, ?string $createdBefore = null, ?string $listType = null): array
    {
        $this->offsets[] = $offset;
        return ($this->fetch)($offset);
    }
    public function getIncomingInvoicePage(string $startDate, string $endDate, int $offset = 0, string $dateType = 'ISSUE'): array
    {
        $this->offsets[] = $offset;
        $this->incomingRanges[] = [$startDate, $endDate, $offset, $dateType];
        return ($this->fetch)($offset, $startDate, $endDate);
    }
    public function getInvoiceXml(string $uuid, string $direction = 'OUT'): string { $this->xmlRequests[] = $uuid; $this->xmlDirections[] = $direction; return '<Invoice/>'; }
}
final class SyncOfflineImporter extends EInvoiceService
{
    public array $imported = [];
    public array $directions = [];
    public ?string $failUuid = null;
    public ?PDOException $dbError = null;
    public ?RuntimeException $systemError = null;
    public ?string $invalidUuid = null;
    public ?Closure $onImport = null;
    public function __construct() {}
    public function importSyncedInvoice(int $firmId, array $item, int $userId): string
    {
        if ($this->dbError) throw $this->dbError;
        if ($this->systemError) throw $this->systemError;
        if ($item['uuid'] === $this->invalidUuid) throw new InvalidArgumentException('XML fatura numarası geçersiz.');
        if ($item['uuid'] === $this->failUuid) throw new RuntimeException('Temporary record lock');
        $this->imported[] = [$item['uuid'], $firmId, $userId];
        $this->directions[] = $item['direction'] ?? 'GIDEN';
        if ($this->onImport) ($this->onImport)();
        return 'added_count';
    }
}
final class EInvoiceSyncWorkerTest extends TestCase
{
    private function items(int $start, int $count): array { return array_map(fn($i) => ['uuid' => 'uuid-' . $i, 'xml' => '<Invoice/>', 'status' => 'LOAD - SUCCEED'], range($start, $start + $count - 1)); }
    private function worker(SyncMemoryJobs $jobs, SyncOfflinePages $pages, SyncOfflineImporter $importer, ?Closure $sleep = null): EInvoiceSyncWorker
    {
        return new EInvoiceSyncWorker($jobs, $importer, fn() => $pages, $sleep ?? fn() => null);
    }
    public function testIncomingImportsMoreThanOneThousandInvoicesInBackground(): void
    {
        $jobs = new SyncMemoryJobs();
        $jobs->job['state']['list_type'] = 'gelen';
        $jobs->job['state']['start_date'] = '2026-01-01';
        $jobs->job['state']['end_date'] = '2026-10-06';
        $importer = new SyncOfflineImporter();
        $pages = new SyncOfflinePages(fn($offset, $start) => $start === '2026-01-01' && $offset < 1004 ? $this->items($offset, min(50, 1004 - $offset)) : []);
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame('completed', $jobs->job['status']);
        self::assertSame(1004, $jobs->job['state']['processed_count']);
        self::assertCount(1004, $importer->imported);
        self::assertCount(1004, $pages->xmlRequests);
        self::assertSame(['IN'], array_values(array_unique($pages->xmlDirections)));
        self::assertSame(['GELEN'], array_values(array_unique($importer->directions)));
        self::assertSame(array_merge(range(0, 1000, 50), [0, 0, 0]), $pages->offsets);
        self::assertSame([['2026-01-01', '2026-03-31'], ['2026-04-01', '2026-06-29'], ['2026-06-30', '2026-09-27'], ['2026-09-28', '2026-10-06']], array_values(array_unique(array_map(fn($range) => array_slice($range, 0, 2), $pages->incomingRanges), SORT_REGULAR)));
    }

    public function testIncomingResumesInFailedWindowWithoutSkippingEmptyWindows(): void
    {
        $jobs = new SyncMemoryJobs();
        $jobs->job['state']['list_type'] = 'gelen';
        $jobs->job['state']['date_type'] = 'CREATE';
        $jobs->job['state']['start_date'] = '2026-01-01';
        $jobs->job['state']['end_date'] = '2026-10-06';
        $importer = new SyncOfflineImporter();
        $pages = new SyncOfflinePages(function($offset, $start) {
            if ($start === '2026-04-01') throw new EdmOperationException('business', 'Temporary failure');
            return $start === '2026-01-01' && $offset === 0 ? $this->items(0, 1) : [];
        });
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame('paused', $jobs->job['status']);
        self::assertSame('2026-04-01', $jobs->job['state']['window_start']);
        self::assertSame(1, $jobs->job['state']['processed_count']);
        $jobs->job['status'] = 'queued';
        $pages = new SyncOfflinePages(fn($offset, $start) => $start === '2026-09-28' && $offset === 0 ? $this->items(1, 1) : []);
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame('completed', $jobs->job['status']);
        self::assertSame(2, $jobs->job['state']['processed_count']);
        self::assertSame(['uuid-0', 'uuid-1'], array_column($importer->imported, 0));
        self::assertSame('CREATE', $pages->incomingRanges[0][3]);
    }

    public function testPauseBetweenInvoicesPreservesCursorAndResumeImportsRemaining(): void
    {
        $jobs = new SyncMemoryJobs(); $jobs->job['state']['list_type'] = 'gelen';
        $pages = new SyncOfflinePages(fn($offset) => $offset === 0 ? $this->items(0, 3) : []);
        $importer = new SyncOfflineImporter();
        $importer->onImport = fn() => $jobs->requestPause($jobs->job['id'], 2, 3, true);
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame('paused', $jobs->job['status']);
        self::assertSame(1, $jobs->job['state']['cursor']);
        self::assertSame(['uuid-0'], array_column($importer->imported, 0));
        $jobs->requestPause($jobs->job['id'], 2, 3, false);
        $jobs->job['status'] = 'queued'; $importer->onImport = null;
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame('completed', $jobs->job['status']);
        self::assertSame(['uuid-0', 'uuid-1', 'uuid-2'], array_column($importer->imported, 0));
    }

    public function testRetryFetchesOnlyFailedUuidsAndNeverScansDateRange(): void
    {
        $jobs = new SyncMemoryJobs(); $jobs->job['state']['list_type'] = 'gelen';
        $jobs->job['state']['retry_mode'] = true;
        $jobs->job['state']['retry_items'] = [['uuid' => 'failed-uuid', 'status' => '', 'status_desc' => '', 'xml' => '']];
        $pages = new SyncOfflinePages(fn() => throw new RuntimeException('Date range must not be scanned'));
        $importer = new SyncOfflineImporter();
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame('completed', $jobs->job['status']);
        self::assertSame(['failed-uuid'], $pages->xmlRequests);
        self::assertSame(['failed-uuid'], array_column($importer->imported, 0));
        self::assertSame([], $pages->incomingRanges);
    }

    public function testErrorReportKeepsMoreThanFiftyFailuresAndInvoiceMetadata(): void
    {
        $jobs = new SyncMemoryJobs(); $jobs->job['state']['list_type'] = 'gelen';
        $pages = new SyncOfflinePages(fn($offset) => $offset < 53 ? array_map(fn($item) => $item + ['fatura_no' => 'ABC2026000000001', 'issue_date' => '2026-10-01', 'supplier' => 'Offline supplier'], $this->items($offset, min(50, 53 - $offset))) : []);
        $importer = new SyncOfflineImporter();
        $importer->onImport = null;
        // Use an importer whose individual source records are all invalid.
        $invalid = new class extends EInvoiceService {
            public function __construct() {}
            public function importSyncedInvoice(int $firmId, array $item, int $userId): string { throw new InvalidArgumentException('Invalid source'); }
        };
        (new EInvoiceSyncWorker($jobs, $invalid, fn() => $pages))->run($jobs->job['id']);
        self::assertSame('partial', $jobs->job['status']);
        self::assertCount(53, $jobs->job['state']['validation_errors']);
        self::assertSame('ABC2026000000001', $jobs->job['state']['validation_errors'][52]['fatura_no']);
        self::assertSame('Offline supplier', $jobs->job['state']['validation_errors'][52]['supplier']);
    }

    public function testServerCapAndShortLastPageAreFullyImported(): void
    {
        $jobs = new SyncMemoryJobs(); $importer = new SyncOfflineImporter();
        $pages = new SyncOfflinePages(fn($offset) => $this->items($offset, $offset === 4 ? 1 : 2));
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame([0, 2, 4], $pages->offsets);
        self::assertCount(5, $importer->imported);
        self::assertSame(['uuid-0', 2, 3], $importer->imported[0]);
        self::assertSame('completed', $jobs->job['status']);
        self::assertSame(5, $jobs->job['state']['processed_count']);
    }
    public function testShortDraftPageCompletesWithoutRequestingRepeatedPage(): void
    {
        $jobs = new SyncMemoryJobs(); $jobs->job['state']['list_type'] = 'taslak';
        $importer = new SyncOfflineImporter();
        $pages = new SyncOfflinePages(fn() => $this->items(0, 3));
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame([0], $pages->offsets);
        self::assertSame('completed', $jobs->job['status']);
        self::assertCount(3, $importer->imported);
    }

    public function testDraftResumeReplacesOldShortPageSize(): void
    {
        $jobs = new SyncMemoryJobs(); $jobs->job['state']['list_type'] = 'taslak';
        $jobs->job['state']['page_size'] = 3;
        $jobs->job['state']['cursor'] = 1;
        $items = $this->items(0, 3);
        $jobs->job['state']['pending_signature'] = hash('sha256', implode("\n", array_column($items, 'uuid')));
        $importer = new SyncOfflineImporter(); $pages = new SyncOfflinePages(fn() => $items);
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame([0], $pages->offsets);
        self::assertSame('completed', $jobs->job['status']);
        self::assertSame(['uuid-1', 'uuid-2'], array_column($importer->imported, 0));
    }

    public function testFullDraftPageContinuesToShortFinalPage(): void
    {
        $jobs = new SyncMemoryJobs(); $jobs->job['state']['list_type'] = 'taslak';
        $importer = new SyncOfflineImporter();
        $pages = new SyncOfflinePages(fn($offset) => $this->items($offset, $offset === 0 ? 50 : 3));
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame([0, 50], $pages->offsets);
        self::assertSame('completed', $jobs->job['status']);
        self::assertCount(53, $importer->imported);
    }

    public function testOutgoingSkipsDraftXmlAndAdvancesRawPageOffset(): void
    {
        $jobs = new SyncMemoryJobs(); $jobs->job['state']['list_type'] = 'giden';
        $importer = new SyncOfflineImporter();
        $pages = new SyncOfflinePages(function($offset) {
            if ($offset) return [];
            return [['uuid' => 'draft', 'status' => 'LOAD - SUCCEED'], ['uuid' => 'sent', 'status' => 'PACKAGE - PROCESSING']];
        });
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame([0, 2], $pages->offsets);
        self::assertSame(['sent'], $pages->xmlRequests);
        self::assertSame(['sent'], array_column($importer->imported, 0));
        self::assertSame(1, $jobs->job['state']['processed_count']);
        self::assertSame('completed', $jobs->job['status']);
    }

    public function testConnectionRetryUsesSameOffsetAndBackoff(): void
    {
        $jobs = new SyncMemoryJobs(); $importer = new SyncOfflineImporter(); $attempts = 0; $sleeps = [];
        $pages = new SyncOfflinePages(function() use (&$attempts) {
            if (++$attempts <= 2) throw new EdmOperationException('connection', 'Timeout');
            return [];
        });
        $this->worker($jobs, $pages, $importer, function($seconds) use (&$sleeps) { $sleeps[] = $seconds; })->run($jobs->job['id']);
        self::assertSame([0, 0, 0], $pages->offsets);
        self::assertSame([2, 4], $sleeps);
        self::assertSame('completed', $jobs->job['status']);
    }
    public function testFailedPageResumesWithoutReimportingEarlierPages(): void
    {
        $jobs = new SyncMemoryJobs(); $importer = new SyncOfflineImporter(); $fail = true;
        $pages = new SyncOfflinePages(function($offset) use (&$fail) {
            if ($offset === 2 && $fail) throw new EdmOperationException('connection', 'Timeout');
            return $offset === 0 ? $this->items(0, 2) : $this->items(2, 1);
        });
        $worker = $this->worker($jobs, $pages, $importer); $worker->run($jobs->job['id']);
        self::assertSame('paused', $jobs->job['status']); self::assertSame(2, $jobs->job['state']['offset']);
        $fail = false; $jobs->job['status'] = 'queued'; $worker->run($jobs->job['id']);
        self::assertSame('completed', $jobs->job['status']);
        self::assertSame(['uuid-0','uuid-1','uuid-2'], array_column($importer->imported, 0));
    }
    public function testMidPageFailureResumesAtFirstUnsavedRecord(): void
    {
        $jobs = new SyncMemoryJobs(); $importer = new SyncOfflineImporter(); $importer->failUuid = 'uuid-1';
        $pages = new SyncOfflinePages(fn($offset) => $offset === 0 ? $this->items(0, 2) : []);
        $worker = $this->worker($jobs, $pages, $importer); $worker->run($jobs->job['id']);
        self::assertSame('paused', $jobs->job['status']); self::assertSame(1, $jobs->job['state']['cursor']);
        $importer->failUuid = null; $jobs->job['status'] = 'queued'; $worker->run($jobs->job['id']);
        self::assertSame(['uuid-0','uuid-1'], array_column($importer->imported, 0));
        self::assertSame(2, $jobs->job['state']['processed_count']);
        self::assertSame('completed', $jobs->job['status']);
    }
    public function testChangedPendingPageIsNotSilentlySkipped(): void
    {
        $jobs = new SyncMemoryJobs(); $importer = new SyncOfflineImporter(); $importer->failUuid = 'uuid-1'; $changed = false;
        // Use a by-reference handler to model a server list changing between runs.
        $pages = new SyncOfflinePages(function() use (&$changed) { return $this->items($changed ? 10 : 0, 2); });
        $worker = $this->worker($jobs, $pages, $importer); $worker->run($jobs->job['id']);
        $changed = true; $importer->failUuid = null; $jobs->job['status'] = 'queued'; $worker->run($jobs->job['id']);
        self::assertSame('paused', $jobs->job['status']); self::assertCount(1, $importer->imported);
        self::assertStringContainsString('değişti', $jobs->job['state']['message']);
    }
    public function testRepeatedPageStopsInsteadOfLoopingOrDuplicating(): void
    {
        $jobs = new SyncMemoryJobs(); $importer = new SyncOfflineImporter();
        $pages = new SyncOfflinePages(fn() => $this->items(0, 2));
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame('paused', $jobs->job['status']); self::assertCount(2, $importer->imported);
        self::assertSame([0, 2], $pages->offsets);
    }
    public function testDatabaseProfileMismatchHasActionableSafeMessage(): void
    {
        $jobs = new SyncMemoryJobs(); $importer = new SyncOfflineImporter();
        $importer->dbError = new PDOException("Data truncated for column 'fatura_profili' at row 1");
        $importer->dbError->errorInfo = ['01000', 1265, 'profile mismatch'];
        $pages = new SyncOfflinePages(fn() => $this->items(0, 2));
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame('paused', $jobs->job['status']);
        self::assertSame(0, $jobs->job['state']['processed_count']);
        self::assertSame('fatura_profili', $jobs->job['state']['last_error']['column']);
        self::assertSame(1265, $jobs->job['state']['last_error']['db_code']);
        self::assertStringContainsString('fatura profili', $jobs->job['state']['message']);
    }
    public function testLongNotesErrorNamesRequiredMigrationWithoutLeakingSource(): void
    {
        $jobs = new SyncMemoryJobs(); $importer = new SyncOfflineImporter();
        $importer->dbError = new PDOException("Data too long for column 'notlar' at row 1");
        $importer->dbError->errorInfo = ['22001', 1406, 'notes length'];
        $pages = new SyncOfflinePages(fn() => $this->items(0, 1));
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame('paused', $jobs->job['status']);
        self::assertSame('notlar', $jobs->job['state']['last_error']['column']);
        self::assertStringContainsString('2026_10_06_efatura_notlar_mediumtext.sql', $jobs->job['state']['message']);
    }

    public function testMissingSettingsColumnReportsSafeDatabaseCodeAndStage(): void
    {
        $jobs = new SyncMemoryJobs();
        $error = new PDOException("Unknown column 'private_column' in secret_database");
        $error->errorInfo = ['42S22', 1054, 'private details'];
        $worker = new EInvoiceSyncWorker($jobs, new SyncOfflineImporter(), function() use ($error) { throw $error; });
        $worker->run($jobs->job['id']);
        self::assertSame('paused', $jobs->job['status']);
        self::assertSame('settings', $jobs->job['state']['last_error']['stage']);
        self::assertStringContainsString('SQLSTATE: 42S22, kod: 1054', $jobs->job['state']['message']);
        self::assertStringContainsString('EDM ayarlarını okuma', $jobs->job['state']['message']);
        self::assertStringNotContainsString('secret_database', $jobs->job['state']['message']);
        self::assertStringNotContainsString('private_column', $jobs->job['state']['message']);
    }

    public function testXmlWriteFailureReportsStoragePermissionsAndKeepsCursor(): void
    {
        $jobs = new SyncMemoryJobs(); $importer = new SyncOfflineImporter();
        $importer->systemError = new RuntimeException('Fatura XML dosyası kaydedilemedi.');
        $pages = new SyncOfflinePages(fn() => $this->items(0, 2));
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame('paused', $jobs->job['status']);
        self::assertSame(0, $jobs->job['state']['cursor']);
        self::assertStringContainsString('storage/invoices', $jobs->job['state']['message']);
        self::assertStringContainsString('faturayı kaydetme', $jobs->job['state']['message']);
    }

    public function testInvalidInvoiceIsReportedAndRemainingPagesContinue(): void
    {
        $jobs = new SyncMemoryJobs(); $importer = new SyncOfflineImporter(); $importer->invalidUuid = 'uuid-1';
        $pages = new SyncOfflinePages(fn($offset) => $offset === 0 ? $this->items(0, 2) : $this->items(2, 1));
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame('partial', $jobs->job['status']);
        self::assertSame(2, $jobs->job['state']['processed_count']);
        self::assertSame(1, $jobs->job['state']['failed_count']);
        self::assertSame(['uuid-0','uuid-2'], array_column($importer->imported, 0));
        self::assertSame('uuid-1', $jobs->job['state']['validation_errors'][0]['uuid']);
        self::assertStringContainsString('XML fatura numarası geçersiz', $jobs->job['state']['message']);
    }
    public function testConcurrentWorkerDoesNotImport(): void
    {
        $jobs = new SyncMemoryJobs(); $jobs->lock($jobs->job['id']); $importer = new SyncOfflineImporter();
        $pages = new SyncOfflinePages(fn() => []);
        $this->worker($jobs, $pages, $importer)->run($jobs->job['id']);
        self::assertSame([], $pages->offsets); self::assertSame('queued', $jobs->job['status']);
    }
    public function testJobTokenCannotAccessAnotherFirm(): void
    {
        $_ENV['ENCRYPTION_KEY'] ??= str_repeat('ab', 32);
        $jobs = new SyncMemoryJobs(); $service = new EInvoiceSyncJobService($jobs, fn() => true);
        $token = Security::encrypt($jobs->job['id']);
        self::assertSame('queued', $service->status(2, 3, $token)['job_status']);
        $this->expectException(InvalidArgumentException::class);
        $service->status(99, 3, $token);
    }
    public function testResumeLaunchFailureIsVisibleAndCheckpointIsPreserved(): void
    {
        $_ENV['ENCRYPTION_KEY'] ??= str_repeat('ab', 32);
        $jobs = new SyncMemoryJobs(); $jobs->job['status'] = 'paused'; $jobs->job['state']['offset'] = 50;
        $service = new EInvoiceSyncJobService($jobs, fn() => false);
        $result = $service->resume(2, 3, Security::encrypt($jobs->job['id']));
        self::assertSame('paused', $result['job_status']);
        self::assertSame(50, $jobs->job['state']['offset']);
        self::assertArrayNotHasKey('id', $result);
        self::assertArrayNotHasKey('offset', $result);
    }
}
