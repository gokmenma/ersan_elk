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

// Saat dilimini ayarla
date_default_timezone_set('Europe/Istanbul');

use App\Model\SettingsModel;
use App\Model\PuantajModel;
use App\Model\TanimlamalarModel;
use App\Model\SystemLogModel;
use App\Model\DemirbasZimmetModel;
use App\Service\KesmeAcmaService;

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
    $guncellenenKayit = 0;
    $atlanAnKayitlar = 0;
    $bosSonucSayisi = 0;

    try {
        $KesmeAcmaSvc = new KesmeAcmaService();
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
            $apiResponse = $KesmeAcmaSvc->getData($tarihAPI, $tarihAPI, $limit, $offset);

            if (!($apiResponse['success'] ?? false)) {
                cronLog("API başarısız yanıt: " . json_encode($apiResponse));
                break;
            }

            $batchData = $apiResponse['data']['data'] ?? [];
            if (empty($batchData)) {
                $hasMore = false;
            } else {
                foreach ($batchData as &$item) {
                    if (!isset($item['TARIH'])) {
                        $item['TARIH'] = $tarih;
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

        foreach ($apiData as $veri) {
            // API'den gelen alan adlarını eşleştir
            $firmaAdi = $veri['FIRMAADI'] ?? 'ER-SAN ELEKTRİK';
            $isEmriTipi = $veri['ISEMRITIPI'] ?? '';
            $ekipKodu = $veri['EKIP'] ?? '';
            $isEmriSonucu = $veri['SONUC'] ?? '';
            $sonuclanmis = $veri['SONUCLANMIS'] ?? 0;
            $acikOlanlar = $veri['ACIK'] ?? 0;

            // SONUCLANMIS 0 olan kayıtları atla
            if ((int) $sonuclanmis === 0) {
                $bosSonucSayisi++;
                continue;
            }

            // Tarih normalize
            $tarihRaw = $veri['TARIH'];
            $normDate = \App\Helper\Date::convertExcelDate($tarihRaw, 'Y-m-d') ?: $tarihRaw;

            // Unique ID oluştur
            $rawIdString = $normDate . '|' . $ekipKodu . '|' . $isEmriTipi . '|' . $isEmriSonucu;
            $islemId = md5($rawIdString);

            // Tanimlamalar'dan is_emri_sonucu_id bul
            $existingTur = $Tanimlamalar->isEmriSonucu($isEmriTipi, $isEmriSonucu);

            if (!$existingTur && (!empty($isEmriTipi) || !empty($isEmriSonucu))) {
                $dataTanim = [
                    'firma_id' => $firmaId ?: ($_SESSION['firma_id'] ?? 0),
                    'grup' => 'is_turu',
                    'tur_adi' => $isEmriTipi,
                    'is_emri_sonucu' => $isEmriSonucu,
                    'aciklama' => "Cron sorgulama sırasında otomatik oluşturuldu"
                ];
                $encryptedId = $Tanimlamalar->saveWithAttr($dataTanim);
                $isEmriSonucuId = \App\Helper\Security::decrypt($encryptedId);
            } else {
                $isEmriSonucuId = $existingTur ? $existingTur->id : 0;
            }

            $saveTipi = $isEmriSonucuId ? '' : $isEmriTipi;
            $saveSonucu = $isEmriSonucuId ? '' : $isEmriSonucu;

            // Daha önce aynı islem_id ile kayıt var mı?
            $checkStmt = $Puantaj->db->prepare("SELECT id, islem_id FROM yapilan_isler WHERE islem_id = ? AND silinme_tarihi IS NULL");
            $checkStmt->execute([$islemId]);
            $mevcutKayit = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($mevcutKayit) {
                // Güncelle
                $updateStmt = $Puantaj->db->prepare("UPDATE yapilan_isler SET sonuclanmis = ?, acik_olanlar = ?, tarih = ?, is_emri_sonucu_id = ?, is_emri_tipi = ?, is_emri_sonucu = ? WHERE islem_id = ?");
                $updateStmt->execute([$sonuclanmis, $acikOlanlar, $normDate, $isEmriSonucuId, $saveTipi, $saveSonucu, $islemId]);
                $guncellenenKayit++;
            } else {
                // Personel eşleştirme (ekip kodundan)
                $personelId = 0;
                $defId = 0;
                $ekipNo = 0;
                if (preg_match('/EK[İI]P-?\s?(\d+)/ui', $ekipKodu, $m)) {
                    $ekipNo = $m[1];
                }

                if ($ekipNo > 0) {
                    $stmtDef = $Puantaj->db->prepare("SELECT id FROM tanimlamalar WHERE (tur_adi LIKE ? OR tur_adi LIKE ?) AND grup = 'ekip_kodu' AND silinme_tarihi IS NULL LIMIT 1");
                    $stmtDef->execute(["%EKİP-$ekipNo", "%EKIP-$ekipNo"]);
                    $defId = $stmtDef->fetchColumn();

                    if ($defId) {
                        // Ekip geçmişinden personeli bul
                        $stmtHist = $Puantaj->db->prepare("SELECT personel_id FROM personel_ekip_gecmisi 
                                                         WHERE ekip_kodu_id = ? AND baslangic_tarihi <= ? 
                                                         AND (bitis_tarihi IS NULL OR bitis_tarihi >= ?) 
                                                         LIMIT 1");
                        $stmtHist->execute([$defId, $normDate, $normDate]);
                        $personelId = $stmtHist->fetchColumn();

                        if (!$personelId) {
                            $stmtPersonel = $Puantaj->db->prepare("SELECT id FROM personel WHERE ekip_no = ? AND silinme_tarihi IS NULL LIMIT 1");
                            $stmtPersonel->execute([$defId]);
                            $personelId = $stmtPersonel->fetchColumn() ?: 0;
                        }
                    }
                }

                // Ekip bulunamadıysa atla
                if ($defId === 0) {
                    $atlanAnKayitlar++;
                    continue;
                }

                // Yeni kayıt ekle
                $insertStmt = $Puantaj->db->prepare("INSERT INTO yapilan_isler (islem_id, personel_id, ekip_kodu_id, firma_id, is_emri_sonucu_id, is_emri_tipi, ekip_kodu, is_emri_sonucu, sonuclanmis, acik_olanlar, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([
                    $islemId,
                    $personelId,
                    $defId,
                    $firmaId,
                    $isEmriSonucuId,
                    $saveTipi,
                    $ekipKodu,
                    $saveSonucu,
                    $sonuclanmis,
                    $acikOlanlar,
                    $normDate
                ]);
                $yeniKayit++;

                // Otomatik Demirbaş İşlemi
                if ($personelId > 0) {
                    $Zimmet->checkAndProcessAutomaticZimmet($personelId, $isEmriSonucu, $normDate, $islemId, $sonuclanmis);
                }
            }
        }

        cronLog("$yeniKayit yeni kayıt, $guncellenenKayit güncellenen, $atlanAnKayitlar atlanan, $bosSonucSayisi boş sonuç.");

    } catch (Exception $e) {
        cronLog("Kesme/Açma sorgulama hatası: " . $e->getMessage());
    }

    // Log kaydet
    $SystemLog->logAction(0, 'Cron - Online Kesme/Açma Sorgulama', "Firma $ilkFirma-$sonFirma, Tarih: $tarih. $yeniKayit yeni, $guncellenenKayit güncellenen kayıt.");

    return [
        'yeni_kayit' => $yeniKayit,
        'guncellenen_kayit' => $guncellenenKayit,
        'atlanAn' => $atlanAnKayitlar,
        'bos_sonuc' => $bosSonucSayisi,
        'toplam_api' => count($apiData ?? [])
    ];
}
