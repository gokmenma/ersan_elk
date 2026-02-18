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
use App\Helper\Security;
use App\Service\EndeskOkumaService;
use App\Service\MailGonderService;

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

            // Mail gönderimi
            try {
                $mailIcerik = "<h3>Endeks Okuma Cron Sonucu</h3>";
                $mailIcerik .= "<p><b>Tarih:</b> " . date('d.m.Y H:i:s') . "</p>";
                $mailIcerik .= "<p><b>Sorgulanan Tarih:</b> " . $bugun . "</p>";
                $mailIcerik .= "<ul>";
                $mailIcerik .= "<li><b>Yeni Kayıt:</b> " . $sonuc['yeni_kayit'] . "</li>";
                $mailIcerik .= "<li><b>Silinen Eski Kayıt:</b> " . $sonuc['silinen_kayit'] . "</li>";
                $mailIcerik .= "<li><b>Toplam API Kaydı:</b> " . $sonuc['toplam_api'] . "</li>";
                $mailIcerik .= "<li><b>Eşleşmeyen/Atlanan Kayıt:</b> " . $sonuc['atlanAn'] . "</li>";
                $mailIcerik .= "</ul>";

                if (!empty($sonuc['atlanAnListesi'])) {
                    $mailIcerik .= "<h4>Eşleşmeyen Ekip Listesi:</h4>";
                    $mailIcerik .= "<ul>";
                    foreach ($sonuc['atlanAnListesi'] as $item) {
                        $mailIcerik .= "<li>" . Security::escape($item) . "</li>";
                    }
                    $mailIcerik .= "</ul>";
                }

                MailGonderService::gonder(
                    ['beyzade83@gmail.com'],
                    'Endeks Okuma Cron Özeti - ' . $bugun,
                    $mailIcerik
                );
                cronLog("Sonuç maili gönderildi: beyzade83@gmail.com");
            } catch (Exception $e) {
                cronLog("Mail gönderim hatası: " . $e->getMessage());
            }
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
    $silinenKayit = 0;
    $atlanAnKayitlar = 0;
    $atlanAnListesi = [];

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
                'silinen_kayit' => 0,
                'toplam_api' => 0,
                'mesaj' => 'API\'den veri gelmedi.'
            ];
        }

        // ========== SİL VE YENİDEN YÜKLE YAKLAŞIMI ==========
        // 1. Personel ve ekip verilerini toplu yükle
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
            if (preg_match('/EK[İI\?]?P-?\s?(\d+)/ui', $ek['tur_adi'], $m)) {
                $ekipKodlari[$m[1]] = $ek['id'];
            }
        }

        $stmtAllHist = $EndeksOkuma->db->prepare("SELECT ekip_kodu_id, personel_id, baslangic_tarihi, bitis_tarihi FROM personel_ekip_gecmisi");
        $stmtAllHist->execute();
        $ekipGecmisi = [];
        while ($h = $stmtAllHist->fetch(PDO::FETCH_ASSOC)) {
            $ekipGecmisi[$h['ekip_kodu_id']][] = $h;
        }

        // 2. Sorgulanan tarihteki mevcut kayıtları soft-delete et
        $silinenKayit = 0;
        $deleteStmt = $EndeksOkuma->db->prepare("UPDATE endeks_okuma SET silinme_tarihi = NOW() WHERE firma_id = ? AND tarih = ? AND silinme_tarihi IS NULL");
        $deleteStmt->execute([$firmaId, $tarih]);
        $silinenKayit = $deleteStmt->rowCount();
        if ($silinenKayit > 0) {
            cronLog("$silinenKayit eski kayıt soft-delete edildi.");
        }

        // 3. API verilerini işle ve insert listesi oluştur
        $insertBatch = [];

        foreach ($apiData as $veri) {
            // Bölgesi boş veya null olan kayıtları atla
            if (!isset($veri['BOLGE']) || empty(trim((string) $veri['BOLGE']))) {
                continue;
            }

            $okuyucuAdi = trim($veri['OKUYUCUADI'] ?? '');
            $bolge = trim($veri['BOLGE'] ?? '');
            $defter = trim($veri['DEFTER'] ?? '');
            $okuyucuNo = trim($veri['OKUYUCUNO'] ?? '');
            $sayacDurum = trim($veri['SAYACDURUM'] ?? '');

            $normDate = \App\Helper\Date::convertExcelDate($veri['OKUMATARIHI'], 'Y-m-d') ?: $veri['OKUMATARIHI'];
            $islemId = md5($normDate . '|' . $bolge . '|' . $defter . '|' . $okuyucuNo . '|' . $sayacDurum);

            // Personel eşleştirme
            $personelId = 0;
            $ekipKoduId = 0;

            if (isset($personelByName[$okuyucuAdi])) {
                $personelId = $personelByName[$okuyucuAdi]['id'];
                $ekipKoduId = $personelByName[$okuyucuAdi]['ekip_no'];
            } else {
                if (preg_match('/EK[İI\?]?P-?\s?(\d+)/ui', $okuyucuAdi, $m)) {
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
                $atlanAnListesi[] = $okuyucuAdi . " (Bölge: " . $bolge . ")";
                continue;
            }

            $insertBatch[] = [
                $islemId,
                $personelId,
                $ekipKoduId,
                $firmaId,
                $bolge,
                $okuyucuAdi,
                0,
                0,
                0,
                0, // sarfiyat, ort_sarfiyat_gunluk, tahakkuk, ort_tahakkuk_gunluk
                1, // okunan_gun_sayisi
                $veri['ABONE_SAYISI'],
                $veri['ABONE_SAYISI'], // ort_okunan_abone_sayisi_gunluk
                100, // okuma_performansi
                $normDate,
                $defter,
                $sayacDurum
            ];
            $yeniKayit++;
        }

        // 4. Toplu INSERT
        if (!empty($insertBatch)) {
            $EndeksOkuma->db->beginTransaction();
            $insertChunks = array_chunk($insertBatch, 500);
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

        unset($apiData);
        unset($insertBatch);

    } catch (Exception $e) {
        cronLog("Endeks sorgulama hatası: " . $e->getMessage());
    }

    // Log kaydet
    $SystemLog->logAction(0, 'Cron - Online Endeks Sorgulama', "Firma $ilkFirma-$sonFirma, Tarih: $tarih. $silinenKayit eski silindi, $yeniKayit yeni eklendi.");

    return [
        'yeni_kayit' => $yeniKayit,
        'silinen_kayit' => $silinenKayit,
        'atlanAn' => $atlanAnKayitlar,
        'atlanAnListesi' => array_unique($atlanAnListesi),
        'toplam_api' => count($apiData ?? [])
    ];
}
