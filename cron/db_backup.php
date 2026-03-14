<?php
/**
 * Database Backup Script for Ersan Elk
 * Uses mysqldump to create a daily backup and keeps only the last 7 days.
 */

// Define paths
$baseDir = dirname(__DIR__);
$backupDir = $baseDir . DIRECTORY_SEPARATOR . 'backups';
$logFile = $baseDir . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'backup.log';

// Ensure log directory exists
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// 1. Load DB Credentials from .env
$envFile = $baseDir . DIRECTORY_SEPARATOR . '.env';
if (!file_exists($envFile)) {
    logMessage("ERROR: .env file not found at $envFile");
    exit(1);
}

$env = parse_ini_file($envFile);
$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_NAME'] ?? '';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';

if (empty($dbName) || empty($dbUser)) {
    logMessage("ERROR: Database credentials missing in .env");
    exit(1);
}

// 2. Prepare Backup Filename
$date = date('Y-m-d_H-i-s');
$backupFile = $backupDir . DIRECTORY_SEPARATOR . "backup_{$dbName}_{$date}.sql";

// 3. Command for mysqldump (Windows path assumed from research)
$mysqldumpPath = 'C:\xampp\mysql\bin\mysqldump.exe';
if (!file_exists($mysqldumpPath)) {
    // Try without path if not found in XAMPP default
    $mysqldumpPath = 'mysqldump';
}

// Prepare command
// Using --result-file to avoid shell redirection issues with long blobs or special characters
$command = sprintf(
    '"%s" --host=%s --user=%s %s %s --result-file="%s" 2>&1',
    $mysqldumpPath,
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    (!empty($dbPass) ? '--password=' . escapeshellarg($dbPass) : ''),
    escapeshellarg($dbName),
    $backupFile
);

logMessage("Starting backup to $backupFile");

// 4. Execute
exec($command, $output, $returnVar);

if ($returnVar === 0) {
    logMessage("SUCCESS: Backup created successfully.");
} else {
    logMessage("ERROR: Backup failed with return code $returnVar. Output: " . implode(' ', $output));
    if (file_exists($backupFile)) {
        unlink($backupFile); // Remove partial/failed file
    }
}

// 5. Rotation (Keep last 7 days)
$daysToKeep = 7;
$files = glob($backupDir . DIRECTORY_SEPARATOR . "backup_{$dbName}_*.sql");
$now = time();

foreach ($files as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) >= ($daysToKeep * 24 * 60 * 60)) {
            unlink($file);
            logMessage("Deleted old backup: " . basename($file));
        }
    }
}

logMessage("Backup process finished.");
