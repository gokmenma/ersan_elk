<?php
/**
 * Endeks Okuma Cron Job
 * 
 * Bu dosya her 15 dakikada bir çalıştırılmalıdır.
 * Ayarlarda belirlenen saatlerde Endeks Okuma sorgulamalarını yapar.
 * 
 * Cron kurulumu (Linux):
 * /usr/local/bin/php -q /home/mbeyazil/repositories/ersan_elk/views/cron/endeks_okuma_cron.php >> /home/mbeyazil/repositories/ersan_elk/views/cron/logs/cron.log 2>&1
 */

// Gerekli dosyaları yükle
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\SettingsModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\SystemLogModel;

// CLI modunda mı çalışıyor?
$isCli = (php_sapi_name() === 'cli');

// Başlangıç zamanı
$startTime = microtime(true);
$logDate = date('Y-m-d H:i:s');

// Log dizinini oluştur
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Hata ayıklama için log fonksiyonu
function cronLog($message) {
    global $logDate;
    $logFile = __DIR__ . '/logs/endeks_cron_' . date('Y-m-d') . '.log';
    $logMessage = "[$logDate] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // CLI'da ekrana da yaz
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

cronLog("=== Endeks Okuma Cron başlatıldı ===");

try {
    // Session simülasyonu (cron'da session olmaz)
    if (!isset($_SESSION)) {
        $_SESSION = [];
    }

    // Ayarları al
    $Settings = new SettingsModel();
    $allSettings = $Settings->getAllSettingsAsKeyValue();

    // Online sorgulama aktif mi?
    $online_sorgulama_aktif = $allSettings['online_sorgulama_aktif'] ?? false;
    
    if (!$online_sorgulama_aktif) {
        cronLog("Online sorgulama devre dışı. Çıkılıyor.");
        exit(0);
    }

    // Endeks saatlerini al (virgülle ayrılmış çoklu saatler)
    $endeksSaatStr = $allSettings['online_sorgulama_endeks_saat'] ?? '08:00';
    $firmaBaslangic = $allSettings['online_sorgulama_firma_baslangic'] ?? 17;
    $firmaBitis = $allSettings['online_sorgulama_firma_bitis'] ?? 17;

    // Çoklu saatleri diziye çevir
    $endeksSaatler = array_filter(array_map('trim', explode(',', $endeksSaatStr)));

    // Şu anki saat (15 dakikalık dilimlere yuvarla)
    $simdikiDakika = (int)date('i');
    $yuvarlanmisDakika = floor($simdikiDakika / 15) * 15;
    $simdikiSaat = date('H') . ':' . str_pad($yuvarlanmisDakika, 2, '0', STR_PAD_LEFT);

    cronLog("Kontrol: Şimdiki saat (yuvarlanmış): $simdikiSaat");
    cronLog("Endeks saatleri: " . implode(', ', $endeksSaatler));

    // Firma ID'sini al (ilk firmadan)
    $firmaId = 0;
    try {
        $db = (new PuantajModel())->db;
        $stmt = $db->prepare("SELECT id FROM firma WHERE firma_kodu = ? LIMIT 1");
        $stmt->execute([$firmaBaslangic]);
        $firma = $stmt->fetch(PDO::FETCH_OBJ);
        $firmaId = $firma ? $firma->id : 0;
    } catch (Exception $e) {
        cronLog("Firma ID alınamadı: " . $e->getMessage());
    }

    $_SESSION['firma_id'] = $firmaId;
    $_SESSION['firma_kodu'] = $firmaBaslangic;

    $bugun = date('Y-m-d');

    // Son çalıştırma log'unu al
    $endeksSonCalistirmaLog = $allSettings['online_sorgulama_endeks_son_calistirma_log'] ?? '';

    // Endeks sorgulama - çoklu saatleri kontrol et
    if (in_array($simdikiSaat, $endeksSaatler)) {
        cronLog("Endeks sorgulama zamanı geldi! (Saat: $simdikiSaat)");
        
        // Bu saat + bugün için zaten çalıştırılmış mı?
        $bugunSaatKey = $bugun . ' ' . $simdikiSaat;
        
        if (strpos($endeksSonCalistirmaLog, $bugunSaatKey) === false) {
            cronLog("Endeks sorgulama başlatılıyor...");
            
            $sonuc = sorgulamaEndeks($firmaBaslangic, $firmaBitis, $bugun, $firmaId);
            
            // Son çalıştırma zamanını güncelle
            $Settings->upsertSetting('online_sorgulama_endeks_son_calistirma', date('d.m.Y H:i:s'));
            
            // Log'a bu saati ekle (gün değişince sıfırlanır)
            $logParts = array_filter(explode(',', $endeksSonCalistirmaLog));
            $logParts = array_filter($logParts, function($item) use ($bugun) {
                return strpos(trim($item), $bugun) === 0;
            });
            $logParts[] = $bugunSaatKey;
            $Settings->upsertSetting('online_sorgulama_endeks_son_calistirma_log', implode(',', $logParts));
            
            cronLog("Endeks sorgulama tamamlandı: " . json_encode($sonuc));
        } else {
            cronLog("Endeks sorgulama bu saat ($simdikiSaat) için bugün zaten çalıştırılmış.");
        }
    } else {
        cronLog("Endeks sorgulama saati değil, atlanıyor.");
    }

} catch (Exception $e) {
    cronLog("HATA: " . $e->getMessage());
    cronLog("Stack trace: " . $e->getTraceAsString());
}

$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2);
cronLog("Endeks Cron tamamlandı. Süre: {$executionTime}ms");
cronLog("========================================\n");

/**
 * Endeks Okuma Sorgulama Fonksiyonu
 */
function sorgulamaEndeks($ilkFirma, $sonFirma, $tarih, $firmaId) {
    global $Settings;
    
    $EndeksOkuma = new EndeksOkumaModel();
    $SystemLog = new SystemLogModel();
    
    $yeniKayit = 0;
    $guncellenenKayit = 0;
    $mevcutKayitlar = [];
    
    // TODO: API hazır olduğunda gerçek API çağrısı yapılacak
    // Şimdilik test verileri
    $bolgeler = ['AFŞİN', 'ELBİSTAN', 'GÖKSUN', 'TÜRKOĞLU'];
    $testVeriler = [
        [
            'islem_id' => 'CRON_ENDEKS_' . uniqid() . '_1',
            'bolge' => $bolgeler[array_rand($bolgeler)],
            'kullanici_adi' => 'ER-SAN ELEKTRİK EKİP-' . rand(1, 20),
            'sarfiyat' => rand(1000, 5000) + (rand(0, 99) / 100),
            'ort_sarfiyat_gunluk' => rand(100, 500) + (rand(0, 99) / 100),
            'tahakkuk' => rand(10000, 50000) + (rand(0, 99) / 100),
            'ort_tahakkuk_gunluk' => rand(1000, 5000) + (rand(0, 99) / 100),
            'okunan_gun_sayisi' => rand(1, 5),
            'okunan_abone_sayisi' => rand(50, 200),
            'ort_okunan_abone_sayisi_gunluk' => rand(30, 100) + (rand(0, 99) / 100),
            'okuma_performansi' => rand(80, 120) + (rand(0, 99) / 100),
            'tarih' => $tarih
        ],
        [
            'islem_id' => 'CRON_ENDEKS_' . uniqid() . '_2',
            'bolge' => $bolgeler[array_rand($bolgeler)],
            'kullanici_adi' => 'ER-SAN ELEKTRİK EKİP-' . rand(1, 20),
            'sarfiyat' => rand(1000, 5000) + (rand(0, 99) / 100),
            'ort_sarfiyat_gunluk' => rand(100, 500) + (rand(0, 99) / 100),
            'tahakkuk' => rand(10000, 50000) + (rand(0, 99) / 100),
            'ort_tahakkuk_gunluk' => rand(1000, 5000) + (rand(0, 99) / 100),
            'okunan_gun_sayisi' => rand(1, 5),
            'okunan_abone_sayisi' => rand(50, 200),
            'ort_okunan_abone_sayisi_gunluk' => rand(30, 100) + (rand(0, 99) / 100),
            'okuma_performansi' => rand(80, 120) + (rand(0, 99) / 100),
            'tarih' => $tarih
        ],
        [
            'islem_id' => 'CRON_ENDEKS_' . uniqid() . '_3',
            'bolge' => $bolgeler[array_rand($bolgeler)],
            'kullanici_adi' => 'ER-SAN ELEKTRİK EKİP-' . rand(1, 20),
            'sarfiyat' => rand(1000, 5000) + (rand(0, 99) / 100),
            'ort_sarfiyat_gunluk' => rand(100, 500) + (rand(0, 99) / 100),
            'tahakkuk' => rand(10000, 50000) + (rand(0, 99) / 100),
            'ort_tahakkuk_gunluk' => rand(1000, 5000) + (rand(0, 99) / 100),
            'okunan_gun_sayisi' => rand(1, 5),
            'okunan_abone_sayisi' => rand(50, 200),
            'ort_okunan_abone_sayisi_gunluk' => rand(30, 100) + (rand(0, 99) / 100),
            'okuma_performansi' => rand(80, 120) + (rand(0, 99) / 100),
            'tarih' => $tarih
        ]
    ];
    
    foreach ($testVeriler as $veri) {
        // Daha önce aynı islem_id ile kayıt var mı kontrol et
        $checkStmt = $EndeksOkuma->db->prepare("SELECT id, islem_id FROM endeks_okuma WHERE islem_id = ?");
        $checkStmt->execute([$veri['islem_id']]);
        $mevcutKayit = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mevcutKayit) {
            // Güncelle
            $updateStmt = $EndeksOkuma->db->prepare("UPDATE endeks_okuma SET sarfiyat = ?, ort_sarfiyat_gunluk = ?, tahakkuk = ?, ort_tahakkuk_gunluk = ?, okunan_gun_sayisi = ?, okunan_abone_sayisi = ?, ort_okunan_abone_sayisi_gunluk = ?, okuma_performansi = ?, tarih = ? WHERE islem_id = ?");
            $updateStmt->execute([
                $veri['sarfiyat'],
                $veri['ort_sarfiyat_gunluk'],
                $veri['tahakkuk'],
                $veri['ort_tahakkuk_gunluk'],
                $veri['okunan_gun_sayisi'],
                $veri['okunan_abone_sayisi'],
                $veri['ort_okunan_abone_sayisi_gunluk'],
                $veri['okuma_performansi'],
                $veri['tarih'],
                $veri['islem_id']
            ]);
            $guncellenenKayit++;
            $mevcutKayitlar[] = $mevcutKayit;
        } else {
            // Personel eşleştirme
            $personelId = 0;
            $ekipNo = 0;
            if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $veri['kullanici_adi'], $m)) {
                $ekipNo = $m[1];
            }
            
            if ($ekipNo > 0) {
                $stmtDef = $EndeksOkuma->db->prepare("SELECT id FROM tanimlamalar WHERE (tur_adi LIKE ? OR tur_adi LIKE ?) AND silinme_tarihi IS NULL LIMIT 1");
                $stmtDef->execute(["%EKİP-$ekipNo", "%EKIP-$ekipNo"]);
                $defId = $stmtDef->fetchColumn();
                
                if ($defId) {
                    $stmtPersonel = $EndeksOkuma->db->prepare("SELECT id FROM personel WHERE ekip_no = ? AND silinme_tarihi IS NULL LIMIT 1");
                    $stmtPersonel->execute([$defId]);
                    $personelId = $stmtPersonel->fetchColumn() ?: 0;
                }
            }
            
            // Yeni kayıt ekle
            $insertStmt = $EndeksOkuma->db->prepare("INSERT INTO endeks_okuma (islem_id, personel_id, firma_id, bolge, kullanici_adi, sarfiyat, ort_sarfiyat_gunluk, tahakkuk, ort_tahakkuk_gunluk, okunan_gun_sayisi, okunan_abone_sayisi, ort_okunan_abone_sayisi_gunluk, okuma_performansi, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([
                $veri['islem_id'],
                $personelId,
                $firmaId,
                $veri['bolge'],
                $veri['kullanici_adi'],
                $veri['sarfiyat'],
                $veri['ort_sarfiyat_gunluk'],
                $veri['tahakkuk'],
                $veri['ort_tahakkuk_gunluk'],
                $veri['okunan_gun_sayisi'],
                $veri['okunan_abone_sayisi'],
                $veri['ort_okunan_abone_sayisi_gunluk'],
                $veri['okuma_performansi'],
                $veri['tarih']
            ]);
            $yeniKayit++;
        }
    }
    
    // Log kaydet
    $SystemLog->logAction(0, 'Cron - Online Endeks Sorgulama', "Firma $ilkFirma-$sonFirma, Tarih: $tarih. $yeniKayit yeni, $guncellenenKayit güncellenen kayıt.");
    
    return [
        'yeni_kayit' => $yeniKayit,
        'guncellenen_kayit' => $guncellenenKayit,
        'mevcut_kayitlar' => count($mevcutKayitlar)
    ];
}
