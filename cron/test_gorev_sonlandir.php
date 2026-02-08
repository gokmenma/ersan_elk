<?php
/**
 * Otomatik Görev Sonlandırma - Test Scripti
 * 
 * Bu script ile açık görevleri kontrol edebilir ve manuel olarak
 * otomatik sonlandırma işlemini test edebilirsiniz.
 * 
 * Kullanım: Tarayıcıda bu dosyayı açın veya CLI'dan çalıştırın
 */

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');

echo "<pre style='font-family: monospace; padding: 20px; background: #1a1a2e; color: #eee;'>";
echo "=========================================\n";
echo "  Otomatik Görev Sonlandırma Test Aracı\n";
echo "=========================================\n\n";

try {
    require_once dirname(__DIR__) . '/Autoloader.php';

    $HareketModel = new \App\Model\PersonelHareketleriModel();
    $PersonelModel = new \App\Model\PersonelModel();

    echo "⏰ Şu anki zaman: " . date('Y-m-d H:i:s') . "\n";
    echo "📋 Otomatik sonlandırma saati: 23:50\n\n";

    // Tüm açık görevleri bul (sonlandırılmamış)
    $sql = "SELECT ph.*, p.adi_soyadi 
            FROM personel_hareketleri ph
            LEFT JOIN personel p ON ph.personel_id = p.id
            WHERE ph.islem_tipi = 'BASLA'
            AND ph.silinme_tarihi IS NULL
            AND NOT EXISTS (
                SELECT 1 FROM personel_hareketleri ph2 
                WHERE ph2.personel_id = ph.personel_id 
                AND ph2.islem_tipi = 'BITIR' 
                AND ph2.zaman > ph.zaman
                AND ph2.silinme_tarihi IS NULL
            )
            ORDER BY ph.zaman DESC";

    $stmt = $HareketModel->getDb()->prepare($sql);
    $stmt->execute();
    $acikGorevler = $stmt->fetchAll(PDO::FETCH_OBJ);

    $sayisi = count($acikGorevler);

    if ($sayisi === 0) {
        echo "✅ Açık (sonlandırılmamış) görev bulunamadı.\n";
    } else {
        echo "⚠️  Toplam {$sayisi} açık görev bulundu:\n";
        echo str_repeat("-", 80) . "\n";

        foreach ($acikGorevler as $index => $gorev) {
            $baslangicZamani = new DateTime($gorev->zaman);
            $simdi = new DateTime();
            $fark = $simdi->diff($baslangicZamani);

            $sure = "";
            if ($fark->days > 0)
                $sure .= $fark->days . " gün ";
            if ($fark->h > 0)
                $sure .= $fark->h . " saat ";
            $sure .= $fark->i . " dakika";

            $baslangicTarihi = $baslangicZamani->format('Y-m-d');
            $bugun = date('Y-m-d');

            $sonlandirilacakMi = ($baslangicTarihi < $bugun || ($baslangicTarihi === $bugun && date('H:i') >= '23:50'));
            $durum = $sonlandirilacakMi ? "🔴 Sonlandırılacak" : "🟢 Aktif";

            echo "\n📌 #" . ($index + 1) . "\n";
            echo "   Personel: {$gorev->adi_soyadi} (ID: {$gorev->personel_id})\n";
            echo "   Başlangıç: {$gorev->zaman}\n";
            echo "   Geçen Süre: {$sure}\n";
            echo "   Durum: {$durum}\n";
        }

        echo "\n" . str_repeat("-", 80) . "\n";

        // Manuel tetikleme seçeneği
        if (isset($_GET['run']) && $_GET['run'] === '1') {
            echo "\n🚀 Otomatik sonlandırma çalıştırılıyor...\n\n";

            $sonlandirilanlar = $HareketModel->tumAcikGorevleriSonlandir();

            if (count($sonlandirilanlar) > 0) {
                echo "✅ " . count($sonlandirilanlar) . " görev başarıyla sonlandırıldı:\n";
                foreach ($sonlandirilanlar as $s) {
                    echo "   - Personel ID: {$s['personel_id']}, Tarih: {$s['tarih']}\n";
                }
            } else {
                echo "ℹ️  Sonlandırılacak görev bulunamadı (zaten güncel veya bugün 23:50'yi geçmemiş).\n";
            }
        } else {
            echo "\n💡 Manuel sonlandırma için: ?run=1 parametresi ekleyin\n";
            echo "   Örnek: " . $_SERVER['SCRIPT_NAME'] . "?run=1\n";
        }
    }

    echo "\n=========================================\n";
    echo "  Test Tamamlandı\n";
    echo "=========================================\n";

} catch (Exception $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
    echo "Satır: " . $e->getLine() . "\n";
    echo "Dosya: " . $e->getFile() . "\n";
}

echo "</pre>";
