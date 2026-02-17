<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\AracModel;
use App\Model\AracZimmetModel;
use App\Model\AracYakitModel;
use App\Model\AracKmModel;
use App\Model\AracServisModel;
use App\Helper\Security;
use App\Helper\Date;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST' || (isset($_GET['action']) && $_GET['action'] == 'get-arac-puantaj-table')) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    $Arac = new AracModel();
    $Zimmet = new AracZimmetModel();
    $Yakit = new AracYakitModel();
    $Km = new AracKmModel();
    $Servis = new AracServisModel();

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
                if (!empty($data['muayene_bitis_tarihi'])) {
                    $data['muayene_bitis_tarihi'] = Date::Ymd($data['muayene_bitis_tarihi']);
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

            case 'arac-excel-yukle':
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
                    'marka' => ['marka'],
                    'model' => ['model'],
                    'model_yili' => ['model yılı', 'model yili', 'yıl', 'yil'],
                    'renk' => ['renk'],
                    'arac_tipi' => ['araç tipi', 'arac tipi', 'tip'],
                    'yakit_tipi' => ['yakıt tipi', 'yakit tipi', 'yakıt'],
                    'guncel_km' => ['güncel km', 'guncel km', 'km', 'kilometre'],
                    'muayene_bitis_tarihi' => ['muayene bitiş tarihi', 'muayene bitis tarihi', 'muayene tarihi', 'muayene'],
                    'sigorta_bitis_tarihi' => ['sigorta bitiş', 'sigorta bitis', 'sigorta'],
                    'kasko_bitis_tarihi' => ['kasko bitiş', 'kasko bitis', 'kasko'],
                    'mulkiyet' => ['mülkiyet', 'mulkiyet', 'mülkiyet durumu', 'mulkiyet durumu'],
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
                $updatedCount = 0;
                $errorDetails = [];

                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    $rowNum = $i + 1;

                    $plaka = strtoupper(trim($row[$colIndices['plaka']] ?? ''));
                    if (empty($plaka))
                        continue;

                    try {
                        // Mevcut aracı kontrol et
                        $mevcutArac = $Arac->plakaKontrol($plaka);

                        $newData = [
                            'firma_id' => $_SESSION['firma_id'],
                            'plaka' => $plaka,
                            'aktif_mi' => 1
                        ];

                        if ($mevcutArac) {
                            $newData['id'] = $mevcutArac->id;
                        }

                        // Diğer alanlar
                        foreach ($columnMap as $dbCol => $names) {
                            if ($dbCol === 'plaka')
                                continue;
                            if (isset($colIndices[$dbCol])) {
                                $val = trim($row[$colIndices[$dbCol]] ?? '');

                                // Tarih alanları
                                if (in_array($dbCol, ['muayene_bitis_tarihi', 'sigorta_bitis_tarihi', 'kasko_bitis_tarihi'])) {
                                    if ($val !== '') {
                                        if (is_numeric($val)) {
                                            $val = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)->format('Y-m-d');
                                        } else {
                                            $timestamp = strtotime(str_replace(['.', '/'], '-', $val));
                                            $val = $timestamp ? date('Y-m-d', $timestamp) : null;
                                        }
                                    } else {
                                        $val = null;
                                    }
                                }

                                // Tip eşleştirmeleri
                                if ($dbCol === 'arac_tipi') {
                                    $val = mb_strtolower($val, 'UTF-8');
                                    $tipMap = [
                                        'binek' => 'binek',
                                        'kamyonet' => 'kamyonet',
                                        'kamyon' => 'kamyon',
                                        'minibüs' => 'minibus',
                                        'minibus' => 'minibus',
                                        'otobüs' => 'otobus',
                                        'otobus' => 'otobus',
                                        'motosiklet' => 'motosiklet',
                                        'diğer' => 'diger',
                                        'diger' => 'diger'
                                    ];
                                    $val = $tipMap[$val] ?? 'binek';
                                }

                                if ($dbCol === 'yakit_tipi') {
                                    $val = mb_strtolower($val, 'UTF-8');
                                    $yakitMap = [
                                        'dizel' => 'dizel',
                                        'motorin' => 'dizel',
                                        'benzin' => 'benzin',
                                        'lpg' => 'lpg',
                                        'elektrik' => 'elektrik',
                                        'hibrit' => 'hibrit'
                                    ];
                                    $val = $yakitMap[$val] ?? 'dizel';
                                }

                                if ($val !== '') {
                                    $newData[$dbCol] = $val;
                                }
                            }
                        }

                        // Eğer yeni araçsa baslangic_km = guncel_km yapalım
                        if (!$mevcutArac && isset($newData['guncel_km'])) {
                            $newData['baslangic_km'] = $newData['guncel_km'];
                        }

                        $Arac->saveWithAttr($newData);

                        if ($mevcutArac) {
                            $updatedCount++;
                        } else {
                            $addedCount++;
                        }

                    } catch (Exception $e) {
                        $errorDetails[] = "Satır $rowNum ($plaka): " . $e->getMessage();
                    }
                }

                $responseMessage = "İşlem tamamlandı. Eklenen: $addedCount, Güncellenen: $updatedCount";
                if (count($errorDetails) > 0) {
                    $responseMessage .= ", Hatalı: " . count($errorDetails);
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => $responseMessage,
                    'errors' => $errorDetails
                ]);
                break;

            // =============================================
            // SERVİS KAYDI İŞLEMLERİ
            // =============================================
            case 'servis-kaydet':
                $data = $_POST;
                $servis_id = isset($data['id']) ? intval($data['id']) : 0;
                $arac_id = intval($data['arac_id'] ?? 0);

                if ($arac_id <= 0) {
                    throw new Exception("Araç seçimi zorunludur.");
                }

                unset($data['action']);
                $data['firma_id'] = $_SESSION['firma_id'];
                $data['servis_tarihi'] = Date::Ymd($data['servis_tarihi'] ?? date('Y-m-d'));
                if (!empty($data['iade_tarihi'])) {
                    $data['iade_tarihi'] = Date::Ymd($data['iade_tarihi']);
                } else {
                    $data['iade_tarihi'] = null;
                }
                $data['olusturan_kullanici_id'] = $_SESSION['user_id'] ?? null;

                // Boş değerleri null yap
                foreach ($data as $key => $value) {
                    if ($value === '') {
                        $data[$key] = null;
                    }
                }

                $Servis->saveWithAttr($data);

                // Araç KM güncelle (eğer çıkış KM girildiyse)
                if (!empty($data['cikis_km'])) {
                    $Arac->updateKm($arac_id, $data['cikis_km']);
                } elseif (!empty($data['giris_km'])) {
                    $Arac->updateKm($arac_id, $data['giris_km']);
                }

                $message = $servis_id > 0 ? "Servis kaydı güncellendi." : "Servis kaydı eklendi.";
                echo json_encode(['status' => 'success', 'message' => $message]);
                break;

            case 'servis-sil':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("Geçersiz servis kaydı ID.");
                }

                $Servis->softDelete($id);
                echo json_encode(['status' => 'success', 'message' => 'Servis kaydı silindi.']);
                break;

            case 'servis-listesi':
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

                    $kayitlar = $Servis->getByDateRange($baslangic, $bitis, $arac_id);
                    $stats = $Servis->getStats(null, null, $baslangic, $bitis, $arac_id);
                } elseif ($arac_id) {
                    $kayitlar = $Servis->getByArac($arac_id);
                    $stats = $Servis->getStats(null, null, null, null, $arac_id);
                } else {
                    $kayitlar = $Servis->all();
                    $stats = $Servis->getStats(date('Y'), date('m')); // Varsayılan aylık stats
                }

                echo json_encode(['status' => 'success', 'data' => $kayitlar, 'stats' => $stats]);
                break;

            case 'servis-detay':
                $id = intval($_POST['id'] ?? 0);
                $kayit = $Servis->find($id);

                if (!$kayit) {
                    throw new Exception("Servis kaydı bulunamadı.");
                }

                if ($kayit->servis_tarihi)
                    $kayit->servis_tarihi = Date::dmY($kayit->servis_tarihi);
                if ($kayit->iade_tarihi)
                    $kayit->iade_tarihi = Date::dmY($kayit->iade_tarihi);

                echo json_encode(['status' => 'success', 'data' => $kayit]);
                break;

            case 'get-arac-puantaj-table':
                $yil = intval($_GET['year'] ?? date('Y'));
                $ay = str_pad($_GET['month'] ?? date('m'), 2, '0', STR_PAD_LEFT);
                $arac_id = isset($_GET['arac_id']) && $_GET['arac_id'] !== '' ? intval($_GET['arac_id']) : null;

                $puantajData = $Km->getMonthlyPuantaj($yil, $ay, $arac_id);
                $gunSayisi = date('t', strtotime("$yil-$ay-01"));

                ob_start();
                ?>
                <div class="table-responsive" style="max-height: 70vh;">
                    <table class="table table-bordered table-sm table-hover align-middle mb-0" id="puantajTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="text-center sticky-col-1" rowspan="2">#</th>
                                <th class="sticky-col-2" rowspan="2">Plaka</th>
                                <th class="sticky-col-3" rowspan="2">Marka/Model</th>
                                <?php for ($i = 1; $i <= $gunSayisi; $i++): ?>
                                    <th class="text-center" colspan="3"><?= $i ?></th>
                                <?php endfor; ?>
                                <th class="text-center bg-info text-white" rowspan="2">Toplam KM</th>
                            </tr>
                            <tr>
                                <?php for ($i = 1; $i <= $gunSayisi; $i++): ?>
                                    <th class="text-center km-col km-start-col d-none" style="font-size: 10px;">Bas.</th>
                                    <th class="text-center km-col km-end-col d-none" style="font-size: 10px;">Bit.</th>
                                    <th class="text-center km-total-col" style="font-size: 10px;">KM</th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($puantajData)): ?>
                                <tr>
                                    <td colspan="<?= ($gunSayisi * 3) + 4 ?>" class="text-center py-4">Kayıt bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php $sira = 1;
                                foreach ($puantajData as $arac_id => $row):
                                    $aylikToplam = 0;
                                    ?>
                                    <tr>
                                        <td class="text-center sticky-col-1"><?= $sira++ ?></td>
                                        <td class="fw-bold sticky-col-2"><?= $row['info']['plaka'] ?></td>
                                        <td class="sticky-col-3 text-truncate" style="max-width: 150px;">
                                            <?= ($row['info']['marka'] ?? '-') . ' ' . ($row['info']['model'] ?? '') ?>
                                        </td>
                                        <?php for ($i = 1; $i <= $gunSayisi; $i++):
                                            $gunData = $row['gunler'][$i] ?? null;
                                            $yapilan = $gunData ? (float) $gunData['yapilan'] : 0;
                                            $aylikToplam += $yapilan;
                                            ?>
                                            <td class="text-center km-col km-start-col d-none bg-light">
                                                <?= $gunData ? number_format($gunData['baslangic'], 0, ',', '.') : '-' ?>
                                            </td>
                                            <td class="text-center km-col km-end-col d-none bg-light">
                                                <?= $gunData ? number_format($gunData['bitis'], 0, ',', '.') : '-' ?>
                                            </td>
                                            <td class="text-center km-total-col fw-bold <?= $yapilan > 0 ? 'text-primary' : 'text-muted' ?>">
                                                <?= $yapilan > 0 ? number_format($yapilan, 0, ',', '.') : '-' ?>
                                            </td>
                                        <?php endfor; ?>
                                        <td class="text-center bg-info-subtle fw-bold"><?= number_format($aylikToplam, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <style>
                    #puantajTable {
                        border-collapse: separate;
                        border-spacing: 0;
                        width: 100% !important;
                        font-size: 11px;
                        /* Smaller font for better fit */
                    }

                    .sticky-col-1,
                    .sticky-col-2,
                    .sticky-col-3 {
                        position: sticky !important;
                        background: #f8f9fa !important;
                        z-index: 20 !important;
                        box-shadow: 2px 0 2px -1px rgba(0, 0, 0, 0.1);
                    }

                    .sticky-col-1 {
                        left: 0;
                        width: 30px;
                        min-width: 30px;
                    }

                    .sticky-col-2 {
                        left: 30px;
                        width: 90px;
                        min-width: 90px;
                        border-right: 1px solid #dee2e6 !important;
                    }

                    .sticky-col-3 {
                        left: 120px;
                        width: 120px;
                        min-width: 120px;
                        border-right: 2px solid #adb5bd !important;
                    }

                    /* Dark mode adjustments for sticky columns */
                    [data-bs-theme="dark"] .sticky-col-1,
                    [data-bs-theme="dark"] .sticky-col-2,
                    [data-bs-theme="dark"] .sticky-col-3 {
                        background: #2a3042 !important;
                    }

                    #puantajTable thead th {
                        background: #f8f9fa !important;
                        z-index: 10;
                        vertical-align: middle;
                        white-space: nowrap;
                        padding: 4px 2px !important;
                        /* Minimal padding */
                    }

                    #puantajTable tbody td {
                        padding: 4px 2px !important;
                    }

                    [data-bs-theme="dark"] #puantajTable thead th {
                        background: #32394e !important;
                    }

                    #puantajTable thead tr:first-child th {
                        top: 0;
                        z-index: 11;
                    }

                    #puantajTable thead tr:last-child th {
                        top: 25px;
                        /* Adjusted for smaller font */
                        z-index: 11;
                    }

                    .km-col {
                        min-width: 25px;
                    }

                    .km-total-col {
                        min-width: 30px;
                    }

                    .bg-info-subtle {
                        background-color: #e0f2f1 !important;
                    }

                    [data-bs-theme="dark"] .bg-info-subtle {
                        background-color: rgba(0, 150, 136, 0.2) !important;
                    }
                </style>
                <?php
                $html = ob_get_clean();
                header('Content-Type: text/html');
                die($html);
                break;

            default:
                throw new Exception("Geçersiz işlem.");
        }

    } catch (Exception $e) {
        if (isset($_GET['action']) && $_GET['action'] == 'get-arac-puantaj-table') {
            die('<div class="alert alert-danger">' . $e->getMessage() . '</div>');
        }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>