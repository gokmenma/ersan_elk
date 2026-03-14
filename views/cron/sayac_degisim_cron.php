<?php
/**
 * Sayaç Değişim Cron Job
 * 
 * Bu dosya her 15 dakikada bir çalıştırılmalıdır (diğer sorgulama saatlerinde de çalışabilir).
 * 
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
use App\Model\SayacDegisimModel;
use App\Service\SayacDegisimService;
use App\Service\MailGonderService;
use App\Helper\Security;

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
    $logFile = __DIR__ . '/logs/sayac_degisim_cron_' . date('Y-m-d') . '.log';
    $logMessage = "[$logDate] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);

    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

cronLog("=== Sayaç Değişim Cron başlatıldı ===");

try {
    if (!isset($_SESSION)) {
        $_SESSION = [];
    }

    $Settings = new SettingsModel();
    $firmaId = 0;
    $settingsFirmaId = null;

    $db = (new PuantajModel())->db;
    try {
        $stmt = $db->prepare("SELECT id FROM firmalar WHERE silinme_tarihi IS NULL ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $firma = $stmt->fetch(PDO::FETCH_OBJ);
        $firmaId = $firma ? $firma->id : 0;
        $settingsFirmaId = $firmaId ?: null;
    } catch (Exception $e) {
        cronLog("Firma ID alınamadı: " . $e->getMessage());
    }

    $allSettings = $Settings->getAllSettingsAsKeyValue($settingsFirmaId);
    $online_sorgulama_aktif = $allSettings['online_sorgulama_aktif'] ?? '0';

    if ($online_sorgulama_aktif !== '1' && $online_sorgulama_aktif !== 'on' && !$online_sorgulama_aktif) {
        cronLog("Online sorgulama devre dışı. Çıkılıyor.");
        exit(0);
    }

    $puantajSaatStr = $allSettings['online_sorgulama_puantaj_saat'] ?? '08:00';
    $firmaBaslangic = $allSettings['online_sorgulama_firma_baslangic'] ?? 17;

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

    $puantajSaatler = array_filter(array_map('trim', explode(',', $puantajSaatStr)));
    for ($i = 8; $i <= 18; $i++) {
        $puantajSaatler[] = sprintf('%02d:00', $i);
    }
    $puantajSaatler[] = '23:45';
    // Ayrıca ekstra test için '00:00' eklenebilir veya sadece yuvarlama:
    $simdikiDakika = (int) date('i');
    $yuvarlanmisDakika = floor($simdikiDakika / 15) * 15;
    $simdikiSaat = date('H') . ':' . str_pad($yuvarlanmisDakika, 2, '0', STR_PAD_LEFT);

    $_SESSION['firma_id'] = $firmaId;
    $_SESSION['firma_kodu'] = $firmaBaslangic;

    $bugun = date('Y-m-d');
    $puantajSonCalistirmaLog = $allSettings['online_sorgulama_sayac_degisim_son_calistirma_log'] ?? '';

    if (in_array($simdikiSaat, $puantajSaatler)) {
        cronLog("Sayaç Değişim sorgulama zamanı geldi! (Saat: $simdikiSaat)");

        $bugunSaatKey = $bugun . ' ' . $simdikiSaat;
        if (strpos($puantajSonCalistirmaLog, $bugunSaatKey) === false) {
            cronLog("Sorgulama başlatılıyor...");

            $sonuc = sorgulamaSayacDegisim($bugun, $firmaId, $db);

            $Settings->upsertSetting('online_sorgulama_sayac_degisim_son_calistirma', date('d.m.Y H:i:s'));

            $logParts = array_filter(explode(',', $puantajSonCalistirmaLog));
            $logParts = array_filter($logParts, function ($item) use ($bugun) {
                return strpos(trim($item), $bugun) === 0;
            });
            $logParts[] = $bugunSaatKey;
            $Settings->upsertSetting('online_sorgulama_sayac_degisim_son_calistirma_log', implode(',', $logParts));

            cronLog("Sorgulama tamamlandı: " . json_encode($sonuc));

            // Mail gönderimi sadece 18:00'da yapılacak
            if ($simdikiSaat === '18:00') {
                try {
                    $mailIcerik = "<h3>Sayaç Değişim Cron Sonucu</h3>";
                    $mailIcerik .= "<p><b>Tarih:</b> " . date('d.m.Y H:i:s') . "</p>";
                    $mailIcerik .= "<p><b>Sorgulanan Tarih:</b> " . $bugun . "</p>";
                    $mailIcerik .= "<ul>";
                    $mailIcerik .= "<li><b>Yeni/Güncellenen Kayıt:</b> " . $sonuc['yeni_kayit'] . "</li>";
                    $mailIcerik .= "<li><b>Toplam API Kaydı:</b> " . $sonuc['toplam_api'] . "</li>";
                    $mailIcerik .= "</ul>";

                    if (!empty($sonuc['atlanAnListesi'])) {
                        $mailIcerik .= "<h4>Eşleşmeyen Ekip Listesi:</h4>";
                        $mailIcerik .= "<ul>";
                        foreach ($sonuc['atlanAnListesi'] as $item) {
                            $mailIcerik .= "<li>" . Security::escape($item) . "</li>";
                        }
                        $mailIcerik .= "</ul>";
                    }

                    MailGonderService::gonder(['beyzade83@gmail.com'], 'Sayaç Değişim Cron Özeti - ' . $bugun, $mailIcerik);
                } catch (Exception $e) {
                    cronLog("Mail gönderim hatası: " . $e->getMessage());
                }
            }

        } else {
            cronLog("Bu saat ($simdikiSaat) için bugün zaten çalıştırılmış.");
        }
    } else {
        cronLog("Saat eşleşmedi ($simdikiSaat), atlanıyor.");
    }

} catch (Exception $e) {
    cronLog("HATA: " . $e->getMessage());
    cronLog("Stack trace: " . $e->getTraceAsString());
}

$endTime = microtime(true);
$executionTime = round(($endTime - $startTime) * 1000, 2);
cronLog("Sayaç Değişim Cron tamamlandı. Süre: {$executionTime}ms");
cronLog("========================================\n");


function sorgulamaSayacDegisim($tarih, $firmaId, $db)
{
    $yeniKayit = 0;
    $silinenKayit = 0;
    $bosSonucSayisi = 0;

    try {
        $SayacDegisimSvc = new SayacDegisimService();
        $tarihAPI = date('d/m/Y', strtotime($tarih));

        cronLog("API sorgusu yapılıyor: Tarih=$tarihAPI");

        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $apiData = [];
        $offset = 0;
        $limit = 500;
        $hasMore = true;

        while ($hasMore) {
            $apiResponse = $SayacDegisimSvc->getData($tarihAPI, $tarihAPI, $limit, $offset);
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
            return ['yeni_kayit' => 0, 'toplam_api' => 0, 'mesaj' => 'API\'den veri gelmedi.'];

        // Personel ve Ekip Eşleşmeleri
        $stmtAllEkip = $db->prepare("SELECT id, tur_adi FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL");
        $stmtAllEkip->execute();
        $ekipKodlari = [];
        while ($ek = $stmtAllEkip->fetch(PDO::FETCH_ASSOC)) {
            if (preg_match('/EK[İI\?]?P-?\s?(\d+)/ui', $ek['tur_adi'], $m)) {
                $ekipKodlari[$m[1]] = $ek['id'];
            }
        }

        $stmtAllPersonel = $db->prepare("SELECT id, ekip_no FROM personel WHERE silinme_tarihi IS NULL");
        $stmtAllPersonel->execute();
        $personelByEkip = [];
        while ($p = $stmtAllPersonel->fetch(PDO::FETCH_ASSOC)) {
            if (($p['ekip_no'] ?? 0) > 0) {
                $personelByEkip[$p['ekip_no']][] = $p['id'];
            }
        }

        $stmtAllHist = $db->prepare("SELECT ekip_kodu_id, personel_id, baslangic_tarihi, bitis_tarihi FROM personel_ekip_gecmisi WHERE firma_id = ?");
        $stmtAllHist->execute([$firmaId]);
        $ekipGecmisi = [];
        while ($h = $stmtAllHist->fetch(PDO::FETCH_ASSOC)) {
            $ekipGecmisi[$h['ekip_kodu_id']][] = $h;
        }

        $insertBatch = [];
        $atlanAnListesi = [];
        foreach ($apiData as $veri) {
            $ekipKoduStr = trim($veri['EKIP'] ?? '');
            $normDate = $tarih;
            $takilanSayacNo = trim($veri['TAKILAN_SAYACNO'] ?? '');
            $aboneNo = trim($veri['ABONE_NO'] ?? '');
            $isemriNo = trim($veri['ISEMRI_NO'] ?? '');

            // islem_id generation must match views/puantaj/api.php
            $islemId = md5($isemriNo . '|' . $aboneNo . '|' . $takilanSayacNo . '|' . $ekipKoduStr);

            // Personel eşleştirme (TÜM personel listesini bul)
            $personelMatches = [];
            $ekipKoduId = 0;

            if (preg_match('/EK[İI\?]?P-?\s?(\d+)/ui', $ekipKoduStr, $m)) {
                $ekipNo = $m[1];
                $ekipKoduId = $ekipKodlari[$ekipNo] ?? 0;

                if ($ekipKoduId) {
                    // O tarihte o ekipte olan TÜM personelleri bul
                    if (isset($ekipGecmisi[$ekipKoduId])) {
                        foreach ($ekipGecmisi[$ekipKoduId] as $hist) {
                            if ($hist['baslangic_tarihi'] <= $normDate && ($hist['bitis_tarihi'] === null || $hist['bitis_tarihi'] >= $normDate)) {
                                $personelMatches[] = ['id' => $hist['personel_id']];
                            }
                        }
                    }
                    
                    // Eğer geçmişte yoksa ama personelin şu anki ekip no'su buysa (fallback)
                    if (empty($personelMatches) && isset($personelByEkip[$ekipKoduId])) {
                        foreach ($personelByEkip[$ekipKoduId] as $pId) {
                            $personelMatches[] = ['id' => $pId];
                        }
                    }
                }
            }

            if (empty($personelMatches)) {
                $atlanAnListesi[] = $ekipKoduStr;
                continue;
            }

            $kayitTarihi = null;
            if (!empty($veri['SONUC_TARIHI'])) {
                $kDate = \DateTime::createFromFormat('d/m/Y H:i:s', $veri['SONUC_TARIHI']);
                if ($kDate) {
                    $kayitTarihi = $kDate->format('Y-m-d H:i:s');
                } else {
                    $kayitTarihi = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $veri['SONUC_TARIHI'])));
                }
            }

            // Bölüştürme Katsayısı
            $personelSayisi = count($personelMatches);
            $isSayisi = 1 / $personelSayisi;
            $ekAciklama = $personelSayisi > 1 ? " (İş $personelSayisi kişiye bölündü)" : "";

            foreach ($personelMatches as $match) {
                $pId = $match['id'];
                $perPersonIslemId = $islemId . '_' . $pId;

                $zimmetDusuldu = 0;
                // Zimmet işlemi (Sadece bu personel üzerinde bu sayaç varsa zimmetten düşer)
                if (!empty($takilanSayacNo)) {
                    $stmtCheck = $db->prepare("SELECT id FROM demirbas_hareketler WHERE islem_id = ? AND hareket_tipi = 'sarf' LIMIT 1");
                    $stmtCheck->execute([$perPersonIslemId]);
                    if ($stmtCheck->fetchColumn()) {
                        $zimmetDusuldu = 1;
                    } else {
                        $stmtZimmet = $db->prepare("
                            SELECT dz.id, d.kategori_id, d.demirbas_adi
                            FROM demirbas_zimmet dz
                            JOIN demirbas d ON d.id = dz.demirbas_id
                            WHERE dz.personel_id = ? 
                            AND d.seri_no = ? 
                            AND dz.silinme_tarihi IS NULL 
                            AND (dz.durum = 'teslim' OR dz.teslim_miktar > (SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler WHERE zimmet_id = dz.id AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL))
                            LIMIT 1
                        ");
                        $stmtZimmet->execute([$pId, $takilanSayacNo]);
                        $zimmetRow = $stmtZimmet->fetch(PDO::FETCH_ASSOC);
                        if ($zimmetRow && $zimmetRow['id']) {
                            try {
                                $zimmetId = $zimmetRow['id'];
                                $kategoriId = $zimmetRow['kategori_id'];
                                $ZimmetModel = new \App\Model\DemirbasZimmetModel();
                                $isemriSonucu = trim($veri['ISEMRI_SONUCU'] ?? '');
                                
                                // Takılan sayacı Tüketim yap
                                $ZimmetModel->tuketimYap($zimmetId, ($kayitTarihi ?: $normDate), 1, "Sayaç değişimi otomatik tüketimi.\nİş Emri No: {$isemriNo}\nAbone No: {$aboneNo}" . $ekAciklama, $perPersonIslemId, $isemriSonucu, 'otomatik');

                                // Sökülen sayacı Hurda olarak ekle ve zimmetle
                                $yeniHurdaAdi = "Sökülen Hurda / Abone: " . $aboneNo;
                                $sqlHurdaInsert = $db->prepare("
                                    INSERT INTO demirbas 
                                    (firma_id, kategori_id, demirbas_adi, seri_no, miktar, kalan_miktar, durum, log_kaydi, aciklama)
                                    VALUES (?, ?, ?, ?, ?, ?, 'hurda', '', ?)
                                ");
                                $sqlHurdaInsert->execute([
                                    $firmaId,
                                    $kategoriId,
                                    $yeniHurdaAdi,
                                    '-',
                                    1,
                                    1,
                                    "Sayaç değişimi sonrası sökülen hurda (İş Emri: {$isemriNo})"
                                ]);
                                $yeniHurdaId = $db->lastInsertId();

                                // Personele zimmetle
                                $ZimmetModel->zimmetVer([
                                    'demirbas_id' => $yeniHurdaId,
                                    'personel_id' => $pId,
                                    'teslim_tarihi' => ($kayitTarihi ?: $normDate),
                                    'teslim_miktar' => 1,
                                    'aciklama' => "Otomatik Hurda Sayaç Zimmeti.\nİş Emri No: {$isemriNo}" . $ekAciklama,
                                    'islem_id' => $perPersonIslemId . "_hurda",
                                    'is_emri_sonucu' => $isemriSonucu,
                                    'kaynak' => 'otomatik'
                                ]);

                                $zimmetDusuldu = 1;
                            } catch (Exception $e) {
                                cronLog("Zimmet Hatası (Personel: $pId): " . $e->getMessage());
                            }
                        }
                    }
                }

                $insertBatch[] = [
                    $perPersonIslemId,
                    $firmaId,
                    $pId,
                    $ekipKoduId,
                    $isemriNo,
                    $aboneNo,
                    trim($veri['ISEMRI_SEBEP'] ?? ''),
                    $ekipKoduStr,
                    trim($veri['MEMUR'] ?? ''),
                    trim($veri['SONUCLANDIRAN_KULLANICI'] ?? ''),
                    trim($veri['BOLGE'] ?? ''),
                    trim($veri['ISEMRI_SONUCU'] ?? ''),
                    trim($veri['SONUC_ACIKLAMA'] ?? '') . $ekAciklama,
                    $takilanSayacNo,
                    $kayitTarihi,
                    $normDate,
                    $zimmetDusuldu,
                    $isSayisi
                ];
                $yeniKayit++;
            }
        }

        $db->beginTransaction();

        // Toplu Kayıt
        if (!empty($insertBatch)) {
            $chunks = array_chunk($insertBatch, 500);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'));
                $sql = "INSERT INTO sayac_degisim (islem_id, firma_id, personel_id, ekip_kodu_id, isemri_no, abone_no, isemri_sebep, ekip, memur, sonuclandiran_kullanici, bolge, isemri_sonucu, sonuc_aciklama, takilan_sayacno, kayit_tarihi, tarih, zimmet_dusuldu, is_sayisi) 
                        VALUES $placeholders
                        ON DUPLICATE KEY UPDATE 
                            silinme_tarihi = NULL,
                            personel_id = VALUES(personel_id),
                            ekip_kodu_id = VALUES(ekip_kodu_id),
                            isemri_sebep = VALUES(isemri_sebep),
                            ekip = VALUES(ekip),
                            memur = VALUES(memur),
                            sonuclandiran_kullanici = VALUES(sonuclandiran_kullanici),
                            bolge = VALUES(bolge),
                            isemri_sonucu = VALUES(isemri_sonucu),
                            sonuc_aciklama = VALUES(sonuc_aciklama),
                            takilan_sayacno = VALUES(takilan_sayacno),
                            kayit_tarihi = VALUES(kayit_tarihi),
                            tarih = VALUES(tarih),
                            zimmet_dusuldu = VALUES(zimmet_dusuldu),
                            is_sayisi = VALUES(is_sayisi),
                            guncelleme_tarihi = CURRENT_TIMESTAMP";
                $stmt = $db->prepare($sql);
                $params = [];
                foreach ($chunk as $row) {
                    $params = array_merge($params, $row);
                }
                $stmt->execute($params);
            }
        }
        $db->commit();

        cronLog("$yeniKayit yeni kayıt eklendi, $silinenKayit eski silindi.");

        // System log kaydi
        $SystemLog = new \App\Model\SystemLogModel();
        $SystemLog->logAction(0, 'Cron - Online Sayaç Değişim Sorgulama', "Firma ID: $firmaId, Tarih: $tarih. $yeniKayit yeni kayıt, $silinenKayit silinen.", \App\Model\SystemLogModel::LEVEL_IMPORTANT);

        return ['yeni_kayit' => $yeniKayit, 'silinen_kayit' => $silinenKayit, 'toplam_api' => count($apiData), 'atlanAnListesi' => array_unique($atlanAnListesi)];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        cronLog("HATA: " . $e->getMessage());
        return ['yeni_kayit' => 0, 'silinen_kayit' => 0, 'toplam_api' => 0, 'error' => $e->getMessage()];
    }
}
