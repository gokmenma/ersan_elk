<?php
/** Creates and drops ONLY a random ersan_efatura_test_* database. No EDM calls. */
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\EInvoiceSettingsModel;
use App\Model\EInvoiceModel;

final class InvoiceMysqlSettings extends EInvoiceSettingsModel
{
    public function __construct(PDO $db) { $this->db = $db; }
}
final class InvoiceMysqlModel extends EInvoiceModel
{
    public function __construct(PDO $db) { $this->db = $db; }
}
$serverDsn = getenv('EFATURA_TEST_SERVER_DSN') ?: 'mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;charset=utf8mb4';
if (str_contains(strtolower($serverDsn), 'dbname=')) throw new RuntimeException('Use a server DSN without dbname. This test creates its own database.');
$user = getenv('EFATURA_TEST_USER') ?: 'root';
$password = getenv('EFATURA_TEST_PASSWORD') ?: '';
$name = 'ersan_efatura_test_' . bin2hex(random_bytes(5));
$connect = static fn(?string $database = null) => new PDO($serverDsn . ($database ? ';dbname=' . $database : ''), $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$runScript = static function(PDO $db, string $path): void {
    $text = file_get_contents($path);
    $text = preg_replace('/^--.*$/m', '', $text);
    $parts = preg_split('/DELIMITER\s+\/\/|DELIMITER\s+;/i', $text);
    foreach ($parts as $index => $part) {
        if ($index % 2 === 1) {
            foreach (explode('//', $part) as $query) if (trim($query) !== '') $db->query(trim($query))->closeCursor();
        } else {
            foreach (explode(';', $part) as $query) if (trim($query) !== '') $db->query(trim($query))->closeCursor();
        }
    }
};
$assert = static function(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$admin = $connect();
$admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$children = [];
try {
    $db = $connect($name);
    $runScript($db, dirname(__DIR__, 2) . '/sql/efatura_modulu.sql');
    $db->exec('CREATE TABLE permissions (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), description TEXT, auth_name VARCHAR(100), group_name VARCHAR(100), permission_level INT, is_required INT, is_active INT)');
    $db->exec('CREATE TABLE user_roles (id INT AUTO_INCREMENT PRIMARY KEY, role_name VARCHAR(100))');
    $db->exec('CREATE TABLE user_role_permissions (role_id INT, permission_id INT, created_by INT)');
    $db->exec("INSERT INTO user_roles (role_name) VALUES ('Muhasebe Sorumlusu')");
    $runScript($db, dirname(__DIR__, 2) . '/sql/efatura_tamamlama.sql');
    $runScript($db, dirname(__DIR__, 2) . '/sql/efatura_tamamlama.sql');
    $assert((int)$db->query('SELECT COUNT(*) FROM permissions')->fetchColumn() === 4, 'Migration permissions must be idempotent');
    $db->exec('CREATE TABLE efatura_test_numbers (worker INT, number VARCHAR(16) UNIQUE)');
    $db = null; $admin = null;
    for ($worker = 0; $worker < 8; $worker++) {
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('Cannot fork');
        if ($pid === 0) {
            try {
                $child = $connect($name); $model = new InvoiceMysqlSettings($child);
                for ($i = 0; $i < 15; $i++) {
                    $number = $model->generateNextInvoiceNumber(2, 'EFATURA', 'ERS', 2025);
                    $stmt = $child->prepare('INSERT INTO efatura_test_numbers VALUES (:worker, :number)');
                    $stmt->execute(['worker' => $worker, 'number' => $number]);
                }
                exit(0);
            } catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }
        }
        $children[] = $pid;
    }
    foreach ($children as $pid) { pcntl_waitpid($pid, $status); $assert(pcntl_wexitstatus($status) === 0, 'Concurrent worker failed'); }
    $children = [];
    $admin = $connect(); $db = $connect($name);
    $assert((int)$db->query('SELECT COUNT(DISTINCT number) FROM efatura_test_numbers')->fetchColumn() === 120, 'Concurrent numbers duplicated');
    $assert($db->query('SELECT MIN(number) FROM efatura_test_numbers')->fetchColumn() === 'ERS2025000000001', 'Number year is wrong');
    $model = new InvoiceMysqlSettings($db);
    $model->reconcileSerial(2, 'EFATURA', 'ERS', 2025, 200);
    $model->reconcileSerial(2, 'EFATURA', 'ERS', 2025, 10);
    $assert($model->generateNextInvoiceNumber(2, 'EFATURA', 'ERS', 2025) === 'ERS2025000000201', 'Reconciliation moved counter backwards');
    $other = $connect($name); $one = new InvoiceMysqlModel($db); $two = new InvoiceMysqlModel($other);
    $assert($one->acquireInvoiceLock(1, 2), 'First lock failed');
    $assert(!$two->acquireInvoiceLock(1, 2), 'Second connection acquired the same invoice lock');
    $assert($two->acquireInvoiceLock(1, 3), 'Different firm should have its own lock');
    $two->releaseInvoiceLock(1, 3); $one->releaseInvoiceLock(1, 2);
    $assert($two->acquireInvoiceLock(1, 2), 'Released lock cannot be acquired'); $two->releaseInvoiceLock(1, 2);
    $db->exec("INSERT INTO efatura_loglari (firm_id,islem_turu,istek_payload,yanit_payload,durum) VALUES (2,'Login','{\"PASSWORD\":\"old-secret\"}','{\"SESSION_ID\":\"old-session\"}','BASARILI')");
    $runScript($db, dirname(__DIR__, 2) . '/sql/efatura_log_hassas_veri_temizleme.sql');
    $assert(!str_contains(json_encode($db->query('SELECT * FROM efatura_loglari')->fetchAll()), 'old-secret'), 'Historic secret remains');
    echo 'OK: migration twice, 120 concurrent numbers, year/reconciliation, cross-connection invoice locks, historic log masking.' . PHP_EOL;
} finally {
    foreach ($children as $pid) pcntl_waitpid($pid, $status);
    ($admin ?? $connect())->exec("DROP DATABASE IF EXISTS `$name`");
}
