<?php
/**
 * KVKK Veri Saklama ve Temizleme Cron Scripti
 *
 * Çalıştırmak için: php cron/data_retention.php
 * Crontab: 0 3 * * 0 /usr/bin/php /path/to/ersan_elk/cron/data_retention.php
 */

if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Bu script sadece CLI üzerinden çalışabilir.');
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once dirname(__DIR__) . '/Autoloader.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

use App\Core\Db;

$db = new Db();
$pdo = $db->getConnection();

$deleted = [];

// system_logs: 2 yıldan eski bilgi/sayfa görüntüleme kayıtları temizle
$stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR) AND level IN (0, 3)");
$stmt->execute();
$deleted['system_logs_info'] = $stmt->rowCount();

// personel_giris_loglari: 1 yıldan eski kayıtlar
$stmt = $pdo->prepare("DELETE FROM personel_giris_loglari WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
$stmt->execute();
$deleted['personel_giris_loglari'] = $stmt->rowCount();

// personel_hareketleri (GPS): 6 aydan eski kayıtlar
$stmt = $pdo->prepare("DELETE FROM personel_hareketleri WHERE kayit_tarihi < DATE_SUB(NOW(), INTERVAL 6 MONTH)");
$stmt->execute();
$deleted['personel_hareketleri'] = $stmt->rowCount();

// mesaj_log: 1 yıldan eski SMS/e-posta logları
$stmt = $pdo->prepare("DELETE FROM mesaj_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
$stmt->execute();
$deleted['mesaj_log'] = $stmt->rowCount();

$summary = implode(', ', array_map(fn($k, $v) => "$k: $v satır", array_keys($deleted), $deleted));
error_log("[data_retention] " . date('Y-m-d H:i:s') . " - Temizlendi: $summary");
echo "Tamamlandı: $summary\n";
