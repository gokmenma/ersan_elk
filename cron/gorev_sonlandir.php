<?php
/**
 * Otomatik Görev Sonlandırma Cron Script'i
 * 
 * Bu script her gece 23:55'te çalıştırılmalıdır.
 * Sonlandırılmamış tüm görevleri 23:50 olarak otomatik sonlandırır.
 * 
 * Windows Task Scheduler için:
 * Program: C:\xampp\php\php.exe
 * Arguments: C:\xampp\htdocs\ersan_elk\cron\gorev_sonlandir.php
 * Tetik: Günlük 23:55
 * 
 * Linux Crontab için:
 * 55 23 * * * /usr/bin/php /path/to/ersan_elk/cron/gorev_sonlandir.php >> /var/log/gorev_sonlandir.log 2>&1
 */

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');

// Log dosyası
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/gorev_sonlandir_' . date('Y-m') . '.log';

// Log fonksiyonu
function writeLog($message, $logFile)
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

writeLog("=== Otomatik Görev Sonlandırma Başladı ===", $logFile);

try {
    // Autoloader'ı dahil et
    require_once dirname(__DIR__) . '/Autoloader.php';

    // Model'i yükle
    $HareketModel = new \App\Model\PersonelHareketleriModel();

    // Tüm açık görevleri sonlandır
    $sonlandirilanlar = $HareketModel->tumAcikGorevleriSonlandir();

    $sayisi = count($sonlandirilanlar);

    if ($sayisi > 0) {
        writeLog("Toplam {$sayisi} görev otomatik sonlandırıldı:", $logFile);

        foreach ($sonlandirilanlar as $gorev) {
            writeLog("  - Personel ID: {$gorev['personel_id']}, Başlangıç: {$gorev['baslangic']}, Tarih: {$gorev['tarih']}", $logFile);
        }

        // Opsiyonel: Yöneticiye bildirim gönder
        try {
            $BildirimModel = new \App\Model\BildirimModel();
            $BildirimModel->createNotification(
                1, // Admin user_id
                'Otomatik Görev Sonlandırma',
                "Bugün {$sayisi} adet görev otomatik olarak sonlandırıldı (23:50 kuralı).",
                'index.php?p=personel-hareketleri/list',
                'schedule',
                'info'
            );
        } catch (Exception $e) {
            writeLog("Bildirim gönderilemedi: " . $e->getMessage(), $logFile);
        }
    } else {
        writeLog("Sonlandırılacak açık görev bulunamadı.", $logFile);
    }

    writeLog("=== Otomatik Görev Sonlandırma Tamamlandı ===", $logFile);

    // CLI çıktısı
    if (php_sapi_name() === 'cli') {
        echo "İşlem tamamlandı. {$sayisi} görev sonlandırıldı.\n";
    }

} catch (Exception $e) {
    $errorMsg = "HATA: " . $e->getMessage();
    writeLog($errorMsg, $logFile);

    if (php_sapi_name() === 'cli') {
        echo $errorMsg . "\n";
        exit(1);
    }
}
