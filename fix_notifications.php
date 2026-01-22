<?php
require_once 'Autoloader.php';
use App\Model\BildirimModel;
use App\Core\Db;

$db = (new Db())->getConnection();

echo "<h1>Bildirim Sistemi Tanı ve Onarım</h1>";

// 1. Tablo Kontrolü
echo "<h3>1. Tablo Kontrolü</h3>";
try {
    $db->query("SELECT 1 FROM bildirimler LIMIT 1");
    echo "<p style='color:green'>[OK] 'bildirimler' tablosu mevcut.</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>[HATA] 'bildirimler' tablosu bulunamadı. Oluşturuluyor...</p>";
    $sql = "CREATE TABLE IF NOT EXISTS bildirimler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        icon VARCHAR(50) DEFAULT 'bell',
        color VARCHAR(20) DEFAULT 'primary',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    try {
        $db->exec($sql);
        echo "<p style='color:green'>[OK] Tablo başarıyla oluşturuldu.</p>";
    } catch (Exception $ex) {
        echo "<p style='color:red'>[HATA] Tablo oluşturulamadı: " . $ex->getMessage() . "</p>";
    }
}

// 2. Yazma İzni Kontrolü
echo "<h3>2. Log Dosyası Kontrolü</h3>";
$logFile = __DIR__ . '/debug_bildirim.log';
if (@file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Tanı testi\n", FILE_APPEND)) {
    echo "<p style='color:green'>[OK] Log dosyasına yazılabiliyor: $logFile</p>";
} else {
    echo "<p style='color:red'>[HATA] Log dosyasına yazılamıyor! Klasör izinlerini (CHMOD 777 veya 755) kontrol edin.</p>";
}

// 3. Örnek Bildirim Testi
echo "<h3>3. Örnek Bildirim Testi</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = $_SESSION['user_id'] ?? 0;
if ($userId > 0) {
    try {
        $model = new BildirimModel();
        $res = $model->createNotification($userId, "Sistem Testi", "Bu bir otomatik tanı bildirimidir.", "index.php", "check", "info");
        echo "<p style='color:green'>[OK] Mevcut kullanıcınız (ID: $userId) için test bildirimi oluşturuldu. ID: $res</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>[HATA] Bildirim oluşturulamadı: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:orange'>[UYARI] Oturum açılmadığı için kullanıcı bazlı test yapılamadı. Lütfen yönetici girişi yapıp bu sayfayı yenileyin.</p>";
}

echo "<hr><p>Bu sayfada hata görmüyorsanız bildirim sistemi teknik olarak çalışıyor demektir. Hala bildirim gelmiyorsa PWA tarafındaki tetikleyicileri (avans/izin talebi) kontrol etmek gerekir.</p>";
