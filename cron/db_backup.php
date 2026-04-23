<?php
/**
 * Database Backup Script for Ersan Elk
 * PHP-Native version: Does not require mysqldump command on the server.
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Gerekli dosyaları yükle
require_once dirname(__DIR__) . '/Autoloader.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Service\MailGonderService;



if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    echo "Bu dosya sadece komut satırı (CLI) üzerinden çalıştırılabilir.";
    exit;
}

// CLI kontrolü (İsteğe bağlı: URL'den testi kolaylaştırmak için şimdilik kapalı tutulabilir)
// if (php_sapi_name() !== 'cli') {
//     header('HTTP/1.0 403 Forbidden');
//     echo "Bu dosya sadece komut satırı üzerinden çalıştırılabilir.";
//     exit;
// }

// Yollar
$baseDir = dirname(__DIR__);
$backupDir = $baseDir . DIRECTORY_SEPARATOR . 'backups';
$logDir = $baseDir . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'logs';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'backup.log';

if (!is_dir($logDir)) mkdir($logDir, 0755, true);
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

$currentLogEntries = "";
function logMessage($message) {
    global $logFile, $currentLogEntries;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logLine, FILE_APPEND);
    $currentLogEntries .= $logLine;
    echo $logLine;
}

logMessage("--- PHP NATIVE BACKUP STARTED ---");

// 1. .env'den Bilgileri Al
$envFile = $baseDir . DIRECTORY_SEPARATOR . '.env';
if (!file_exists($envFile)) {
    logMessage("CRITICAL ERROR: .env file not found.");
    exit(1);
}

// Env dosyasını oku
$env = [];
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    $parts = explode('=', $line, 2);
    if (count($parts) == 2) {
        $env[trim($parts[0])] = trim($parts[1], ' "');
    }
}

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_NAME'] ?? '';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';

if (empty($dbName) || empty($dbUser)) {
    logMessage("CRITICAL ERROR: Database credentials missing in .env.");
    exit(1);
}

// 2. Yedek Dosyası Hazırla
$date = date('Y-m-d_H-i-s');
$backupFile = $backupDir . DIRECTORY_SEPARATOR . "backup_{$dbName}_{$date}.sql";

try {
    // 3. Veritabanına Bağlan
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    logMessage("Database connected. Starting dump for: $dbName");

    // 4. Tabloları Çek
    $tables = [];
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $handle = fopen($backupFile, 'w');
    fwrite($handle, "-- PHP MySQL Dump\n-- Date: " . date('Y-m-d H:i:s') . "\n\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

    foreach ($tables as $table) {
        // Tablo mu yoksa View mu olduğunu anla ve yapısını çek
        $res = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $createStatement = $res[1] ?? ''; // İkinci sütun her zaman oluşturma kodunu içerir
        
        // Eğer bir View ise "CREATE VIEW" ile başlar
        $isView = (stripos($createStatement, 'CREATE VIEW') !== false);
        
        fwrite($handle, "\n\n-- Structure for " . ($isView ? "view" : "table") . " `$table` --\n");
        if ($isView) {
            fwrite($handle, "DROP VIEW IF EXISTS `$table`;\n");
        } else {
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
        }
        fwrite($handle, $createStatement . ";\n\n");

        // Eğer View ise içindeki verileri çekmeye çalışma (View'lar veri barındırmaz)
        if ($isView) {
            logMessage("Processed view: $table (Structure only)");
            continue; 
        }

        // Veriler (Sadece Tablolar için)
        $result = $pdo->query("SELECT * FROM `$table`", PDO::FETCH_NUM);
        $count = 0;
        while ($row = $result->fetch()) {
            $values = array_map(function($val) use ($pdo) {
                if ($val === null) return 'NULL';
                return $pdo->quote($val);
            }, $row);
            fwrite($handle, "INSERT INTO `$table` VALUES (" . implode(", ", $values) . ");\n");
            $count++;
        }
        logMessage("Processed table: $table ($count rows)");
    }

    fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;");
    fclose($handle);

    logMessage("SUCCESS: SQL Dump finished.");

    // 5. Sıkıştırma (ZIP)
    if (file_exists($backupFile) && filesize($backupFile) > 0) {
        $zipFile = str_replace('.sql', '.zip', $backupFile);
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($backupFile, basename($backupFile));
            $zip->close();
            
            $origSize = round(filesize($backupFile) / 1024, 2);
            $zipSize = round(filesize($zipFile) / 1024, 2);
            logMessage("Compressed to ZIP. Original: {$origSize}KB, ZIP: {$zipSize}KB");
            
            unlink($backupFile); // SQL'i sil
        }
    }

} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
}

// 6. Rotasyon (7 gün)
$daysToKeep = 7;
$files = glob($backupDir . DIRECTORY_SEPARATOR . "backup_{$dbName}_*.{sql,zip}", GLOB_BRACE);
$deletedCount = 0;
foreach ($files as $file) {
    if (time() - filemtime($file) >= ($daysToKeep * 24 * 60 * 60)) {
        unlink($file);
        $deletedCount++;
    }
}
logMessage("Rotation: Deleted $deletedCount old backup files.");
logMessage("--- BACKUP FINISHED ---");

// 7. Mail Gönder
try {
    $subject = "DB Backup Report: " . $dbName . " (" . date('d.m.Y H:i') . ")";
    $mailIcerik = "<h3>Database Backup Report</h3>";
    $mailIcerik .= "<pre style='background: #f4f4f4; padding: 10px; border: 1px solid #ddd;'>" . $currentLogEntries . "</pre>";

    MailGonderService::gonder(['beyzade83@gmail.com'], $subject, $mailIcerik);
    echo "Notification email sent to beyzade83@gmail.com\n";
} catch (Exception $e) {
    echo "Mail Error: " . $e->getMessage() . "\n";
}
