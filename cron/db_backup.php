<?php
/**
 * Database Backup Script for Ersan Elk
 * Uses mysqldump to create a daily backup and keeps only the last 7 days.
 */

// Error reporting for early failure tracking
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define paths
$baseDir = dirname(__DIR__);
$backupDir = $baseDir . DIRECTORY_SEPARATOR . 'backups';
$logDir = $baseDir . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'logs';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'backup.log';

// Ensure necessary directories exist
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0755, true)) {
        die("Fatal Error: Could not create log directory at $logDir");
    }
}

if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0755, true)) {
        die("Fatal Error: Could not create backup directory at $backupDir");
    }
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logLine, FILE_APPEND);
    // Also echo to console for batch file capture
    echo $logLine;
}

logMessage("--- BACKUP CRON STARTED ---");

// 1. Load DB Credentials from .env
$envFile = $baseDir . DIRECTORY_SEPARATOR . '.env';
if (!file_exists($envFile)) {
    logMessage("CRITICAL ERROR: .env file not found at $envFile");
    exit(1);
}

// Improved env parsing: parse_ini_file can be finicky with certain formats
$env = @parse_ini_file($envFile);
if ($env === false) {
    // If parse_ini_file fails, try to parse it manually as a fallback
    $env = [];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $env[trim($name)] = trim($value, ' "');
    }
}

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_NAME'] ?? '';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';

if (empty($dbName) || empty($dbUser)) {
    logMessage("CRITICAL ERROR: Database name or user missing in .env config. Found: Name=[$dbName], User=[$dbUser]");
    exit(1);
}

// 2. Prepare Backup Filename
$date = date('Y-m-d_H-i-s');
$backupFile = $backupDir . DIRECTORY_SEPARATOR . "backup_{$dbName}_{$date}.sql";

// 3. Command for mysqldump
$mysqldumpPath = 'C:\xampp\mysql\bin\mysqldump.exe';
if (!file_exists($mysqldumpPath)) {
    logMessage("WARNING: Default path $mysqldumpPath not found. Trying 'mysqldump' from PATH.");
    $mysqldumpPath = 'mysqldump';
}

// Prepare command
// --single-transaction is safer for live databases
$command = sprintf(
    '"%s" --host=%s --user=%s %s %s --result-file="%s" 2>&1',
    $mysqldumpPath,
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    (!empty($dbPass) ? '--password=' . escapeshellarg($dbPass) : ''),
    escapeshellarg($dbName),
    $backupFile
);

logMessage("Executing backup for database: $dbName");
logMessage("Target file: $backupFile");

// 4. Execute
$output = [];
$returnVar = -1;
exec($command, $output, $returnVar);

if ($returnVar === 0) {
    if (file_exists($backupFile) && filesize($backupFile) > 0) {
        $originalSize = filesize($backupFile);
        $sizeKB = round($originalSize / 1024, 2);
        logMessage("SQL Dump success ($sizeKB KB). Compressing...");

        // 4.1. Compression (ZIP)
        $zipFile = str_replace('.sql', '.zip', $backupFile);
        $zip = new ZipArchive();
        
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($backupFile, basename($backupFile));
            $zip->close();
            
            if (file_exists($zipFile)) {
                $compressedSize = filesize($zipFile);
                $zipKB = round($compressedSize / 1024, 2);
                $ratio = round((1 - ($compressedSize / $originalSize)) * 100, 1);
                
                logMessage("SUCCESS: Backup compressed to ZIP ($zipKB KB). Save ratio: $ratio%");
                
                // Delete original SQL
                unlink($backupFile);
            } else {
                logMessage("ERROR: ZIP file creation failed after closing.");
            }
        } else {
            logMessage("WARNING: Could not create ZIP file. Keeping original SQL.");
        }
    } else {
        logMessage("ERROR: Command returned 0 but backup file is missing or empty.");
    }
} else {
    $errorOutput = implode(' ', $output);
    logMessage("ERROR: Backup failed with return code $returnVar.");
    logMessage("Mysqldump Output: " . $errorOutput);
    if (file_exists($backupFile)) {
        unlink($backupFile); // Remove partial/failed file
    }
}

// 5. Rotation (Keep last 7 days)
$daysToKeep = 7;
// Glob for both .sql and .zip
$pattern = $backupDir . DIRECTORY_SEPARATOR . "backup_{$dbName}_*";
$files = glob($pattern . ".{sql,zip}", GLOB_BRACE);
$now = time();
$countDeleted = 0;

if ($files) {
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= ($daysToKeep * 24 * 60 * 60)) {
                if (unlink($file)) {
                    logMessage("Rotated old backup: " . basename($file));
                    $countDeleted++;
                }
            }
        }
    }
}

logMessage("Retention: Keep last $daysToKeep days. Deleted $countDeleted old files.");
logMessage("--- BACKUP CRON FINISHED ---");
