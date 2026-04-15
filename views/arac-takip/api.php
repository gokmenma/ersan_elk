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
use App\Model\AracKmBildirimModel;
use App\Model\SystemLogModel;

use App\Helper\Security;
use App\Helper\Date;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST' || (isset($_GET['action']) && in_array($_GET['action'], ['get-arac-puantaj-table', 'get-arac-ozel-puantaj', 'arac-performans', 'arac-excel-aktar', 'get-arac-analiz', 'arac-karsilastirma', 'get-km-onay-yapmayanlar']))) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    $Arac = new AracModel();
    $Zimmet = new AracZimmetModel();
    $Yakit = new AracYakitModel();
    $Km = new AracKmModel();
    $Servis = new AracServisModel();
    $KmBildirim = new AracKmBildirimModel();

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

                $mevcutArac = $Arac->plakaKontrol($plaka, $arac_id, true);
                if ($mevcutArac && $arac_id == 0) {
                    throw new Exception("Bu plaka ($plaka) zaten kayıtlı baska bir araca ait.");
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
                $aciklama = trim($_POST['aciklama'] ?? '');
                if ($id <= 0) {
                    throw new Exception("Geçersiz araç ID.");
                }

                if (empty($aciklama)) {
                    throw new Exception("Silme işlemi için açıklama girmek zorunludur.");
                }

                // Silmeden önce araç bilgisini al
                $silinecekArac = $Arac->getById($id);
                if (!$silinecekArac) {
                    throw new Exception("Araç bulunamadı.");
                }

                $pdo = $Arac->getDb();
                $kullaniciId = $_SESSION['user_id'] ?? null;

                try {
                    $pdo->beginTransaction();

                    // 1. silen_kullanici sütunu olan tablolar
                    $tablesWithUser = [
                        'arac_zimmetleri',
                        'arac_yakit_kayitlari',
                        'arac_servis_kayitlari',
                        'arac_sigorta_kayitlari'
                    ];

                    foreach ($tablesWithUser as $table) {
                        $stmt = $pdo->prepare("UPDATE {$table} SET silinme_tarihi = NOW(), silen_kullanici = ? WHERE arac_id = ? AND silinme_tarihi IS NULL");
                        $stmt->execute([$kullaniciId, $id]);
                    }

                    // 2. Sadece silinme_tarihi sütunu olan tablolar
                    $tablesWithoutUser = [
                        'arac_km_kayitlari',
                        'arac_bakim_kayitlari'
                    ];

                    foreach ($tablesWithoutUser as $table) {
                        $stmt = $pdo->prepare("UPDATE {$table} SET silinme_tarihi = NOW() WHERE arac_id = ? AND silinme_tarihi IS NULL");
                        $stmt->execute([$id]);
                    }

                    // 3. En son ana tabloyu sil (araclar tablosunda silen_kullanici yok)
                    $stmtArac = $pdo->prepare("UPDATE araclar SET silinme_tarihi = NOW() WHERE id = ? AND silinme_tarihi IS NULL");
                    $stmtArac->execute([$id]);

                    $pdo->commit();

                    // Logla
                    $SystemLog = new SystemLogModel();
                    $userId = $_SESSION['user_id'] ?? 0;
                    $plaka = $silinecekArac->plaka ?? 'Bilinmiyor';
                    $SystemLog->logAction($userId, 'Araç Silme', "{$plaka} plakalı araç tüm verileriyle beraber silindi.\nAçıklama: {$aciklama}", SystemLogModel::LEVEL_IMPORTANT);

                    echo json_encode(['status' => 'success', 'message' => 'Araç başarıyla silindi.']);

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw new Exception("Araç silinirken bir hata oluştu: " . $e->getMessage());
                }
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

            case 'arac-excel-aktar':
                require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
                
                $filter = $_GET['filter'] ?? null;

                if ($filter === 'muayene') {
                    $araclar = $Arac->getMuayeneBitenler();
                } elseif ($filter === 'muayene_yaklasan') {
                    $araclar = $Arac->getMuayeneYaklasanlar(30);
                } elseif ($filter === 'sigorta') {
                    $araclar = $Arac->getSigortaBitenler();
                } elseif ($filter === 'sigorta_yaklasan') {
                    $araclar = $Arac->getSigortaYaklasanlar(30);
                } elseif ($filter === 'kasko') {
                    $araclar = $Arac->getKaskoBitenler();
                } elseif ($filter === 'kasko_yaklasan') {
                    $araclar = $Arac->getKaskoYaklasanlar(30);
                } elseif ($filter === 'zimmetli') {
                    $araclar = $Arac->getZimmetliAraclar();
                } elseif ($filter === 'bosta') {
                    $araclar = $Arac->getBostaAraclar();
                } elseif ($filter === 'serviste') {
                    $araclar = $Arac->getServistekiAraclar();
                } elseif ($filter === 'ikame') {
                    $araclar = $Arac->getIkameAraclar();
                } else {
                    $araclar = $Arac->all();
                }

                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Araç Listesi');

                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '556EE6']],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
                ];

                $headers = [
                    'A' => 'SIRA', 'B' => 'TİP', 'C' => 'PLAKA', 'D' => 'MARKA', 'E' => 'MODEL', 
                    'F' => 'DEPARTMAN', 'G' => 'MÜLKİYET', 'H' => 'ZİMMETLİ PERSONEL', 'I' => 'DURUM', 
                    'J' => 'YAKIT', 'K' => 'GÜNCEL KM', 'L' => 'MUAYENE BİTİŞ', 'M' => 'SİGORTA BİTİŞ', 'N' => 'KASKO BİTİŞ'
                ];

                foreach ($headers as $col => $title) {
                    $sheet->setCellValue($col . '1', $title);
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                
                $sheet->getStyle('A1:N1')->applyFromArray($headerStyle);
                $sheet->getRowDimension(1)->setRowHeight(25);

                $row = 2;
                $i = 1;
                
                $tipLabels = [
                    'binek' => 'Binek', 'kamyonet' => 'Kamyonet', 'kamyon' => 'Kamyon',
                    'minibus' => 'Minibüs', 'otobus' => 'Otobüs', 'motosiklet' => 'Motosiklet',
                    'diger' => 'Diğer', 'ikame' => 'İkame'
                ];

                $yakitLabels = [
                    'benzin' => 'Benzin', 'dizel' => 'Dizel', 'lpg' => 'LPG',
                    'elektrik' => 'Elektrik', 'hibrit' => 'Hibrit'
                ];

                foreach ($araclar as $arac) {
                    $sheet->setCellValue('A' . $row, $i++);
                    $sheet->setCellValue('B' . $row, $tipLabels[$arac->arac_tipi] ?? '-');
                    $sheet->setCellValue('C' . $row, $arac->plaka);
                    $sheet->setCellValue('D' . $row, $arac->marka ?? '-');
                    $sheet->setCellValue('E' . $row, $arac->model ?? '-');
                    $sheet->setCellValue('F' . $row, $arac->departmani ?: '-');
                    $sheet->setCellValue('G' . $row, $arac->mulkiyet ?: '-');
                    $sheet->setCellValue('H' . $row, $arac->zimmetli_personel_adi ?: 'Boşta');
                    
                    if ($arac->serviste_mi) {
                        $durum = 'Serviste';
                    } else {
                        $durum = $arac->aktif_mi ? 'Aktif' : 'Pasif';
                    }
                    $sheet->setCellValue('I' . $row, $durum);
                    
                    $sheet->setCellValue('J' . $row, $yakitLabels[$arac->yakit_tipi] ?? '-');
                    $sheet->setCellValue('K' . $row, $arac->guncel_km ?? 0);
                    $sheet->getStyle('K' . $row)->getNumberFormat()->setFormatCode('#,##0');
                    
                    $sheet->setCellValue('L' . $row, $arac->muayene_bitis_tarihi ? date('d.m.Y', strtotime($arac->muayene_bitis_tarihi)) : '-');
                    $sheet->setCellValue('M' . $row, $arac->sigorta_bitis_tarihi ? date('d.m.Y', strtotime($arac->sigorta_bitis_tarihi)) : '-');
                    $sheet->setCellValue('N' . $row, $arac->kasko_bitis_tarihi ? date('d.m.Y', strtotime($arac->kasko_bitis_tarihi)) : '-');
                    
                    $row++;
                }

                $sheet->getStyle('A2:N' . ($row - 1))->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                    'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER]
                ]);

                $filename = 'Arac_Listesi_' . date('Ymd_His') . '.xlsx';
                
                // Clear any previous output that might corrupt the Excel file
                if (ob_get_length()) ob_clean();
                
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="' . $filename . '"');
                header('Cache-Control: max-age=0');
                
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
                exit;

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
                $iade_tarihi = $_POST['iade_tarihi'] ?? date('Y-m-d');
                $notlar = $_POST['notlar'] ?? null;

                if ($zimmet_id <= 0) {
                    throw new Exception("Geçersiz zimmet ID.");
                }

                $iade_tarihi = Date::Ymd($iade_tarihi);

                // Zimmet iade işlemini yap
                $Zimmet->iadeEt($zimmet_id, $iade_km, $notlar, $iade_tarihi);

                // Araç KM güncelle ve Mülkiyet Kontrolü
                $zimmetBilgi = $Zimmet->find($zimmet_id);
                if ($zimmetBilgi) {
                    if ($iade_km > 0) {
                        $Arac->updateKm($zimmetBilgi->arac_id, $iade_km);
                    }

                    // Eğer mülkiyet durumu 'Personel Aracı' ise aracı pasif yap
                    $aracData = $Arac->find($zimmetBilgi->arac_id);
                    if ($aracData && $aracData->mulkiyet === 'Personel Aracı') {
                        $Arac->saveWithAttr([
                            'id' => $aracData->id,
                            'aktif_mi' => 0
                        ]);
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Araç iadesi başarıyla tamamlandı.']);
                break;

            case 'zimmet-listesi':
                $zimmetler = $Zimmet->all();
                echo json_encode(['status' => 'success', 'data' => $zimmetler]);
                break;

            case 'zimmet-gecmisi':
                $arac_id = intval($_POST['arac_id'] ?? 0);
                if ($arac_id <= 0) {
                    throw new Exception("Araç ID geçersiz.");
                }
                $gecmis = $Zimmet->getByArac($arac_id);
                // Tarihi formatlayalim
                foreach($gecmis as $g) {
                    $g->zimmet_tarihi_fmt = Date::dmY($g->zimmet_tarihi);
                    $g->iade_tarihi_fmt = $g->iade_tarihi ? Date::dmY($g->iade_tarihi) : '-';
                }
                echo json_encode(['status' => 'success', 'data' => $gecmis]);
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

                // Hesaplama mantığı
                $miktar = floatval($data['yakit_miktari'] ?? 0);
                $birim = floatval($data['birim_fiyat'] ?? 0);
                $iskontoOran = floatval($data['iskonto'] ?? 0); // Yüzde olarak kabul ediyoruz
                
                if ($miktar > 0 && $birim > 0) {
                    $brut = $miktar * $birim;
                    $net = $brut * (1 - ($iskontoOran / 100));
                    
                    if (empty($data['brut_tutar'])) $data['brut_tutar'] = round($brut, 2);
                    if (empty($data['toplam_tutar'])) $data['toplam_tutar'] = round($net, 2);
                } elseif (!empty($data['toplam_tutar']) && $miktar > 0 && empty($data['birim_fiyat'])) {
                    // Sadece Net ve Miktar varsa Birim Fiyat (Brüt üzerinden) hesapla
                    $toplam = floatval($data['toplam_tutar']);
                    $netBirim = $toplam / $miktar;
                    $data['birim_fiyat'] = round($netBirim / (1 - ($iskontoOran / 100)), 2);
                    $data['brut_tutar'] = round($data['birim_fiyat'] * $miktar, 2);
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
                $aciklama = trim($_POST['aciklama'] ?? '');
                if ($id <= 0) {
                    throw new Exception("Geçersiz yakıt kaydı ID.");
                }

                if (empty($aciklama)) {
                    throw new Exception("Silme işlemi için açıklama girmek zorunludur.");
                }

                // Silmeden önce yakıt kaydı bilgisini al
                $silinecekYakit = $Yakit->find($id);
                $Yakit->softDelete($id);

                // Logla
                $SystemLog = new SystemLogModel();
                $userId = $_SESSION['user_id'] ?? 0;
                $yakitTarih = $silinecekYakit->tarih ?? '';
                $SystemLog->logAction($userId, 'Yakıt Kaydı Silme', "ID: {$id}, Tarih: {$yakitTarih} yakıt kaydı silindi.\nAçıklama: {$aciklama}", SystemLogModel::LEVEL_IMPORTANT);

                echo json_encode(['status' => 'success', 'message' => 'Yakıt kaydı silindi.']);
                break;

            case 'yakit-listesi':

                $arac_id = isset($_POST['arac_id']) && $_POST['arac_id'] !== '' ? intval($_POST['arac_id']) : null;
                $departman = isset($_POST['departman']) && $_POST['departman'] !== '' ? $_POST['departman'] : null;
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

                    $kayitlar = $Yakit->getByDateRange($baslangic, $bitis, $arac_id, $departman);
                    $stats = $Yakit->getStats(null, null, $baslangic, $bitis, $arac_id, $departman);
                } elseif ($arac_id) {
                    $kayitlar = $Yakit->getByArac($arac_id, null, $departman);
                    $stats = $Yakit->getStats(null, null, null, null, $arac_id, $departman);
                } elseif ($departman) {
                    $kayitlar = $Yakit->all($departman);
                    $stats = $Yakit->getStats(date('Y'), date('m'), null, null, null, $departman); // Varsayılan aylık
                } else {
                    $kayitlar = $Yakit->all();
                    $stats = $Yakit->getStats(date('Y'), date('m')); // Varsayılan aylık
                }

                echo json_encode(['status' => 'success', 'data' => $kayitlar, 'stats' => $stats]);
                break;

            case 'yakit-detay':
                $id = intval($_POST['id'] ?? 0);
                $kayit = $Yakit->find($id);
                /**tarihi çevir */
                $kayit->tarih = Date::dmY($kayit->tarih);

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
                // Eğer yeni ekliyorsak (km_id <= 0), silinmişleri de kontrol etmeliyiz ki DB unique constraint hatası vermesin
                if ($km_id <= 0) {
                    $mevcutKayit = $Km->kayitVarMi($arac_id, $tarih, null, true);
                    if ($mevcutKayit) {
                        // Eğer kayıt varsa ve silinmişse, bu kaydı tekrar canlandıracağız (re-use ID)
                        if ($mevcutKayit->silinme_tarihi !== null) {
                            $km_id = $mevcutKayit->id;
                            $data['id'] = $km_id;
                            $data['silinme_tarihi'] = null; // Silinme tarihini temizle
                        } else {
                            // Eğer silinmemiş bir kayıt varsa hata ver
                            throw new Exception("Bu araç için bu tarihte zaten KM kaydı bulunmaktadır.");
                        }
                    }
                } else {
                    // Düzenleme modundaysak, sadece başka bir kayıtta aynı tarih var mı kontrol et (silinmişleri görmeyebiliriz)
                    $mevcutKayit = $Km->kayitVarMi($arac_id, $tarih, $km_id);
                    if ($mevcutKayit) {
                        throw new Exception("Bu araç için bu tarihte zaten KM kaydı bulunmaktadır.");
                    }
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
                $id = intval(Security::decrypt($_POST['id'] ?? ''));
                $aciklama = trim($_POST['aciklama'] ?? '');
                if ($id <= 0) {
                    throw new Exception("Geçersiz KM kaydı ID.");
                }

                if (empty($aciklama)) {
                    throw new Exception("Silme işlemi için açıklama girmek zorunludur.");
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

                // Logla
                $SystemLog = new SystemLogModel();
                $userId = $_SESSION['user_id'] ?? 0;
                $SystemLog->logAction($userId, 'KM Kaydı Silme', "Araç ID: {$silinecekAracId}, Tarih: {$silinecekTarih}, Bitiş KM: {$silinecekBitisKm} KM kaydı silindi.\nAçıklama: {$aciklama}", SystemLogModel::LEVEL_IMPORTANT);

                break;


            case 'km-listesi':
                $arac_id = isset($_POST['arac_id']) && $_POST['arac_id'] !== '' ? intval($_POST['arac_id']) : null;
                $departman = isset($_POST['departman']) && $_POST['departman'] !== '' ? $_POST['departman'] : null;
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

                    $kayitlar = $Km->getByDateRange($baslangic, $bitis, $arac_id, $departman);
                    $stats = $Km->getStats(null, null, $baslangic, $bitis, $arac_id, $departman);
                } elseif ($arac_id) {
                    $kayitlar = $Km->getByArac($arac_id, null, $departman);
                    $stats = $Km->getStats(null, null, null, null, $arac_id, $departman);
                } elseif ($departman) {
                    $kayitlar = $Km->all($departman);
                    $stats = $Km->getStats(date('Y'), date('m'), null, null, null, $departman); // Varsayılan aylık
                } else {
                    $kayitlar = $Km->all();
                    $stats = $Km->getStats(date('Y'), date('m')); // Varsayılan aylık
                }

                echo json_encode(['status' => 'success', 'data' => $kayitlar, 'stats' => $stats]);
                break;

            case 'km-detay':
                $id = intval($_POST['id'] ?? 0);
                $kayit = $Km->find($id);
                /**tarihi çevir */
                $kayit->tarih = Date::dmY($kayit->tarih);

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

                $inputFileName = $_FILES['excel_file']['tmp_name'];
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
                $excelSheet = $spreadsheet->getActiveSheet();
                $rows = $excelSheet->toArray();

                if (count($rows) < 2) {
                    throw new Exception("Excel dosyası boş veya sadece başlık satırı içeriyor.");
                }

                $success = 0;
                $skip = 0;
                $errors = [];
                $unmatchedPlates = [];

                // Header mapping logic
                $headers = array_map(function ($h) {
                    $h = trim($h ?? '');
                    $h = str_replace(['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'], ['i', 'i', 'g', 'u', 's', 'o', 'c'], $h);
                    return mb_strtolower($h, 'UTF-8');
                }, $rows[0]);

                $columnMap = [
                    'plaka' => ['plaka', 'araç plakası', 'arac plakasi', 'araç', 'plakası', 'plaka no', 'arac'],
                    'tarih' => ['tarih', 'gün', 'km tarihi', 'tarih (gg.aa.yyyy)', 'tarihi'],
                    'baslangic_km' => ['başlangıç km', 'baslangic km', 'ilk km', 'başlangıç', 'baslangic', 'ilk'],
                    'bitis_km' => ['bitiş km', 'bitis km', 'son km', 'bitiş', 'bitis', 'son', 'bitis km.'],
                    'notlar' => ['not', 'notlar', 'açıklama', 'notları', 'notu']
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

                // Gerekli kolonlar var mı kontrol et
                $requiredCols = ['plaka', 'tarih', 'bitis_km'];
                $missingCols = [];
                foreach ($requiredCols as $req) {
                    if (!isset($colIndices[$req])) $missingCols[] = $columnMap[$req][0];
                }

                if (!empty($missingCols)) {
                    throw new Exception("Excel dosyasında gerekli sütunlar bulunamadı: " . implode(', ', $missingCols) . ". Lütfen başlık isimlerini kontrol edin (Plaka, Tarih, Bitiş KM).");
                }

                // Fallback for notlar if missing
                if (!isset($colIndices['notlar'])) $colIndices['notlar'] = -1;
                if (!isset($colIndices['baslangic_km'])) $colIndices['baslangic_km'] = -1;

                $normalize = function($p) {
                    $p = mb_strtoupper((string) $p, 'UTF-8');
                    $p = str_replace(
                        ['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'],
                        ['I', 'I', 'G', 'U', 'S', 'O', 'C'],
                        $p
                    );
                    $p = preg_replace('/[^A-Z0-9]/', '', $p);
                    return ltrim($p, '0');
                };

                // Tüm araçları plaka => id map'i oluştur
                $pdo = $Arac->getDb();
                $stmtAraclar = $pdo->prepare(
                    "SELECT id, plaka FROM araclar WHERE firma_id = :firma_id AND aktif_mi = 1 AND silinme_tarihi IS NULL"
                );
                $stmtAraclar->execute(['firma_id' => $_SESSION['firma_id']]);
                $aracMap = [];
                foreach ($stmtAraclar->fetchAll(\PDO::FETCH_OBJ) as $a) {
                    $normalized = $normalize($a->plaka);
                    $aracMap[$normalized] = $a->id;
                }

                $firma_id = $_SESSION['firma_id'] ?? 0;
                if (!$firma_id) {
                    throw new Exception("Oturum süresi dolmuş veya firma bilgisi bulunamadı. Lütfen tekrar giriş yapın.");
                }

                $rowNum = 0;
                $debugDate = "";
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    $rowNum = $i + 1;

                    $plaka = trim($row[$colIndices['plaka']] ?? '');
                    $tarihRaw = trim($row[$colIndices['tarih']] ?? '');
                    $bitisKmRaw = trim($row[$colIndices['bitis_km']] ?? '');

                    if (empty($plaka) && empty($bitisKmRaw)) {
                        continue;
                    }
                    if (empty($plaka)) {
                        $errors[] = "Satır $rowNum: Plaka eksik.";
                        continue;
                    }

                    // KM değerini temizle ve parse et (Ondalık varsa yuvarla)
                    $cleanNum = function($val) {
                        if (empty($val)) return 0;
                        // Binlik ayırıcıları temizle (Türkçe formatında . binliktir)
                        $val = str_replace([' ', '.'], '', $val);
                        // Ondalık ayırıcıyı nokta yap (Türkçe formatında , ondalıktır)
                        $val = str_replace(',', '.', $val);
                        return (int) round(floatval($val));
                    };

                    $eVal = $cleanNum($bitisKmRaw);
                    if (empty($bitisKmRaw) || $eVal <= 0) {
                        $skip++;
                        continue;
                    }

                    $plakaNorm = $normalize($plaka);
                    if (!isset($aracMap[$plakaNorm])) {
                        if (!in_array($plaka, $unmatchedPlates)) {
                            $unmatchedPlates[] = $plaka;
                        }
                        continue;
                    }
                    $arac_id_id_yukle = $aracMap[$plakaNorm];

                    // Tarihi dönüştür (Kullanıcının isteği üzerine Date::Ymd kullanıldı)
                    $tarih = Date::Ymd($tarihRaw);
                    if (!$tarih) {
                        $errors[] = "Satır $rowNum ($plaka): Geçersiz tarih '$tarihRaw'.";
                        continue;
                    }

                    // İlk satırın tarihini debug için sakla
                    if ($debugDate === "") $debugDate = "Excel: $tarihRaw -> Sistem: $tarih";

                    // Başlangıç KM
                    $bVal = 0;
                    if ($colIndices['baslangic_km'] !== -1) {
                        $bVal = $cleanNum($row[$colIndices['baslangic_km']] ?? '');
                    }

                    // Eğer başlangıç KM yoksa sistemden önceki kaydı bulmaya çalış
                    if ($bVal <= 0) {
                        $onceki = $Km->getOncekiKayit($arac_id_id_yukle, $tarih);
                        if ($onceki) {
                            $bVal = (int) $onceki->bitis_km;
                        }
                    }

                    if ($bVal > 0 && $eVal < $bVal) {
                        $errors[] = "Satır $rowNum ($plaka, $tarih): Bitiş KM ({$eVal}) başlangıçtan ({$bVal}) küçük.";
                        continue;
                    }

                    $mevcutKayit = $Km->kayitVarMi($arac_id_id_yukle, $tarih, null, true);
                    $saveData = [
                        'firma_id' => $firma_id,
                        'arac_id' => $arac_id_id_yukle,
                        'tarih' => Date::Ymd($tarih),
                        'baslangic_km' => $bVal,
                        'bitis_km' => $eVal,
                        'yapilan_km' => ($eVal > 0 && $bVal > 0) ? ($eVal - $bVal) : 0,
                        'olusturan_kullanici_id' => $_SESSION['user_id'] ?? null,
                        'silinme_tarihi' => null
                    ];
                    
                    if ($colIndices['notlar'] !== -1 && !empty($row[$colIndices['notlar']])) {
                        $saveData['aciklama'] = trim($row[$colIndices['notlar']]);
                    }

                    if ($mevcutKayit) {
                        $saveData['id'] = $mevcutKayit->id;
                    }
                    $Km->saveWithAttr($saveData);
                    if ($eVal > 0) {
                        $Arac->updateKm($arac_id_id_yukle, $eVal);
                    }
                    $success++;
                }

                if ($success === 0 && empty($errors) && empty($unmatchedPlates)) {
                    $message = "Yüklenecek veri bulunamadı.";
                    if ($skip > 0) $message .= " ($skip satır 'Bitiş KM' eksik olduğu için atlandı)";
                    echo json_encode(['status' => 'error', 'message' => $message]);
                    exit;
                }

                // KM Excel yükleme logla
                $SystemLog = new SystemLogModel();
                $userId = $_SESSION['user_id'] ?? 0;
                $SystemLog->logAction($userId, 'KM Excel Yükleme', "Excel'den {$success} adet KM kaydı yüklendi, {$skip} atlandı. Debug: $debugDate", SystemLogModel::LEVEL_IMPORTANT);
                
                echo json_encode([
                    'status' => 'success', 
                    'success' => $success, 
                    'skip' => $skip, 
                    'errors' => $errors, 
                    'unmatchedPlates' => $unmatchedPlates,
                    'debugDate' => $debugDate
                ]);
                exit;
                if ($success === 0) {
                    $message = "Yüklenecek veri bulunamadı.";
                    if ($skip > 0) $message .= " ($skip satır atlandı. Lütfen Bitiş KM sütununu kontrol edin)";
                }

                echo json_encode([
                    'status' => 'success',
                    'success' => $success,
                    'skip' => $skip,
                    'errors' => $errors,
                    'unmatchedPlates' => $unmatchedPlates,
                    'message' => $message
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

                if (!$baslangic)
                    $baslangic = date('Y-m-01');
                if (!$bitis)
                    $bitis = date('Y-m-t');

                $yakitOzet = $Yakit->getRangeOzet($baslangic, $bitis, $arac_id);
                $kmOzet = $Km->getRangeOzet($baslangic, $bitis, $arac_id);
                $genelYakitStats = $Yakit->getStats(null, null, $baslangic, $bitis, $arac_id);
                $genelKmStats = $Km->getStats(null, null, $baslangic, $bitis, $arac_id);

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
                    'external_id' => ['id', 'ıd', 'satış id', 'satis id', 'satıs ıd', 'islem numarasi', 'işlem numarası', 'fis no', 'fiş no'],
                    'plaka' => ['plaka', 'araç plakası', 'arac plakasi'],
                    'tarih' => ['tarih', 'yakıt tarihi', 'yakit tarihi'],
                    'km' => ['km', 'kilometre', 'güncel km', 'guncel km'],
                    'yakit_miktari' => ['litre', 'yakıt miktarı', 'yakit miktari', 'miktar'],
                    'birim_fiyat' => ['birim fiyat', 'litre fiyatı', 'litre fiyati', 'fiyat', 'birim fiyatı'],
                    'toplam_tutar' => ['tutar', 'toplam tutar', 'toplam', 'ödenen', 'net tutar'],
                    'brut_tutar' => ['brüt tutar', 'brut tutar'],
                    'iskonto' => ['iskonto', 'indirim'],
                    'iskonto_tipi' => ['iskonto tipi', 'indirim tipi'],
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
                $addedDetails = [];
                $updatedDetails = [];

                $cleanNum = function ($val) {
                    if ($val === null || $val === '')
                        return 0.0;
                    $val = (string)$val;
                    $val = str_replace(['TL', ' ', '%', '₺', 'L'], '', $val);
                    
                    // Detect thousand separator and decimal separator
                    if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
                        if (strrpos($val, ',') > strrpos($val, '.')) {
                            // Turkish format: 1.234,56
                            $val = str_replace('.', '', $val);
                            $val = str_replace(',', '.', $val);
                        } else {
                            // US format: 1,234.56
                            $val = str_replace(',', '', $val);
                        }
                    } elseif (strpos($val, ',') !== false) {
                        // Check if comma is decimal (common in TR: 5,50) or thousand (common in US if no decimals: 1,000)
                        if (strlen(substr($val, strpos($val, ',') + 1)) == 3) {
                            // Likely thousand separator: 1,000
                            $val = str_replace(',', '', $val);
                        } else {
                            // Likely decimal separator: 5,50
                            $val = str_replace(',', '.', $val);
                        }
                    }
                    
                    return (float) $val;
                };

                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    $rowNum = $i + 1;

                    $plaka = strtoupper(trim($row[$colIndices['plaka']] ?? ''));
                    if (empty($plaka))
                        continue;

                    // Araç bul veya ekle (Silinenler dahil kontrol et)
                    $mevcutArac = $Arac->plakaKontrol($plaka, null, true);
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
                        // Eğer araç silinmişse geri getir
                        $pdo = $Arac->getDb();
                        $stmtRestore = $pdo->prepare("UPDATE araclar SET silinme_tarihi = NULL, aktif_mi = 1 WHERE id = ?");
                        $stmtRestore->execute([$aracId]);
                    }

                    try {
                        $newData = [
                            'firma_id' => $_SESSION['firma_id'],
                            'arac_id' => $aracId,
                            'olusturan_kullanici_id' => $_SESSION['user_id'] ?? null
                        ];

                        // Tarih
                        if (isset($colIndices['tarih'])) {
                            $newData['tarih'] = Date::convertExcelDate($row[$colIndices['tarih']]) ?? date('Y-m-d');
                        } else {
                            $newData['tarih'] = date('Y-m-d');
                        }

                        // Sayısal alanlar
                        foreach (['km', 'yakit_miktari', 'birim_fiyat', 'toplam_tutar', 'brut_tutar', 'iskonto'] as $field) {
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
                            $newData['fatura_tarihi'] = Date::convertExcelDate($row[$colIndices['fatura_tarihi']]);
                        }

                        // Hesaplamaları yap
                        $quantity = floatval($newData['yakit_miktari'] ?? 0);
                        $price = floatval($newData['birim_fiyat'] ?? 0);
                        $discountPct = floatval($newData['iskonto'] ?? 0);
                        
                        // Birim fiyat yoksa tutar/miktar üzerinden bul
                        if ($price <= 0 && $quantity > 0 && !empty($newData['toplam_tutar'])) {
                            $price = floatval($newData['toplam_tutar']) / $quantity;
                            $newData['birim_fiyat'] = round($price, 2);
                        }

                        // Brüt ve Net hesapla
                        if ($quantity > 0 && $price > 0) {
                            $brut = $quantity * $price;
                            if (empty($newData['brut_tutar'])) $newData['brut_tutar'] = round($brut, 2);
                            
                            if (empty($newData['toplam_tutar'])) {
                                $net = $brut * (1 - ($discountPct / 100));
                                $newData['toplam_tutar'] = round($net, 2);
                            }
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
                                        iskonto, yakit_tipi, olusturan_kullanici_id
                                    ) VALUES (
                                        :firma_id, :arac_id, :tarih, :km, :yakit_miktari, :birim_fiyat, 
                                        :toplam_tutar, :istasyon, :fatura_no, :external_id, 
                                        :cihaz_numarasi, :kart_numarasi, :brut_tutar, :fatura_tarihi,
                                        :iskonto, 'dizel', :olusturan_kullanici_id
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
                                        iskonto = VALUES(iskonto),
                                        silinme_tarihi = NULL,
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
                                ':iskonto' => $newData['iskonto'] ?? 0,
                                ':olusturan_kullanici_id' => $newData['olusturan_kullanici_id']
                            ]);

                            if ($stmt->rowCount() == 1) {
                                $addedCount++;
                                $addedDetails[] = "Satır $rowNum (Plaka: $plaka, Fiş: " . ($newData['external_id'] ?? '-') . ")";
                            } else {
                                $updatedCount++;
                                $updatedDetails[] = "Satır $rowNum (Plaka: $plaka, Fiş: " . ($newData['external_id'] ?? '-') . ")";
                            }
                        } else {
                            $checkSql = "SELECT id FROM arac_yakit_kayitlari 
                                          WHERE arac_id = ? AND tarih = ? AND silinme_tarihi IS NULL";
                            $checkParams = [$aracId, $newData['tarih']];

                            // Eğer istasyon veya tutar varsa, onları da kontrole dahil et ki 
                            // aynı gün farklı alımlar ezilmesin (eğer Satış ID yoksa)
                            if (!empty($newData['istasyon'])) {
                                $checkSql .= " AND istasyon = ?";
                                $checkParams[] = $newData['istasyon'];
                            }
                            if (!empty($newData['toplam_tutar'])) {
                                $checkSql .= " AND toplam_tutar = ?";
                                $checkParams[] = $newData['toplam_tutar'];
                            }

                            $checkSql .= " LIMIT 1";
                            $mevcutKayit = $Yakit->getDb()->prepare($checkSql);
                            $mevcutKayit->execute($checkParams);
                            $var_mi = $mevcutKayit->fetch(\PDO::FETCH_OBJ);

                            if ($var_mi) {
                                $newData['id'] = $var_mi->id;
                                $Yakit->saveWithAttr($newData);
                                $updatedCount++;
                                $updatedDetails[] = "Güncellendi: Satır $rowNum (Plaka: $plaka, Tarih: " . $newData['tarih'] . ")";
                            } else {
                                $Yakit->saveWithAttr($newData);
                                $addedCount++;
                                $addedDetails[] = "Eklendi: Satır $rowNum (Plaka: $plaka, Tarih: " . $newData['tarih'] . ")";
                            }
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

                // Yakıt Excel yükleme logla
                $SystemLog = new SystemLogModel();
                $userId = $_SESSION['user_id'] ?? 0;
                $SystemLog->logAction($userId, 'Yakıt Excel Yükleme', "Excel'den {$addedCount} adet yakıt kaydı eklendi, {$updatedCount} adet güncellendi.", SystemLogModel::LEVEL_IMPORTANT);

                echo json_encode([
                    'status' => 'success',
                    'message' => $responseMessage,
                    'errors' => $errorDetails,
                    'addedDetails' => $addedDetails,
                    'updatedDetails' => $updatedDetails
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
                        // Mevcut aracı kontrol et (silinenler dahil)
                        $mevcutArac = $Arac->plakaKontrol($plaka, null, true);

                        $newData = [
                            'firma_id' => $_SESSION['firma_id'],
                            'plaka' => $plaka,
                            'aktif_mi' => 1,
                            'silinme_tarihi' => null // Geri getir veya sıfırla
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

                // Araç Excel yükleme logla
                $SystemLog = new SystemLogModel();
                $userId = $_SESSION['user_id'] ?? 0;
                $SystemLog->logAction($userId, 'Araç Excel Yükleme', "Excel'den {$addedCount} adet araç eklendi, {$updatedCount} adet güncellendi.", SystemLogModel::LEVEL_IMPORTANT);

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
                if (!empty($data['servis_tarihi'])) {
                    $data['servis_tarihi'] = Date::dttoeng($data['servis_tarihi']);
                } else {
                    $data['servis_tarihi'] = date('Y-m-d'); // Default to today if empty
                }
                
                if (!empty($data['iade_tarihi'])) {
                    $data['iade_tarihi'] = Date::dttoeng($data['iade_tarihi']);
                } else {
                    $data['iade_tarihi'] = null;
                }

                if (!empty($data['ikame_alis_tarihi'])) {
                    $data['ikame_alis_tarihi'] = Date::dttoeng($data['ikame_alis_tarihi']);
                }
                if (!empty($data['ikame_iade_tarihi'])) {
                    $data['ikame_iade_tarihi'] = Date::dttoeng($data['ikame_iade_tarihi']);
                }
                $data['olusturan_kullanici_id'] = $_SESSION['user_id'] ?? null;

                // İkame araç bilgileri - servis tarafından verilen araç
                $ikame_arac_id = !empty($data['ikame_arac_id']) ? intval($data['ikame_arac_id']) : null;
                $ikame_plaka = trim($data['ikame_plaka'] ?? '');

                // İkame plaka girildiyse yeni araç kaydı oluştur veya mevcut ikame aracı bul
                if (!empty($ikame_plaka) && !$ikame_arac_id) {
                    // Aynı plaka ile daha önce ikame araç kaydedilmiş mi kontrol et
                    $mevcutIkame = $Arac->getDb()->prepare("SELECT id FROM araclar WHERE plaka = :plaka AND firma_id = :firma_id AND silinme_tarihi IS NULL LIMIT 1");
                    $mevcutIkame->execute(['plaka' => $ikame_plaka, 'firma_id' => $_SESSION['firma_id']]);
                    $mevcutIkameRow = $mevcutIkame->fetch(PDO::FETCH_OBJ);

                    if ($mevcutIkameRow) {
                        $ikame_arac_id = $mevcutIkameRow->id;
                        // Mevcut kaydı güncelle - aracı tekrar aktif hale getir (aktif_mi = 1)
                        $updateStmt = $Arac->getDb()->prepare("UPDATE araclar SET ikame_mi = 1, aktif_mi = 1, arac_tipi = 'ikame', mulkiyet = 'İkame Araç', marka = :marka, model = :model WHERE id = :id");
                        $updateStmt->execute([
                            'marka' => $data['ikame_marka'] ?? null,
                            'model' => $data['ikame_model'] ?? null,
                            'id' => $ikame_arac_id
                        ]);
                    } else {
                        // Yeni ikame araç kaydı oluştur
                        $ikameAracData = [
                            'firma_id' => $_SESSION['firma_id'],
                            'plaka' => $ikame_plaka,
                            'marka' => $data['ikame_marka'] ?? null,
                            'model' => $data['ikame_model'] ?? null,
                            'arac_tipi' => 'ikame',
                            'mulkiyet' => 'İkame Araç',
                            'aktif_mi' => 1,
                            'ikame_mi' => 1,
                            'baslangic_km' => $data['ikame_teslim_km'] ?? 0,
                            'guncel_km' => $data['ikame_teslim_km'] ?? 0
                        ];
                        $encryptedId = $Arac->saveWithAttr($ikameAracData);
                        $ikame_arac_id = Security::decrypt($encryptedId);
                    }
                }
                $data['ikame_arac_id'] = $ikame_arac_id;

                // Boş değerleri null yap
                foreach ($data as $key => $value) {
                    if ($value === '') {
                        $data[$key] = null;
                    }
                }

                // Servis çıkışı yapılıyorsa (iade_tarihi set) ve ikame araç varsa → otomatik iade
                $isServisCikisi = !empty($data['iade_tarihi']);
                $oncekiServis = $servis_id > 0 ? $Servis->find($servis_id) : null;
                $oncekiIadeTarihi = $oncekiServis ? $oncekiServis->iade_tarihi : null;
                $yeniServisCikisi = $isServisCikisi && empty($oncekiIadeTarihi); // İlk kez çıkış yapılıyorsa

                if ($isServisCikisi && !empty($data['ikame_plaka']) && empty($data['ikame_iade_km'])) {
                    throw new Exception("İkame araç kullanıldıysa, iade KM bilgisi zorunludur.");
                }

                $savedEncryptedId = $Servis->saveWithAttr($data);
                $target_servis_id = $servis_id > 0 ? $servis_id : \App\Helper\Security::decrypt($savedEncryptedId);

                // Araç KM güncelle (eğer çıkış KM girildiyse)
                if (!empty($data['cikis_km'])) {
                    $Arac->updateKm($arac_id, $data['cikis_km']);
                } elseif (!empty($data['giris_km'])) {
                    $Arac->updateKm($arac_id, $data['giris_km']);
                }

                // === İKAME ARAÇ OTOMATİK ZİMMET / İADE ===
                $ikameMessages = [];
                
                if ($servis_id == 0 && $ikame_arac_id) {
                    // YENİ SERVİS KAYDI: Araç personelin zimmetindeyse, ikame aracı personele zimmetle
                    $aktifZimmet = $Zimmet->getAktifZimmetByArac($arac_id);
                    if ($aktifZimmet) {
                        $personel_id = $aktifZimmet->personel_id;
                        
                        // İkame aracın boşta olduğunu kontrol et
                        $ikameZimmet = $Zimmet->getAktifZimmetByArac($ikame_arac_id);
                        if (!$ikameZimmet) {
                            // İkame aracı personele zimmetle
                            $zimmetData = [
                                'firma_id' => $_SESSION['firma_id'],
                                'arac_id' => $ikame_arac_id,
                                'personel_id' => $personel_id,
                                'zimmet_tarihi' => $data['servis_tarihi'],
                                'teslim_km' => $data['ikame_teslim_km'] ?? null,
                                'notlar' => 'İkame araç olarak otomatik zimmetlendi (Servis: ' . ($data['ikame_plaka'] ?? '') . ' → ' . ($aktifZimmet->personel_adi ?? '') . ')',
                                'durum' => 'aktif',
                                'olusturan_kullanici_id' => $_SESSION['user_id'] ?? null
                            ];
                            $Zimmet->saveWithAttr($zimmetData);
                            $ikameMessages[] = 'İkame araç personele zimmetlendi.';
                        }
                    }
                }

                if ($yeniServisCikisi && $ikame_arac_id) {
                    // SERVİS ÇIKIŞI: İkame aracı iade et ve asıl aracı tekrar personele zimmetle
                    
                    // 1. İkame aracın aktif zimmetini iade et
                    $ikameAktifZimmet = $Zimmet->getAktifZimmetByArac($ikame_arac_id);
                    if ($ikameAktifZimmet) {
                        $personel_id_iade = $ikameAktifZimmet->personel_id;
                        $Zimmet->iadeEt($ikameAktifZimmet->id, $data['ikame_iade_km'] ?? null, 'Servis çıkışı sonrası ikame araç otomatik iade edildi.');
                        $ikameMessages[] = 'İkame araç iade edildi.';

                        // 2. İkame araç KM güncelle
                        if (!empty($data['ikame_iade_km'])) {
                            $Arac->updateKm($ikame_arac_id, $data['ikame_iade_km']);
                        }

                        // 3. Asıl aracı personele geri zimmetle (eğer personel başka araca zimmetli değilse)
                        $personelMevcutZimmet = $Zimmet->getAktifZimmetByPersonel($personel_id_iade);
                        if (!$personelMevcutZimmet) {
                            $geriZimmetData = [
                                'firma_id' => $_SESSION['firma_id'],
                                'arac_id' => $arac_id,
                                'personel_id' => $personel_id_iade,
                                'zimmet_tarihi' => $data['iade_tarihi'],
                                'teslim_km' => $data['cikis_km'] ?? null,
                                'notlar' => 'Servis çıkışı sonrası asıl araç geri zimmetlendi.',
                                'durum' => 'aktif',
                                'olusturan_kullanici_id' => $_SESSION['user_id'] ?? null
                            ];
                            $Zimmet->saveWithAttr($geriZimmetData);
                            $ikameMessages[] = 'Asıl araç personele geri zimmetlendi.';
                        }
                    }
                    
                    // İkame iade tarihini güncelle ve aracı pasife çek
                    if ($target_servis_id > 0) {
                        $pdo = $Arac->getDb();
                        $ikame_iade_tarihi_val = !empty($data['ikame_iade_tarihi']) ? $data['ikame_iade_tarihi'] : date('Y-m-d');
                        $stmt = $pdo->prepare("UPDATE arac_servis_kayitlari SET ikame_iade_tarihi = ? WHERE id = ? AND firma_id = ?");
                        $stmt->execute([$ikame_iade_tarihi_val, $target_servis_id, $_SESSION['firma_id']]);

                        // İkame aracı pasife çek (Artık listede görünmemeli)
                        $stmtPasif = $pdo->prepare("UPDATE araclar SET aktif_mi = 0 WHERE id = ? AND firma_id = ?");
                        $stmtPasif->execute([$ikame_arac_id, $_SESSION['firma_id']]);
                    }
                }

                $message = $servis_id > 0 ? "Servis kaydı güncellendi." : "Servis kaydı eklendi.";
                if (!empty($ikameMessages)) {
                    $message .= ' ' . implode(' ', $ikameMessages);
                }
                echo json_encode(['status' => 'success', 'message' => $message]);
                break;

            case 'servis-sil':
                $id = intval($_POST['id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');
                if ($id <= 0) {
                    throw new Exception("Geçersiz servis kaydı ID.");
                }

                if (empty($aciklama)) {
                    throw new Exception("Silme işlemi için açıklama girmek zorunludur.");
                }

                // Silmeden önce servis bilgisini al
                $silinecekServis = $Servis->find($id);
                $Servis->softDelete($id);

                // Logla
                $SystemLog = new SystemLogModel();
                $userId = $_SESSION['user_id'] ?? 0;
                $servisTarih = $silinecekServis->tarih ?? '';
                $SystemLog->logAction($userId, 'Servis Kaydı Silme', "ID: {$id}, Tarih: {$servisTarih} servis kaydı silindi.\nAçıklama: {$aciklama}", SystemLogModel::LEVEL_IMPORTANT);

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
                if ($kayit->ikame_alis_tarihi)
                    $kayit->ikame_alis_tarihi = Date::dmY($kayit->ikame_alis_tarihi);
                if ($kayit->ikame_iade_tarihi)
                    $kayit->ikame_iade_tarihi = Date::dmY($kayit->ikame_iade_tarihi);

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
                                            if ($yapilan < 0) $yapilan = 0; // Bitiş KM yoksa negatif gelebilir, 0 sayalım
                                            $aylikToplam += $yapilan;
                                            $isSunday = date('N', strtotime("$yil-$ay-$i")) == 7;
                                            $cellClass = $isSunday ? 'weekend-cell' : '';

                                            $tooltip = '';
                                            if ($gunData && (!empty($gunData['created_at']) || !empty($gunData['giren_kullanici']))) {
                                                $tarihStr = !empty($gunData['created_at']) ? date('d.m.Y H:i', strtotime($gunData['created_at'])) : '-';
                                                $kisiStr = !empty($gunData['giren_kullanici']) ? htmlspecialchars($gunData['giren_kullanici'], ENT_QUOTES, 'UTF-8') : '-';
                                                $tooltip = 'data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="top" title="<div class=\'text-center\'><span class=\'fw-bold\'>' . $kisiStr . '</span><br><small class=\'opacity-75\'>' . $tarihStr . '</small></div>"';
                                            }
                                            ?>
                                            <td class="text-center km-col km-start-col d-none bg-light <?= $cellClass ?> km-ctx-target"
                                                data-arac-id="<?= Security::encrypt($arac_id) ?>" 
                                                data-km-id="<?= $gunData && isset($gunData['id']) ? Security::encrypt($gunData['id']) : '' ?>"
                                                data-day="<?= $i ?>" data-type="baslangic"
                                                <?= $tooltip ?>>
                                                <?= $gunData ? number_format($gunData['baslangic'], 0, ',', '.') : '-' ?>
                                            </td>
                                            <td class="text-center km-col km-end-col d-none bg-light <?= $cellClass ?> km-ctx-target"
                                                data-arac-id="<?= Security::encrypt($arac_id) ?>" 
                                                data-km-id="<?= $gunData && isset($gunData['id']) ? Security::encrypt($gunData['id']) : '' ?>"
                                                data-day="<?= $i ?>" data-type="bitis" <?= $tooltip ?>>
                                                <?= $gunData ? number_format($gunData['bitis'], 0, ',', '.') : '-' ?>
                                            </td>
                                            <td class="text-center km-total-col fw-bold <?= $yapilan > 0 ? 'text-dark' : 'text-muted opacity-50' ?> <?= $cellClass ?> km-ctx-target"
                                                data-arac-id="<?= Security::encrypt($arac_id) ?>" 
                                                data-km-id="<?= $gunData && isset($gunData['id']) ? Security::encrypt($gunData['id']) : '' ?>"
                                                data-day="<?= $i ?>" data-type="yapilan"
                                                <?= $tooltip ?>>
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
                                                $yapVal = (float) ($gd['yapilan'] ?? 0);
                                                $gunlukBaslangic += (float) ($gd['baslangic'] ?? 0);
                                                $gunlukBitis += (float) ($gd['bitis'] ?? 0);
                                                $gunlukToplam += ($yapVal > 0 ? $yapVal : 0);
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
                                <td class="text-center text-primary fw-bold fs-6" id="puantajGrandTotal">
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

                                $tooltip = '';
                                if ($gunData && (!empty($gunData['created_at']) || !empty($gunData['giren_kullanici']))) {
                                    $tarihStr = !empty($gunData['created_at']) ? date('d.m.Y H:i', strtotime($gunData['created_at'])) : '-';
                                    $kisiStr = !empty($gunData['giren_kullanici']) ? htmlspecialchars($gunData['giren_kullanici'], ENT_QUOTES, 'UTF-8') : '-';
                                    $tooltip = 'data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="top" title="<div class=\'text-center\'><span class=\'fw-bold\'>' . $kisiStr . '</span><br><small class=\'opacity-75\'>' . $tarihStr . '</small></div>"';
                                }
                                ?>
                                <tr class="km-quick-row" data-date="<?= $tarih ?>" data-arac-id="<?= $arac_id ?>"
                                    data-arac-encrypt="<?= Security::encrypt($arac_id) ?>" data-day="<?= $i ?>"
                                    data-id="<?= $gunData['id'] ?? '' ?>" <?= $tooltip ?>>
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

            // =============================================
            // ARAÇ PERFORMANS RAPORU
            // =============================================
            case 'arac-performans':
                $baslangic = $_GET['baslangic'] ?? null;
                $bitis = $_GET['bitis'] ?? null;

                if (!$baslangic)
                    $baslangic = date('Y-m-01');
                if (!$bitis)
                    $bitis = date('Y-m-t');

                // Yakıt özeti (araç bazlı)
                $yakitOzet = $Yakit->getRangeOzet($baslangic, $bitis);
                // KM özeti (araç bazlı)
                $kmOzet = $Km->getRangeOzet($baslangic, $bitis);
                // Servis kayıtları
                $servisKayitlar = $Servis->getByDateRange($baslangic, $bitis);

                // Aktif zimmetleri (sürücüleri) al
                $aktifZimmetler = $Zimmet->getAktifZimmetler();
                $surucuMap = [];
                foreach ($aktifZimmetler as $z) {
                    $surucuMap[$z->arac_id] = $z->personel_adi;
                }

                // Servis verilerini araç bazlı grupla
                $servisMap = [];
                foreach ($servisKayitlar as $sk) {
                    $aid = $sk->arac_id;
                    if (!isset($servisMap[$aid])) {
                        $servisMap[$aid] = ['sayi' => 0, 'maliyet' => 0, 'plaka' => $sk->plaka, 'marka' => $sk->marka, 'model' => $sk->model];
                    }
                    $servisMap[$aid]['sayi']++;
                    $servisMap[$aid]['maliyet'] += floatval($sk->tutar ?? 0);
                }

                // Araçları birleştir
                $aracPerformans = [];
                $aracIdSet = [];

                // Yakıt verilerinden
                foreach ($yakitOzet as $y) {
                    $aracIdSet[$y->arac_id] = true;
                    if (!isset($aracPerformans[$y->arac_id])) {
                        $aracPerformans[$y->arac_id] = [
                            'arac_id' => $y->arac_id,
                            'plaka' => $y->plaka,
                            'marka' => $y->marka,
                            'model' => $y->model,
                            'toplam_litre' => 0,
                            'yakit_maliyet' => 0,
                            'toplam_km' => 0,
                            'servis_sayisi' => 0,
                            'servis_maliyet' => 0,
                            'surucu' => $surucuMap[$y->arac_id] ?? null
                        ];
                    }
                    $aracPerformans[$y->arac_id]['toplam_litre'] = floatval($y->toplam_litre);
                    $aracPerformans[$y->arac_id]['yakit_maliyet'] = floatval($y->toplam_tutar);
                }

                // KM verilerinden
                foreach ($kmOzet as $k) {
                    $aracIdSet[$k->arac_id] = true;
                    if (!isset($aracPerformans[$k->arac_id])) {
                        $aracPerformans[$k->arac_id] = [
                            'arac_id' => $k->arac_id,
                            'plaka' => $k->plaka,
                            'marka' => $k->marka,
                            'model' => $k->model,
                            'toplam_litre' => 0,
                            'yakit_maliyet' => 0,
                            'toplam_km' => 0,
                            'servis_sayisi' => 0,
                            'servis_maliyet' => 0,
                            'surucu' => $surucuMap[$k->arac_id] ?? null
                        ];
                    }
                    $aracPerformans[$k->arac_id]['toplam_km'] = floatval($k->toplam_km);
                }

                // Servis verilerinden
                foreach ($servisMap as $aid => $s) {
                    if (!isset($aracPerformans[$aid])) {
                        $aracPerformans[$aid] = [
                            'arac_id' => $aid,
                            'plaka' => $s['plaka'],
                            'marka' => $s['marka'],
                            'model' => $s['model'],
                            'toplam_litre' => 0,
                            'yakit_maliyet' => 0,
                            'toplam_km' => 0,
                            'servis_sayisi' => 0,
                            'servis_maliyet' => 0,
                            'surucu' => $surucuMap[$aid] ?? null
                        ];
                    }
                    $aracPerformans[$aid]['servis_sayisi'] = $s['sayi'];
                    $aracPerformans[$aid]['servis_maliyet'] = $s['maliyet'];
                }

                $aracList = array_values($aracPerformans);

                // Aylık trend verileri (yakıt bazlı)
                $trendSql = "SELECT 
                        DATE_FORMAT(y.tarih, '%Y-%m') as ay,
                        COALESCE(SUM(y.yakit_miktari), 0) as toplam_litre,
                        COALESCE(SUM(y.toplam_tutar), 0) as toplam_tutar
                    FROM arac_yakit_kayitlari y
                    WHERE y.firma_id = :firma_id
                    AND y.silinme_tarihi IS NULL
                    AND y.tarih BETWEEN :baslangic AND :bitis
                    GROUP BY DATE_FORMAT(y.tarih, '%Y-%m')
                    ORDER BY ay ASC";
                $trendStmt = $Yakit->getDb()->prepare($trendSql);
                $trendStmt->execute([
                    'firma_id' => $_SESSION['firma_id'],
                    'baslangic' => $baslangic,
                    'bitis' => $bitis
                ]);
                $aylikTrend = $trendStmt->fetchAll(PDO::FETCH_OBJ);

                // KM aylık trend
                $kmTrendSql = "SELECT 
                        DATE_FORMAT(k.tarih, '%Y-%m') as ay,
                        COALESCE(SUM(k.yapilan_km), 0) as toplam_km
                    FROM arac_km_kayitlari k
                    WHERE k.firma_id = :firma_id
                    AND k.silinme_tarihi IS NULL
                    AND k.tarih BETWEEN :baslangic AND :bitis
                    GROUP BY DATE_FORMAT(k.tarih, '%Y-%m')
                    ORDER BY ay ASC";
                $kmTrendStmt = $Km->getDb()->prepare($kmTrendSql);
                $kmTrendStmt->execute([
                    'firma_id' => $_SESSION['firma_id'],
                    'baslangic' => $baslangic,
                    'bitis' => $bitis
                ]);
                $kmAylikTrend = $kmTrendStmt->fetchAll(PDO::FETCH_OBJ);

                // Özet bilgiler
                $toplamLitre = array_sum(array_column($aracList, 'toplam_litre'));
                $toplamYakitMaliyet = array_sum(array_column($aracList, 'yakit_maliyet'));
                $toplamKm = array_sum(array_column($aracList, 'toplam_km'));
                $toplamServisMaliyet = array_sum(array_column($aracList, 'servis_maliyet'));
                $toplamServisSayisi = array_sum(array_column($aracList, 'servis_sayisi'));
                $aracSayisi = count($aracList);

                // En çok/az sıralamaları
                $enCokYakit = null;
                $enAzYakit = null;
                $enCokKm = null;
                $enAzKm = null;
                $enCokServis = null;

                $yakitArr = array_filter($aracList, fn($a) => $a['toplam_litre'] > 0);
                if (!empty($yakitArr)) {
                    usort($yakitArr, fn($a, $b) => $b['toplam_litre'] <=> $a['toplam_litre']);
                    $enCokYakit = $yakitArr[0];
                    $enAzYakit = end($yakitArr);
                }

                $kmArr = array_filter($aracList, fn($a) => $a['toplam_km'] > 0);
                if (!empty($kmArr)) {
                    usort($kmArr, fn($a, $b) => $b['toplam_km'] <=> $a['toplam_km']);
                    $enCokKm = $kmArr[0];
                    $enAzKm = end($kmArr);
                }

                $servisArr = array_filter($aracList, fn($a) => $a['servis_sayisi'] > 0);
                if (!empty($servisArr)) {
                    usort($servisArr, fn($a, $b) => $b['servis_sayisi'] <=> $a['servis_sayisi']);
                    $enCokServis = $servisArr[0];
                }

                echo json_encode([
                    'status' => 'success',
                    'araclar' => $aracList,
                    'yakit_trend' => $aylikTrend,
                    'km_trend' => $kmAylikTrend,
                    'summary' => [
                        'toplam_litre' => $toplamLitre,
                        'toplam_yakit_maliyet' => $toplamYakitMaliyet,
                        'toplam_km' => $toplamKm,
                        'toplam_servis_maliyet' => $toplamServisMaliyet,
                        'toplam_servis_sayisi' => $toplamServisSayisi,
                        'arac_sayisi' => $aracSayisi,
                        'toplam_maliyet' => $toplamYakitMaliyet + $toplamServisMaliyet,
                        'en_cok_yakit' => $enCokYakit,
                        'en_az_yakit' => $enAzYakit,
                        'en_cok_km' => $enCokKm,
                        'en_az_km' => $enAzKm,
                        'en_cok_servis' => $enCokServis
                    ],
                    'baslangic' => $baslangic,
                    'bitis' => $bitis
                ]);
                break;

            case 'get-arac-analiz':
                $arac_id = intval($_GET['arac_id'] ?? 0);
                $baslangic = $_GET['baslangic'] ?? date('Y-m-01');
                $bitis = $_GET['bitis'] ?? date('Y-m-t');

                if ($arac_id <= 0) throw new Exception("Araç seçimi zorunludur.");

                // Seçilen dönem verileri
                $yakitData = $Yakit->getByDateRange($baslangic, $bitis, $arac_id);
                $kmData = $Km->getByDateRange($baslangic, $bitis, $arac_id);
                $servisData = $Servis->getByDateRange($baslangic, $bitis, $arac_id);

                // Bir önceki dönem (Kıyaslama için)
                $diff = (strtotime($bitis) - strtotime($baslangic)) + 86400;
                $prev_baslangic = date('Y-m-d', strtotime($baslangic) - $diff);
                $prev_bitis = date('Y-m-d', strtotime($baslangic) - 86400);

                $prevYakit = $Yakit->getStats(null, null, $prev_baslangic, $prev_bitis, $arac_id);
                $prevKm = $Km->getStats(null, null, $prev_baslangic, $prev_bitis, $arac_id);

                $currentYakitVal = array_sum(array_column($yakitData, 'yakit_miktari'));
                $currentKmVal = array_sum(array_column($kmData, 'yapilan_km'));

                echo json_encode([
                    'status' => 'success',
                    'current' => [
                        'yakit' => floatval($currentYakitVal),
                        'km' => floatval($currentKmVal),
                        'yakit_maliyet' => floatval(array_sum(array_column($yakitData, 'toplam_tutar'))),
                        'servis_maliyet' => floatval(array_sum(array_column($servisData, 'tutar'))),
                        'servis_sayisi' => count($servisData)
                    ],
                    'prev' => [
                        'yakit' => floatval($prevYakit ? $prevYakit->toplam_litre : 0),
                        'km' => floatval($prevKm ? $prevKm->toplam_km : 0)
                    ],
                    'charts' => [
                        'yakit' => $yakitData,
                        'km' => $kmData
                    ]
                ]);
                break;


            case 'arac-karsilastirma':
                $donemler = $_POST['donemler'] ?? []; // ['2026/01', '2026/02']
                $arac_id = isset($_POST['arac_id']) ? intval($_POST['arac_id']) : 0;
                $baslangic = $_POST['baslangic'] ?? null;
                $bitis = $_POST['bitis'] ?? null;

                $periods = [];
                if (!empty($donemler) && is_array($donemler)) {
                    foreach ($donemler as $d) {
                        $p = str_replace(['/', '-'], '', (string)$d);
                        if (strlen($p) == 6) {
                            $periods[] = $p;
                        }
                    }
                } elseif ($baslangic && $bitis) {
                    $start = new DateTime(substr($baslangic, 0, 4) . '-' . substr($baslangic, 4, 2) . '-01');
                    $end = new DateTime(substr($bitis, 0, 4) . '-' . substr($bitis, 4, 2) . '-01');
                    
                    while ($start <= $end) {
                        $periods[] = $start->format('Ym');
                        $start->modify('+1 month');
                    }
                }

                if (empty($periods)) {
                    for ($i = 3; $i >= 1; $i--) {
                        $periods[] = date('Ym', strtotime("-$i months"));
                    }
                }

                sort($periods);

                if ($arac_id > 0) {
                    $arac_data = $Arac->getById($arac_id);
                    $aracListesi = $arac_data ? [$arac_data] : [];
                } else {
                    $aracListesi = $Arac->all();
                }

                if (!$aracListesi) {
                    $aracListesi = [];
                }
                
                $karsilastirmaData = [];
                foreach ($aracListesi as $arac) {
                    $karsilastirmaData[$arac->id] = [
                        'id' => $arac->id,
                        'plaka' => $arac->plaka,
                        'marka' => $arac->marka,
                        'model' => $arac->model,
                        'departman' => $arac->departmani ?? '',
                        'donemler' => []
                    ];
                }

                $summary = [
                    'toplam_arac' => count($aracListesi),
                    'toplam_donem' => count($periods),
                    'donemler' => []
                ];

                foreach ($periods as $p) {
                    $y = (int)substr($p, 0, 4);
                    $m = (int)substr($p, 4, 2);
                    
                    $yakitOzet = $Yakit->getAylikOzet($y, $m);
                    $kmOzet = $Km->getAylikOzet($y, $m);
                    
                    $yakitMap = [];
                    if ($yakitOzet) {
                        foreach ($yakitOzet as $y_row) { $yakitMap[$y_row->arac_id] = $y_row; }
                    }
                    
                    $kmMap = [];
                    if ($kmOzet) {
                        foreach ($kmOzet as $k_row) { $kmMap[$k_row->arac_id] = $k_row; }
                    }
                    
                    $donemLitre = 0;
                    $donemTutar = 0;
                    $donemKm = 0;

                    foreach ($karsilastirmaData as $aid => &$adata) {
                        $y_data = $yakitMap[$aid] ?? null;
                        $k_data = $kmMap[$aid] ?? null;
                        
                        $litre = $y_data ? floatval($y_data->toplam_litre) : 0;
                        $tutar = $y_data ? floatval($y_data->toplam_tutar) : 0;
                        $km = $k_data ? floatval($k_data->toplam_km) : 0;
                        
                        $adata['donemler'][$p] = [
                            'litre' => $litre,
                            'tutar' => $tutar,
                            'km' => $km,
                            'ortalama' => ($km > 0) ? round(($litre / $km) * 100, 2) : 0
                        ];

                        $donemLitre += $litre;
                        $donemTutar += $tutar;
                        $donemKm += $km;
                    }

                    $summary['donemler'][$p] = [
                        'donem' => $y . '/' . str_pad($m, 2, '0', STR_PAD_LEFT),
                        'toplam_litre' => $donemLitre,
                        'toplam_tutar' => $donemTutar,
                        'toplam_km' => $donemKm,
                        'ortalama' => ($donemKm > 0) ? round(($donemLitre / $donemKm) * 100, 2) : 0
                    ];
                }

                echo json_encode([
                    'status' => 'success',
                    'debug' => [
                        'periods_count' => count($periods),
                        'arac_count' => count($aracListesi)
                    ],
                    'periods' => $periods,
                    'data' => array_values($karsilastirmaData),
                    'summary' => $summary
                ]);
                break;

            case 'get-km-onay-yapmayanlar':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $today = date('Y-m-d');
                $now = date('H:i');

                $list = [];

                // 1. Yesterday Sabah
                $res = $KmBildirim->getUnreported($yesterday, 'sabah');
                foreach ($res as $r) {
                    $r->gecikme_turu = 'Dün Sabah';
                    $r->hedef_tarih = $yesterday;
                    $r->hedef_tur = 'sabah';
                    $list[] = $r;
                }

                // 2. Yesterday Akşam
                $res = $KmBildirim->getUnreported($yesterday, 'aksam');
                foreach ($res as $r) {
                    $r->gecikme_turu = 'Dün Akşam';
                    $r->hedef_tarih = $yesterday;
                    $r->hedef_tur = 'aksam';
                    $list[] = $r;
                }

                // 3. Today Sabah (if after 08:00)
                if ($now >= '08:00') {
                    $res = $KmBildirim->getUnreported($today, 'sabah');
                    foreach ($res as $r) {
                        $r->gecikme_turu = 'Bugün Sabah';
                        $r->hedef_tarih = $today;
                        $r->hedef_tur = 'sabah';
                        $list[] = $r;
                    }
                }

                // 4. Today Akşam (if after 17:00)
                if ($now >= '17:00') {
                    $res = $KmBildirim->getUnreported($today, 'aksam');
                    foreach ($res as $r) {
                        $r->gecikme_turu = 'Bugün Akşam';
                        $r->hedef_tarih = $today;
                        $r->hedef_tur = 'aksam';
                        $list[] = $r;
                    }
                }

                echo json_encode(['status' => 'success', 'data' => $list]);
                break;

            case 'send-km-bildirim-hatirlatma':
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $tarih = $_POST['tarih'] ?? '';
                $tur = $_POST['tur'] ?? '';

                if (!$personel_id)
                    throw new Exception("Personel ID geçersiz.");

                $turFmt = $tur === 'sabah' ? 'Sabah' : 'Akşam';
                $tarihFmt = date('d.m.Y', strtotime($tarih));
                $message = "{$tarihFmt} tarihli {$turFmt} bildirimini yapmadınız, Lütfen yapınız.";

                // Bildirim gönder
                $Bildirim = new \App\Model\BildirimModel();
                
                // Personelin bağlı olduğu kullanıcıyı bul
                $pdo = $Arac->getDb();
                $stmt = $pdo->prepare("SELECT id FROM users WHERE personel_id = ? AND durum = 'Aktif'");
                $stmt->execute([$personel_id]);
                $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($userIds as $uid) {
                    $Bildirim->createNotification(
                        $uid,
                        'KM Bildirim Hatırlatması',
                        $message,
                        'index.php?p=personel-pwa/pages/ana-sayfa', // PWA ana sayfa veya bildirim sayfası
                        'bell',
                        'warning'
                    );

                    // Push
                    try {
                        $push = new \App\Service\PushNotificationService();
                        $push->sendToUser($uid, [
                            'title' => 'KM Bildirim Hatırlatması',
                            'body' => $message,
                            'url' => 'index_pwa.php' 
                        ], true);
                    } catch (\Exception $e) {
                        // Log but ignore
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Hatırlatma başarıyla gönderildi.']);
                break;

            case 'km-onay-ver':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception("Geçersiz bildirim ID.");

                $bildirim = $KmBildirim->find($id);
                if (!$bildirim) throw new Exception("Bildirim bulunamadı.");

                if ($bildirim->durum !== 'beklemede') {
                    $statusText = $bildirim->durum === 'onaylandi' ? 'onaylanmış' : 'reddedilmiş';
                    throw new Exception("Bu bildirim daha önce zaten {$statusText}. Sayfa yenileniyor...");
                }

                $tarih = $bildirim->tarih;
                $arac_id = $bildirim->arac_id;
                $deger_km = $bildirim->bitis_km; // Bu alan bildirilen degeri tutuyor
                $tur = $bildirim->tur;

                // Mevcut kaydı bul
                $mevcutKmKaydi = $Km->kayitVarMi($arac_id, $tarih, null, true);
                
                if ($tur === 'sabah') {
                    // SABAH (BAŞLANGIÇ) BİLDİRİMİ
                    $baslangic_km = $deger_km;
                    $bitis_km = $mevcutKmKaydi ? $mevcutKmKaydi->bitis_km : 0;
                } else {
                    // AKŞAM (BİTİŞ) BİLDİRİMİ
                    if ($mevcutKmKaydi) {
                        $baslangic_km = $mevcutKmKaydi->baslangic_km;
                    } else {
                        // Önceki kaydın bitiş KM'sini al
                        $sql = $Km->getDb()->prepare("SELECT bitis_km FROM arac_km_kayitlari WHERE arac_id = ? AND tarih <= ? AND silinme_tarihi IS NULL ORDER BY tarih DESC, id DESC LIMIT 1");
                        $sql->execute([$arac_id, $tarih]);
                        $baslangic_km = $sql->fetchColumn() ?: 0;
                    }
                    $bitis_km = $deger_km;
                }

                $saveData = [
                    'firma_id' => $bildirim->firma_id,
                    'arac_id' => $arac_id,
                    'tarih' => $tarih,
                    'baslangic_km' => $baslangic_km,
                    'bitis_km' => $bitis_km,
                    'yapilan_km' => ($bitis_km > $baslangic_km && $baslangic_km > 0) ? ($bitis_km - $baslangic_km) : 0,
                    'aciklama' => $bildirim->aciklama,
                    'olusturan_kullanici_id' => $_SESSION['user_id'] ?? null
                ];

                if ($mevcutKmKaydi) {
                    $saveData['id'] = $mevcutKmKaydi->id;
                }

                $Km->saveWithAttr($saveData);

                // 2. Araç Tablosunu Güncelle (Yalnızca bitiş KM'si bildirilmişse veya sabah bildirimi araç KM'sinden büyükse)
                $Arac->updateKm($arac_id, $deger_km);

                // 3. Bildirimi Onayla
                $KmBildirim->saveWithAttr([
                    'id' => $id,
                    'durum' => 'onaylandi',
                    'onaylayan_id' => $_SESSION['user_id'] ?? null,
                    'onay_tarihi' => date('Y-m-d H:i:s')
                ]);

                echo json_encode(['status' => 'success', 'message' => 'KM bildirimi onaylandı ve sisteme işlendi.']);
                break;

            case 'km-onay-reddet':
                $id = intval($_POST['id'] ?? 0);
                $neden = trim($_POST['red_nedeni'] ?? '');
                
                if ($id <= 0) throw new Exception("Geçersiz bildirim ID.");

                $bildirim = $KmBildirim->find($id);
                if (!$bildirim) throw new Exception("Bildirim bulunamadı.");

                if ($bildirim->durum !== 'beklemede') {
                    $statusText = $bildirim->durum === 'onaylandi' ? 'onaylanmış' : 'reddedilmiş';
                    throw new Exception("Bu bildirim daha önce zaten {$statusText}. Sayfa yenileniyor...");
                }

                $KmBildirim->saveWithAttr([
                    'id' => $id,
                    'durum' => 'reddedildi',
                    'red_nedeni' => $neden,
                    'onaylayan_id' => $_SESSION['user_id'] ?? null,
                    'onay_tarihi' => date('Y-m-d H:i:s')
                ]);

                echo json_encode(['status' => 'success', 'message' => 'KM bildirimi reddedildi.']);
                break;

            case 'km-onay-toplu-onayla':
                $ids = $_POST['ids'] ?? [];
                if (empty($ids) || !is_array($ids)) {
                    throw new Exception("Seçili bildirim bulunamadı.");
                }

                $successCount = 0;
                $errorCount = 0;
                $userId = $_SESSION['user_id'] ?? null;
                $now = date('Y-m-d H:i:s');

                foreach ($ids as $id) {
                    try {
                        $bildirim = $KmBildirim->find($id);
                        if (!$bildirim || $bildirim->durum !== 'beklemede') continue;

                        $tarih = $bildirim->tarih;
                        $arac_id = $bildirim->arac_id;
                        $deger_km = $bildirim->bitis_km;
                        $tur = $bildirim->tur;

                        // Mevcut kaydı bul
                        $mevcutKmKaydi = $Km->kayitVarMi($arac_id, $tarih, null, true);
                        
                        if ($tur === 'sabah') {
                            $baslangic_km = $deger_km;
                            $bitis_km = $mevcutKmKaydi ? $mevcutKmKaydi->bitis_km : 0;
                        } else {
                            if ($mevcutKmKaydi) {
                                $baslangic_km = $mevcutKmKaydi->baslangic_km;
                            } else {
                                $sql = $Km->getDb()->prepare("SELECT bitis_km FROM arac_km_kayitlari WHERE arac_id = ? AND tarih <= ? AND silinme_tarihi IS NULL ORDER BY tarih DESC, id DESC LIMIT 1");
                                $sql->execute([$arac_id, $tarih]);
                                $baslangic_km = $sql->fetchColumn() ?: 0;
                            }
                            $bitis_km = $deger_km;
                        }

                        $saveData = [
                            'firma_id' => $bildirim->firma_id,
                            'arac_id' => $arac_id,
                            'tarih' => $tarih,
                            'baslangic_km' => $baslangic_km,
                            'bitis_km' => $bitis_km,
                            'yapilan_km' => ($bitis_km > $baslangic_km && $baslangic_km > 0) ? ($bitis_km - $baslangic_km) : 0,
                            'aciklama' => $bildirim->aciklama,
                            'olusturan_kullanici_id' => $userId
                        ];

                        if ($mevcutKmKaydi) {
                            $saveData['id'] = $mevcutKmKaydi->id;
                        }

                        $Km->saveWithAttr($saveData);
                        $Arac->updateKm($arac_id, $deger_km);

                        $KmBildirim->saveWithAttr([
                            'id' => $id,
                            'durum' => 'onaylandi',
                            'onaylayan_id' => $userId,
                            'onay_tarihi' => $now
                        ]);

                        $successCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                    }
                }

                echo json_encode([
                    'status' => 'success', 
                    'message' => "{$successCount} adet bildirim başarıyla onaylandı." . ($errorCount > 0 ? " {$errorCount} adet bildirimde hata oluştu." : "")
                ]);
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