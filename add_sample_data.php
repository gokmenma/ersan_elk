<?php
/**
 * Örnek Veri Ekleme Scripti
 */

require_once __DIR__ . '/Autoloader.php';

use App\Core\Db;

$dbInstance = new Db();
$db = $dbInstance->db;

echo "=== Örnek Veri Ekleme ===\n\n";

try {
    // Mevcut personelleri al
    $stmt = $db->query("SELECT id, adi_soyadi FROM personel WHERE silinme_tarihi IS NULL AND aktif_mi = 1 LIMIT 10");
    $personeller = $stmt->fetchAll(PDO::FETCH_OBJ);

    if (empty($personeller)) {
        echo "⚠️ Aktif personel bulunamadı!\n";
        exit;
    }

    echo "📋 " . count($personeller) . " personel bulundu.\n\n";

    // Örnek konumlar (Türkiye'den şehirler)
    $ornek_konumlar = [
        ['enlem' => 41.0082, 'boylam' => 28.9784],  // İstanbul
        ['enlem' => 39.9334, 'boylam' => 32.8597],  // Ankara
        ['enlem' => 38.4192, 'boylam' => 27.1287],  // İzmir
        ['enlem' => 40.1885, 'boylam' => 29.0610],  // Bursa
        ['enlem' => 36.8969, 'boylam' => 30.7133],  // Antalya
        ['enlem' => 37.0662, 'boylam' => 37.3833],  // Gaziantep
        ['enlem' => 40.7654, 'boylam' => 29.9408],  // Kocaeli
        ['enlem' => 41.4534, 'boylam' => 31.7890],  // Zonguldak
    ];

    $insert_sql = "INSERT INTO personel_hareketleri 
                   (personel_id, islem_tipi, zaman, konum_enlem, konum_boylam, konum_hassasiyeti, cihaz_bilgisi, ip_adresi, firma_id) 
                   VALUES 
                   (:personel_id, :islem_tipi, :zaman, :konum_enlem, :konum_boylam, :hassasiyet, :cihaz, :ip, :firma_id)";

    $insert_stmt = $db->prepare($insert_sql);
    $eklenen = 0;

    foreach ($personeller as $index => $personel) {
        $konum = $ornek_konumlar[$index % count($ornek_konumlar)];

        echo "👤 {$personel->adi_soyadi} için veri ekleniyor...\n";

        // Son 7 gün için veri oluştur
        for ($gun = 6; $gun >= 0; $gun--) {
            $tarih = date('Y-m-d', strtotime("-{$gun} days"));

            // %80 olasılıkla o gün çalışmış
            if (rand(1, 100) <= 80) {
                // Rastgele başlama saati (07:30 - 09:30 arası)
                $baslama_saat = rand(7, 9);
                $baslama_dakika = rand(0, 59);
                $baslama_zaman = $tarih . ' ' . sprintf('%02d:%02d:00', $baslama_saat, $baslama_dakika);

                // Küçük konum varyasyonu
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

                // Bugün değilse bitir (bugün aktif olsun)
                if ($gun > 0) {
                    // Rastgele bitiş saati (16:30 - 18:30)
                    $bitis_saat = rand(16, 18);
                    $bitis_dakika = rand(0, 59);
                    $bitis_zaman = $tarih . ' ' . sprintf('%02d:%02d:00', $bitis_saat, $bitis_dakika);

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
    }

    echo "\n✅ Toplam {$eklenen} kayıt eklendi!\n";

    // Özet göster
    $stmt = $db->query("SELECT COUNT(*) as toplam FROM personel_hareketleri");
    $toplam = $stmt->fetch(PDO::FETCH_OBJ)->toplam;
    echo "\n📊 Tablodaki toplam kayıt: {$toplam}\n";

    // Aktif görevde olanlar
    $stmt = $db->query("SELECT COUNT(DISTINCT personel_id) as aktif FROM personel_hareketleri ph 
                        WHERE ph.islem_tipi = 'BASLA' 
                        AND DATE(ph.zaman) = CURDATE()
                        AND NOT EXISTS (
                            SELECT 1 FROM personel_hareketleri ph2 
                            WHERE ph2.personel_id = ph.personel_id 
                            AND ph2.islem_tipi = 'BITIR' 
                            AND ph2.zaman > ph.zaman
                        )");
    $aktif = $stmt->fetch(PDO::FETCH_OBJ)->aktif;
    echo "🏃 Bugün aktif görevde: {$aktif} personel\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>