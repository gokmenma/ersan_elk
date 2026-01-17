<?php
// Çıktı tamponlamasını başlat (Olası boşluk veya hata mesajlarının Excel dosyasını bozmasını engellemek için)
ob_start();

// Hataları ekrana basmayı kapat, sadece logla
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
        throw new Exception("PhpSpreadsheet sınıfı bulunamadı. Lütfen 'composer install' komutunu çalıştırın.");
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Başlıklar ve Veri Tipleri
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
        'Takım' => 'takim',
        'DSS Sınıfı Üst' => 'dss_sinifi_ust',
        'DSS Sınıfı Alt' => 'dss_sinifi_alt',
        'IBAN Numarası' => 'iban_numarasi',
        'Maaş Durumu' => 'maas_durumu',
        'Maaş Tutarı' => 'maas_tutari',
        'Saatlik Ücret' => 'maas_birim_saat'
    ];

    // Başlıkları yaz
    $colIndex = 1;
    foreach ($columns as $header => $dbField) {
        // Sütun harfini bul (Örn: 1 -> A, 2 -> B)
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

        // Hücreye değeri yaz
        $sheet->setCellValue($columnLetter . '1', $header);
        
        // Sütun genişliğini otomatik ayarla
        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        
        // Başlık stili
        $style = $sheet->getStyle($columnLetter . '1');
        $style->getFont()->setBold(true);
        $style->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCCCCC'); // Gri arka plan
        
        $colIndex++;
    }

    // Dropdown Verileri Hazırla
    $firmaListStr = "";
    try {
        // Firmalar veritabanından çekiliyor
        if (class_exists('\App\Model\FirmaModel')) {
            $FirmaModel = new \App\Model\FirmaModel();
            $firmalar = $FirmaModel->option();
            
            if ($firmalar && count($firmalar) > 0) {
                $firmaList = [];
                foreach($firmalar as $f) {
                    // Virgül içeren firma adlarını temizle veya yönet
                    $cleanName = str_replace(',', ' ', $f->firma_adi);
                    $firmaList[] = $cleanName;
                }
                // Excel formülü için listeyi stringe çevir (maksimum 255 karakter sınırlaması olabilir, dikkat!)
                // Çok uzun listelerde ayrı bir sayfaya yazıp referans vermek daha iyidir ama şimdilik basit tutalım.
                $firmaListStr = '"' . implode(',', $firmaList) . '"';
            }
        }
    } catch (Exception $e) {
        // Veritabanı hatası olursa dropdown boş kalsın, işlem durmasın
        error_log("Firma listesi çekilemedi: " . $e->getMessage());
    }

    // Diğer Enumlar
    $cinsiyetList = '"Erkek,Kadın"';
    $medeniList = '"Evli,Bekar"';
    $evetHayirList = '"Evet,Hayır"';
    $varYokList = '"Var,Yok"';
    $personelSinifiList = '"Beyaz Yaka,Mavi Yaka"';
    $maasDurumuList = '"Brüt,Net"';
    $aktifList = '"1,0"';

    // Veri Doğrulama (Data Validation) - 100 satır için uygula
    $rowCount = 100;
    for ($i = 2; $i <= $rowCount; $i++) {
        // Firma (A sütunu)
        if (!empty($firmaListStr)) {
            $objValidation = $sheet->getCell("A$i")->getDataValidation();
            $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $objValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
            $objValidation->setAllowBlank(true);
            $objValidation->setShowInputMessage(true);
            $objValidation->setShowErrorMessage(true);
            $objValidation->setShowDropDown(true);
            $objValidation->setFormula1($firmaListStr);
        }

        // Cinsiyet (J sütunu - 10. sütun)
        $objValidation = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(10) . $i)->getDataValidation();
        $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $objValidation->setAllowBlank(true);
        $objValidation->setShowDropDown(true);
        $objValidation->setFormula1($cinsiyetList);

        // Medeni Durum (K sütunu - 11. sütun)
        $objValidation = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(11) . $i)->getDataValidation();
        $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $objValidation->setAllowBlank(true);
        $objValidation->setShowDropDown(true);
        $objValidation->setFormula1($medeniList);

        // Eşi Çalışıyor mu (L sütunu - 12. sütun)
        $objValidation = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(12) . $i)->getDataValidation();
        $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $objValidation->setAllowBlank(true);
        $objValidation->setShowDropDown(true);
        $objValidation->setFormula1($evetHayirList);

        // Seyahat Engeli (M sütunu - 13. sütun)
        $objValidation = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(13) . $i)->getDataValidation();
        $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $objValidation->setAllowBlank(true);
        $objValidation->setShowDropDown(true);
        $objValidation->setFormula1($varYokList);

        // Aktif mi (AB sütunu - 28. sütun)
        $objValidation = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(28) . $i)->getDataValidation();
        $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $objValidation->setAllowBlank(true);
        $objValidation->setShowDropDown(true);
        $objValidation->setFormula1($aktifList);

        // Personel Sınıfı (AI sütunu - 35. sütun)
        $objValidation = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(35) . $i)->getDataValidation();
        $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $objValidation->setAllowBlank(true);
        $objValidation->setShowDropDown(true);
        $objValidation->setFormula1($personelSinifiList);

        // Maaş Durumu (AM sütunu - 42. sütun)
        $objValidation = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(42) . $i)->getDataValidation();
        $objValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $objValidation->setAllowBlank(true);
        $objValidation->setShowDropDown(true);
        $objValidation->setFormula1($maasDurumuList);
    }

    // Çıktı tamponunu temizle (Böylece sadece Excel dosyası gider)
    ob_end_clean();

    // Dosya İndirme Headerları
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="personel_yukleme_sablonu.xlsx"');
    header('Cache-Control: max-age=0');
    // HTTP/1.1
    header('Pragma: public'); 

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
     // Hata durumunda tamponu temizle ve hatayı göster
     if (ob_get_length()) ob_end_clean();
     
     // Hata sayfasını HTML olarak göster (kullanıcı daha rahat okusun)
     header('Content-Type: text/html; charset=utf-8');
     echo '<div style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #f44336; background-color: #ffebee; color: #c62828; border-radius: 5px;">';
     echo '<h3>Şablon Oluşturulurken Hata Oluştu</h3>';
     echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
     echo '<button onclick="window.history.back()" style="padding: 10px 20px; background: #555; color: white; border: none; cursor: pointer;">Geri Dön</button>';
     echo '</div>';
}
?>