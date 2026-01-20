<?php
// Çıktı tamponlamasını başlat
ob_start();

// Hataları ekrana basmayı kapat, sadece logla
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Autoloader'ı dahil et
    $autoloaderPath = dirname(__DIR__, 2) . '/Autoloader.php';
    if (!file_exists($autoloaderPath)) {
        throw new Exception("Autoloader bulunamadı: $autoloaderPath");
    }
    require_once $autoloaderPath;

    // Composer Autoloader
    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        throw new Exception("Excel kütüphanesi (vendor/autoload.php) bulunamadı.");
    }

    // PhpSpreadsheet kontrolü
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        throw new Exception("PhpSpreadsheet sınıfı bulunamadı.");
    }

    // Model
    $PersonelModel = new \App\Model\PersonelModel();
    $personeller = $PersonelModel->all();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Başlıklar ve Veri Tipleri (Template ile uyumlu)
    $columns = [
        'Firma' => 'firma_id', // Firma adı yazılacak
        'TC Kimlik No' => 'tc_kimlik_no',
        'Adı Soyadı' => 'adi_soyadi',
        'Anne Adı' => 'anne_adi',
        'Baba Adı' => 'baba_adi',
        'Doğum Tarihi' => 'dogum_tarihi',
        'Doğum Yeri (İl)' => 'dogum_yeri_il',
        'Doğum Yeri (İlçe)' => 'dogum_yeri_ilce',
        'Adres' => 'adres',
        'Cinsiyet' => 'cinsiyet',
        'Medeni Durum' => 'medeni_durum',
        'Eşi Çalışıyor mu?' => 'esi_calisiyor_mu',
        'Seyahat Engeli' => 'seyahat_engeli',
        'Ehliyet Sınıfı' => 'ehliyet_sinifi',
        'Kan Grubu' => 'kan_grubu',
        'Cep Telefonu' => 'cep_telefonu',
        '2. Cep Telefonu' => 'cep_telefonu_2',
        'E-posta Adresi' => 'email_adresi',
        'Ayakkabı No' => 'ayakkabi_numarasi',
        'Üst Beden No' => 'ust_beden_no',
        'Alt Beden No' => 'alt_beden_no',
        'Referans Adı Soyadı' => 'referans_adi_soyadi',
        'Referans Telefonu' => 'referans_telefonu',
        'Referans Firma' => 'referans_firma',
        'Acil Durum Kişisi' => 'acil_kisi_adi_soyadi',
        'Acil Durum Yakınlık' => 'acil_kisi_yakinlik',
        'Acil Durum Telefonu' => 'acil_kisi_telefonu',
        'Aktif mi?' => 'aktif_mi',
        'İşe Giriş Tarihi' => 'ise_giris_tarihi',
        'İşten Çıkış Tarihi' => 'isten_cikis_tarihi',
        'SGK No' => 'sgk_no',
        'SGK Yapılan Firma' => 'sgk_yapilan_firma',
        'Personel Sınıfı' => 'personel_sinifi',
        'Departman' => 'departman',
        'Görev' => 'gorev',
        'Takım' => 'ekip_no',
        'DSS Sınıfı Üst' => 'dss_sinifi_ust',
        'DSS Sınıfı Alt' => 'dss_sinifi_alt',
        'IBAN Numarası' => 'iban_numarasi',
        'Maaş Durumu' => 'maas_durumu',
        'Maaş Tutarı' => 'maas_tutari',
        'Saatlik Ücret' => 'maas_birim_saat'
    ];

    // Firma isimlerini almak için
    $FirmaModel = new \App\Model\FirmaModel();
    $firmalar = $FirmaModel->option();
    $firmaMap = [];
    foreach ($firmalar as $f) {
        $firmaMap[$f->id] = $f->firma_adi;
    }

    // Başlıkları yaz
    $colIndex = 1;
    foreach ($columns as $header => $dbField) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($columnLetter . '1', $header);

        // Stil
        $style = $sheet->getStyle($columnLetter . '1');
        $style->getFont()->setBold(true);
        $style->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCCCCC');

        $colIndex++;
    }

    // Verileri yaz
    $rowIndex = 2;
    foreach ($personeller as $personel) {
        $colIndex = 1;
        foreach ($columns as $header => $dbField) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $val = $personel->$dbField ?? '';

            // Özel formatlamalar
            if ($dbField == 'firma_id') {
                $val = $firmaMap[$val] ?? $val;
            } elseif ($dbField == 'aktif_mi') {
                $val = $val == 1 ? '1' : '0';
            } elseif (in_array($dbField, ['dogum_tarihi', 'ise_giris_tarihi', 'isten_cikis_tarihi'])) {
                if (!empty($val) && $val != '0000-00-00') {
                    $val = date('d.m.Y', strtotime($val));
                } else {
                    $val = '';
                }
            }

            $sheet->setCellValueExplicit($columnLetter . $rowIndex, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $colIndex++;
        }
        $rowIndex++;
    }

    // Sütun genişliklerini ayarla
    for ($i = 1; $i < count($columns) + 1; $i++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
    }

    // Çıktı tamponunu temizle
    ob_end_clean();

    // Dosya İndirme Headerları
    $filename = 'personel_listesi_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_end_clean();
    header('HTTP/1.1 500 Internal Server Error');
    echo "Hata: " . $e->getMessage();
}
?>