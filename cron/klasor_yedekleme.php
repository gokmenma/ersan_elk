<?php

if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    echo "Bu dosya sadece komut satırı (CLI) üzerinden çalıştırılabilir.";
    exit;
}

date_default_timezone_set('Europe/Istanbul');

$baseDir = dirname(__DIR__);

$foldersToBackup = [
    'personel_evraklar' => $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'personel_evraklar',
    'uploads'           => $baseDir . DIRECTORY_SEPARATOR . 'uploads',
];

$envFile = $baseDir . DIRECTORY_SEPARATOR . '.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) == 2) {
            $env[trim($parts[0])] = trim($parts[1], " \t\n\r\0\x0B\"'");
        }
    }
}

$backupDir = $env['FOLDER_BACKUP_DIR'] ?? (dirname($baseDir) . DIRECTORY_SEPARATOR . 'ersan_elk_yedekler');
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0750, true);
}
if (!is_dir($backupDir) || !is_writable($backupDir)) {
    $backupDir = $baseDir . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'klasorler';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0750, true);
    }
}

$logDir = $baseDir . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}
$logFile = $logDir . DIRECTORY_SEPARATOR . 'klasor_yedekleme.log';

function logMessage($logFile, $message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    echo "[$timestamp] $message" . PHP_EOL;
}

function addFolderToZip(ZipArchive $zip, string $sourceDir, string $zipRootName) {
    $sourceDir = rtrim($sourceDir, '/\\');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) continue;
        $realPath = $file->getRealPath();
        $relativePath = $zipRootName . '/' . substr($realPath, strlen($sourceDir) + 1);
        $zip->addFile($realPath, str_replace('\\', '/', $relativePath));
    }
}

function rotateOldBackups(string $backupDir, string $key, string $currentFile) {
    $files = glob($backupDir . DIRECTORY_SEPARATOR . $key . '_*.zip');
    foreach ($files as $file) {
        if (realpath($file) !== realpath($currentFile)) {
            @unlink($file);
        }
    }
}

logMessage($logFile, "--- KLASOR YEDEKLEME BASLADI (Hedef: $backupDir) ---");

$dateStamp = date('Y-m-d_H-i-s');

foreach ($foldersToBackup as $key => $sourceDir) {
    if (!is_dir($sourceDir)) {
        logMessage($logFile, "UYARI: Kaynak klasor bulunamadi, atlaniyor: $sourceDir");
        continue;
    }

    $zipFile = $backupDir . DIRECTORY_SEPARATOR . "{$key}_{$dateStamp}.zip";

    try {
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("ZIP dosyasi acilamadi: $zipFile");
        }

        addFolderToZip($zip, $sourceDir, $key);
        $zip->close();

        $size = round(filesize($zipFile) / 1024, 2);
        logMessage($logFile, "BASARILI: '$key' yedeklendi -> " . basename($zipFile) . " ({$size} KB)");

        rotateOldBackups($backupDir, $key, $zipFile);
        logMessage($logFile, "ROTASYON: '$key' icin onceki yedekler silindi, sadece son yedek tutuluyor.");
    } catch (Exception $e) {
        logMessage($logFile, "HATA ('$key'): " . $e->getMessage());
        error_log("[klasor_yedekleme] " . $e->getMessage());
    }
}

logMessage($logFile, "--- KLASOR YEDEKLEME TAMAMLANDI ---");
