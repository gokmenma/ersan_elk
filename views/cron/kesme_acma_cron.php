<?php
/**
 * Kesme/Açma (Puantaj) Cron Job
 * 
 * Bu dosya her 15 dakikada bir çalıştırılmalıdır.
 * Ayarlarda belirlenen saatlerde Kesme/Açma sorgulamalarını yapar.
 * 
 * Cron kurulumu için "Online Sorgulama Ayarları" sayfasındaki güncel komutu kullanın.
 */

// Gerekli dosyaları yükle
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Saat dilimini ayarla
date_default_timezone_set('Europe/Istanbul');

use App\Model\SettingsModel;
use App\Model\PuantajModel;
use App\Model\TanimlamalarModel;
use App\Model\SystemLogModel;
use App\Model\DemirbasZimmetModel;
use App\Helper\Security;
use App\Helper\Helper;
use App\Service\KesmeAcmaService;
use App\Service\MailGonderService;

// CLI modunda mı çalışıyor?
if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    echo "Bu dosya sadece komut satırı (CLI) üzerinden çalıştırılabilir.";
    exit;
}

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

    // Puantaj (Kesme/Açma) saatlerini al (virgülle ayrılmış çoklu saatler)
    $puantajSaatStr = $allSettings['online_sorgulama_puantaj_saat'] ?? '08:00';
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
    $puantajSaatler = array_filter(array_map('trim', explode(',', $puantajSaatStr)));

    // Otomatik çalışma: 08:00 ile 18:00 saatlerini (dahil) listeye ekle
    for ($i = 8; $i <= 18; $i++) {
        $puantajSaatler[] = sprintf('%02d:00', $i);
    }
    // Gün sonu çalışma: 23:55
    $puantajSaatler[] = '23:45';

    // Şu anki saat (15 dakikalık dilimlere yuvarla)
    $simdikiDakika = (int) date('i');
    $yuvarlanmisDakika = floor($simdikiDakika / 15) * 15;
    $simdikiSaat = date('H') . ':' . str_pad($yuvarlanmisDakika, 2, '0', STR_PAD_LEFT);

    cronLog("Kontrol: Şimdiki saat (yuvarlanmış): $simdikiSaat");
    cronLog("Kesme/Açma saatleri: " . implode(', ', $puantajSaatler));

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

            $sonuc = sorgulamaPuantaj($firmaBaslangic, $firmaBitis, $bugun, $firmaId, $Settings);

            // Son çalıştırma zamanını güncelle
            $Settings->upsertSetting('online_sorgulama_puantaj_son_calistirma', date('d.m.Y H:i:s'));

            // Log'a bu saati ekle (gün değişince sıfırlanır)
            $logParts = array_filter(explode(',', $puantajSonCalistirmaLog));
            $logParts = array_filter($logParts, function ($item) use ($bugun) {
                return strpos(trim($item), $bugun) === 0;
            });
            $logParts[] = $bugunSaatKey;
            $Settings->upsertSetting('online_sorgulama_puantaj_son_calistirma_log', implode(',', $logParts));

            cronLog("Kesme/Açma sorgulama tamamlandı: " . json_encode($sonuc));

            // Mail gönderimi
            try {
                $mailIcerik = "<h3>Kesme/Açma (Puantaj) Cron Sonucu</h3>";
                $mailIcerik .= "<p><b>Tarih:</b> " . date('d.m.Y H:i:s') . "</p>";
                $mailIcerik .= "<p><b>Sorgulanan Tarih:</b> " . $bugun . "</p>";
                $mailIcerik .= "<ul>";
                $mailIcerik .= "<li><b>Yeni Kayıt:</b> " . $sonuc['yeni_kayit'] . "</li>";
                $mailIcerik .= "<li><b>Güncellenen Kayıt:</b> " . $sonuc['guncellenen_kayit'] . "</li>";
                $mailIcerik .= "<li><b>Toplam API Kaydı:</b> " . $sonuc['toplam_api'] . "</li>";
                $mailIcerik .= "<li><b>Eşleşmeyen Ekip:</b> " . $sonuc['atlanAn'] . "</li>";
                $mailIcerik .= "<li><b>Boş Sonuç (Sonuçlanmamış):</b> " . $sonuc['bos_sonuc'] . "</li>";
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
                    'Kesme/Açma Cron Özeti - ' . $bugun,
                    $mailIcerik
                );
                cronLog("Sonuç maili gönderildi: beyzade83@gmail.com");
            } catch (Exception $e) {
                cronLog("Mail gönderim hatası: " . $e->getMessage());
            }
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
 * Puantaj (Kesme/Açma) Sorgulama Fonksiyonu (Gerçek API)
 */
function sorgulamaPuantaj($ilkFirma, $sonFirma, $tarih, $firmaId, $Settings)
{
    $Puantaj = new PuantajModel();
    $Tanimlamalar = new TanimlamalarModel();
    $Zimmet = new DemirbasZimmetModel();
    $SystemLog = new SystemLogModel();

    $yeniKayit = 0;
    $silinenKayit = 0;
    $atlanAnKayitlar = 0;
    $bosSonucSayisi = 0;
    $atlanAnListesi = [];

    try {
        $KesmeAcmaSvc = new KesmeAcmaService();
        $tarihAPI = date('d/m/Y', strtotime($tarih));

        cronLog("API sorgusu yapılıyor: Tarih=$tarihAPI");

        // PHP zaman aşımını uzat
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $apiData = [];
        $offset = 0;
        $limit = 500;
        $hasMore = true;

        while ($hasMore) {
            // API Service getData parametre sırası: ($startDate, $endDate, $ilkFirma, $sonFirma, $limit, $offset)
            $apiResponse = $KesmeAcmaSvc->getData($tarihAPI, $tarihAPI, $ilkFirma, $sonFirma, $limit, $offset);
            if (!($apiResponse['success'] ?? false))
                break;

            $batchData = $apiResponse['data']['data'] ?? [];
            if (empty($batchData)) {
                $hasMore = false;
            } else {
                foreach ($batchData as $item) {
                    $item['TARIH'] = $tarih;
                    $apiData[] = $item;
                }
                if (count($batchData) < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }
            }
            if ($offset >= 5000)
                break;
        }

        cronLog("API'den " . count($apiData) . " kayıt geldi.");
        if (empty($apiData))
            return ['yeni_kayit' => 0, 'guncellenen_kayit' => 0, 'toplam_api' => 0, 'mesaj' => 'API\'den veri gelmedi.'];

        // 1. Ekip ve Personel lookup verilerini yükle
        $stmtAllEkip = $Puantaj->db->prepare("SELECT id, tur_adi FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL");
        $stmtAllEkip->execute();
        $ekipKodlari = [];
        while ($ek = $stmtAllEkip->fetch(PDO::FETCH_ASSOC)) {
            if (preg_match('/EK[İI\?]?P-?\s?(\d+)/ui', $ek['tur_adi'], $m)) {
                $ekipKodlari[$m[1]] = $ek['id'];
            }
        }

        $stmtAllPersonel = $Puantaj->db->prepare("SELECT id, ekip_no FROM personel WHERE silinme_tarihi IS NULL");
        $stmtAllPersonel->execute();
        $personelByEkip = [];
        while ($p = $stmtAllPersonel->fetch(PDO::FETCH_ASSOC)) {
            if (($p['ekip_no'] ?? 0) > 0) {
                $personelByEkip[$p['ekip_no']] = $p['id'];
            }
        }

        $stmtAllHist = $Puantaj->db->prepare("SELECT ekip_kodu_id, personel_id, baslangic_tarihi, bitis_tarihi FROM personel_ekip_gecmisi WHERE firma_id = ?");
        $stmtAllHist->execute([$firmaId]);
        $ekipGecmisi = [];
        while ($h = $stmtAllHist->fetch(PDO::FETCH_ASSOC)) {
            $ekipGecmisi[$h['ekip_kodu_id']][] = $h;
        }

        // 2. API verilerini işle
        $insertBatch = [];
        $resultNamesInApi = [];
        foreach ($apiData as $veri) {
            $isEmriTipi = trim($veri['ISEMRITIPI'] ?? '');
            $ekipKoduStr = trim($veri['EKIP'] ?? '');
            $isEmriSonucu = trim($veri['SONUC'] ?? '');

            if (!empty($isEmriSonucu)) {
                $resultNamesInApi[] = $isEmriSonucu;
            }

            $sonuclanmis = $veri['SONUCLANMIS'] ?? 0;
            $acikOlanlar = $veri['ACIK'] ?? 0;

            if ((int) $sonuclanmis === 0) {
                $bosSonucSayisi++;
                continue;
            }

            $normDate = $tarih;
            $islemId = md5($normDate . '|' . trim($ekipKoduStr) . '|' . trim($isEmriTipi) . '|' . trim($isEmriSonucu));

            // İş Türü ID Bul/Oluştur
            $existingTur = $Tanimlamalar->isEmriSonucu(trim($isEmriTipi), trim($isEmriSonucu));
            $isEmriSonucuId = $existingTur ? $existingTur->id : 0;
            if (!$isEmriSonucuId && (!empty($isEmriTipi) || !empty($isEmriSonucu))) {
                $encryptedId = $Tanimlamalar->saveWithAttr([
                    'firma_id' => $firmaId,
                    'grup' => 'is_turu',
                    'tur_adi' => $isEmriTipi,
                    'is_emri_sonucu' => $isEmriSonucu,
                    'aciklama' => "Cron sorgulama"
                ]);
                $isEmriSonucuId = \App\Helper\Security::decrypt($encryptedId);
            }

            // Ekip ve Personel Bul
            $personelId = 0;
            $defId = 0;
            $ekipNo = 0;
            if (preg_match('/EK[İI\?]?P-?\s?(\d+)/ui', $ekipKoduStr, $m)) {
                $ekipNo = $m[1];
                $defId = $ekipKodlari[$ekipNo] ?? 0;
                if ($defId) {
                    if (isset($ekipGecmisi[$defId])) {
                        foreach ($ekipGecmisi[$defId] as $hist) {
                            if ($hist['baslangic_tarihi'] <= $normDate && ($hist['bitis_tarihi'] === null || $hist['bitis_tarihi'] >= $normDate)) {
                                $personelId = $hist['personel_id'];
                                break;
                            }
                        }
                    }
                    if (!$personelId)
                        $personelId = $personelByEkip[$defId] ?? 0;
                }
            }

            if ($defId === 0) {
                $atlanAnKayitlar++;
                $atlanAnListesi[] = $ekipKoduStr . " (API: $isEmriTipi - $isEmriSonucu)";
                continue;
            }

            $insertBatch[] = [$islemId, $personelId, $defId, $firmaId, $isEmriSonucuId, $isEmriTipi, $ekipKoduStr, $isEmriSonucu, $sonuclanmis, $acikOlanlar, $normDate];
            $yeniKayit++;

            // Demirbaş işlemi
            if ($personelId > 0)
                $Zimmet->checkAndProcessAutomaticZimmet($personelId, $isEmriSonucu, $normDate, $islemId, $sonuclanmis);
        }

        $Puantaj->db->beginTransaction();

        // 3. Mevcut kayıtları temizle (Sadece gelen tipler için)
        if (!empty($resultNamesInApi)) {
            $uniqueNames = array_unique($resultNamesInApi);
            $placeholders = implode(',', array_fill(0, count($uniqueNames), '?'));
            $deleteStmt = $Puantaj->db->prepare("UPDATE yapilan_isler SET silinme_tarihi = NOW() WHERE firma_id = ? AND tarih = ? AND silinme_tarihi IS NULL AND TRIM(is_emri_sonucu) IN ($placeholders)");
            $deleteStmt->execute(array_merge([$firmaId, $tarih], $uniqueNames));
            $silinenKayit = $deleteStmt->rowCount();
        }

        // 4. Toplu Kayıt
        if (!empty($insertBatch)) {
            $chunks = array_chunk($insertBatch, 500);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?,?,?,?)'));
                $sql = "INSERT INTO yapilan_isler (islem_id, personel_id, ekip_kodu_id, firma_id, is_emri_sonucu_id, is_emri_tipi, ekip_kodu, is_emri_sonucu, sonuclanmis, acik_olanlar, tarih) VALUES $placeholders";
                $stmt = $Puantaj->db->prepare($sql);
                $flatParams = [];
                foreach ($chunk as $row) {
                    $flatParams = array_merge($flatParams, $row);
                }
                $stmt->execute($flatParams);
            }
        }
        $Puantaj->db->commit();

        cronLog("$yeniKayit yeni kayıt eklendi, $silinenKayit eski silindi.");
        unset($apiData);
        unset($insertBatch);

    } catch (Exception $e) {
        cronLog("HATA: " . $e->getMessage());
    }

    $SystemLog->logAction(0, 'Cron - Online Kesme/Açma Sorgulama', "Firma ID: $firmaId, Tarih: $tarih. $yeniKayit yeni kayıt, $silinenKayit silinen.", SystemLogModel::LEVEL_IMPORTANT);
    return ['yeni_kayit' => $yeniKayit, 'silinen_kayit' => $silinenKayit, 'guncellenen_kayit' => 0, 'atlanAn' => $atlanAnKayitlar, 'atlanAnListesi' => array_unique($atlanAnListesi), 'bos_sonuc' => $bosSonucSayisi, 'toplam_api' => count($apiData ?? [])];
}
