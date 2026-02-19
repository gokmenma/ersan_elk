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

if ($_SERVER['REQUEST_METHOD'] == 'POST' || (isset($_GET['action']) && in_array($_GET['action'], ['get-arac-puantaj-table', 'get-arac-ozel-puantaj']))) {
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
                if ($arac) {
                    $arac->muayene_bitis_tarihi = Date::dmY($arac->muayene_bitis_tarihi);
                    $arac->sigorta_bitis_tarihi = Date::dmY($arac->sigorta_bitis_tarihi);
                    $arac->kasko_bitis_tarihi = Date::dmY($arac->kasko_bitis_tarihi);

                    // Debug Log
                    file_put_contents(dirname(__DIR__, 2) . '/debug_arac_detay.txt', "ID: $id | Muayene: $arac->muayene_bitis_tarihi | Sigorta: $arac->sigorta_bitis_tarihi | Kasko: $arac->kasko_bitis_tarihi\n", FILE_APPEND);
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

                // Araç aktif zimmet kontrolü
                $aktifZimmetArac = $Zimmet->getAktifZimmetByArac($arac_id);
                if ($aktifZimmetArac) {
                    $personelIsim = $aktifZimmetArac->personel_adi ?? 'Bilinmeyen Personel';
                    throw new Exception("Bu araç zaten {$personelIsim} adlı personele zimmetli. Önce iade alınmalı.");
                }

                // Personel aktif zimmet kontrolü
                $aktifZimmetPersonel = $Zimmet->getAktifZimmetByPersonel($personel_id);
                if ($aktifZimmetPersonel) {
                    throw new Exception("Bu personelin üzerine zaten aktif bir araç ({$aktifZimmetPersonel->plaka}) zimmetli. Önce o araç iade edilmeli.");
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

                $baslangic_km = intval($data['baslangic_km'] ?? 0);
                $bitis_km = intval($data['bitis_km'] ?? 0);

                if ($bitis_km > 0 && $bitis_km < $baslangic_km) {
                    throw new Exception("Bitiş KM, başlangıç KM'den küçük olamaz.");
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

                // Yapılan KM hesapla (Eğer gönderilmemişse veya hatalıysa)
                $b = intval($data['baslangic_km'] ?? 0);
                $e = intval($data['bitis_km'] ?? 0);
                $data['yapilan_km'] = ($e > 0 && $b > 0) ? ($e - $b) : 0;

                $Km->saveWithAttr($data);

                // Araç güncel KM güncelle
                if ($e > 0) {
                    $Arac->updateKm($arac_id, $e);
                }

                $message = $km_id > 0 ? "KM kaydı güncellendi." : "KM kaydı eklendi.";
                echo json_encode(['status' => 'success', 'message' => $message]);
                break;

            case 'km-sil':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("Geçersiz KM kaydı ID.");
                }

                // Silmeden önce kaydın bilgilerini al
                $silinecekKayit = $Km->find($id);
                if (!$silinecekKayit) {
                    throw new Exception("KM kaydı bulunamadı.");
                }

                $silinecekAracId = $silinecekKayit->arac_id;
                $silinecekTarih = $silinecekKayit->tarih;
                $silinecekBitisKm = intval($silinecekKayit->bitis_km);

                // Önce soft delete yap
                $Km->softDelete($id);

                // Silinen kaydın ardından gelen sonraki kaydı bul
                $sonrakiKayit = $Km->getSonrakiKayit($silinecekAracId, $silinecekTarih);

                if ($sonrakiKayit) {
                    // Silinen kaydın öncesindeki kaydı bul
                    $oncekiKayit = $Km->getOncekiKayit($silinecekAracId, $silinecekTarih, $id);

                    if ($oncekiKayit && intval($oncekiKayit->bitis_km) > 0) {
                        // Önceki kaydın bitiş KM'sini sonraki kaydın başlangıcı yap
                        $yeniBaslangic = intval($oncekiKayit->bitis_km);
                    } else {
                        // Önceki kayıt yoksa araç tablosundaki başlangıç KM'yi kullan
                        $aracBilgi = $Arac->getById($silinecekAracId);
                        $yeniBaslangic = intval($aracBilgi->baslangic_km ?? 0);
                    }

                    // Sonraki kaydın başlangıç KM'sini ve yapılan KM'sini güncelle
                    $Km->zincirlemeGuncelle($sonrakiKayit->id, $yeniBaslangic);
                }

                // Araç güncel KM'yi güncelle (silme sonrası kalan en son bitiş KM)
                $enSonBitisKm = $Km->getEnSonBitisKm($silinecekAracId);
                if ($enSonBitisKm > 0) {
                    $Arac->updateKm($silinecekAracId, $enSonBitisKm);
                }

                echo json_encode(['status' => 'success', 'message' => 'KM kaydı silindi ve zincir güncellendi.']);
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

            case 'km-excel-yukle':
                require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

                if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== 0) {
                    throw new Exception("Dosya yüklenemedi. Lütfen tekrar deneyin.");
                }

                $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['xlsx', 'xls'])) {
                    throw new Exception("Sadece .xlsx ve .xls dosyaları kabul edilmektedir.");
                }

                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['excel_file']['tmp_name']);
                $excelSheet = $spreadsheet->getActiveSheet();
                $excelRows = $excelSheet->toArray(null, true, true, true);

                $success = 0;
                $skip = 0;
                $errors = [];
                $unmatchedPlates = [];

                // Tüm araçları plaka => id map'i oluştur
                $pdo = $Arac->getDb();
                $stmtAraclar = $pdo->prepare(
                    "SELECT id, plaka FROM araclar WHERE firma_id = :firma_id AND aktif_mi = 1 AND silinme_tarihi IS NULL"
                );
                $stmtAraclar->execute(['firma_id' => $_SESSION['firma_id']]);
                $aracMap = [];
                foreach ($stmtAraclar->fetchAll(\PDO::FETCH_OBJ) as $a) {
                    $normalized = strtoupper(str_replace(' ', '', $a->plaka));
                    $aracMap[$normalized] = $a->id;
                }

                $rowNum = 0;
                foreach ($excelRows as $row) {
                    $rowNum++;
                    if ($rowNum <= 1)
                        continue; // Sadece 1. satır (başlık) atla

                    $plaka = trim($row['A'] ?? '');
                    $tarihRaw = trim($row['B'] ?? '');
                    $baslangicKm = trim($row['C'] ?? '');
                    $bitisKm = trim($row['D'] ?? '');

                    if (empty($plaka) && empty($bitisKm)) {
                        $skip++;
                        continue;
                    }
                    if (empty($plaka)) {
                        $errors[] = "Satır $rowNum: Plaka eksik.";
                        continue;
                    }
                    if (empty($bitisKm) || $bitisKm === '0') {
                        $skip++;
                        continue;
                    }

                    $plakaNorm = strtoupper(str_replace(' ', '', $plaka));
                    if (!isset($aracMap[$plakaNorm])) {
                        if (!in_array($plaka, $unmatchedPlates)) {
                            $unmatchedPlates[] = $plaka;
                        }
                        continue;
                    }
                    $arac_id_yukle = $aracMap[$plakaNorm];

                    // Tarihi dönüştür
                    $tarih = null;
                    if (!empty($tarihRaw)) {
                        if (strpos($tarihRaw, '.') !== false) {
                            $parts = explode('.', $tarihRaw);
                            if (count($parts) === 3) {
                                $tarih = $parts[2] . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                            }
                        } elseif (strpos($tarihRaw, '-') !== false) {
                            $tarih = $tarihRaw;
                        } elseif (is_numeric($tarihRaw)) {
                            // Excel serial date
                            $unixDate = ($tarihRaw - 25569) * 86400;
                            $tarih = date('Y-m-d', $unixDate);
                        }
                    }
                    if (!$tarih || !strtotime($tarih)) {
                        $errors[] = "Satır $rowNum ($plaka): Geçersiz tarih '$tarihRaw'.";
                        continue;
                    }
                    $tarih = date('Y-m-d', strtotime($tarih));

                    $bVal = intval(str_replace(['.', ',', ' '], '', $baslangicKm));
                    $eVal = intval(str_replace(['.', ',', ' '], '', $bitisKm));

                    if ($eVal <= 0) {
                        $skip++;
                        continue;
                    }
                    if ($bVal > 0 && $eVal < $bVal) {
                        $errors[] = "Satır $rowNum ($plaka, $tarih): Bitiş KM ({$eVal}) başlangıçtan ({$bVal}) küçük.";
                        continue;
                    }

                    $mevcutKayit = $Km->kayitVarMi($arac_id_yukle, $tarih, null, true);
                    $saveData = [
                        'firma_id' => $_SESSION['firma_id'],
                        'arac_id' => $arac_id_yukle,
                        'tarih' => $tarih,
                        'baslangic_km' => $bVal,
                        'bitis_km' => $eVal,
                        'yapilan_km' => ($eVal > 0 && $bVal > 0) ? ($eVal - $bVal) : 0,
                        'olusturan_kullanici_id' => $_SESSION['user_id'] ?? null,
                        'silinme_tarihi' => null // Eğer silinmişse geri getir
                    ];
                    if ($mevcutKayit) {
                        $saveData['id'] = $mevcutKayit->id;
                    }
                    $Km->saveWithAttr($saveData);
                    if ($eVal > 0) {
                        $Arac->updateKm($arac_id_yukle, $eVal);
                    }
                    $success++;
                }

                echo json_encode([
                    'status' => 'success',
                    'success' => $success,
                    'skip' => $skip,
                    'errors' => $errors,
                    'unmatchedPlates' => $unmatchedPlates,
                    'message' => "$success kayıt başarıyla işlendi."
                ]);
                break;

            case 'km-kaydet-inline':
                $data = $_POST;
                $arac_id = intval($data['arac_id'] ?? 0);
                $km_id = intval($data['id'] ?? 0);

                // Tarih formatını dd.mm.yyyy -> Y-m-d dönüştür
                $inputTarih = $data['tarih'] ?? '';
                if (strpos($inputTarih, '.') !== false) {
                    $parts = explode('.', $inputTarih);
                    if (count($parts) === 3) {
                        $inputTarih = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                    }
                }
                $tarih = date('Y-m-d', strtotime($inputTarih));

                $baslangic_km = !empty($data['baslangic_km']) ? intval($data['baslangic_km']) : 0;
                $bitis_km = !empty($data['bitis_km']) ? intval($data['bitis_km']) : 0;

                if ($bitis_km > 0 && $bitis_km < $baslangic_km) {
                    throw new Exception("Bitiş KM, başlangıç KM'den küçük olamaz.");
                }

                if ($arac_id <= 0) {
                    throw new Exception("Araç seçimi zorunludur.");
                }

                // ID yoksa tarihten bulmayı dene
                if ($km_id <= 0) {
                    $mevcutKayit = $Km->kayitVarMi($arac_id, $tarih, null, true);
                    if ($mevcutKayit) {
                        $km_id = $mevcutKayit->id;
                    }
                }

                // Kayıt yoksa ve bitiş KM de girilmemişse => yeni kayıt oluşturma
                // Sadece görsel zincir güncellemesi için başarılı dön
                if ($km_id <= 0 && $bitis_km <= 0) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Kayıt gerekmedi (bitiş KM girilmemiş).',
                        'yapilan' => 0,
                        'id' => null
                    ]);
                    break;
                }

                $saveData = [
                    'firma_id' => $_SESSION['firma_id'],
                    'arac_id' => $arac_id,
                    'tarih' => $tarih,
                    'baslangic_km' => $baslangic_km,
                    'bitis_km' => $bitis_km,
                    'yapilan_km' => ($bitis_km > 0 && $baslangic_km > 0) ? ($bitis_km - $baslangic_km) : 0,
                    'olusturan_kullanici_id' => $_SESSION['user_id'] ?? null,
                    'silinme_tarihi' => null // Geri yükleme ihtimaline karşı
                ];

                if ($km_id > 0) {
                    $saveData['id'] = $km_id;
                }

                $resultId = $Km->saveWithAttr($saveData);

                // saveWithAttr insert'de şifreli ID döner, update'de null döner
                // Her durumda düz (numeric) ID döndür
                if ($km_id > 0) {
                    // Update ise zaten elimizde var
                    $numericId = $km_id;
                } else {
                    // Insert ise decrypt et
                    $numericId = intval(Security::decrypt($resultId));
                }

                // Araç güncel KM güncelle
                if ($bitis_km > 0) {
                    $Arac->updateKm($arac_id, $bitis_km);
                }

                echo json_encode(['status' => 'success', 'message' => 'KM güncellendi.', 'yapilan' => $saveData['yapilan_km'], 'id' => $numericId]);
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
                    $h = trim($h ?? '');
                    // Normalize for comparison: lower case and handle Turkish chars
                    $h = str_replace(['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'], ['i', 'i', 'g', 'u', 's', 'o', 'c'], $h);
                    return mb_strtolower($h, 'UTF-8');
                }, $rows[0]);

                // Sütun eşleştirme
                $columnMap = [
                    'external_id' => ['id', 'ıd'],
                    'plaka' => ['plaka', 'araç plakası', 'arac plakasi'],
                    'tarih' => ['tarih', 'yakıt tarihi', 'yakit tarihi'],
                    'km' => ['km', 'kilometre', 'güncel km', 'guncel km'],
                    'yakit_miktari' => ['litre', 'yakıt miktarı', 'yakit miktari', 'miktar'],
                    'birim_fiyat' => ['birim fiyat', 'litre fiyatı', 'litre fiyati', 'fiyat', 'birim fiyatı'],
                    'toplam_tutar' => ['tutar', 'toplam tutar', 'toplam', 'ödenen', 'net tutar'],
                    'brut_tutar' => ['brüt tutar', 'brut tutar'],
                    'istasyon' => ['istasyon', 'akaryakıt istasyonu', 'benzin istasyonu'],
                    'fatura_no' => ['fatura no', 'fatura numarası', 'fiş no', 'fatura numarası'],
                    'fatura_tarihi' => ['fatura tarihi'],
                    'cihaz_numarasi' => ['cihaz numarası', 'cihaz numarasi'],
                    'kart_numarasi' => ['kart numarası', 'kart numarasi'],
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

                // Numeric parsing function
                $cleanNum = function ($val) {
                    if ($val === null || $val === '')
                        return 0.0;
                    $val = str_replace(['TL', ' ', '%'], '', $val);
                    $val = str_replace(',', '', $val);
                    return (float) $val;
                };

                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    $rowNum = $i + 1;

                    $plaka = strtoupper(trim($row[$colIndices['plaka']] ?? ''));
                    if (empty($plaka))
                        continue;

                    // Araç bul veya ekle
                    $mevcutArac = $Arac->plakaKontrol($plaka);
                    if (!$mevcutArac) {
                        try {
                            $encryptedId = $Arac->saveWithAttr([
                                'firma_id' => $_SESSION['firma_id'],
                                'plaka' => $plaka,
                                'aktif_mi' => 1
                            ]);
                            $aracId = (int) Security::decrypt($encryptedId);
                        } catch (Exception $e) {
                            $errorDetails[] = "Satır $rowNum ($plaka): Araç eklenemedi - " . $e->getMessage();
                            continue;
                        }
                    } else {
                        $aracId = $mevcutArac->id;
                    }

                    try {
                        $newData = [
                            'firma_id' => $_SESSION['firma_id'],
                            'arac_id' => $aracId,
                            'olusturan_kullanici_id' => $_SESSION['user_id'] ?? null
                        ];

                        // Tarih
                        if (isset($colIndices['tarih'])) {
                            $tarihVal = $row[$colIndices['tarih']];
                            if (is_numeric($tarihVal) && $tarihVal > 30000) { // Excel format check
                                $tarihVal = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tarihVal)->format('Y-m-d');
                            } else {
                                $tarihVal = trim($tarihVal);
                                if (!empty($tarihVal)) {
                                    $timestamp = strtotime(str_replace(['.', '/'], '-', $tarihVal));
                                    $tarihVal = $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
                                } else {
                                    $tarihVal = date('Y-m-d');
                                }
                            }
                            $newData['tarih'] = $tarihVal;
                        } else {
                            $newData['tarih'] = date('Y-m-d');
                        }

                        // Sayısal alanlar
                        foreach (['km', 'yakit_miktari', 'birim_fiyat', 'toplam_tutar', 'brut_tutar'] as $field) {
                            if (isset($colIndices[$field])) {
                                $newData[$field] = $cleanNum($row[$colIndices[$field]]);
                            }
                        }

                        // Metinsel alanlar
                        foreach (['istasyon', 'fatura_no', 'notlar', 'cihaz_numarasi', 'kart_numarasi', 'external_id'] as $field) {
                            if (isset($colIndices[$field])) {
                                $newData[$field] = trim($row[$colIndices[$field]] ?? '');
                            }
                        }

                        // Fatura Tarihi
                        if (isset($colIndices['fatura_tarihi'])) {
                            $ft = $row[$colIndices['fatura_tarihi']];
                            if (is_numeric($ft) && $ft > 30000) {
                                $newData['fatura_tarihi'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($ft)->format('Y-m-d');
                            } else {
                                $ft = trim($ft);
                                if (!empty($ft)) {
                                    $timestamp = strtotime(str_replace(['.', '/'], '-', $ft));
                                    $newData['fatura_tarihi'] = $timestamp ? date('Y-m-d', $timestamp) : null;
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

                        // UPSERT mantığı
                        if (!empty($newData['external_id'])) {
                            $sql = "INSERT INTO arac_yakit_kayitlari (
                                        firma_id, arac_id, tarih, km, yakit_miktari, birim_fiyat, 
                                        toplam_tutar, istasyon, fatura_no, external_id, 
                                        cihaz_numarasi, kart_numarasi, brut_tutar, fatura_tarihi,
                                        yakit_tipi, olusturan_kullanici_id
                                    ) VALUES (
                                        :firma_id, :arac_id, :tarih, :km, :yakit_miktari, :birim_fiyat, 
                                        :toplam_tutar, :istasyon, :fatura_no, :external_id, 
                                        :cihaz_numarasi, :kart_numarasi, :brut_tutar, :fatura_tarihi,
                                        'dizel', :olusturan_kullanici_id
                                    ) ON DUPLICATE KEY UPDATE 
                                        arac_id = VALUES(arac_id),
                                        tarih = VALUES(tarih),
                                        km = VALUES(km),
                                        yakit_miktari = VALUES(yakit_miktari),
                                        birim_fiyat = VALUES(birim_fiyat),
                                        toplam_tutar = VALUES(toplam_tutar),
                                        istasyon = VALUES(istasyon),
                                        fatura_no = VALUES(fatura_no),
                                        cihaz_numarasi = VALUES(cihaz_numarasi),
                                        kart_numarasi = VALUES(kart_numarasi),
                                        brut_tutar = VALUES(brut_tutar),
                                        fatura_tarihi = VALUES(fatura_tarihi),
                                        guncelleme_tarihi = NOW()";

                            $stmt = $Yakit->getDb()->prepare($sql);
                            $stmt->execute([
                                ':firma_id' => $newData['firma_id'],
                                ':arac_id' => $newData['arac_id'],
                                ':tarih' => $newData['tarih'],
                                ':km' => $newData['km'] ?? 0,
                                ':yakit_miktari' => $newData['yakit_miktari'] ?? 0,
                                ':birim_fiyat' => $newData['birim_fiyat'] ?? 0,
                                ':toplam_tutar' => $newData['toplam_tutar'] ?? 0,
                                ':istasyon' => $newData['istasyon'] ?? null,
                                ':fatura_no' => $newData['fatura_no'] ?? null,
                                ':external_id' => $newData['external_id'],
                                ':cihaz_numarasi' => $newData['cihaz_numarasi'] ?? null,
                                ':kart_numarasi' => $newData['kart_numarasi'] ?? null,
                                ':brut_tutar' => $newData['brut_tutar'] ?? null,
                                ':fatura_tarihi' => $newData['fatura_tarihi'] ?? null,
                                ':olusturan_kullanici_id' => $newData['olusturan_kullanici_id']
                            ]);

                            if ($stmt->rowCount() == 1)
                                $addedCount++;
                            else
                                $updatedCount++;
                        } else {
                            $Yakit->saveWithAttr($newData);
                            $addedCount++;
                        }

                        // Araç KM güncelle
                        if (!empty($newData['km'])) {
                            $Arac->updateKm($aracId, $newData['km']);
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
                    'muayene_bitis_tarihi' => ['muayene bitiş', 'muayene bitis', 'muayene bitiş tarihi', 'muayene bitis tarihi', 'muayene tarihi', 'muayene'],
                    'sigorta_bitis_tarihi' => ['sigorta bitiş', 'sigorta bitis', 'sigorta bitiş tarihi', 'sigorta bitis tarihi', 'sigorta'],
                    'kasko_bitis_tarihi' => ['kasko bitiş', 'kasko bitis', 'kasko bitiş tarihi', 'kasko bitis tarihi', 'kasko'],
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
                                    $val = Date::convertExcelDate($val);
                                }

                                // Sayısal alanlar
                                if (in_array($dbCol, ['guncel_km', 'model_yili'])) {
                                    $val = preg_replace('/[^0-9]/', '', $val);
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
                                <th class="text-center sticky-col-1 fw-bold">SIRA</th>
                                <th class="sticky-col-2 fw-bold">PLAKA</th>
                                <th class="sticky-col-3 fw-bold">MARKA / MODEL</th>
                                <?php for ($i = 1; $i <= $gunSayisi; $i++):
                                    $isSunday = date('N', strtotime("$yil-$ay-$i")) == 7;
                                    ?>
                                    <th class="text-center <?= $isSunday ? 'weekend-header' : '' ?>" colspan="3"><?= $i ?></th>
                                <?php endfor; ?>
                                <th class="text-center bg-primary text-white">AYLIK TOPLAM</th>
                            </tr>
                            <tr>
                                <th class="sticky-col-1 bg-light p-1">
                                    <input type="text" class="form-control form-control-xs table-filter text-center" data-col="0"
                                        placeholder="SIF">
                                </th>
                                <th class="sticky-col-2 bg-light p-1">
                                    <input type="text" class="form-control form-control-xs table-filter" data-col="1"
                                        placeholder="PLAKA">
                                </th>
                                <th class="sticky-col-3 bg-light p-1">
                                    <input type="text" class="form-control form-control-xs table-filter" data-col="2" placeholder="ARA">
                                </th>
                                <?php for ($i = 1; $i <= $gunSayisi; $i++):
                                    $isSunday = date('N', strtotime("$yil-$ay-$i")) == 7;
                                    ?>
                                    <th class="text-center km-col km-start-col d-none <?= $isSunday ? 'weekend-header' : '' ?>"
                                        style="font-size: 9px;">Bas.</th>
                                    <th class="text-center km-col km-end-col d-none <?= $isSunday ? 'weekend-header' : '' ?>"
                                        style="font-size: 9px;">Bit.</th>
                                    <th class="text-center km-total-col <?= $isSunday ? 'weekend-header' : '' ?>" style="font-size: 9px;">KM
                                    </th>
                                <?php endfor; ?>
                                <th class="bg-primary bg-opacity-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($puantajData)): ?>
                                <tr>
                                    <td colspan="<?= ($gunSayisi * 3) + 4 ?>" class="text-center py-4 text-muted">Arama kriterlerine uygun
                                        araç kaydı bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php $sira = 1;
                                foreach ($puantajData as $arac_id => $row):
                                    $aylikToplam = 0;
                                    ?>
                                    <tr>
                                        <td class="text-center sticky-col-1 bg-white"><?= $sira++ ?></td>
                                        <td class="fw-bold sticky-col-2 text-primary cursor-pointer btn-arac-detay bg-white"
                                            data-id="<?= Security::encrypt($arac_id) ?>"><?= $row['info']['plaka'] ?></td>
                                        <td class="sticky-col-3 bg-white text-truncate" style="max-width: 15rem;">
                                            <?= ($row['info']['marka'] ?? '-') . ' ' . ($row['info']['model'] ?? '') ?>
                                        </td>
                                        <?php for ($i = 1; $i <= $gunSayisi; $i++):
                                            $gunData = $row['gunler'][$i] ?? null;
                                            $yapilan = $gunData ? (float) $gunData['yapilan'] : 0;
                                            $aylikToplam += $yapilan;
                                            $isSunday = date('N', strtotime("$yil-$ay-$i")) == 7;
                                            $cellClass = $isSunday ? 'weekend-cell' : '';
                                            ?>
                                            <td class="text-center km-col km-start-col d-none bg-light <?= $cellClass ?>"
                                                data-arac-id="<?= Security::encrypt($arac_id) ?>" data-day="<?= $i ?>" data-type="baslangic">
                                                <?= $gunData ? number_format($gunData['baslangic'], 0, ',', '.') : '-' ?>
                                            </td>
                                            <td class="text-center km-col km-end-col d-none bg-light <?= $cellClass ?>"
                                                data-arac-id="<?= Security::encrypt($arac_id) ?>" data-day="<?= $i ?>" data-type="bitis">
                                                <?= $gunData ? number_format($gunData['bitis'], 0, ',', '.') : '-' ?>
                                            </td>
                                            <td class="text-center km-total-col fw-bold <?= $yapilan > 0 ? 'text-dark' : 'text-muted opacity-50' ?> <?= $cellClass ?>"
                                                data-arac-id="<?= Security::encrypt($arac_id) ?>" data-day="<?= $i ?>" data-type="yapilan">
                                                <?= $yapilan > 0 ? number_format($yapilan, 0, ',', '.') : '-' ?>
                                            </td>
                                        <?php endfor; ?>
                                        <td class="text-center bg-light-blue fw-800 text-primary"
                                            data-arac-id="<?= Security::encrypt($arac_id) ?>" data-type="row-total">
                                            <?= number_format($aylikToplam, 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="sticky-footer">
                            <tr class="fw-bold">
                                <td class="sticky-col-1 bg-light"></td>
                                <td colspan="2" class="text-center sticky-footer-label-span bg-light border-end-2">GÜNLÜK TOPLAMLAR</td>
                                <?php
                                $genelAyToplam = 0;
                                for ($i = 1; $i <= $gunSayisi; $i++):
                                    $gunlukBaslangic = 0;
                                    $gunlukBitis = 0;
                                    $gunlukToplam = 0;
                                    if (!empty($puantajData)) {
                                        foreach ($puantajData as $row) {
                                            $gd = $row['gunler'][$i] ?? null;
                                            if ($gd) {
                                                $gunlukBaslangic += (float) ($gd['baslangic'] ?? 0);
                                                $gunlukBitis += (float) ($gd['bitis'] ?? 0);
                                                $gunlukToplam += (float) ($gd['yapilan'] ?? 0);
                                            }
                                        }
                                    }
                                    $genelAyToplam += $gunlukToplam;
                                    ?>
                                    <td class="text-center km-col km-start-col d-none" data-day="<?= $i ?>" data-type="col-baslangic">
                                        <?= $gunlukBaslangic > 0 ? number_format($gunlukBaslangic, 0, ',', '.') : '-' ?>
                                    </td>
                                    <td class="text-center km-col km-end-col d-none" data-day="<?= $i ?>" data-type="col-bitis">
                                        <?= $gunlukBitis > 0 ? number_format($gunlukBitis, 0, ',', '.') : '-' ?>
                                    </td>
                                    <td class="text-center km-total-col text-dark" data-day="<?= $i ?>" data-type="col-yapilan">
                                        <?= $gunlukToplam > 0 ? number_format($gunlukToplam, 0, ',', '.') : '-' ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="text-center bg-primary text-white" id="puantajGrandTotal">
                                    <?= number_format($genelAyToplam, 0, ',', '.') ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <style>
                    #puantajTable {
                        border-collapse: separate !important;
                        border-spacing: 0 !important;
                        width: 100% !important;
                        font-size: 11px;
                        border: 1px solid #dee2e6;
                    }

                    #puantajTable.table-bordered> :not(caption)>*>* {
                        border-width: 1px !important;
                    }

                    .table-responsive {
                        max-height: calc(100vh - 350px) !important;
                        overflow: auto !important;
                        border-radius: 8px;
                        box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
                    }

                    /* Sticky Header & Footer */
                    #puantajTable thead th {
                        background: #f1f3f7 !important;
                        z-index: 30;
                        vertical-align: middle;
                        text-align: center;
                        white-space: nowrap;
                        color: #495057;
                        border: 1px solid #dee2e6 !important;
                    }

                    .sticky-footer {
                        position: sticky !important;
                        bottom: 0 !important;
                        z-index: 40 !important;
                    }

                    .sticky-footer td {
                        background: #f8f9fa !important;
                        border-top: 2px solid #adb5bd !important;
                        border-bottom: 0 !important;
                        position: sticky !important;
                        bottom: 0 !important;
                        z-index: 41 !important;
                        height: 40px;
                        vertical-align: middle;
                    }

                    /* Sticky Columns */
                    .sticky-col-1,
                    .sticky-col-2,
                    .sticky-col-3 {
                        position: sticky !important;
                        z-index: 25 !important;
                        border-right: 1px solid #dee2e6 !important;
                    }

                    .sticky-col-1 {
                        left: 0;
                        min-width: 40px;
                        width: 40px;
                    }

                    .sticky-col-2 {
                        left: 40px;
                        min-width: 100px;
                        width: 100px;
                    }

                    .sticky-col-3 {
                        left: 140px;
                        min-width: 160px;
                        width: 160px;
                        border-right: 2px solid #adb5bd !important;
                    }

                    .sticky-footer-label-span {
                        position: sticky !important;
                        left: 40px !important;
                        z-index: 45 !important;
                        background: #f8f9fa !important;
                    }

                    .bg-light-blue {
                        background-color: #f0f7ff !important;
                    }

                    [data-bs-theme="dark"] .bg-light-blue {
                        background-color: #2b394e !important;
                    }

                    .sticky-footer .sticky-col-3 {
                        z-index: 45 !important;
                        bottom: 0 !important;
                    }

                    /* Filtrations */
                    .form-control-xs {
                        height: 24px;
                        padding: 2px 5px;
                        font-size: 10px;
                        border-radius: 4px;
                        border: 1px solid #ced4da;
                        background-color: #fff;
                    }

                    .form-control-xs:focus {
                        border-color: #0d6efd;
                        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
                    }

                    /* Weekend Highlighting */
                    .weekend-header {
                        background-color: #fff5f5 !important;
                        color: #dc3545 !important;
                    }

                    .weekend-cell {
                        background-color: #fffafa !important;
                    }

                    /* Helper Classes */
                    .fw-800 {
                        font-weight: 800;
                    }

                    .border-end-2 {
                        border-right: 2px solid #adb5bd !important;
                    }

                    [data-bs-theme="dark"] #puantajTable thead th {
                        background: #2e3548 !important;
                        color: #ced4da;
                    }

                    [data-bs-theme="dark"] .sticky-col-1,
                    [data-bs-theme="dark"] .sticky-col-2,
                    [data-bs-theme="dark"] .sticky-col-3,
                    [data-bs-theme="dark"] .bg-white {
                        background: #2a3042 !important;
                    }

                    [data-bs-theme="dark"] .bg-light {
                        background: #32394e !important;
                    }

                    [data-bs-theme="dark"] .sticky-footer td {
                        background: #2e3548 !important;
                        border-top-color: #495057 !important;
                    }

                    [data-bs-theme="dark"] .weekend-header {
                        background-color: #3d2b2b !important;
                    }

                    [data-bs-theme="dark"] .weekend-cell {
                        background-color: #352626 !important;
                    }
                </style>
                <?php
                $html = ob_get_clean();
                header('Content-Type: text/html');
                die($html);
                break;

            case 'get-arac-ozel-puantaj':
                $yil = intval($_GET['year'] ?? date('Y'));
                $ay = str_pad($_GET['month'] ?? date('m'), 2, '0', STR_PAD_LEFT);
                $arac_id = intval(Security::decrypt($_GET['id'] ?? '0'));

                if ($arac_id <= 0) {
                    throw new Exception("Geçersiz araç ID.");
                }

                $data = $Km->getSingleVehicleMonthlyPuantaj($yil, $ay, $arac_id);
                if (!$data) {
                    throw new Exception("Araç bilgisi bulunamadı.");
                }

                $monthName = Date::monthName($ay);

                ob_start();
                ?>
                <div id="printableArea">
                    <div class="alert alert-soft-info d-flex align-items-center mb-3 no-print" role="alert">
                        <i class="bx bxs-info-circle fs-4 me-2"></i>
                        <div>
                            <strong>İpucu:</strong> Tablodaki hücrelere <strong>tıklayarak</strong> Excel gibi veri girişi
                            yapabilirsiniz. Ok tuşları ve Enter ile hücreler arası geçiş yapabilirsiniz.
                        </div>
                    </div>
                    <div class="report-header">
                        <div class="report-title"><?= $yil ?>                 <?= $monthName ?> Ayı Araç Puantaj Cetveli</div>
                        <div class="report-info-grid">
                            <div class="info-item">
                                <span class="info-label">PLAKA:</span>
                                <span class="info-value"><?= htmlspecialchars($data['info']->plaka) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">ŞOFÖR:</span>
                                <span
                                    class="info-value"><?= htmlspecialchars($data['info']->sofor_adi ?? 'Zimmetli Personel Yok') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">ARAÇ:</span>
                                <span
                                    class="info-value"><?= htmlspecialchars(($data['info']->marka ?? '') . ' ' . ($data['info']->model ?? '')) ?></span>
                            </div>
                        </div>
                    </div>

                    <table class="report-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">TARİH</th>
                                <th>BAŞLANGIÇ KM</th>
                                <th>BİTİŞ KM</th>
                                <th>TOPLAM KM</th>
                                <th style="width: 100px;">İMZA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $genelToplam = 0;
                            // Başlangıç KM'sini belirle: İlk kayıttan önceki en son bitiş veya aracın baslangic_km'si
                            $lastBitis = (float) ($data['info']->baslangic_km ?? 0);

                            // Ay içindeki her gün için dön
                            for ($i = 1; $i <= $data['gunSayisi']; $i++) {
                                $gunData = $data['gunler'][$i] ?? null;

                                // Eğer o gün kayıt varsa, o günün başlangıcını kullan
                                if ($gunData && (float) $gunData['baslangic'] > 0) {
                                    $baslangicGoster = (float) $gunData['baslangic'];
                                } else {
                                    // Kayıt yoksa bir önceki günün bitişini göster
                                    $baslangicGoster = $lastBitis;
                                }

                                $bitisGoster = $gunData ? (float) $gunData['bitis'] : 0;
                                $yapilan = $gunData ? (float) $gunData['yapilan'] : 0;
                                $genelToplam += $yapilan;

                                // Eğer o gün için bitiş girilmişse, bir sonraki günün başlangıcı için sakla
                                if ($bitisGoster > 0) {
                                    $lastBitis = $bitisGoster;
                                }

                                $tarih = str_pad($i, 2, '0', STR_PAD_LEFT) . '.' . $ay . '.' . $yil;
                                ?>
                                <tr class="km-quick-row" data-date="<?= $tarih ?>" data-arac-id="<?= $arac_id ?>"
                                    data-arac-encrypt="<?= Security::encrypt($arac_id) ?>" data-day="<?= $i ?>"
                                    data-id="<?= $gunData['id'] ?? '' ?>">
                                    <td class="text-center fw-bold"><?= $tarih ?></td>
                                    <td class="text-center km-editable" data-type="baslangic" data-day="<?= $i ?>" contenteditable="true">
                                        <?= $baslangicGoster > 0 ? (int) $baslangicGoster : '' ?>
                                    </td>
                                    <td class="text-center km-editable" data-type="bitis" data-day="<?= $i ?>" contenteditable="true">
                                        <?= $bitisGoster > 0 ? (int) $bitisGoster : '' ?>
                                    </td>
                                    <td class="text-center fw-bold yapilan-km <?= $yapilan > 0 ? 'text-primary' : '' ?>"
                                        data-day="<?= $i ?>">
                                        <?= $yapilan > 0 ? number_format($yapilan, 0, ',', '.') : '-' ?>
                                    </td>
                                    <td></td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold bg-light">
                                <td colspan="3" class="text-end">GENEL TOPLAM:</td>
                                <td class="text-center text-primary" style="font-size: 14px;">
                                    <?= number_format($genelToplam, 0, ',', '.') ?> KM
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php $prefix = isset($_GET['full_print']) ? '' : 'views/arac-takip/'; ?>
                <div class="text-end mt-3 no-print">
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Kapat</button>
                    <a href="<?= $prefix ?>export-cetvel.php?id=<?= $_GET['id'] ?>&year=<?= $yil ?>&month=<?= $ay ?>"
                        class="btn btn-success me-2">
                        <i class="mdi mdi-file-excel me-1"></i> Excel'e Aktar
                    </a>
                    <button type="button" class="btn btn-primary"
                        onclick="window.open('<?= $prefix ?>api.php?action=get-arac-ozel-puantaj&id=<?= $_GET['id'] ?>&year=<?= $yil ?>&month=<?= $ay ?>&full_print=1', '_blank')">
                        <i class="mdi mdi-printer me-1"></i> Yazdır
                    </button>
                </div>
                <?php if (isset($_GET['full_print'])): ?>
                    <script>
                        window.onload = function () {
                            setTimeout(() => {
                                window.print();
                                window.onafterprint = function () { window.close(); }
                            }, 500);
                        }
                    </script>
                <?php endif; ?>
                <style>
                    /* Modal/Screen Styles */
                    .report-header {
                        margin-bottom: 20px;
                        border-bottom: 2px solid #333;
                        padding-bottom: 10px;
                    }

                    .report-title {
                        font-size: 20px;
                        font-weight: 800;
                        text-align: center;
                        margin-bottom: 15px;
                        text-transform: uppercase;
                        color: #1a1a1a;
                    }

                    .report-info-grid {
                        display: grid;
                        grid-template-columns: repeat(3, 1fr);
                        gap: 10px;
                    }

                    .info-item {
                        background: #f8f9fa;
                        border: 1px solid #dee2e6;
                        padding: 8px 12px;
                        border-radius: 4px;
                        display: flex;
                        flex-direction: column;
                    }

                    .info-label {
                        font-size: 10px;
                        font-weight: 700;
                        color: #6c757d;
                        margin-bottom: 2px;
                    }

                    .info-value {
                        font-size: 14px;
                        font-weight: 700;
                        color: #333;
                    }

                    .report-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                        border: 1px solid #333;
                    }

                    .report-table th {
                        background: #333;
                        color: #fff;
                        padding: 8px;
                        font-size: 12px;
                        text-align: center;
                        border: 1px solid #333;
                    }

                    .km-editable {
                        cursor: cell;
                        transition: background-color 0.2s;
                    }

                    .km-editable:hover {
                        background-color: #fff8e1 !important;
                    }

                    .km-editable:focus {
                        background-color: #fff !important;
                        outline: 2px solid #556ee6 !important;
                        outline-offset: -2px;
                        z-index: 10;
                        box-shadow: 0 0 10px rgba(85, 110, 230, 0.3) !important;
                    }

                    .km-editable:empty::before {
                        content: '-';
                        color: #ccc;
                    }

                    .bg-soft-success {
                        background-color: #d4edda !important;
                    }

                    .bg-soft-warning {
                        background-color: #fff3cd !important;
                    }

                    .bg-soft-danger {
                        background-color: #f8d7da !important;
                    }

                    .report-table td {
                        padding: 5px 8px;
                        border: 1px solid #333;
                        font-size: 12px;
                        height: 24px;
                    }

                    .report-table .text-primary {
                        color: #0062cc !important;
                    }

                    @media print {
                        @page {
                            size: A4 portrait;
                            margin: 10mm !important;
                        }

                        body {
                            background: #fff !important;
                        }

                        .container,
                        .container-fluid {
                            width: 100% !important;
                            max-width: none !important;
                            padding: 0 !important;
                            margin: 0 !important;
                        }

                        .report-header {
                            border-bottom: 3px solid #000 !important;
                        }

                        .info-item {
                            border: 2px solid #000 !important;
                            background: #fff !important;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }

                        .report-table {
                            border: 2px solid #000 !important;
                        }

                        .report-table th,
                        .report-table td {
                            border: 1px solid #000 !important;
                        }

                        .no-print {
                            display: none !important;
                        }
                    }
                </style>
                <?php
                $html = ob_get_clean();
                if (isset($_GET['full_print'])) {
                    header('Content-Type: text/html; charset=UTF-8');
                    ?>
                    <!DOCTYPE html>
                    <html lang="tr">

                    <head>
                        <meta charset="UTF-8">
                        <title><?= $yil ?>                     <?= $monthName ?> Araç Puantaj Cetveli</title>
                        <link href="../../assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
                        <style>
                            body {
                                background: #fff !important;
                            }

                            @media print {
                                body {
                                    background: #fff !important;
                                }

                                .no-print {
                                    display: none !important;
                                }
                            }
                        </style>
                    </head>

                    <body class="bg-white">
                        <div class="container py-4">
                            <?= $html ?>
                        </div>
                    </body>

                    </html>
                    <?php
                    exit;
                }
                header('Content-Type: text/html; charset=UTF-8');
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