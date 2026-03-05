<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Model\GorevModel;

$db = (new GorevModel())->db;

// 1. PHP Zamanı
date_default_timezone_set('Europe/Istanbul');
echo "PHP Zamanı: " . date('Y-m-d H:i:s') . "\n";

// 2. MySQL Zamanı
$stmt = $db->query("SELECT NOW() as db_now, CURDATE() as db_date, CURTIME() as db_time");
$dbTime = $stmt->fetch(PDO::FETCH_ASSOC);
echo "MySQL Zamanı:\n";
print_r($dbTime);

// 3. Görevleri çek
$stmt = $db->query("SELECT id, baslik, tarih, saat, bildirim_gonderildi, tamamlandi FROM gorevler WHERE id = 2");
echo "Görev Verisi:\n";
print_r($stmt->fetch(PDO::FETCH_ASSOC));

// 4. Model üzerinden test
$model = new GorevModel();
$Settings = new \App\Model\SettingsModel();
$offset = (int) ($Settings->getSettings('gorev_bildirim_dakika') ?? 0);
echo "Offset Ayarı: $offset dakika\n";

$sql = "SELECT g.id, g.baslik, g.tarih, g.saat, ADDTIME(CURTIME(), SEC_TO_TIME(:offset * 60)) as limit_saat
        FROM gorevler g
        WHERE g.tarih = CURDATE()
          AND g.tamamlandi = 0
          AND g.bildirim_gonderildi = 0
          AND (
            g.saat IS NULL
            OR g.saat <= ADDTIME(CURTIME(), SEC_TO_TIME(:offset2 * 60))
          )";
$stmt = $db->prepare($sql);
$stmt->execute([':offset' => $offset, ':offset2' => $offset]);
echo "Bekleyen Görevler Sorgusu Sonucu:\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
