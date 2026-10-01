<?php
/** Isolated schema only: application records and schema are never changed. */
if (PHP_SAPI !== 'cli' || getenv('PWA_TRANSFER_MYSQL_TEST') !== '1') {
    fwrite(STDERR, "PWA_TRANSFER_MYSQL_TEST=1 ile CLI üzerinden çalıştırın.\n"); exit(1);
}
require dirname(__DIR__, 2) . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->load();
function connection(string $schema = ''): PDO {
    if ($schema && !preg_match('/^codex_pwa_test_[a-f0-9]{16}$/D', $schema)) throw new RuntimeException('Invalid test schema');
    return new PDO('mysql:host=' . $_ENV['DB_HOST'] . ($schema ? ';dbname=' . $schema : '') . ';charset=utf8mb4', $_ENV['DB_USER'], $_ENV['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
function model(PDO $db, int $firma = 1, int $personel = 10): \App\Model\PwaTransferModel {
    $reflection = new ReflectionClass(\App\Model\PwaTransferModel::class);
    $m = $reflection->newInstanceWithoutConstructor(); $m->db = $db;
    $reflection->getProperty('firma')->setValue($m, $firma);
    $reflection->getProperty('personel')->setValue($m, $personel);
    return $m;
}
function scalar(PDO $db, string $sql) { $stmt = $db->prepare($sql); $stmt->execute(); return $stmt->fetchColumn(); }
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
if (($argv[1] ?? '') === 'worker') {
    $db = connection($argv[2]); $m = model($db);
    $cached = $m->begin('concurrent', 'createIhbar');
    check($cached && $cached['success'], 'Concurrent request did not replay committed receipt');
    $db->rollBack(); echo "REPLAYED\n"; exit;
}
$schema = 'codex_pwa_test_' . bin2hex(random_bytes(8));
$admin = connection();
$admin->prepare("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4")->execute();
try {
    $db = connection($schema);
    $db->prepare(file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_10_01_pwa_reliable_transfers.sql'))->execute();
    $db->prepare('CREATE TABLE events (id INT AUTO_INCREMENT PRIMARY KEY, payload VARCHAR(100)) ENGINE=InnoDB')->execute();
    $m = model($db); $response = ['success'=>true,'data'=>['target_token'=>'encrypted'],'message'=>'ok'];
    check($m->begin('main', 'createIhbar') === null, 'Fresh operation must execute');
    $db->prepare('INSERT INTO events (payload) VALUES (?)')->execute(['main']); $m->finish($response);
    check($m->begin('main', 'createIhbar') === $response, 'Lost response must replay'); $db->rollBack();
    check((int) scalar($db, 'SELECT COUNT(*) FROM events') === 1, 'Replay duplicated business write');
    echo "PASS atomic record and replay receipt\n";
    $m->begin('rollback', 'updateIhbar');
    $db->prepare('INSERT INTO events (payload) VALUES (?)')->execute(['must-rollback']); $m->finish(['success'=>false]);
    check($m->begin('rollback', 'updateIhbar') === null, 'Rejected write must be retriable'); $m->finish($response);
    check((int) scalar($db, 'SELECT COUNT(*) FROM events') === 1, 'Business rollback failed');
    echo "PASS failed mutation and receipt roll back together\n";
    $other = model($db, 2, 10); check($other->begin('main', 'createIhbar') === null, 'Company scope failed'); $other->finish($response);
    $other = model($db, 1, 11); check($other->begin('main', 'createIhbar') === null, 'Person scope failed'); $other->finish($response);
    echo "PASS firm and person receipt isolation\n";
    try { $m->begin('main', 'updateIhbar'); throw new LogicException('Action mismatch accepted'); }
    catch (RuntimeException $e) { check(str_contains($e->getMessage(), 'başka'), 'Unexpected mismatch error'); $db->rollBack(); }
    echo "PASS operation cannot be reused for another action\n";
    $m->begin('concurrent','createIhbar');
    $db->prepare('INSERT INTO events (payload) VALUES (?)')->execute(['concurrent']);
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', $schema], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    check(is_resource($process), 'Could not start concurrent worker'); fclose($pipes[0]);
    usleep(400000); $m->finish($response);
    $stdout = stream_get_contents($pipes[1]);$stderr = stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($process) === 0 && str_contains($stdout,'REPLAYED'), 'Concurrent replay failed: ' . $stderr);
    check((int)scalar($db, "SELECT COUNT(*) FROM events WHERE payload='concurrent'")===1,'Concurrent duplicate');
    echo "PASS concurrent DB requests serialize and replay\n";
    check($m->receipt('main','createIhbar')===$response,'Read-only completion lookup failed');
    echo "6 MySQL scenarios passed\n";
} finally {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    $admin->prepare("DROP DATABASE `{$schema}`")->execute();
}
