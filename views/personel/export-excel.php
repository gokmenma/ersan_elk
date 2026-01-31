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

    $term = isset($_GET['search']) ? $_GET['search'] : null;
    $colSearches = [];

    if (isset($_GET['col_search'])) {
        $colSearches = json_decode($_GET['col_search'], true);
    }

    $personeller = $PersonelModel->filter($term, $colSearches);




    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Personel Listesi');

    // Başlıklar ve Veri Tipleri (Template ile birebir uyumlu - yükleme için)
    $columns = [
        'Firma' => 'firma_id',
        'TC Kimlik No' => 'tc_kimlik_no',
        'Adı Soyadı' => 'adi_soyadi',
        'Anne Adı' => 'anne_adi',
        'Baba Adı' => 'baba_adi',
        'Doğum Tarihi (GG.AA.YYYY)' => 'dogum_tarihi',
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
        'Program Şifre' => 'sifre',
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
        'Ekip Bölge' => 'ekip_bolge',
        'Takım' => 'ekip_no',
        'DSS Sınıfı Üst' => 'dss_sinifi_ust',
        'DSS Sınıfı Alt' => 'dss_sinifi_alt',
        'Banka' => 'banka',
        'IBAN Numarası' => 'iban_numarasi',
        'Maaş Durumu' => 'maas_durumu',
        'Maaş Tutarı' => 'maas_tutari',
        'Sodexo Ödemesi Tutarı' => 'sodexo',
        'Günlük Ücret' => 'gunluk_ucret',
        'Bes Kesintisi Var mı?' => 'bes_kesintisi_varmi',
    ];

    // Personeller filter() metodu ile çekildi - firma_adi ve ekip_adi JOIN ile geliyor

    // Başlıkları yaz
    $colIndex = 1;
    foreach ($columns as $header => $dbField) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($columnLetter . '1', $header);

        // Sütun genişliğini otomatik ayarla
        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);

        // Stil
        $style = $sheet->getStyle($columnLetter . '1');
        $style->getFont()->setBold(true);
        
        // TC Kimlik No sütunu için sarı arka plan (güncelleme anahtarı)
        if ($dbField === 'tc_kimlik_no') {
            $style->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFFF99');
        } else {
            $style->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFCCCCCC');
        }

        $colIndex++;
    }

    // Başlık satırını dondur
    $sheet->freezePane('A2');

    // Verileri yaz
    $rowIndex = 2;
    foreach ($personeller as $personel) {
        $colIndex = 1;
        foreach ($columns as $header => $dbField) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $val = $personel->$dbField ?? '';

            // Özel formatlamalar (Template ile uyumlu)
            if ($dbField == 'firma_id') {
                // Firma adı JOIN ile geldi
                $val = $personel->firma_adi ?? $val;
            } elseif ($dbField == 'ekip_no') {
                // Ekip adı JOIN ile geldi
                $val = $personel->ekip_adi ?? $val;
            } elseif (in_array($dbField, ['dogum_tarihi', 'ise_giris_tarihi', 'isten_cikis_tarihi'])) {
                // Tarih formatı: DD.MM.YYYY
                if (!empty($val) && $val != '0000-00-00') {
                    $val = date('d.m.Y', strtotime($val));
                } else {
                    $val = '';
                }
            } elseif ($dbField == 'cinsiyet') {
                // Cinsiyet dönüşümü
                if ($val === 'E' || $val === 'e') {
                    $val = 'Erkek';
                } elseif ($val === 'K' || $val === 'k') {
                    $val = 'Kadın';
                }
            } elseif ($dbField == 'medeni_durum') {
                // Medeni durum dönüşümü
                if ($val === 'E' || $val === 'e') {
                    $val = 'Evli';
                } elseif ($val === 'B' || $val === 'b') {
                    $val = 'Bekar';
                }
            } elseif (in_array($dbField, ['esi_calisiyor_mu', 'bes_kesintisi_varmi'])) {
                // Evet/Hayır dönüşümü
                if ($val === '1' || $val === 1) {
                    $val = 'Evet';
                } elseif ($val === '0' || $val === 0) {
                    $val = 'Hayır';
                }
            } elseif ($dbField == 'seyahat_engeli') {
                // Var/Yok dönüşümü
                if ($val === '1' || $val === 1) {
                    $val = 'Var';
                } elseif ($val === '0' || $val === 0) {
                    $val = 'Yok';
                }
            } elseif ($dbField == 'sifre') {
                // Şifre alanını boş bırak (güvenlik için)
                $val = '';
            }

            $sheet->setCellValueExplicit($columnLetter . $rowIndex, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $colIndex++;
        }
        
        // TC Kimlik No hücresini vurgula
        $tcColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2); // TC Kimlik No 2. sütun
        $sheet->getStyle($tcColLetter . $rowIndex)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFFDE7');
        
        $rowIndex++;
    }

    // Sütun genişliklerini ayarla (tekrar)
    for ($i = 1; $i <= count($columns); $i++) {
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