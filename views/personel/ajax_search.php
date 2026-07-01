<?php
session_start();

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Service\Gate;
use App\Helper\Security;
use Dotenv\Dotenv;

header('Content-Type: application/json; charset=utf-8');

if (!Gate::allows('personel_listesi')) {
    echo json_encode(['results' => []]);
    exit;
}

$firma_id = $_SESSION['firma_id'] ?? 0;
if (empty($firma_id)) {
    echo json_encode(['results' => []]);
    exit;
}

$dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? '';
$username = $_ENV['DB_USER'] ?? '';
$password = $_ENV['DB_PASS'] ?? '';

$q = trim($_GET['q'] ?? '');
if (empty($q)) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $db = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $searchTerm = "%" . $q . "%";

    if (preg_match('/^\d{11}$/', $q)) {
        $tcHash = hash('sha256', $q);
        $sql = "SELECT id, adi_soyadi, tc_kimlik_no FROM personel WHERE firma_id = :firma_id AND aktif_mi = 1 AND (adi_soyadi LIKE :q1 OR tc_hash = :tc_hash) LIMIT 20";
        $params = [':firma_id' => $firma_id, ':q1' => $searchTerm, ':tc_hash' => $tcHash];
    } else {
        $sql = "SELECT id, adi_soyadi, tc_kimlik_no FROM personel WHERE firma_id = :firma_id AND aktif_mi = 1 AND adi_soyadi LIKE :q1 LIMIT 20";
        $params = [':firma_id' => $firma_id, ':q1' => $searchTerm];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $decryptedTc = $row['tc_kimlik_no'] ? (Security::decrypt($row['tc_kimlik_no']) ?: '') : '';
        $results[] = [
            'id' => Security::encrypt($row['id']),
            'text' => $row['adi_soyadi'] . ($decryptedTc ? ' (' . $decryptedTc . ')' : '')
        ];
    }

    echo json_encode(['results' => $results]);
} catch (PDOException $e) {
    error_log('ajax_search PDO: ' . $e->getMessage());
    echo json_encode(['results' => []]);
}
