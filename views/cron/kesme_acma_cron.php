<?php
/**
 * Kesme/Açma (Puantaj) Cron Job
 * 
 * Bu dosya her 15 dakikada bir çalıştırılmalıdır.
 * Ayarlarda belirlenen saatlerde Kesme/Açma sorgulamalarını yapar.
 * 
 * Cron kurulumu (Linux):
 * /usr/local/bin/php -q /home/mbeyazil/repositories/ersan_elk/views/cron/kesme_acma_cron.php >> /home/mbeyazil/repositories/ersan_elk/views/cron/logs/cron.log 2>&1
 */

// Gerekli dosyaları yükle
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\SettingsModel;
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
    $logFile = __DIR__ . '/logs/kesme_acma_cron_' . date('Y-m-d') . '.log';
    $logMessage = "[$logDate] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // CLI'da ekrana da yaz
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

cronLog("=== Kesme/Açma Cron başlatıldı ===");

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

    // Puantaj (Kesme/Açma) saatlerini al (virgülle ayrılmış çoklu saatler)
    $puantajSaatStr = $allSettings['online_sorgulama_puantaj_saat'] ?? '08:00';
    $firmaBaslangic = $allSettings['online_sorgulama_firma_baslangic'] ?? 17;
    $firmaBitis = $allSettings['online_sorgulama_firma_bitis'] ?? 17;

    // Çoklu saatleri diziye çevir
    $puantajSaatler = array_filter(array_map('trim', explode(',', $puantajSaatStr)));

    // Şu anki saat (15 dakikalık dilimlere yuvarla)
    $simdikiDakika = (int)date('i');
    $yuvarlanmisDakika = floor($simdikiDakika / 15) * 15;
    $simdikiSaat = date('H') . ':' . str_pad($yuvarlanmisDakika, 2, '0', STR_PAD_LEFT);

    cronLog("Kontrol: Şimdiki saat (yuvarlanmış): $simdikiSaat");
    cronLog("Kesme/Açma saatleri: " . implode(', ', $puantajSaatler));

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
    $puantajSonCalistirmaLog = $allSettings['online_sorgulama_puantaj_son_calistirma_log'] ?? '';

    // Puantaj sorgulama - çoklu saatleri kontrol et
    if (in_array($simdikiSaat, $puantajSaatler)) {
        cronLog("Kesme/Açma sorgulama zamanı geldi! (Saat: $simdikiSaat)");
        
        // Bu saat + bugün için zaten çalıştırılmış mı?
        $bugunSaatKey = $bugun . ' ' . $simdikiSaat;
        
        if (strpos($puantajSonCalistirmaLog, $bugunSaatKey) === false) {
            cronLog("Kesme/Açma sorgulama başlatılıyor...");
            
            $sonuc = sorgulamaPuantaj($firmaBaslangic, $firmaBitis, $bugun, $firmaId);
            
            // Son çalıştırma zamanını güncelle
            $Settings->upsertSetting('online_sorgulama_puantaj_son_calistirma', date('d.m.Y H:i:s'));
            
            // Log'a bu saati ekle (gün değişince sıfırlanır)
            $logParts = array_filter(explode(',', $puantajSonCalistirmaLog));
            $logParts = array_filter($logParts, function($item) use ($bugun) {
                return strpos(trim($item), $bugun) === 0;
            });
            $logParts[] = $bugunSaatKey;
            $Settings->upsertSetting('online_sorgulama_puantaj_son_calistirma_log', implode(',', $logParts));
            
            cronLog("Kesme/Açma sorgulama tamamlandı: " . json_encode($sonuc));
        } else {
            cronLog("Kesme/Açma sorgulama bu saat ($simdikiSaat) için bugün zaten çalıştırılmış.");
        }
    } else {
        cronLog("Kesme/Açma sorgulama saati değil, atlanıyor.");
    }

} catch (Exception $e) {
    cronLog("HATA: " . $e->getMessage());
    cronLog("Stack trace: " . $e->getTraceAsString());
}

$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2);
cronLog("Kesme/Açma Cron tamamlandı. Süre: {$executionTime}ms");
cronLog("========================================\n");

/**
 * Puantaj (Kesme/Açma) Sorgulama Fonksiyonu
 */
function sorgulamaPuantaj($ilkFirma, $sonFirma, $tarih, $firmaId) {
    global $Settings;
    
    $Puantaj = new PuantajModel();
    $SystemLog = new SystemLogModel();
    
    $yeniKayit = 0;
    $guncellenenKayit = 0;
    $mevcutKayitlar = [];
    
    // TODO: API hazır olduğunda gerçek API çağrısı yapılacak
    // Şimdilik test verileri
    $testVeriler = [
        [
            'islem_id' => 'CRON_PUANTAJ_' . uniqid() . '_1',
            'firma' => 'ER-SAN ELEKTRİK',
            'is_emri_tipi' => 'KESME İŞEMRİ',
            'ekip_kodu' => 'EKİP-' . rand(1, 20),
            'is_emri_sonucu' => 'BAŞARILI KESME',
            'sonuclanmis' => rand(5, 20),
            'acik_olanlar' => rand(0, 5),
            'tarih' => $tarih
        ],
        [
            'islem_id' => 'CRON_PUANTAJ_' . uniqid() . '_2',
            'firma' => 'ER-SAN ELEKTRİK',
            'is_emri_tipi' => 'KESME İŞEMRİ',
            'ekip_kodu' => 'EKİP-' . rand(1, 20),
            'is_emri_sonucu' => 'AÇMA İŞLEMİ',
            'sonuclanmis' => rand(3, 15),
            'acik_olanlar' => rand(0, 3),
            'tarih' => $tarih
        ],
        [
            'islem_id' => 'CRON_PUANTAJ_' . uniqid() . '_3',
            'firma' => 'ER-SAN ELEKTRİK',
            'is_emri_tipi' => 'KESME İŞEMRİ',
            'ekip_kodu' => 'EKİP-' . rand(1, 20),
            'is_emri_sonucu' => 'İPTAL EDİLDİ',
            'sonuclanmis' => rand(1, 10),
            'acik_olanlar' => rand(0, 2),
            'tarih' => $tarih
        ]
    ];
    
    foreach ($testVeriler as $veri) {
        // Daha önce aynı islem_id ile kayıt var mı kontrol et
        $checkStmt = $Puantaj->db->prepare("SELECT id, islem_id FROM yapilan_isler WHERE islem_id = ?");
        $checkStmt->execute([$veri['islem_id']]);
        $mevcutKayit = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mevcutKayit) {
            // Güncelle
            $updateStmt = $Puantaj->db->prepare("UPDATE yapilan_isler SET sonuclanmis = ?, acik_olanlar = ?, tarih = ? WHERE islem_id = ?");
            $updateStmt->execute([$veri['sonuclanmis'], $veri['acik_olanlar'], $veri['tarih'], $veri['islem_id']]);
            $guncellenenKayit++;
            $mevcutKayitlar[] = $mevcutKayit;
        } else {
            // Personel eşleştirme
            $personelId = 0;
            $ekipNo = 0;
            if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $veri['ekip_kodu'], $m)) {
                $ekipNo = $m[1];
            }
            
            if ($ekipNo > 0) {
                $stmtDef = $Puantaj->db->prepare("SELECT id FROM tanimlamalar WHERE (tur_adi LIKE ? OR tur_adi LIKE ?) AND silinme_tarihi IS NULL LIMIT 1");
                $stmtDef->execute(["%EKİP-$ekipNo", "%EKIP-$ekipNo"]);
                $defId = $stmtDef->fetchColumn();
                
                if ($defId) {
                    $stmtPersonel = $Puantaj->db->prepare("SELECT id FROM personel WHERE ekip_no = ? AND silinme_tarihi IS NULL LIMIT 1");
                    $stmtPersonel->execute([$defId]);
                    $personelId = $stmtPersonel->fetchColumn() ?: 0;
                }
            }
            
            // Yeni kayıt ekle
            $insertStmt = $Puantaj->db->prepare("INSERT INTO yapilan_isler (islem_id, personel_id, firma_id, firma, is_emri_tipi, ekip_kodu, is_emri_sonucu, sonuclanmis, acik_olanlar, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([
                $veri['islem_id'],
                $personelId,
                $firmaId,
                $veri['firma'],
                $veri['is_emri_tipi'],
                $veri['ekip_kodu'],
                $veri['is_emri_sonucu'],
                $veri['sonuclanmis'],
                $veri['acik_olanlar'],
                $veri['tarih']
            ]);
            $yeniKayit++;
        }
    }
    
    // Log kaydet
    $SystemLog->logAction(0, 'Cron - Online Kesme/Açma Sorgulama', "Firma $ilkFirma-$sonFirma, Tarih: $tarih. $yeniKayit yeni, $guncellenenKayit güncellenen kayıt.");
    
    return [
        'yeni_kayit' => $yeniKayit,
        'guncellenen_kayit' => $guncellenenKayit,
        'mevcut_kayitlar' => count($mevcutKayitlar)
    ];
}
