<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\AracModel;
use App\Model\AracZimmetModel;
use App\Model\AracYakitModel;
use App\Model\AracKmModel;
use App\Helper\Security;
use App\Helper\Date;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    $Arac = new AracModel();
    $Zimmet = new AracZimmetModel();
    $Yakit = new AracYakitModel();
    $Km = new AracKmModel();

    try {
        switch ($action) {
            // =============================================
            // ARAÇ İŞLEMLERİ
            // =============================================
            case 'arac-kaydet':
                $data = $_POST;
                $arac_id = isset($data['id']) ? intval($data['id']) : 0;

                // Plaka kontrolü
                $plaka = strtoupper(trim($data['plaka'] ?? ''));
                if (empty($plaka)) {
                    throw new Exception("Plaka zorunludur.");
                }

                $mevcutArac = $Arac->plakaKontrol($plaka, $arac_id > 0 ? $arac_id : null);
                if ($mevcutArac) {
                    throw new Exception("Bu plaka zaten kayıtlı.");
                }

                // Veriyi hazırla
                unset($data['action']);
                $data['plaka'] = $plaka;
                $data['firma_id'] = $_SESSION['firma_id'];

                // Tarih formatları
                if (!empty($data['muayene_tarihi'])) {
                    $data['muayene_tarihi'] = Date::Ymd($data['muayene_tarihi']);
                }
                if (!empty($data['sigorta_bitis_tarihi'])) {
                    $data['sigorta_bitis_tarihi'] = Date::Ymd($data['sigorta_bitis_tarihi']);
                }
                if (!empty($data['kasko_bitis_tarihi'])) {
                    $data['kasko_bitis_tarihi'] = Date::Ymd($data['kasko_bitis_tarihi']);
                }

                // Boş değerleri null yap
                foreach ($data as $key => $value) {
                    if ($value === '') {
                        $data[$key] = null;
                    }
                }

                // Güncel KM başlangıç KM'den küçük olamaz
                if (isset($data['baslangic_km']) && isset($data['guncel_km'])) {
                    if (intval($data['guncel_km']) < intval($data['baslangic_km'])) {
                        $data['guncel_km'] = $data['baslangic_km'];
                    }
                }

                $Arac->saveWithAttr($data);

                $message = $arac_id > 0 ? "Araç başarıyla güncellendi." : "Araç başarıyla kaydedildi.";
                echo json_encode(['status' => 'success', 'message' => $message]);
                break;

            case 'arac-sil':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("Geçersiz araç ID.");
                }

                $Arac->softDelete($id);
                echo json_encode(['status' => 'success', 'message' => 'Araç başarıyla silindi.']);
                break;

            case 'arac-detay':
                $id = intval($_POST['id'] ?? 0);
                $arac = $Arac->getById($id);

                if (!$arac) {
                    throw new Exception("Araç bulunamadı.");
                }

                echo json_encode(['status' => 'success', 'data' => $arac]);
                break;

            case 'arac-listesi':
                $araclar = $Arac->getForSelect($_POST['search'] ?? '');
                echo json_encode(['results' => $araclar]);
                break;

            // =============================================
            // ZİMMET İŞLEMLERİ
            // =============================================
            case 'zimmet-ver':
                $arac_id = intval($_POST['arac_id'] ?? 0);
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $zimmet_tarihi = $_POST['zimmet_tarihi'] ?? date('Y-m-d');
                $teslim_km = intval($_POST['teslim_km'] ?? 0);
                $notlar = $_POST['notlar'] ?? null;

                if ($arac_id <= 0 || $personel_id <= 0) {
                    throw new Exception("Araç ve personel seçimi zorunludur.");
                }

                // Aktif zimmet kontrolü
                $aktifZimmet = $Zimmet->getAktifZimmetByArac($arac_id);
                if ($aktifZimmet) {
                    throw new Exception("Bu araç zaten {$aktifZimmet->personel_adi} adlı personele zimmetli. Önce iade alınmalı.");
                }

                $data = [
                    'firma_id' => $_SESSION['firma_id'],
                    'arac_id' => $arac_id,
                    'personel_id' => $personel_id,
                    'zimmet_tarihi' => Date::Ymd($zimmet_tarihi),
                    'teslim_km' => $teslim_km,
                    'notlar' => $notlar,
                    'durum' => 'aktif',
                    'olusturan_kullanici_id' => $_SESSION['user_id'] ?? null
                ];

                $Zimmet->saveWithAttr($data);

                // Araç KM güncelle
                if ($teslim_km > 0) {
                    $Arac->updateKm($arac_id, $teslim_km);
                }

                echo json_encode(['status' => 'success', 'message' => 'Araç başarıyla zimmetlendi.']);
                break;

            case 'zimmet-iade':
                $zimmet_id = intval($_POST['zimmet_id'] ?? 0);
                $iade_km = intval($_POST['iade_km'] ?? 0);
                $notlar = $_POST['notlar'] ?? null;

                if ($zimmet_id <= 0) {
                    throw new Exception("Geçersiz zimmet ID.");
                }

                $Zimmet->iadeEt($zimmet_id, $iade_km, $notlar);

                // Araç KM güncelle
                if ($iade_km > 0) {
                    $zimmetBilgi = $Zimmet->find($zimmet_id);
                    if ($zimmetBilgi) {
                        $Arac->updateKm($zimmetBilgi->arac_id, $iade_km);
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Araç iadesi başarıyla tamamlandı.']);
                break;

            case 'zimmet-listesi':
                $zimmetler = $Zimmet->all();
                echo json_encode(['status' => 'success', 'data' => $zimmetler]);
                break;

            // =============================================
            // YAKIT KAYDI İŞLEMLERİ
            // =============================================
            case 'yakit-kaydet':
                $data = $_POST;
                $yakit_id = isset($data['id']) ? intval($data['id']) : 0;
                $arac_id = intval($data['arac_id'] ?? 0);

                if ($arac_id <= 0) {
                    throw new Exception("Araç seçimi zorunludur.");
                }

                unset($data['action']);
                $data['firma_id'] = $_SESSION['firma_id'];
                $data['tarih'] = Date::Ymd($data['tarih'] ?? date('Y-m-d'));
                $data['olusturan_kullanici_id'] = $_SESSION['user_id'] ?? null;

                // Önceki KM değerini al
                if ($yakit_id == 0) {
                    $oncekiKm = $Yakit->getOncekiKm($arac_id);
                    $data['onceki_km'] = $oncekiKm;
                }

                // Birim fiyat hesapla
                if (!empty($data['yakit_miktari']) && !empty($data['toplam_tutar']) && empty($data['birim_fiyat'])) {
                    $data['birim_fiyat'] = round(floatval($data['toplam_tutar']) / floatval($data['yakit_miktari']), 2);
                }

                // Boş değerleri null yap
                foreach ($data as $key => $value) {
                    if ($value === '') {
                        $data[$key] = null;
                    }
                }

                $Yakit->saveWithAttr($data);

                // Araç KM güncelle
                if (!empty($data['km'])) {
                    $Arac->updateKm($arac_id, $data['km']);
                }

                $message = $yakit_id > 0 ? "Yakıt kaydı güncellendi." : "Yakıt kaydı eklendi.";
                echo json_encode(['status' => 'success', 'message' => $message]);
                break;

            case 'yakit-sil':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("Geçersiz yakıt kaydı ID.");
                }

                $Yakit->softDelete($id);
                echo json_encode(['status' => 'success', 'message' => 'Yakıt kaydı silindi.']);
                break;

            case 'yakit-listesi':

                $arac_id = isset($_POST['arac_id']) && $_POST['arac_id'] !== '' ? intval($_POST['arac_id']) : null;
                $baslangic = null;
                if (!empty($_POST['baslangic'])) {
                    $d = DateTime::createFromFormat('d.m.Y', $_POST['baslangic']);
                    if ($d)
                        $baslangic = $d->format('Y-m-d');
                }
                $bitis = null;
                if (!empty($_POST['bitis'])) {
                    $d = DateTime::createFromFormat('d.m.Y', $_POST['bitis']);
                    if ($d)
                        $bitis = $d->format('Y-m-d');
                }

                if ($baslangic || $bitis) {
                    // Eğer sadece biri seçildiyse diğerini varsayılan yap
                    if (!$baslangic)
                        $baslangic = date('Y-m-01');
                    if (!$bitis)
                        $bitis = date('Y-m-t');

                    $kayitlar = $Yakit->getByDateRange($baslangic, $bitis, $arac_id);
                    $stats = $Yakit->getStats(null, null, $baslangic, $bitis, $arac_id);
                } elseif ($arac_id) {
                    $kayitlar = $Yakit->getByArac($arac_id);
                    $stats = $Yakit->getStats(null, null, null, null, $arac_id);
                } else {
                    $kayitlar = $Yakit->all();
                    $stats = $Yakit->getStats(date('Y'), date('m')); // Varsayılan aylık
                }

                echo json_encode(['status' => 'success', 'data' => $kayitlar, 'stats' => $stats]);
                break;

            case 'yakit-detay':
                $id = intval($_POST['id'] ?? 0);
                $kayit = $Yakit->find($id);

                if (!$kayit) {
                    throw new Exception("Yakıt kaydı bulunamadı.");
                }

                echo json_encode(['status' => 'success', 'data' => $kayit]);
                break;

            // =============================================
            // KM KAYDI İŞLEMLERİ
            // =============================================
            case 'km-kaydet':
                $data = $_POST;
                $km_id = isset($data['id']) ? intval($data['id']) : 0;
                $arac_id = intval($data['arac_id'] ?? 0);

                if ($arac_id <= 0) {
                    throw new Exception("Araç seçimi zorunludur.");
                }

                $tarih = Date::Ymd($data['tarih'] ?? date('Y-m-d'));

                // Aynı tarihte kayıt var mı kontrolü
                $mevcutKayit = $Km->kayitVarMi($arac_id, $tarih, $km_id > 0 ? $km_id : null);
                if ($mevcutKayit) {
                    throw new Exception("Bu araç için bu tarihte zaten KM kaydı bulunmaktadır.");
                }

                unset($data['action']);
                $data['firma_id'] = $_SESSION['firma_id'];
                $data['tarih'] = $tarih;
                $data['olusturan_kullanici_id'] = $_SESSION['user_id'] ?? null;

                // Boş değerleri null yap
                foreach ($data as $key => $value) {
                    if ($value === '') {
                        $data[$key] = null;
                    }
                }

                $Km->saveWithAttr($data);

                // Araç güncel KM güncelle
                if (!empty($data['bitis_km'])) {
                    $Arac->updateKm($arac_id, $data['bitis_km']);
                }

                $message = $km_id > 0 ? "KM kaydı güncellendi." : "KM kaydı eklendi.";
                echo json_encode(['status' => 'success', 'message' => $message]);
                break;

            case 'km-sil':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("Geçersiz KM kaydı ID.");
                }

                $Km->softDelete($id);
                echo json_encode(['status' => 'success', 'message' => 'KM kaydı silindi.']);
                break;

            case 'km-listesi':
                $arac_id = isset($_POST['arac_id']) && $_POST['arac_id'] !== '' ? intval($_POST['arac_id']) : null;
                $baslangic = null;
                if (!empty($_POST['baslangic'])) {
                    $d = DateTime::createFromFormat('d.m.Y', $_POST['baslangic']);
                    if ($d)
                        $baslangic = $d->format('Y-m-d');
                }
                $bitis = null;
                if (!empty($_POST['bitis'])) {
                    $d = DateTime::createFromFormat('d.m.Y', $_POST['bitis']);
                    if ($d)
                        $bitis = $d->format('Y-m-d');
                }

                if ($baslangic || $bitis) {
                    // Eğer sadece biri seçildiyse diğerini varsayılan yap
                    if (!$baslangic)
                        $baslangic = date('Y-m-01');
                    if (!$bitis)
                        $bitis = date('Y-m-t');

                    $kayitlar = $Km->getByDateRange($baslangic, $bitis, $arac_id);
                    $stats = $Km->getStats(null, null, $baslangic, $bitis, $arac_id);
                } elseif ($arac_id) {
                    $kayitlar = $Km->getByArac($arac_id);
                    $stats = $Km->getStats(null, null, null, null, $arac_id);
                } else {
                    $kayitlar = $Km->all();
                    $stats = $Km->getStats(date('Y'), date('m')); // Varsayılan aylık
                }

                echo json_encode(['status' => 'success', 'data' => $kayitlar, 'stats' => $stats]);
                break;

            case 'km-detay':
                $id = intval($_POST['id'] ?? 0);
                $kayit = $Km->find($id);

                if (!$kayit) {
                    throw new Exception("KM kaydı bulunamadı.");
                }

                echo json_encode(['status' => 'success', 'data' => $kayit]);
                break;

            // =============================================
            // RAPORLAR
            // =============================================
            case 'aylik-rapor':
                $yil = intval($_POST['yil'] ?? date('Y'));
                $ay = intval($_POST['ay'] ?? date('m'));
                $arac_id = isset($_POST['arac_id']) && $_POST['arac_id'] !== '' ? intval($_POST['arac_id']) : null;

                $yakitOzet = $Yakit->getAylikOzet($yil, $ay, $arac_id);
                $kmOzet = $Km->getAylikOzet($yil, $ay, $arac_id);
                $genelYakitStats = $Yakit->getStats($yil, $ay);
                $genelKmStats = $Km->getStats($yil, $ay);

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'yakit_ozet' => $yakitOzet,
                        'km_ozet' => $kmOzet,
                        'genel_yakit' => $genelYakitStats,
                        'genel_km' => $genelKmStats
                    ]
                ]);
                break;

            case 'gunluk-rapor':
                $tarih = $_POST['tarih'] ?? date('Y-m-d');
                $arac_id = isset($_POST['arac_id']) && $_POST['arac_id'] !== '' ? intval($_POST['arac_id']) : null;

                $yakitOzet = $Yakit->getGunlukOzet($tarih, $arac_id);
                $kmOzet = $Km->getGunlukOzet($tarih, $arac_id);

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'yakit_ozet' => $yakitOzet,
                        'km_ozet' => $kmOzet
                    ]
                ]);
                break;

            case 'yillik-rapor':
                $yil = intval($_POST['yil'] ?? date('Y'));

                $yakitRapor = $Yakit->getYillikRapor($yil);

                echo json_encode([
                    'status' => 'success',
                    'data' => $yakitRapor
                ]);
                break;

            // =============================================
            // EXCEL YÜKLEME
            // =============================================
            case 'yakit-excel-yukle':
                $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
                if (file_exists($vendorAutoload)) {
                    require_once $vendorAutoload;
                } else {
                    throw new Exception("Excel kütüphanesi bulunamadı.");
                }

                if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) {
                    throw new Exception("Dosya yüklenemedi.");
                }

                $inputFileName = $_FILES['excel_file']['tmp_name'];
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                if (count($rows) < 2) {
                    throw new Exception("Excel dosyası boş veya sadece başlık satırı içeriyor.");
                }

                // Başlıkları al
                $headers = array_map(function ($h) {
                    $h = str_replace(['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'], ['i', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], $h ?? '');
                    return mb_strtolower(trim($h), 'UTF-8');
                }, $rows[0]);

                // Sütun eşleştirme
                $columnMap = [
                    'plaka' => ['plaka', 'araç plakası', 'arac plakasi'],
                    'tarih' => ['tarih', 'yakıt tarihi', 'yakit tarihi'],
                    'km' => ['km', 'kilometre', 'güncel km', 'guncel km'],
                    'yakit_miktari' => ['litre', 'yakıt miktarı', 'yakit miktari', 'miktar'],
                    'birim_fiyat' => ['birim fiyat', 'litre fiyatı', 'litre fiyati', 'fiyat'],
                    'toplam_tutar' => ['tutar', 'toplam tutar', 'toplam', 'ödenen'],
                    'istasyon' => ['istasyon', 'akaryakıt istasyonu', 'benzin istasyonu'],
                    'fatura_no' => ['fatura no', 'fatura numarası', 'fiş no'],
                    'notlar' => ['not', 'notlar', 'açıklama']
                ];

                $colIndices = [];
                foreach ($columnMap as $dbCol => $possibleNames) {
                    foreach ($possibleNames as $name) {
                        $index = array_search($name, $headers);
                        if ($index !== false) {
                            $colIndices[$dbCol] = $index;
                            break;
                        }
                    }
                }

                if (!isset($colIndices['plaka'])) {
                    throw new Exception("'Plaka' sütunu bulunamadı.");
                }

                $addedCount = 0;
                $errorDetails = [];

                // Tüm araçları al (plaka -> id eşleştirmesi için)
                $tumAraclar = $Arac->all();
                $plakaMap = [];
                foreach ($tumAraclar as $arac) {
                    $plakaMap[strtoupper(trim($arac->plaka))] = $arac->id;
                }

                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    $rowNum = $i + 1;

                    $plaka = strtoupper(trim($row[$colIndices['plaka']] ?? ''));
                    if (empty($plaka))
                        continue;

                    if (!isset($plakaMap[$plaka])) {
                        $errorDetails[] = "Satır $rowNum: '$plaka' plakalı araç bulunamadı.";
                        continue;
                    }

                    $aracId = $plakaMap[$plaka];

                    try {
                        $newData = [
                            'firma_id' => $_SESSION['firma_id'],
                            'arac_id' => $aracId,
                            'olusturan_kullanici_id' => $_SESSION['user_id'] ?? null
                        ];

                        // Tarih
                        if (isset($colIndices['tarih'])) {
                            $tarihVal = $row[$colIndices['tarih']];
                            if (is_numeric($tarihVal)) {
                                $tarihVal = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tarihVal)->format('Y-m-d');
                            } else {
                                $timestamp = strtotime(str_replace(['.', '/'], '-', $tarihVal));
                                $tarihVal = $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
                            }
                            $newData['tarih'] = $tarihVal;
                        } else {
                            $newData['tarih'] = date('Y-m-d');
                        }

                        // Diğer alanlar
                        foreach (['km', 'yakit_miktari', 'birim_fiyat', 'toplam_tutar', 'istasyon', 'fatura_no', 'notlar'] as $field) {
                            if (isset($colIndices[$field])) {
                                $val = trim($row[$colIndices[$field]] ?? '');
                                if ($val !== '') {
                                    $newData[$field] = $val;
                                }
                            }
                        }

                        // Birim fiyat hesapla
                        if (empty($newData['birim_fiyat']) && !empty($newData['yakit_miktari']) && !empty($newData['toplam_tutar'])) {
                            $newData['birim_fiyat'] = round(floatval($newData['toplam_tutar']) / floatval($newData['yakit_miktari']), 2);
                        }

                        // Önceki KM
                        $oncekiKm = $Yakit->getOncekiKm($aracId);
                        $newData['onceki_km'] = $oncekiKm;

                        $Yakit->saveWithAttr($newData);

                        // Araç KM güncelle
                        if (!empty($newData['km'])) {
                            $Arac->updateKm($aracId, $newData['km']);
                        }

                        $addedCount++;

                    } catch (Exception $e) {
                        $errorDetails[] = "Satır $rowNum ($plaka): " . $e->getMessage();
                    }
                }

                $responseMessage = "İşlem tamamlandı. Eklenen: $addedCount";
                if (count($errorDetails) > 0) {
                    $responseMessage .= ", Hatalı: " . count($errorDetails);
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => $responseMessage,
                    'errors' => $errorDetails
                ]);
                break;

            default:
                throw new Exception("Geçersiz işlem.");
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>