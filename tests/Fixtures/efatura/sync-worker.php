<?php
/** Detached worker fixture. Only a disposable integration database is allowed. */
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 3) . '/vendor/autoload.php';
$name = $argv[1] ?? '';
$id = $argv[2] ?? '';
if (!preg_match('/^ersan_efatura_test_[a-f0-9]{10}$/D', $name) || !preg_match('/^[a-f0-9]{32}$/D', $id)) exit(1);
$dsn = getenv('EFATURA_TEST_SERVER_DSN') ?: 'mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;charset=utf8mb4';
if (str_contains(strtolower($dsn), 'dbname=')) exit(1);
$db = new PDO($dsn . ';dbname=' . $name, getenv('EFATURA_TEST_USER') ?: 'root', getenv('EFATURA_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
class DetachedInvoicePages extends App\Service\EdmSoapClient {
    public function __construct() {}
    public function getInvoicePage(string $direction, string $startDate, string $endDate, int $offset = 0, int $limit = 50, ?string $createdBefore = null, ?string $listType = null): array {
        usleep(250000);
        return $offset === 0 ? [['uuid' => 'first', 'status' => 'LOAD - SUCCEED'], ['uuid' => 'second', 'status' => 'LOAD - SUCCEED']] : [['uuid' => 'last', 'status' => 'LOAD - SUCCEED']];
    }
}
class DetachedInvoiceImporter extends App\Service\EInvoiceService {
    public function __construct() {}
    public function importSyncedInvoice(int $firmId, array $item, int $userId): string { return 'added_count'; }
}
(new App\Service\EInvoiceSyncWorker(new App\Model\EInvoiceSyncJobModel($db), new DetachedInvoiceImporter(), fn() => new DetachedInvoicePages()))->run($id);
