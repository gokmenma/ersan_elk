<?php
/**
 * Personel Hareketleri Tablosu Oluşturma ve Örnek Veri Ekleme
 * Bu dosyayı bir kez çalıştırın: http://localhost/ersan_elk/create_personel_hareketleri.php
 */

require_once __DIR__ . '/Autoloader.php';

use App\Core\Db;

$dbInstance = new Db();
$db = $dbInstance->db;

echo "<h2>Personel Hareketleri Tablosu Kurulumu</h2>";

try {
    // 1. Tablo oluştur
    $sql = "CREATE TABLE IF NOT EXISTS `personel_hareketleri` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `personel_id` INT NOT NULL COMMENT 'İşlemi yapan personel',
        `islem_tipi` ENUM('BASLA', 'BITIR') NOT NULL COMMENT 'İşlem türü',
        `zaman` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Sunucu saati',
        `konum_enlem` DECIMAL(10, 7) NOT NULL COMMENT 'GPS Latitude',
        `konum_boylam` DECIMAL(10, 7) NOT NULL COMMENT 'GPS Longitude',
        `konum_hassasiyeti` DECIMAL(10, 2) NULL COMMENT 'GPS doğruluk (metre)',
        `cihaz_bilgisi` VARCHAR(500) NULL COMMENT 'User agent',
        `ip_adresi` VARCHAR(45) NULL COMMENT 'IP adresi',
        `firma_id` INT NULL COMMENT 'Firma ID',
        `silinme_tarihi` DATETIME NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_personel_tarih` (`personel_id`, `zaman`),
        INDEX `idx_islem_tipi` (`islem_tipi`),
        INDEX `idx_firma_tarih` (`firma_id`, `zaman`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
    COMMENT='Saha personelinin görev giriş-çıkış konum takibi'";

    $db->exec($sql);
    echo "<p style='color: green;'>✅ Tablo başarıyla oluşturuldu!</p>";

    // 2. Mevcut personelleri al
    $stmt = $db->query("SELECT id, adi_soyadi FROM personel WHERE silinme_tarihi IS NULL AND durum = 'Aktif' LIMIT 10");
    $personeller = $stmt->fetchAll(PDO::FETCH_OBJ);

    if (empty($personeller)) {
        echo "<p style='color: orange;'>⚠️ Aktif personel bulunamadı. Örnek veri eklenemedi.</p>";
        exit;
    }

    echo "<p>📋 " . count($personeller) . " personel bulundu.</p>";

    // 3. Örnek veriler ekle (son 7 gün için)
    $ornek_konumlar = [
        ['enlem' => 41.0082, 'boylam' => 28.9784, 'sehir' => 'İstanbul'],
        ['enlem' => 39.9334, 'boylam' => 32.8597, 'sehir' => 'Ankara'],
        ['enlem' => 38.4192, 'boylam' => 27.1287, 'sehir' => 'İzmir'],
        ['enlem' => 40.1885, 'boylam' => 29.0610, 'sehir' => 'Bursa'],
        ['enlem' => 36.8969, 'boylam' => 30.7133, 'sehir' => 'Antalya'],
        ['enlem' => 37.0662, 'boylam' => 37.3833, 'sehir' => 'Gaziantep'],
        ['enlem' => 40.7654, 'boylam' => 29.9408, 'sehir' => 'Kocaeli'],
        ['enlem' => 41.4534, 'boylam' => 31.7890, 'sehir' => 'Zonguldak'],
    ];

    $insert_sql = "INSERT INTO personel_hareketleri 
                   (personel_id, islem_tipi, zaman, konum_enlem, konum_boylam, konum_hassasiyeti, cihaz_bilgisi, ip_adresi, firma_id) 
                   VALUES 
                   (:personel_id, :islem_tipi, :zaman, :konum_enlem, :konum_boylam, :hassasiyet, :cihaz, :ip, :firma_id)";

    $insert_stmt = $db->prepare($insert_sql);
    $eklenen = 0;

    // Her personel için son 7 günde rastgele veriler oluştur
    foreach ($personeller as $index => $personel) {
        $konum = $ornek_konumlar[$index % count($ornek_konumlar)];

        // Son 7 günlük veri
        for ($gun = 6; $gun >= 0; $gun--) {
            $tarih = date('Y-m-d', strtotime("-{$gun} days"));

            // %70 olasılıkla o gün çalışmış
            if (rand(1, 100) <= 70) {
                // Rastgele başlama saati (07:30 - 09:30 arası)
                $baslama_saat = rand(7, 9);
                $baslama_dakika = rand(0, 59);
                $baslama_zaman = $tarih . ' ' . sprintf('%02d:%02d:00', $baslama_saat, $baslama_dakika);

                // Küçük konum varyasyonu ekle
                $enlem = $konum['enlem'] + (rand(-100, 100) / 10000);
                $boylam = $konum['boylam'] + (rand(-100, 100) / 10000);

                // BASLA kaydı
                $insert_stmt->execute([
                    ':personel_id' => $personel->id,
                    ':islem_tipi' => 'BASLA',
                    ':zaman' => $baslama_zaman,
                    ':konum_enlem' => $enlem,
                    ':konum_boylam' => $boylam,
                    ':hassasiyet' => rand(5, 50),
                    ':cihaz' => 'Mozilla/5.0 (Linux; Android 12) Chrome/120.0',
                    ':ip' => '192.168.1.' . rand(1, 254),
                    ':firma_id' => 1
                ]);
                $eklenen++;

                // %90 olasılıkla bitirmiş (bugün hariç)
                if ($gun > 0 && rand(1, 100) <= 90) {
                    // Rastgele bitiş saati (16:30 - 18:30 arası)
                    $bitis_saat = rand(16, 18);
                    $bitis_dakika = rand(0, 59);
                    $bitis_zaman = $tarih . ' ' . sprintf('%02d:%02d:00', $bitis_saat, $bitis_dakika);

                    // Biraz farklı konum
                    $enlem2 = $konum['enlem'] + (rand(-100, 100) / 10000);
                    $boylam2 = $konum['boylam'] + (rand(-100, 100) / 10000);

                    // BITIR kaydı
                    $insert_stmt->execute([
                        ':personel_id' => $personel->id,
                        ':islem_tipi' => 'BITIR',
                        ':zaman' => $bitis_zaman,
                        ':konum_enlem' => $enlem2,
                        ':konum_boylam' => $boylam2,
                        ':hassasiyet' => rand(5, 50),
                        ':cihaz' => 'Mozilla/5.0 (Linux; Android 12) Chrome/120.0',
                        ':ip' => '192.168.1.' . rand(1, 254),
                        ':firma_id' => 1
                    ]);
                    $eklenen++;
                }
            }
        }

        // Bugün için bazı personeller aktif görevde olsun
        if ($index < 3 && $gun == 0) {
            // Bugün için BASLA kaydı (bitirmemiş - aktif görevde)
            $bugun = date('Y-m-d');
            $baslama_saat = rand(7, 9);
            $baslama_dakika = rand(0, 30);
            $baslama_zaman = $bugun . ' ' . sprintf('%02d:%02d:00', $baslama_saat, $baslama_dakika);

            $enlem = $konum['enlem'] + (rand(-100, 100) / 10000);
            $boylam = $konum['boylam'] + (rand(-100, 100) / 10000);

            $insert_stmt->execute([
                ':personel_id' => $personel->id,
                ':islem_tipi' => 'BASLA',
                ':zaman' => $baslama_zaman,
                ':konum_enlem' => $enlem,
                ':konum_boylam' => $boylam,
                ':hassasiyet' => rand(5, 50),
                ':cihaz' => 'Mozilla/5.0 (Linux; Android 12) Chrome/120.0',
                ':ip' => '192.168.1.' . rand(1, 254),
                ':firma_id' => 1
            ]);
            $eklenen++;
        }
    }

    echo "<p style='color: green;'>✅ Toplam {$eklenen} örnek kayıt eklendi!</p>";

    // 4. Özet göster
    $stmt = $db->query("SELECT COUNT(*) as toplam FROM personel_hareketleri");
    $toplam = $stmt->fetch(PDO::FETCH_OBJ)->toplam;

    $stmt = $db->query("SELECT COUNT(*) as aktif FROM personel_hareketleri ph 
                        WHERE ph.islem_tipi = 'BASLA' 
                        AND NOT EXISTS (
                            SELECT 1 FROM personel_hareketleri ph2 
                            WHERE ph2.personel_id = ph.personel_id 
                            AND ph2.islem_tipi = 'BITIR' 
                            AND ph2.zaman > ph.zaman
                        )");
    $aktif = $stmt->fetch(PDO::FETCH_OBJ)->aktif;

    echo "<hr>";
    echo "<h3>📊 Özet</h3>";
    echo "<ul>";
    echo "<li>Toplam kayıt: <strong>{$toplam}</strong></li>";
    echo "<li>Aktif görevde olan: <strong>{$aktif}</strong> personel</li>";
    echo "</ul>";

    echo "<hr>";
    echo "<h3>🔗 Bağlantılar</h3>";
    echo "<ul>";
    echo "<li><a href='index.php?p=personel-takip/list'>Yönetici Paneli</a></li>";
    echo "<li><a href='views/personel-pwa/index.php'>PWA Personel Portalı</a></li>";
    echo "</ul>";

    echo "<p style='color: green; font-weight: bold;'>✅ Kurulum tamamlandı!</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Hata: " . $e->getMessage() . "</p>";
}
?>