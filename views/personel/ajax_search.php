<?php
session_start();

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Service\Gate;
use App\Helper\Security;
use Dotenv\Dotenv;

header('Content-Type: application/json; charset=utf-8');

// Yetki kontrolü
if (!Gate::allows('personel_listesi')) {
    echo json_encode(['results' => []]);
    exit;
}

$firma_id = $_SESSION['firma_id'] ?? 0;
if (empty($firma_id)) {
    echo json_encode(['results' => []]);
    exit;
}

// .env'den bilgileri al
$dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? '';
$username = $_ENV['DB_USER'] ?? '';
$password = $_ENV['DB_PASS'] ?? '';

$q = $_GET['q'] ?? '';
if(empty($q)) {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $db = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $searchTerm = "%" . trim($q) . "%";
    
    $sql = "SELECT id, adi_soyadi, tc_kimlik_no FROM personel WHERE firma_id = :firma_id AND aktif_mi = 1 AND (adi_soyadi LIKE :q1 OR tc_kimlik_no LIKE :q2) LIMIT 20";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':firma_id' => $firma_id,
        ':q1' => $searchTerm,
        ':q2' => $searchTerm
    ]);
    
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => Security::encrypt($row['id']),
            'text' => $row['adi_soyadi'] . ' (' . $row['tc_kimlik_no'] . ')'
        ];
    }
    
    echo json_encode(['results' => $results]);
} catch (PDOException $e) {
    echo json_encode(['results' => [], 'error' => 'Database error']);
}
