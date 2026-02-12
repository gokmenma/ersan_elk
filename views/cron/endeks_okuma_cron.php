<?php
/**
 * Endeks Okuma Cron Job
 * 
 * Bu dosya her 15 dakikada bir çalıştırılmalıdır.
 * Ayarlarda belirlenen saatlerde Endeks Okuma sorgulamalarını yapar.
 * 
 * Cron kurulumu için "Online Sorgulama Ayarları" sayfasındaki güncel komutu kullanın.
 */

// Gerekli dosyaları yükle
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Saat dilimini ayarla
date_default_timezone_set('Europe/Istanbul');

use App\Model\SettingsModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\TanimlamalarModel;
use App\Model\SystemLogModel;
use App\Service\EndeskOkumaService;

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
function cronLog($message)
{
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

    // Önce firma ID'sini bul (ayarlar firma bazlı kaydedilmiş olabilir)
    $Settings = new SettingsModel();
    $firmaId = 0;
    $settingsFirmaId = null;

    try {
        $db = (new PuantajModel())->db;
        // İlk firmayı bul
        $stmt = $db->prepare("SELECT id FROM firmalar WHERE silinme_tarihi IS NULL ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $firma = $stmt->fetch(PDO::FETCH_OBJ);
        $firmaId = $firma ? $firma->id : 0;
        $settingsFirmaId = $firmaId ?: null;
    } catch (Exception $e) {
        cronLog("Firma ID alınamadı: " . $e->getMessage());
    }

    // Ayarları al (önce firma bazlı, yoksa global)
    $allSettings = $Settings->getAllSettingsAsKeyValue($settingsFirmaId);

    cronLog("Ayarlar firma_id=$settingsFirmaId ile yüklendi. Toplam " . count($allSettings) . " ayar.");

    // Online sorgulama aktif mi?
    $online_sorgulama_aktif = $allSettings['online_sorgulama_aktif'] ?? '0';

    if ($online_sorgulama_aktif !== '1' && $online_sorgulama_aktif !== 'on' && !$online_sorgulama_aktif) {
        cronLog("Online sorgulama devre dışı. (Değer: " . var_export($online_sorgulama_aktif, true) . ") Çıkılıyor.");
        exit(0);
    }

    cronLog("Online sorgulama aktif. (Değer: " . var_export($online_sorgulama_aktif, true) . ")");

    // Endeks saatlerini al (virgülle ayrılmış çoklu saatler)
    $endeksSaatStr = $allSettings['online_sorgulama_endeks_saat'] ?? '08:00';
    $firmaBaslangic = $allSettings['online_sorgulama_firma_baslangic'] ?? 17;
    $firmaBitis = $allSettings['online_sorgulama_firma_bitis'] ?? 17;

    // firma_kodu'na göre firma_id'yi al
    try {
        $stmt = $db->prepare("SELECT id FROM firmalar WHERE firma_kodu = ? AND silinme_tarihi IS NULL LIMIT 1");
        $stmt->execute([$firmaBaslangic]);
        $firmaObj = $stmt->fetch(PDO::FETCH_OBJ);
        if ($firmaObj) {
            $firmaId = $firmaObj->id;
        }
    } catch (Exception $e) {
        cronLog("Firma kodu ile ID alınamadı: " . $e->getMessage());
    }

    // Çoklu saatleri diziye çevir
    $endeksSaatler = array_filter(array_map('trim', explode(',', $endeksSaatStr)));

    // Şu anki saat (15 dakikalık dilimlere yuvarla)
    $simdikiDakika = (int) date('i');
    $yuvarlanmisDakika = floor($simdikiDakika / 15) * 15;
    $simdikiSaat = date('H') . ':' . str_pad($yuvarlanmisDakika, 2, '0', STR_PAD_LEFT);

    cronLog("Kontrol: Şimdiki saat (yuvarlanmış): $simdikiSaat");
    cronLog("Endeks saatleri: " . implode(', ', $endeksSaatler));

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

            $sonuc = sorgulamaEndeks($firmaBaslangic, $firmaBitis, $bugun, $firmaId, $Settings);

            // Son çalıştırma zamanını güncelle
            $Settings->upsertSetting('online_sorgulama_endeks_son_calistirma', date('d.m.Y H:i:s'));

            // Log'a bu saati ekle (gün değişince sıfırlanır)
            $logParts = array_filter(explode(',', $endeksSonCalistirmaLog));
            $logParts = array_filter($logParts, function ($item) use ($bugun) {
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
 * Endeks Okuma Sorgulama Fonksiyonu (Gerçek API)
 */
function sorgulamaEndeks($ilkFirma, $sonFirma, $tarih, $firmaId, $Settings)
{
    $EndeksOkuma = new EndeksOkumaModel();
    $SystemLog = new SystemLogModel();

    $yeniKayit = 0;
    $guncellenenKayit = 0;
    $mevcutKayitlar = 0;
    $atlanAnKayitlar = 0;

    try {
        $apiService = new EndeskOkumaService();
        $tarihAPI = date('d/m/Y', strtotime($tarih));

        cronLog("API sorgusu yapılıyor: Tarih=$tarihAPI");

        // API'den verileri çek (pagination ile)
        $apiData = [];
        $offset = 0;
        $limit = 100;
        $hasMore = true;

        // PHP zaman aşımını uzat
        set_time_limit(300);

        while ($hasMore) {
            $apiResponse = $apiService->getData($tarihAPI, $tarihAPI, $limit, $offset);

            if (!($apiResponse['success'] ?? false)) {
                cronLog("API başarısız yanıt: " . json_encode($apiResponse));
                break;
            }

            $batchData = $apiResponse['data']['data'] ?? [];
            if (empty($batchData)) {
                $hasMore = false;
            } else {
                foreach ($batchData as &$item) {
                    if (!isset($item['OKUMATARIHI']) || empty($item['OKUMATARIHI'])) {
                        $item['OKUMATARIHI'] = $tarih;
                    }
                }
                $apiData = array_merge($apiData, $batchData);
                if (count($batchData) < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }
            }

            if ($offset >= 1000)
                break; // Güvenlik sınırı
        }

        cronLog("API'den " . count($apiData) . " kayıt geldi.");

        if (empty($apiData)) {
            return [
                'yeni_kayit' => 0,
                'guncellenen_kayit' => 0,
                'toplam_api' => 0,
                'mesaj' => 'API\'den veri gelmedi.'
            ];
        }

        // ========== PERFORMANS OPTİMİZASYONU ==========
        // 1. Tüm islem_id'leri önceden hesapla
        $processedData = [];
        foreach ($apiData as $veri) {
            $normDate = \App\Helper\Date::convertExcelDate($veri['OKUMATARIHI'], 'Y-m-d') ?: $veri['OKUMATARIHI'];
            $rawIdString = $normDate . '|' . $veri['BOLGE'] . '|' . ($veri['DEFTER'] ?? '') . '|' . $veri['OKUYUCUNO'] . '|' . $veri['ABONE_SAYISI'];
            $islemId = md5($rawIdString);
            $processedData[] = [
                'islem_id' => $islemId,
                'norm_date' => $normDate,
                'veri' => $veri
            ];
        }

        // 2. Mevcut kayıtları toplu çek
        $allIslemIds = array_column($processedData, 'islem_id');
        $existingRecords = [];
        if (!empty($allIslemIds)) {
            $chunks = array_chunk($allIslemIds, 500);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $stmt = $EndeksOkuma->db->prepare("SELECT id, islem_id FROM endeks_okuma WHERE islem_id IN ($placeholders) AND silinme_tarihi IS NULL");
                $stmt->execute($chunk);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $existingRecords[$row['islem_id']] = $row;
                }
            }
        }

        // 3. Personel ve ekip verilerini toplu yükle
        $stmtAllPersonel = $EndeksOkuma->db->prepare("SELECT id, adi_soyadi, ekip_no FROM personel WHERE silinme_tarihi IS NULL");
        $stmtAllPersonel->execute();
        $personelByName = [];
        $personelByEkip = [];
        while ($p = $stmtAllPersonel->fetch(PDO::FETCH_ASSOC)) {
            $personelByName[$p['adi_soyadi']] = $p;
            if ($p['ekip_no'] > 0) {
                $personelByEkip[$p['ekip_no']] = $p['id'];
            }
        }

        $stmtAllEkip = $EndeksOkuma->db->prepare("SELECT id, tur_adi FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL");
        $stmtAllEkip->execute();
        $ekipKodlari = [];
        while ($ek = $stmtAllEkip->fetch(PDO::FETCH_ASSOC)) {
            if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $ek['tur_adi'], $m)) {
                $ekipKodlari[$m[1]] = $ek['id'];
            }
        }

        $stmtAllHist = $EndeksOkuma->db->prepare("SELECT ekip_kodu_id, personel_id, baslangic_tarihi, bitis_tarihi FROM personel_ekip_gecmisi");
        $stmtAllHist->execute();
        $ekipGecmisi = [];
        while ($h = $stmtAllHist->fetch(PDO::FETCH_ASSOC)) {
            $ekipGecmisi[$h['ekip_kodu_id']][] = $h;
        }

        // 4. Kayıtları işle
        $updateBatch = [];
        $insertBatch = [];

        foreach ($processedData as $item) {
            $islemId = $item['islem_id'];
            $normDate = $item['norm_date'];
            $veri = $item['veri'];

            if (isset($existingRecords[$islemId])) {
                $updateBatch[] = [
                    'bolge' => $veri['BOLGE'],
                    'kullanici_adi' => $veri['OKUYUCUADI'],
                    'okunan_abone_sayisi' => $veri['ABONE_SAYISI'],
                    'tarih' => $normDate,
                    'defter' => $veri['DEFTER'] ?? '',
                    'sayac_durum' => $veri['SAYACDURUM'] ?? '',
                    'islem_id' => $islemId
                ];
                $guncellenenKayit++;
            } else {
                // Personel eşleştirme
                $personelId = 0;
                $ekipKoduId = 0;

                if (isset($personelByName[$veri['OKUYUCUADI']])) {
                    $personelId = $personelByName[$veri['OKUYUCUADI']]['id'];
                    $ekipKoduId = $personelByName[$veri['OKUYUCUADI']]['ekip_no'];
                } else {
                    if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $veri['OKUYUCUADI'], $m)) {
                        $ekipNo = $m[1];
                        $ekipKoduId = $ekipKodlari[$ekipNo] ?? 0;

                        if ($ekipKoduId) {
                            if (isset($ekipGecmisi[$ekipKoduId])) {
                                foreach ($ekipGecmisi[$ekipKoduId] as $hist) {
                                    if ($hist['baslangic_tarihi'] <= $normDate && ($hist['bitis_tarihi'] === null || $hist['bitis_tarihi'] >= $normDate)) {
                                        $personelId = $hist['personel_id'];
                                        break;
                                    }
                                }
                            }
                            if (!$personelId) {
                                $personelId = $personelByEkip[$ekipKoduId] ?? 0;
                            }
                        }
                    }
                }

                if ($ekipKoduId === 0) {
                    $atlanAnKayitlar++;
                    continue;
                }

                $insertBatch[] = [
                    $islemId,
                    $personelId,
                    $ekipKoduId,
                    $firmaId,
                    $veri['BOLGE'],
                    $veri['OKUYUCUADI'],
                    0,
                    0,
                    0,
                    0, // sarfiyat, ort_sarfiyat_gunluk, tahakkuk, ort_tahakkuk_gunluk
                    1, // okunan_gun_sayisi
                    $veri['ABONE_SAYISI'],
                    $veri['ABONE_SAYISI'], // ort_okunan_abone_sayisi_gunluk
                    100, // okuma_performansi
                    $normDate,
                    $veri['DEFTER'] ?? '',
                    $veri['SAYACDURUM'] ?? ''
                ];
                $yeniKayit++;
            }
        }

        // 5. Toplu UPDATE
        if (!empty($updateBatch)) {
            $updateStmt = $EndeksOkuma->db->prepare("UPDATE endeks_okuma SET bolge = ?, kullanici_adi = ?, okunan_abone_sayisi = ?, tarih = ?, defter = ?, sayac_durum = ? WHERE islem_id = ?");
            $EndeksOkuma->db->beginTransaction();
            foreach ($updateBatch as $row) {
                $updateStmt->execute(array_values($row));
            }
            $EndeksOkuma->db->commit();
            cronLog("$guncellenenKayit kayıt güncellendi.");
        }

        // 6. Toplu INSERT
        if (!empty($insertBatch)) {
            $EndeksOkuma->db->beginTransaction();
            $insertChunks = array_chunk($insertBatch, 50);
            foreach ($insertChunks as $chunk) {
                $valuesPart = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'));
                $sql = "INSERT INTO endeks_okuma (islem_id, personel_id, ekip_kodu_id, firma_id, bolge, kullanici_adi, sarfiyat, ort_sarfiyat_gunluk, tahakkuk, ort_tahakkuk_gunluk, okunan_gun_sayisi, okunan_abone_sayisi, ort_okunan_abone_sayisi_gunluk, okuma_performansi, tarih, defter, sayac_durum) VALUES $valuesPart";
                $params = [];
                foreach ($chunk as $row) {
                    $params = array_merge($params, $row);
                }
                $stmt = $EndeksOkuma->db->prepare($sql);
                $stmt->execute($params);
            }
            $EndeksOkuma->db->commit();
            cronLog("$yeniKayit yeni kayıt eklendi.");
        }

        if ($atlanAnKayitlar > 0) {
            cronLog("$atlanAnKayitlar kayıt atlandı (ekip eşleşmedi).");
        }

    } catch (Exception $e) {
        cronLog("Endeks sorgulama hatası: " . $e->getMessage());
    }

    // Log kaydet
    $SystemLog->logAction(0, 'Cron - Online Endeks Sorgulama', "Firma $ilkFirma-$sonFirma, Tarih: $tarih. $yeniKayit yeni, $guncellenenKayit güncellenen kayıt.");

    return [
        'yeni_kayit' => $yeniKayit,
        'guncellenen_kayit' => $guncellenenKayit,
        'atlanAn' => $atlanAnKayitlar,
        'toplam_api' => count($apiData ?? [])
    ];
}
