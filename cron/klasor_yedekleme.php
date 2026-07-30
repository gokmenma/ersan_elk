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
    'files'             => $baseDir . DIRECTORY_SEPARATOR . 'files',
    'assets_belgeler'   => $baseDir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'belgeler',
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

function rsyncAvailable(): bool {
    if (!function_exists('exec')) return false;
    exec('command -v rsync 2>/dev/null', $output, $returnCode);
    return $returnCode === 0 && !empty($output);
}

function rsyncMirror(string $sourceDir, string $destDir, string $logFile): void {
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0750, true);
    }

    $source = escapeshellarg(rtrim($sourceDir, '/\\') . '/');
    $dest = escapeshellarg(rtrim($destDir, '/\\') . '/');
    $cmd = "rsync -a --delete --stats $source $dest 2>&1";

    exec($cmd, $outputLines, $returnCode);

    if ($returnCode !== 0) {
        logMessage($logFile, "HATA: rsync basarisiz (kod $returnCode): " . implode(' | ', array_slice($outputLines, -5)));
        return;
    }

    $transferred = '-';
    $totalTransferredSize = '-';
    foreach ($outputLines as $line) {
        if (stripos($line, 'Number of regular files transferred') !== false) {
            $transferred = trim(explode(':', $line, 2)[1] ?? '-');
        }
        if (stripos($line, 'Total transferred file size') !== false) {
            $totalTransferredSize = trim(explode(':', $line, 2)[1] ?? '-');
        }
    }

    logMessage($logFile, "BASARILI: senkronize edildi (degisen/yeni dosya: $transferred, aktarilan boyut: $totalTransferredSize).");
}

logMessage($logFile, "--- KLASOR YEDEKLEME BASLADI (Hedef: $backupDir) ---");

if (!rsyncAvailable()) {
    logMessage($logFile, "KRITIK HATA: rsync komutu bulunamadi, klasor senkronizasyonu atlaniyor.");
} else {
    foreach ($foldersToBackup as $key => $sourceDir) {
        if (!is_dir($sourceDir)) {
            logMessage($logFile, "UYARI: Kaynak klasor bulunamadi, atlaniyor: $sourceDir");
            continue;
        }

        logMessage($logFile, "'$key' senkronize ediliyor...");
        rsyncMirror($sourceDir, $backupDir . DIRECTORY_SEPARATOR . $key, $logFile);
    }
}

$dbBackupSourceDir = $baseDir . DIRECTORY_SEPARATOR . 'backups';
$dbBackupCandidates = array_merge(
    glob($dbBackupSourceDir . DIRECTORY_SEPARATOR . 'backup_*.sql') ?: [],
    glob($dbBackupSourceDir . DIRECTORY_SEPARATOR . 'backup_*.zip') ?: []
);

if (!empty($dbBackupCandidates)) {
    usort($dbBackupCandidates, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });
    $latestDbBackup = $dbBackupCandidates[0];
    $destFile = $backupDir . DIRECTORY_SEPARATOR . 'veritabani_' . basename($latestDbBackup);

    foreach (glob($backupDir . DIRECTORY_SEPARATOR . 'veritabani_*') as $old) {
        if (realpath($old) !== realpath($destFile)) {
            @unlink($old);
        }
    }

    if (copy($latestDbBackup, $destFile)) {
        $size = round(filesize($destFile) / 1024, 2);
        logMessage($logFile, "BASARILI: Veritabani yedegi kopyalandi -> " . basename($destFile) . " ({$size} KB)");
    } else {
        logMessage($logFile, "HATA: Veritabani yedegi kopyalanamadi: $latestDbBackup");
    }
} else {
    logMessage($logFile, "UYARI: backups/ klasorunde kopyalanacak veritabani yedegi bulunamadi.");
}

logMessage($logFile, "--- KLASOR YEDEKLEME TAMAMLANDI ---");
